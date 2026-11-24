<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\NingEventType;
use App\Enums\TicketStatus;
use App\Events\NetworkAlertTriggered;
use App\Http\Controllers\Controller;
use App\Http\Resources\TicketResource;
use App\Models\InventoryItem;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Services\TicketAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class NingWebhookController extends Controller
{
    public function __construct(
        private readonly TicketAssignmentService $assignmentService,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_type' => ['required', 'string', Rule::enum(NingEventType::class)],
            'device_name' => ['required', 'string', 'max:150'],
            'device_ip' => ['nullable', 'ip'],
            'location' => ['nullable', 'string', 'max:200'],
            'asset_code' => ['nullable', 'string', 'max:80'],
            'asset_codes' => ['nullable', 'array'],
            'asset_codes.*' => ['string', 'max:80'],
            'inventory_item_ids' => ['nullable', 'array'],
            'inventory_item_ids.*' => ['integer', 'exists:inventory_items,id'],
            'latency_ms' => ['nullable', 'numeric', 'min:0', 'max:600000'],
            'message' => ['nullable', 'string', 'max:1000'],
            'incident_id' => ['nullable', 'string', 'max:100'],
        ]);

        $eventType = NingEventType::from($validated['event_type']);
        $deviceName = trim($validated['device_name']);
        $deviceIp = $validated['device_ip'] ?? null;
        $location = isset($validated['location']) ? trim($validated['location']) : null;
        $assetCode = isset($validated['asset_code']) ? strtoupper(trim($validated['asset_code'])) : null;
        $assetCodes = array_values(array_unique(array_filter(array_merge(
            $assetCode ? [$assetCode] : [],
            array_map(static fn (string $code): string => strtoupper(trim($code)), $validated['asset_codes'] ?? []),
        ))));
        $inventoryItemIdsInput = array_values(array_unique(array_map('intval', $validated['inventory_item_ids'] ?? [])));
        $latencyMs = isset($validated['latency_ms']) ? (float) $validated['latency_ms'] : null;
        $incidentId = isset($validated['incident_id']) ? trim($validated['incident_id']) : null;

        $linkedItems = collect();
        if (! empty($inventoryItemIdsInput)) {
            $linkedItems = $linkedItems->merge(InventoryItem::whereIn('id', $inventoryItemIdsInput)->get());
        }
        if (! empty($assetCodes)) {
            $linkedItems = $linkedItems->merge(InventoryItem::whereIn('asset_code', $assetCodes)->get());
        }
        $linkedItems = $linkedItems->unique('id')->values();
        $linkedItemIds = $linkedItems->pluck('id')->all();
        $inventoryItem = $linkedItems->first();

        if (! $location && $inventoryItem?->location_detail) {
            $location = $inventoryItem->location_detail;
        }

        $alertMessage = $this->buildAlertMessage($eventType, $deviceName, $deviceIp, $latencyMs, $validated['message'] ?? null);

        $result = DB::transaction(function () use (
            $eventType,
            $deviceName,
            $deviceIp,
            $location,
            $inventoryItem,
            $linkedItemIds,
            $latencyMs,
            $incidentId,
            $alertMessage,
        ): array {
            $activeTicket = $this->findActiveNingTicket($incidentId, $inventoryItem, $deviceName);

            if ($eventType === NingEventType::DeviceRestored) {
                if (! $activeTicket) {
                    return ['ticket' => null, 'status_code' => 200, 'deduplicated' => false, 'resolved' => false];
                }

                $activeTicket->update([
                    'status' => TicketStatus::Finished->value,
                    'resolved_at' => now(),
                    'status_note' => $alertMessage,
                ]);

                TicketMessage::create([
                    'ticket_id' => $activeTicket->id,
                    'user_id' => null,
                    'sender_type' => 'system',
                    'message' => "✅ **NING Network Monitor:** {$alertMessage}",
                    'is_internal' => false,
                ]);

                return ['ticket' => $activeTicket->fresh(['user', 'assignee', 'inventoryItem', 'inventoryItems']), 'status_code' => 200, 'deduplicated' => false, 'resolved' => true];
            }

            if ($activeTicket) {
                TicketMessage::create([
                    'ticket_id' => $activeTicket->id,
                    'user_id' => null,
                    'sender_type' => 'system',
                    'message' => "📡 **Update NING ({$eventType->value}):** {$alertMessage}",
                    'is_internal' => false,
                ]);

                return ['ticket' => $activeTicket->fresh(['user', 'assignee', 'inventoryItem', 'inventoryItems']), 'status_code' => 200, 'deduplicated' => true, 'resolved' => false];
            }

            $systemUser = User::where('is_active', true)->where('type', 'Staf')->first()
                ?? User::first();

            if (! $systemUser) {
                return ['ticket' => null, 'status_code' => 503, 'deduplicated' => false, 'resolved' => false];
            }

            $assignee = $this->assignmentService->assignTechnician();
            $priority = $eventType === NingEventType::OutageDetected ? 'high' : 'medium';
            $slaHours = $priority === 'high' ? 4 : 12;

            $ticket = Ticket::create([
                'ticket_number' => $this->generateUniqueTicketNumber(),
                'user_id' => $systemUser->id,
                'inventory_item_id' => $inventoryItem?->id,
                'assigned_to' => $assignee?->id,
                'category' => 'Jaringan & Internet',
                'service_item' => 'Jaringan Kabel LAN Gedung / Ruangan',
                'authority' => 'local',
                'scope' => 'internal_surabaya',
                'status' => TicketStatus::Open->value,
                'priority' => $priority,
                'location' => $location ?: 'Infrastruktur Jaringan Kampus',
                'title' => "[NING] {$eventType->label()}: {$deviceName}",
                'description' => $this->buildDescription($eventType, $deviceName, $deviceIp, $location, $latencyMs, $alertMessage),
                'central_ticket_ref' => $incidentId,
                'source_channel' => 'ning_webhook',
                'reporter_name' => 'NING Network Monitor',
                'sla_due_at' => now()->addHours($slaHours),
            ]);

            if (! empty($linkedItemIds)) {
                $ticket->syncInventoryItems($linkedItemIds);
            }

            TicketMessage::create([
                'ticket_id' => $ticket->id,
                'user_id' => null,
                'sender_type' => 'system',
                'message' => "🚨 **NING Network Monitor:** {$alertMessage}",
                'is_internal' => false,
            ]);

            return ['ticket' => $ticket->fresh(['user', 'assignee', 'inventoryItem', 'inventoryItems']), 'status_code' => 201, 'deduplicated' => false, 'resolved' => false];
        });

        if ($result['status_code'] === 503) {
            return response()->json([
                'success' => false,
                'message' => 'No system user available to attach NING automated ticket.',
            ], 503);
        }

        /** @var ?Ticket $ticket */
        $ticket = $result['ticket'];

        event(new NetworkAlertTriggered(
            eventType: $eventType->value,
            severity: $eventType->severity(),
            label: $eventType->label(),
            deviceName: $deviceName,
            deviceIp: $deviceIp,
            location: $location,
            assetCode: $assetCode,
            latencyMs: $latencyMs,
            message: $alertMessage,
            ticketId: $ticket?->id,
            ticketNumber: $ticket?->ticket_number,
            ticketUrl: $ticket ? route('ticket.show', $ticket) : null,
            triggeredAt: now()->toIso8601String(),
        ));

        return response()->json([
            'success' => true,
            'event_type' => $eventType->value,
            'deduplicated' => $result['deduplicated'],
            'resolved' => $result['resolved'],
            'data' => $ticket ? new TicketResource($ticket) : null,
        ], $result['status_code']);
    }

    private function findActiveNingTicket(?string $incidentId, ?InventoryItem $inventoryItem, string $deviceName): ?Ticket
    {
        $baseQuery = Ticket::where('source_channel', 'ning_webhook')
            ->whereIn('status', [TicketStatus::Open->value, TicketStatus::InProgress->value]);

        if ($incidentId !== null && $incidentId !== '') {
            $byIncident = (clone $baseQuery)->where('central_ticket_ref', $incidentId)->latest()->first();
            if ($byIncident) {
                return $byIncident;
            }
        }

        if ($inventoryItem) {
            $byAsset = (clone $baseQuery)->where('inventory_item_id', $inventoryItem->id)->latest()->first();
            if ($byAsset) {
                return $byAsset;
            }
        }

        return (clone $baseQuery)
            ->where('title', 'like', '%'.$deviceName.'%')
            ->latest()
            ->first();
    }

    private function generateUniqueTicketNumber(): string
    {
        $prefix = 'TIC-'.now()->format('Ym').'-';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $candidate = $prefix.str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
            if (! Ticket::where('ticket_number', $candidate)->exists()) {
                return $candidate;
            }
        }

        return $prefix.strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    }

    private function buildAlertMessage(
        NingEventType $eventType,
        string $deviceName,
        ?string $deviceIp,
        ?float $latencyMs,
        ?string $customMessage,
    ): string {
        if ($customMessage !== null && trim($customMessage) !== '') {
            return trim($customMessage);
        }

        $ipSuffix = $deviceIp ? " ({$deviceIp})" : '';

        return match ($eventType) {
            NingEventType::OutageDetected => "Perangkat {$deviceName}{$ipSuffix} tidak merespons (offline/outage).",
            NingEventType::HighLatencyWarning => "Perangkat {$deviceName}{$ipSuffix} mengalami latensi tinggi".($latencyMs !== null ? " ({$latencyMs} ms)." : '.'),
            NingEventType::DeviceRestored => "Perangkat {$deviceName}{$ipSuffix} kembali online dan normal.",
        };
    }

    private function buildDescription(
        NingEventType $eventType,
        string $deviceName,
        ?string $deviceIp,
        ?string $location,
        ?float $latencyMs,
        string $alertMessage,
    ): string {
        $lines = [
            "Event NING: {$eventType->value}",
            "Perangkat: {$deviceName}",
        ];

        if ($deviceIp) {
            $lines[] = "IP Address: {$deviceIp}";
        }
        if ($location) {
            $lines[] = "Lokasi: {$location}";
        }
        if ($latencyMs !== null) {
            $lines[] = "Latensi: {$latencyMs} ms";
        }

        $lines[] = "Ringkasan: {$alertMessage}";

        return implode("\n", $lines);
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Middleware\VerifyLunaApiKey;
use App\Http\Resources\TicketResource;
use App\Models\InventoryItem;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\TicketSlaLog;
use App\Models\User;
use App\Services\InventorySyncService;
use App\Services\LunaAiService;
use App\Services\TicketAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class LunaTicketApiController extends Controller
{
    public function __construct(
        public LunaAiService $lunaAi,
        public TicketAssignmentService $assignmentService,
        public InventorySyncService $inventorySync
    ) {}

    /**
     * Service & Inventory Catalog for Luna AI Context
     */
    public function catalog(): JsonResponse
    {
        $assets = InventoryItem::where('condition', '!=', 'retired')
            ->orderBy('asset_code')
            ->get(['id', 'asset_code', 'name', 'category', 'location_detail', 'condition']);

        return response()->json([
            'success' => true,
            'services' => Ticket::SERVICES,
            'inventory_items' => $assets,
        ]);
    }

    /**
     * List Recent Tickets for the Whitelisted WhatsApp Number
     */
    public function index(Request $request): JsonResponse
    {
        $query = Ticket::with(['user', 'assignee', 'inventoryItem'])->latest();

        $reporterPhone = VerifyLunaApiKey::normalizeWaPhone($request->query('reporter_phone'));

        if ($reporterPhone !== '') {
            $query->where('reporter_phone', $reporterPhone);
        } else {
            $query->where('source_channel', 'whatsapp_luna');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $tickets = $query->take(20)->get();

        return response()->json([
            'success' => true,
            'data' => TicketResource::collection($tickets),
        ]);
    }

    /**
     * Create Ticket Directly from Luna AI / WhatsApp
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:50'],
            'reporter_phone' => ['nullable', 'string', 'max:50'],
            'reporter_status' => ['nullable', 'in:mahasiswa,dosen,staf'],
            'unit_kerja' => ['nullable', 'string', 'max:150'],
            'request_type' => ['nullable', 'in:PENGADUAN IT,PERMINTAAN LAYANAN'],
            'external_id' => ['nullable', 'string', 'max:100'],
            'reporter_name' => ['nullable', 'string', 'max:150'],
            'nim' => ['nullable', 'string', 'max:50'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'min:3'],
            'category' => ['nullable', 'string', 'max:100'],
            'service_item' => ['nullable', 'string', 'max:150'],
            'location' => ['nullable', 'string', 'max:255'],
            'priority' => ['nullable', 'in:low,medium,high,urgent'],
            'asset_code' => ['nullable', 'string', 'max:50'],
            'inventory_item_id' => ['nullable', 'exists:inventory_items,id'],
        ]);

        // Ensure strict single-number check if configured
        $allowedNumberRaw = (string) (config('services.luna.allowed_wa_number') ?: Setting::where('key', 'luna_allowed_wa_number')->value('value'));
        $allowedNumber = VerifyLunaApiKey::normalizeWaPhone($allowedNumberRaw);
        $normalizedPhone = VerifyLunaApiKey::normalizeWaPhone($validated['phone']);

        if ($allowedNumber !== '' && ! hash_equals($allowedNumber, $normalizedPhone)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: Nomor WhatsApp ini tidak diizinkan untuk membuat tiket melalui Luna AI.',
            ], 403);
        }

        $idempotencyKey = trim((string) ($request->header('Idempotency-Key') ?: ($validated['external_id'] ?? '')));

        if ($idempotencyKey === '') {
            return $this->createTicket($validated, $normalizedPhone, null);
        }

        return Cache::lock("luna-ticket-idempotency:{$idempotencyKey}", 10)
            ->block(3, function () use ($validated, $normalizedPhone, $idempotencyKey): JsonResponse {
                $existing = Ticket::with(['user', 'assignee', 'inventoryItem', 'messages.user'])
                    ->where('idempotency_key', $idempotencyKey)
                    ->where('created_at', '>=', now()->subDay())
                    ->latest('id')
                    ->first();

                if ($existing) {
                    return response()->json([
                        'success' => true,
                        'message' => "Tiket {$existing->ticket_number} sudah pernah dibuat dengan Idempotency-Key yang sama.",
                        'data' => new TicketResource($existing),
                    ]);
                }

                return $this->createTicket($validated, $normalizedPhone, $idempotencyKey);
            });
    }

    /**
     * Persist a new ticket from validated Luna payload.
     *
     * @param  array<string, mixed>  $validated
     */
    private function createTicket(array $validated, string $normalizedPhone, ?string $idempotencyKey): JsonResponse
    {
        $reporterPhone = VerifyLunaApiKey::normalizeWaPhone($validated['reporter_phone'] ?? null);
        $reporter = $this->resolveReporter($reporterPhone ?: $normalizedPhone, $validated['nim'] ?? null, $validated['reporter_name'] ?? null);

        $mainDescription = $this->mainDescription($validated['description']);
        $lunaCategoryHint = preg_match('/^Kategori \(Luna\):\s*(.+)$/mi', $validated['description'], $hintMatch)
            ? trim($hintMatch[1])
            : '';

        // Resolve linked inventory item by ID or asset_code
        $linkedItem = null;
        if (! empty($validated['inventory_item_id'])) {
            $linkedItem = InventoryItem::find($validated['inventory_item_id']);
        } elseif (! empty($validated['asset_code'])) {
            $linkedItem = InventoryItem::where('asset_code', $validated['asset_code'])->first();
        }

        // Infer category & service_item via Luna AI if omitted
        $category = $validated['category'] ?? null;
        $serviceItem = $validated['service_item'] ?? null;

        if (! $category || ! $serviceItem || ! isset(Ticket::SERVICES[$category][$serviceItem])) {
            $suggestion = $this->lunaAi->suggestService(trim(($validated['title'] ?? '').' '.$mainDescription.' '.$lunaCategoryHint));
            if ($suggestion) {
                $category = $suggestion['category'];
                $serviceItem = $suggestion['service_item'];
            } else {
                $category = 'Lainnya';
                $serviceItem = 'Konsultasi / Kendala IT Lainnya';
            }
        }

        $authority = Ticket::SERVICES[$category][$serviceItem]['authority'] ?? 'local';
        $location = $validated['location'] ?? ($linkedItem?->display_location !== '-' ? $linkedItem?->display_location : null);
        $priority = $validated['priority'] ?? 'medium';
        $title = ! empty($validated['title'])
            ? $validated['title']
            : Str::limit($mainDescription, 80, '...');

        $ambiguityMsg = $this->lunaAi->evaluateAmbiguity(
            $mainDescription,
            $location,
            $authority
        );

        $now = now();
        $assignedTo = null;
        $slaDueAt = null;
        $slaPausedAt = null;

        if ($authority === 'central') {
            $scope = 'escalated_central';
            $status = TicketStatus::Redirected->value;
            $slaPausedAt = $now;
        } elseif ($ambiguityMsg !== null) {
            $scope = 'needs_clarification';
            $status = TicketStatus::Open->value;
            $slaPausedAt = $now;
        } else {
            $scope = 'internal_surabaya';
            $status = TicketStatus::Open->value;
            $assignedTo = $this->assignmentService->assignTechnician()?->id;

            $hours = match ($priority) {
                'urgent' => 4,
                'high' => 12,
                'medium' => 24,
                default => 48,
            };
            $slaDueAt = $now->copy()->addHours($hours);
        }

        $ticket = Ticket::create([
            'ticket_number' => Ticket::generateTicketNumber(),
            'user_id' => $reporter->id,
            'inventory_item_id' => $linkedItem?->id,
            'assigned_to' => $assignedTo,
            'category' => $category,
            'service_item' => $serviceItem,
            'authority' => $authority,
            'scope' => $scope,
            'status' => $status,
            'priority' => $priority,
            'location' => $location,
            'title' => $title,
            'description' => $validated['description'],
            'source_channel' => 'whatsapp_luna',
            'reporter_phone' => $reporterPhone ?: $normalizedPhone,
            'reporter_name' => $validated['reporter_name'] ?? $reporter->name,
            'reporter_status' => $validated['reporter_status'] ?? null,
            'unit_kerja' => $validated['unit_kerja'] ?? null,
            'request_type' => $validated['request_type'] ?? null,
            'idempotency_key' => $idempotencyKey,
            'sla_due_at' => $slaDueAt,
            'sla_paused_at' => $slaPausedAt,
        ]);

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'sender_type' => 'user',
            'message' => $validated['description'],
        ]);

        $lunaReply = "Halo! Laporan Anda melalui WhatsApp telah diterima oleh **Luna AI PuTI Surabaya** dengan nomor tiket **{$ticket->ticket_number}** (Status: **{$ticket->user_status_label}**).";

        if ($authority === 'central') {
            $lunaReply = "Halo! Saya **Luna AI** dari PuTI Surabaya. Laporan Anda telah kami terima dan tercatat dengan nomor **{$ticket->ticket_number}**.\n\nKarena layanan **{$ticket->service_item}** memerlukan sinkronisasi pada sistem terpusat, tim PuTI Surabaya sedang mengawal dan mengoordinasikan laporan Anda bersama tim sistem terpusat Telkom University. 😊";

            TicketMessage::create([
                'ticket_id' => $ticket->id,
                'user_id' => null,
                'sender_type' => 'ai_luna',
                'message' => $lunaReply,
            ]);

            TicketSlaLog::create([
                'ticket_id' => $ticket->id,
                'paused_at' => $now,
                'reason' => 'Koordinasi Sistem Terpusat',
            ]);
        } elseif ($ambiguityMsg !== null) {
            $lunaReply = $ambiguityMsg;

            TicketMessage::create([
                'ticket_id' => $ticket->id,
                'user_id' => null,
                'sender_type' => 'ai_luna',
                'message' => $ambiguityMsg,
            ]);

            TicketSlaLog::create([
                'ticket_id' => $ticket->id,
                'paused_at' => $now,
                'reason' => 'Menunggu Tanggapan Pelapor (Klarifikasi Informasi Kendala)',
            ]);
        }

        if ($linkedItem) {
            $this->inventorySync->syncFromTicket($ticket, $reporter);
        }

        $ticket->load(['user', 'assignee', 'inventoryItem', 'messages.user']);

        return response()->json([
            'success' => true,
            'message' => "Tiket {$ticket->ticket_number} berhasil dibuat melalui WhatsApp Luna AI.",
            'luna_reply' => $lunaReply,
            'data' => new TicketResource($ticket),
        ], 201);
    }

    /**
     * Show Ticket Status & Thread by Ticket Number
     */
    public function show(string $ticketNumber): JsonResponse
    {
        $ticket = Ticket::with(['user', 'assignee', 'inventoryItem', 'messages.user'])
            ->where('ticket_number', $ticketNumber)
            ->first();

        if (! $ticket) {
            return response()->json([
                'success' => false,
                'message' => "Tiket {$ticketNumber} tidak ditemukan.",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new TicketResource($ticket),
        ]);
    }

    /**
     * Reply to Ticket from WhatsApp
     */
    public function reply(Request $request, string $ticketNumber): JsonResponse
    {
        $ticket = Ticket::where('ticket_number', $ticketNumber)->first();

        if (! $ticket) {
            return response()->json([
                'success' => false,
                'message' => "Tiket {$ticketNumber} tidak ditemukan.",
            ], 404);
        }

        if ($ticket->isFinalStatus()) {
            return response()->json([
                'success' => false,
                'message' => 'Tiket ini sudah selesai atau dibatalkan dan tidak dapat menerima balasan baru.',
            ], 422);
        }

        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:50'],
            'message' => ['required', 'string', 'min:2'],
        ]);

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $ticket->user_id,
            'sender_type' => 'user',
            'message' => $validated['message'],
        ]);

        if ($ticket->needsClarification()) {
            $now = now();
            $pausedMinutes = $ticket->sla_paused_at
                ? (int) $ticket->sla_paused_at->diffInMinutes($now)
                : 0;

            $activeLog = $ticket->slaLogs()->whereNull('resumed_at')->latest()->first();
            if ($activeLog) {
                $activeLog->update([
                    'resumed_at' => $now,
                    'duration_minutes' => $pausedMinutes,
                ]);
            }

            $newDueAt = $ticket->sla_due_at
                ? $ticket->sla_due_at->copy()->addMinutes($pausedMinutes)
                : $now->copy()->addHours(24);

            $assigneeId = $ticket->assigned_to ?: $this->assignmentService->assignTechnician()?->id;

            $ticket->update([
                'status' => TicketStatus::Open->value,
                'scope' => 'internal_surabaya',
                'assigned_to' => $assigneeId,
                'sla_due_at' => $newDueAt,
                'sla_paused_at' => null,
            ]);

            TicketMessage::create([
                'ticket_id' => $ticket->id,
                'user_id' => null,
                'sender_type' => 'system',
                'message' => 'ℹ️ **Sistem (WhatsApp):** Pelapor telah memberikan informasi tambahan. Tiket aktif kembali dan SLA dilanjutkan.',
            ]);
        }

        $ticket->load(['user', 'assignee', 'inventoryItem', 'messages.user']);

        return response()->json([
            'success' => true,
            'message' => 'Balasan WhatsApp berhasil ditambahkan ke tiket.',
            'data' => new TicketResource($ticket),
        ]);
    }

    /**
     * Cancel Ticket from WhatsApp (while status is open)
     */
    public function cancel(Request $request, string $ticketNumber): JsonResponse
    {
        $ticket = Ticket::where('ticket_number', $ticketNumber)->first();

        if (! $ticket) {
            return response()->json([
                'success' => false,
                'message' => "Tiket {$ticketNumber} tidak ditemukan.",
            ], 404);
        }

        if ($ticket->status !== TicketStatus::Open->value) {
            return response()->json([
                'success' => false,
                'message' => 'Tiket hanya dapat dibatalkan selama berstatus Diterima (Open).',
            ], 422);
        }

        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:50'],
            'status_note' => ['nullable', 'string', 'max:500'],
        ]);

        $now = now();
        if ($ticket->sla_paused_at) {
            $duration = (int) $ticket->sla_paused_at->diffInMinutes($now);
            $activeLog = $ticket->slaLogs()->whereNull('resumed_at')->latest()->first();
            if ($activeLog) {
                $activeLog->update([
                    'resumed_at' => $now,
                    'duration_minutes' => $duration,
                ]);
            }
        }

        $ticket->update([
            'status' => TicketStatus::Canceled->value,
            'cancel_reason' => 'user_withdrawn',
            'status_note' => $validated['status_note'] ?? 'Dibatalkan melalui WhatsApp Luna AI.',
            'sla_paused_at' => null,
            'resolved_at' => $now,
        ]);

        $this->inventorySync->syncFromTicket($ticket, $ticket->user, $ticket->status_note);

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $ticket->user_id,
            'sender_type' => 'system',
            'message' => '🚫 **Tiket Dibatalkan (WhatsApp):** Tiket dibatalkan melalui Luna AI WhatsApp.',
        ]);

        $ticket->load(['user', 'assignee', 'inventoryItem', 'messages.user']);

        return response()->json([
            'success' => true,
            'message' => "Tiket {$ticket->ticket_number} berhasil dibatalkan.",
            'data' => new TicketResource($ticket),
        ]);
    }

    /**
     * Strip the Luna metadata block (after a "---" separator line) from a description.
     */
    private function mainDescription(string $description): string
    {
        $normalized = str_replace("\r\n", "\n", $description);
        $main = trim(Str::before($normalized, "\n\n---"));

        return $main !== '' ? $main : trim($normalized);
    }

    /**
     * Resolve or create a staff/reporter User record for the whitelisted WhatsApp number.
     */
    private function resolveReporter(string $normalizedPhone, ?string $nim, ?string $reporterName): User
    {
        $localZeroPhone = str_starts_with($normalizedPhone, '62')
            ? '0'.substr($normalizedPhone, 2)
            : $normalizedPhone;

        $user = User::whereIn('phone', [$normalizedPhone, $localZeroPhone, '+'.$normalizedPhone])
            ->when($nim, fn ($q) => $q->orWhere('nim', $nim))
            ->first();

        if ($user) {
            return $user;
        }

        $fallbackUser = User::orderBy('id')->first();
        if ($fallbackUser) {
            return $fallbackUser;
        }

        return User::create([
            'name' => $reporterName ?: 'Pelapor WhatsApp Luna',
            'username' => 'wa_'.$normalizedPhone,
            'phone' => $normalizedPhone,
            'email' => "wa.{$normalizedPhone}@puti.telkomuniversity.ac.id",
            'password' => bcrypt(Str::random(24)),
            'type' => 'Staf',
            'is_active' => true,
        ]);
    }
}

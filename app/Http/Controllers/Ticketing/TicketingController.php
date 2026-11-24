<?php

namespace App\Http\Controllers\Ticketing;

use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\TicketSlaLog;
use App\Models\User;
use App\Services\InventorySyncService;
use App\Services\LunaAiService;
use App\Services\TicketAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TicketingController extends Controller
{
    public function __construct(
        public LunaAiService $lunaAi,
        public TicketAssignmentService $assignmentService,
        public InventorySyncService $inventorySync
    ) {}

    /**
     * Overview & Key Stats
     */
    public function index(): View
    {
        $user = $this->currentUser();
        $userId = $user->id;
        $isStaff = $user->isTicketingStaff();

        $baseQuery = $isStaff ? Ticket::query() : Ticket::where('user_id', $userId);

        $stats = [
            'total' => (clone $baseQuery)->count(),
            'open' => (clone $baseQuery)->where('status', TicketStatus::Open->value)->count(),
            'in_progress' => (clone $baseQuery)->where('status', TicketStatus::InProgress->value)->count(),
            'redirected' => (clone $baseQuery)->where('status', TicketStatus::Redirected->value)->count(),
            'finished' => (clone $baseQuery)->where('status', TicketStatus::Finished->value)->count(),
            'failed' => (clone $baseQuery)->where('status', TicketStatus::Failed->value)->count(),
            'canceled' => (clone $baseQuery)->where('status', TicketStatus::Canceled->value)->count(),
        ];

        $recentTickets = (clone $baseQuery)
            ->with(['user', 'assignee'])
            ->latest()
            ->take(6)
            ->get();

        return view('pages.ticketing.index', compact('stats', 'recentTickets', 'isStaff'));
    }

    /**
     * Create Ticket Form
     */
    public function create(): View
    {
        $services = Ticket::SERVICES;
        $inventoryItems = InventoryItem::where('condition', '!=', 'retired')
            ->orderBy('asset_code')
            ->get();

        return view('pages.ticketing.create', compact('services', 'inventoryItems'));
    }

    /**
     * Store New Ticket
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category' => 'required|string|max:100',
            'service_item' => 'required|string|max:150',
            'title' => 'required|string|max:255',
            'description' => 'required|string|min:5',
            'location' => 'nullable|string|max:255',
            'inventory_item_id' => 'nullable|exists:inventory_items,id',
            'inventory_item_ids' => 'nullable|array',
            'inventory_item_ids.*' => 'integer|exists:inventory_items,id',
            'priority' => 'required|in:low,medium,high,urgent',
            'attachment' => 'nullable|file|mimes:jpeg,png,jpg,webp,heic,heif,pdf|max:10240',
        ]);

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('tickets/attachments', 'public');
        }

        $linkedItemIds = $validated['inventory_item_ids'] ?? [];
        if (! empty($validated['inventory_item_id'])) {
            $linkedItemIds[] = (int) $validated['inventory_item_id'];
        }
        $linkedItemIds = array_values(array_unique(array_filter(
            array_map('intval', $linkedItemIds),
            static fn (int $id): bool => $id > 0,
        )));

        $linkedItems = ! empty($linkedItemIds)
            ? InventoryItem::whereIn('id', $linkedItemIds)->get()
            : collect();
        $linkedItem = $linkedItems->first();

        $resolvedLocation = $validated['location'] ?? ($linkedItem?->display_location !== '-' ? $linkedItem?->display_location : null);

        // 1. Determine Authority from Service Catalog
        $authority = Ticket::SERVICES[$validated['category']][$validated['service_item']]['authority'] ?? 'local';

        // 2. Evaluate Ambiguity with Luna AI
        $ambiguityMsg = $this->lunaAi->evaluateAmbiguity(
            $validated['description'],
            $resolvedLocation,
            $authority
        );

        // 3. Routing & Status determination
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

            // SLA Hours based on priority
            $hours = match ($validated['priority']) {
                'urgent' => 4,
                'high' => 12,
                'medium' => 24,
                default => 48,
            };
            $slaDueAt = $now->copy()->addHours($hours);
        }

        // Short-window duplicate protection for double-tapped web submissions (15 seconds)
        $recentDuplicate = Ticket::where('user_id', Auth::id())
            ->where('source_channel', 'web')
            ->where('title', trim($validated['title']))
            ->where('description', trim($validated['description']))
            ->where('created_at', '>=', now()->subSeconds(15))
            ->latest()
            ->first();

        if ($recentDuplicate) {
            return redirect()->route('ticket.show', $recentDuplicate)
                ->with('success', "Tiket {$recentDuplicate->ticket_number} sudah tercatat.");
        }

        // 4. Create Ticket Record, Initial Message & SLA Logs Atomically
        $currentUser = $this->currentUser();
        $ticket = DB::transaction(function () use (
            $linkedItem,
            $linkedItemIds,
            $assignedTo,
            $validated,
            $authority,
            $scope,
            $status,
            $resolvedLocation,
            $attachmentPath,
            $slaDueAt,
            $slaPausedAt,
            $ambiguityMsg,
            $now,
            $currentUser,
        ): Ticket {
            $ticket = Ticket::create([
                'ticket_number' => Ticket::generateTicketNumber(),
                'user_id' => Auth::id(),
                'inventory_item_id' => $linkedItem?->id,
                'assigned_to' => $assignedTo,
                'category' => $validated['category'],
                'service_item' => $validated['service_item'],
                'authority' => $authority,
                'scope' => $scope,
                'status' => $status,
                'priority' => $validated['priority'],
                'location' => $resolvedLocation,
                'title' => $validated['title'],
                'description' => $validated['description'],
                'attachment' => $attachmentPath,
                'source_channel' => 'web',
                'sla_due_at' => $slaDueAt,
                'sla_paused_at' => $slaPausedAt,
            ]);

            if (! empty($linkedItemIds)) {
                $ticket->syncInventoryItems($linkedItemIds);
                $this->inventorySync->syncFromTicket($ticket, $currentUser);
            }

            // 5. Initial User Message
            TicketMessage::create([
                'ticket_id' => $ticket->id,
                'user_id' => Auth::id(),
                'sender_type' => 'user',
                'message' => $validated['description'],
                'attachment' => $attachmentPath,
            ]);

            // 6. Automated Luna Responses & SLA Logs
            if ($authority === 'central') {
                TicketMessage::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => null,
                    'sender_type' => 'ai_luna',
                    'message' => "Halo! Saya **Luna AI** dari PuTI Surabaya. Laporan Anda telah kami terima dan tercatat dengan nomor **{$ticket->ticket_number}**.\n\nKarena layanan **{$ticket->service_item}** memerlukan sinkronisasi pada sistem terpusat, tim PuTI Surabaya sedang mengawal dan mengoordinasikan laporan Anda bersama tim sistem terpusat Telkom University. Perkembangan penanganan akan kami informasikan langsung di thread ini. 😊",
                ]);

                TicketSlaLog::create([
                    'ticket_id' => $ticket->id,
                    'paused_at' => $now,
                    'reason' => 'Koordinasi Sistem Terpusat',
                ]);
            } elseif ($ambiguityMsg !== null) {
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

            return $ticket;
        });

        return redirect()->route('ticket.show', $ticket)
            ->with('success', "Tiket {$ticket->ticket_number} berhasil dibuat!");
    }

    /**
     * User's Tickets List
     */
    public function myTickets(Request $request): View
    {
        $query = Ticket::where('user_id', Auth::id())
            ->with(['assignee', 'messages'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $tickets = $query->paginate(10)->withQueryString();

        return view('pages.ticketing.my-tickets', compact('tickets'));
    }

    /**
     * Ticket Detail & Conversation Thread
     */
    public function show(Ticket $ticket): View
    {
        $user = $this->currentUser();
        $isStaff = $user->isTicketingStaff();

        // Authorization check: User can only view their own ticket, unless staff
        if (! $isStaff && $ticket->user_id !== $user->id) {
            abort(403, 'Anda tidak memiliki akses ke tiket ini.');
        }

        $ticket->load(['user', 'assignee', 'inventoryItem', 'inventoryItems', 'maintenances.inventoryItem', 'maintenances.performer', 'messages.user', 'slaLogs.creator']);
        $inventoryItems = InventoryItem::where('condition', '!=', 'retired')
            ->orderBy('asset_code')
            ->get();

        return view('pages.ticketing.show', compact('ticket', 'isStaff', 'inventoryItems'));
    }

    /**
     * Reply to Ticket Thread
     */
    public function reply(Request $request, Ticket $ticket): RedirectResponse
    {
        $user = $this->currentUser();
        $isStaff = $user->isTicketingStaff();

        if (! $isStaff && $ticket->user_id !== $user->id) {
            abort(403, 'Akses ditolak.');
        }

        if ($ticket->isFinalStatus()) {
            throw ValidationException::withMessages([
                'message' => 'Tiket ini sudah selesai atau dibatalkan dan tidak dapat menerima balasan baru.',
            ]);
        }

        $validated = $request->validate([
            'message' => 'required|string|min:2',
            'attachment' => 'nullable|file|mimes:jpeg,png,jpg,webp,heic,heif,pdf|max:10240',
            'is_internal' => 'nullable|boolean',
        ]);

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('tickets/attachments', 'public');
        }

        $senderType = $isStaff ? 'staff' : 'user';
        $isInternal = $isStaff && $request->boolean('is_internal');

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'sender_type' => $senderType,
            'message' => $validated['message'],
            'attachment' => $attachmentPath,
            'is_internal' => $isInternal,
        ]);

        // If user replies while ticket was waiting for clarification, resume SLA and activate ticket
        if (! $isStaff && $ticket->needsClarification()) {
            $now = now();
            $pausedMinutes = 0;

            if ($ticket->sla_paused_at) {
                $pausedMinutes = (int) $ticket->sla_paused_at->diffInMinutes($now);
            }

            // Close active SLA pause log
            $activeLog = $ticket->slaLogs()->whereNull('resumed_at')->latest()->first();
            if ($activeLog) {
                $activeLog->update([
                    'resumed_at' => $now,
                    'duration_minutes' => $pausedMinutes,
                ]);
            }

            // Push SLA due date forward
            $newDueAt = $ticket->sla_due_at ? $ticket->sla_due_at->copy()->addMinutes($pausedMinutes) : $now->copy()->addHours(24);

            // Auto-assign if not assigned
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
                'message' => 'ℹ️ **Sistem:** Pelapor telah memberikan informasi tambahan. Tiket aktif kembali dan SLA dilanjutkan.',
            ]);
        }

        return back()->with('success', 'Balasan berhasil dikirim.');
    }

    /**
     * Update Ticket Status (Technician only)
     */
    public function updateStatus(Request $request, Ticket $ticket): RedirectResponse
    {
        $user = $this->currentUser();
        $isStaff = $user->isTicketingStaff();

        if (! $isStaff) {
            abort(403, 'Hanya staf yang dapat mengubah status tiket.');
        }

        $validated = $request->validate([
            'status' => ['required', Rule::enum(TicketStatus::class)],
            'central_ticket_ref' => ['nullable', 'string', 'max:100'],
            'cancel_reason' => ['nullable', 'required_if:status,canceled', 'in:user_withdrawn,transferred_unit'],
            'transferred_unit' => ['nullable', 'required_if:cancel_reason,transferred_unit', 'string', 'max:150'],
            'status_note' => ['nullable', 'required_if:status,failed', 'string', 'min:5', 'max:1000'],
        ]);

        $currentEnum = $ticket->statusEnum() ?? TicketStatus::Open;
        $newEnum = TicketStatus::from($validated['status']);

        if ($currentEnum === $newEnum || ! $currentEnum->canTransitionTo($newEnum)) {
            throw ValidationException::withMessages([
                'status' => "Perubahan status dari {$currentEnum->label()} ke {$newEnum->label()} tidak diizinkan.",
            ]);
        }

        $now = now();
        $updateData = [
            'status' => $newEnum->value,
        ];

        if (! empty($validated['central_ticket_ref'])) {
            $updateData['central_ticket_ref'] = $validated['central_ticket_ref'];
        }

        if (! empty($validated['status_note'])) {
            $updateData['status_note'] = $validated['status_note'];
        }

        if ($newEnum === TicketStatus::Canceled) {
            $updateData['cancel_reason'] = $validated['cancel_reason'];
            $updateData['transferred_unit'] = $validated['cancel_reason'] === 'transferred_unit'
                ? $validated['transferred_unit']
                : null;
        }

        if ($newEnum === TicketStatus::Redirected) {
            $updateData['scope'] = 'escalated_central';
            $updateData['authority'] = 'central';
        } elseif ($currentEnum === TicketStatus::Redirected && $newEnum === TicketStatus::InProgress) {
            $updateData['scope'] = 'internal_surabaya';
            $updateData['authority'] = 'local';
        } elseif ($ticket->scope === 'needs_clarification' && $newEnum === TicketStatus::InProgress) {
            $updateData['scope'] = 'internal_surabaya';
        }

        // SLA Handling based on transition
        if ($newEnum->pausesSla() && ! $ticket->sla_paused_at) {
            $updateData['sla_paused_at'] = $now;

            TicketSlaLog::create([
                'ticket_id' => $ticket->id,
                'paused_at' => $now,
                'reason' => 'Koordinasi Sistem Terpusat',
                'created_by' => $user->id,
            ]);
        } elseif (! $newEnum->pausesSla() && $ticket->sla_paused_at) {
            $duration = (int) $ticket->sla_paused_at->diffInMinutes($now);
            if ($ticket->sla_due_at && ! $newEnum->stopsSla()) {
                $updateData['sla_due_at'] = $ticket->sla_due_at->copy()->addMinutes($duration);
            } elseif (! $ticket->sla_due_at && $newEnum === TicketStatus::InProgress) {
                $updateData['sla_due_at'] = $now->copy()->addHours(24);
            }

            $activeLog = $ticket->slaLogs()->whereNull('resumed_at')->latest()->first();
            if ($activeLog) {
                $activeLog->update([
                    'resumed_at' => $now,
                    'duration_minutes' => $duration,
                ]);
            }

            $updateData['sla_paused_at'] = null;
        }

        if ($newEnum->stopsSla()) {
            $updateData['resolved_at'] = $now;
        } elseif ($currentEnum === TicketStatus::Failed && $newEnum === TicketStatus::InProgress) {
            $updateData['resolved_at'] = null;
        }

        // Auto claim ticket if staff moves it to in_progress and not assigned
        if ($newEnum === TicketStatus::InProgress && ! $ticket->assigned_to) {
            $updateData['assigned_to'] = $user->id;
        }

        $ticket->update($updateData);
        $this->inventorySync->syncFromTicket($ticket, $user, $validated['status_note'] ?? null);

        $detailNote = '';
        if ($newEnum === TicketStatus::Canceled) {
            if ($ticket->cancel_reason === 'transferred_unit' && $ticket->transferred_unit) {
                $detailNote = " (Dialihkan ke unit kampus: **{$ticket->transferred_unit}**)";
            } else {
                $detailNote = ' (Dibatalkan / diselesaikan mandiri oleh pelapor)';
            }
        }
        if (! empty($validated['status_note'])) {
            $detailNote .= "\n\n*Catatan:* {$validated['status_note']}";
        }

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'sender_type' => 'system',
            'message' => "ℹ️ **Status Diperbarui:** Status tiket diubah menjadi **{$ticket->user_status_label}** oleh **{$user->name}**{$detailNote}.",
        ]);

        return back()->with('success', 'Status tiket berhasil diperbarui.');
    }

    /**
     * Self-Cancel Ticket (Reporter only, while status is open)
     */
    public function cancel(Request $request, Ticket $ticket): RedirectResponse
    {
        $user = $this->currentUser();

        if ($ticket->user_id !== $user->id) {
            abort(403, 'Hanya pelapor yang dapat membatalkan tiket ini.');
        }

        if ($ticket->status !== TicketStatus::Open->value) {
            throw ValidationException::withMessages([
                'status' => 'Tiket hanya dapat dibatalkan selama berstatus Diterima (Open).',
            ]);
        }

        $validated = $request->validate([
            'status_note' => 'nullable|string|max:500',
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
            'status_note' => $validated['status_note'] ?? null,
            'sla_paused_at' => null,
            'resolved_at' => $now,
        ]);

        $this->inventorySync->syncFromTicket($ticket, $user, $validated['status_note'] ?? null);

        $noteSuffix = ! empty($validated['status_note']) ? "\n\n*Catatan pelapor:* {$validated['status_note']}" : '';

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'sender_type' => 'system',
            'message' => "🚫 **Tiket Dibatalkan:** Pelapor (**{$user->name}**) membatalkan tiket ini karena kendala telah teratasi atau tidak lagi memerlukan penanganan.{$noteSuffix}",
        ]);

        return back()->with('success', 'Tiket berhasil dibatalkan.');
    }

    /**
     * Manual Override Scope (Technician only)
     */
    public function overrideScope(Request $request, Ticket $ticket): RedirectResponse
    {
        $user = $this->currentUser();
        $isStaff = $user->isTicketingStaff();

        if (! $isStaff) {
            abort(403, 'Akses ditolak.');
        }

        if ($ticket->isFinalStatus()) {
            throw ValidationException::withMessages([
                'scope' => 'Tiket yang sudah selesai atau dibatalkan tidak dapat diubah wewenangnya.',
            ]);
        }

        $validated = $request->validate([
            'scope' => 'required|in:internal_surabaya,escalated_central',
            'override_notes' => 'required|string|min:5',
            'central_ticket_ref' => 'nullable|string|max:100',
        ]);

        $now = now();
        $newScope = $validated['scope'];

        if ($newScope === 'escalated_central') {
            $ticket->update([
                'scope' => 'escalated_central',
                'status' => TicketStatus::Redirected->value,
                'authority' => 'central',
                'override_notes' => $validated['override_notes'],
                'central_ticket_ref' => $validated['central_ticket_ref'] ?? $ticket->central_ticket_ref,
                'sla_paused_at' => $now,
            ]);

            TicketSlaLog::create([
                'ticket_id' => $ticket->id,
                'paused_at' => $now,
                'reason' => 'Manual Override: Dialihkan ke Koordinasi Sistem Terpusat ('.$validated['override_notes'].')',
                'created_by' => $user->id,
            ]);

            $refText = $ticket->central_ticket_ref ? " (No. Rujukan Pusat: #{$ticket->central_ticket_ref})" : '';
            TicketMessage::create([
                'ticket_id' => $ticket->id,
                'user_id' => $user->id,
                'sender_type' => 'system',
                'message' => "🔄 **Pengalihan Wewenang:** Tiket dialihkan ke **Koordinasi Sistem Terpusat**{$refText}.\n\n*Catatan teknisi:* {$validated['override_notes']}",
            ]);
        } else {
            // Revert back to local Surabaya
            $newDueAt = $ticket->sla_due_at;
            if ($ticket->sla_paused_at) {
                $duration = (int) $ticket->sla_paused_at->diffInMinutes($now);
                $newDueAt = $ticket->sla_due_at
                    ? $ticket->sla_due_at->copy()->addMinutes($duration)
                    : $now->copy()->addHours(24);

                $activeLog = $ticket->slaLogs()->whereNull('resumed_at')->latest()->first();
                if ($activeLog) {
                    $activeLog->update([
                        'resumed_at' => $now,
                        'duration_minutes' => $duration,
                    ]);
                }
            } elseif (! $newDueAt) {
                $newDueAt = $now->copy()->addHours(24);
            }

            $ticket->update([
                'scope' => 'internal_surabaya',
                'status' => TicketStatus::InProgress->value,
                'authority' => 'local',
                'override_notes' => $validated['override_notes'],
                'sla_due_at' => $newDueAt,
                'sla_paused_at' => null,
                'assigned_to' => $ticket->assigned_to ?: $user->id,
            ]);

            TicketMessage::create([
                'ticket_id' => $ticket->id,
                'user_id' => $user->id,
                'sender_type' => 'system',
                'message' => "🔄 **Pengalihan Wewenang:** Tiket ditarik kembali untuk **ditangani secara lokal** oleh teknisi PuTI Surabaya.\n\n*Catatan teknisi:* {$validated['override_notes']}",
            ]);
        }

        $this->inventorySync->syncFromTicket($ticket, $user, $validated['override_notes']);

        return back()->with('success', 'Wewenang tiket berhasil dialihkan.');
    }

    /**
     * Link or Unlink Inventory Asset on Ticket (Technician only)
     */
    public function linkInventory(Request $request, Ticket $ticket): RedirectResponse
    {
        $user = $this->currentUser();
        $isStaff = $user->isTicketingStaff();

        if (! $isStaff) {
            abort(403, 'Akses ditolak.');
        }

        $validated = $request->validate([
            'inventory_item_id' => 'nullable|exists:inventory_items,id',
            'inventory_item_ids' => 'nullable|array',
            'inventory_item_ids.*' => 'integer|exists:inventory_items,id',
        ]);

        $newIds = $validated['inventory_item_ids'] ?? [];
        if (! empty($validated['inventory_item_id'])) {
            $newIds[] = (int) $validated['inventory_item_id'];
        }
        $newIds = array_values(array_unique(array_filter(
            array_map('intval', $newIds),
            static fn (int $id): bool => $id > 0,
        )));

        DB::transaction(function () use ($ticket, $newIds, $user): void {
            $previousIds = $ticket->linkedInventoryItems()->pluck('id')->all();
            $detachedIds = array_values(array_diff($previousIds, $newIds));

            $ticket->syncInventoryItems($newIds);

            if (! empty($detachedIds)) {
                $this->inventorySync->detachAssetsFromTicket($ticket, $detachedIds, $user);
            }

            if (! empty($newIds)) {
                $this->inventorySync->syncFromTicket($ticket, $user);

                $linkedAssets = $ticket->linkedInventoryItems();
                $summary = $linkedAssets
                    ->map(static fn (InventoryItem $asset): string => "{$asset->asset_code} ({$asset->name})")
                    ->implode(', ');
                $count = $linkedAssets->count();

                TicketMessage::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $user->id,
                    'sender_type' => 'system',
                    'message' => "📦 **Sinkronisasi Inventaris:** Tiket ditautkan ke **{$count} aset**: {$summary} oleh **{$user->name}**.",
                ]);
            } else {
                TicketMessage::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $user->id,
                    'sender_type' => 'system',
                    'message' => "📦 **Sinkronisasi Inventaris:** Seluruh tautan aset inventaris dilepas oleh **{$user->name}**.",
                ]);
            }
        });

        return back()->with('success', 'Tautan aset inventaris berhasil diperbarui.');
    }

    /**
     * NOC Tasks Board for Technicians
     */
    public function tasks(Request $request): View
    {
        $user = $this->currentUser();
        $isStaff = $user->isTicketingStaff();

        if (! $isStaff) {
            abort(403, 'Halaman ini khusus untuk staf dan teknisi PuTI.');
        }

        $tab = $request->query('tab', 'all');
        $finalStatuses = [TicketStatus::Finished->value, TicketStatus::Canceled->value];

        $query = Ticket::with(['user', 'assignee'])->latest();

        match ($tab) {
            'unassigned' => $query->whereNull('assigned_to')->whereNotIn('status', $finalStatuses),
            'open' => $query->where('status', TicketStatus::Open->value),
            'in_progress' => $query->where('status', TicketStatus::InProgress->value),
            'redirected' => $query->where('status', TicketStatus::Redirected->value),
            'finished' => $query->where('status', TicketStatus::Finished->value),
            'failed' => $query->where('status', TicketStatus::Failed->value),
            'canceled' => $query->where('status', TicketStatus::Canceled->value),
            default => $query->whereNotIn('status', $finalStatuses),
        };

        $tickets = $query->paginate(15)->withQueryString();

        $counts = [
            'all' => Ticket::whereNotIn('status', $finalStatuses)->count(),
            'unassigned' => Ticket::whereNull('assigned_to')->whereNotIn('status', $finalStatuses)->count(),
            'open' => Ticket::where('status', TicketStatus::Open->value)->count(),
            'in_progress' => Ticket::where('status', TicketStatus::InProgress->value)->count(),
            'redirected' => Ticket::where('status', TicketStatus::Redirected->value)->count(),
            'finished' => Ticket::where('status', TicketStatus::Finished->value)->count(),
            'failed' => Ticket::where('status', TicketStatus::Failed->value)->count(),
            'canceled' => Ticket::where('status', TicketStatus::Canceled->value)->count(),
        ];

        return view('pages.ticketing.tasks', compact('tickets', 'tab', 'counts'));
    }

    /**
     * Poll endpoint for NOC audio chime & live counter
     */
    public function tasksPoll(): JsonResponse
    {
        $latestTicket = Ticket::latest()->first();
        $activeCount = Ticket::whereNotIn('status', [
            TicketStatus::Finished->value,
            TicketStatus::Canceled->value,
        ])->count();

        return response()->json([
            'latest_id' => $latestTicket?->id ?? 0,
            'latest_number' => $latestTicket?->ticket_number ?? '',
            'latest_title' => $latestTicket?->title ?? '',
            'latest_cat' => $latestTicket?->category ?? '',
            'active_count' => $activeCount,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Ticket History
     */
    public function history(Request $request): View
    {
        $user = $this->currentUser();
        $isStaff = $user->isTicketingStaff();

        $query = Ticket::with(['user', 'assignee'])->latest();

        if (! $isStaff) {
            $query->where('user_id', $user->id);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('ticket_number', 'like', "%{$s}%")
                    ->orWhere('title', 'like', "%{$s}%")
                    ->orWhere('location', 'like', "%{$s}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $tickets = $query->paginate(15)->withQueryString();
        $categories = array_keys(Ticket::SERVICES);

        return view('pages.ticketing.history', compact('tickets', 'categories', 'isStaff'));
    }

    /**
     * Interactive Luna AI Page
     */
    public function luna(): View
    {
        return view('pages.ticketing.luna');
    }

    /**
     * Luna AJAX Suggestion for Form
     */
    public function lunaSuggest(Request $request): JsonResponse
    {
        $text = $request->input('text', '');
        $suggestion = $this->lunaAi->suggestService($text);

        return response()->json([
            'success' => $suggestion !== null,
            'suggestion' => $suggestion,
        ]);
    }

    /**
     * Luna AJAX Interactive Chat
     */
    public function lunaChat(Request $request): JsonResponse
    {
        $message = $request->input('message', '');
        if (empty(trim($message))) {
            return response()->json(['reply' => 'Ada yang bisa saya bantu terkait layanan IT PuTI Surabaya? 😊']);
        }

        $reply = $this->lunaAi->chat($message);

        return response()->json([
            'reply' => $reply,
            'timestamp' => now()->format('H:i'),
        ]);
    }

    private function currentUser(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}

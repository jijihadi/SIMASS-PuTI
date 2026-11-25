<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Middleware\VerifyLunaApiKey;
use App\Http\Resources\TicketResource;
use App\Models\InventoryItem;
use App\Models\ServiceCatalog;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
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

        $serviceCatalogs = ServiceCatalog::where('is_active', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get(['id', 'category', 'name', 'sla_resolution_hours']);

        return response()->json([
            'success' => true,
            'services' => Ticket::SERVICES,
            'service_catalogs' => $serviceCatalogs,
            'inventory_items' => $assets,
        ]);
    }

    /**
     * List Recent Tickets, or tickets changed since `updated_since` for incremental sync
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'updated_since' => ['nullable', 'date'],
            'reporter_phone' => ['nullable', 'string', 'max:80'],
        ], [
            'updated_since.date' => 'updated_since harus berupa tanggal/waktu yang valid (ISO 8601).',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors()->first(), $validator->errors()->toArray());
        }

        $query = Ticket::with(['user', 'assignee', 'inventoryItem', 'inventoryItems']);

        // `phone`/X-Wa-Number adalah nomor bot, jadi bukan penyaring tiket.
        $reporterPhone = VerifyLunaApiKey::normalizeWaPhone($request->query('reporter_phone'));

        if ($reporterPhone !== '') {
            $query->where('reporter_phone', $reporterPhone);
        } else {
            $query->where('source_channel', 'whatsapp_luna');
        }

        if ($request->filled('status')) {
            $status = strtolower(trim((string) $request->query('status')));
            $query->where('status', $status);
        }

        if ($request->filled('updated_since')) {
            // Urut dari perubahan terlama supaya pemanggil bisa lanjut dari updated_at terakhir.
            $query->where('updated_at', '>=', $request->date('updated_since')->setTimezone(config('app.timezone')))
                ->orderBy('updated_at')
                ->orderBy('id')
                ->take(100);
        } else {
            $query->latest()->take(20);
        }

        $tickets = $query->get();

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
        // Pre-normalize priority & authority so case/language variations from WA/AI don't fail unnecessarily
        $input = $request->all();
        if (isset($input['priority']) && is_string($input['priority'])) {
            $input['priority'] = $this->normalizePriority($input['priority']);
        }
        if (isset($input['authority']) && is_string($input['authority'])) {
            $input['authority'] = strtolower(trim($input['authority']));
        }
        if (isset($input['asset_code']) && is_string($input['asset_code'])) {
            $input['asset_code'] = strtoupper(trim($input['asset_code']));
        }
        if (isset($input['asset_codes']) && is_array($input['asset_codes'])) {
            $input['asset_codes'] = array_values(array_filter(array_map(
                static fn ($code): string => is_string($code) ? strtoupper(trim($code)) : '',
                $input['asset_codes'],
            )));
        }
        $input['external_id'] = $request->header('Idempotency-Key') ?: ($input['external_id'] ?? null);

        $validator = Validator::make($input, [
            'phone' => ['required', 'string', 'max:80'],
            'reporter_phone' => ['nullable', 'string', 'max:80'],
            'reporter_status' => ['nullable', 'in:mahasiswa,dosen,staf'],
            'unit_kerja' => ['nullable', 'string', 'max:150'],
            'request_type' => ['nullable', 'in:PENGADUAN IT,PERMINTAAN LAYANAN'],
            'external_id' => ['nullable', 'string', 'max:100'],
            'reporter_name' => ['nullable', 'string', 'max:150'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'min:3', 'max:4000'],
            'category' => ['nullable', 'string', 'max:100'],
            'service_item' => ['nullable', 'string', 'max:150'],
            'authority' => ['nullable', 'in:local,central'],
            'location' => ['nullable', 'string', 'max:255'],
            'priority' => ['nullable', 'in:low,medium,high,urgent'],
            'asset_code' => ['nullable', 'string', 'max:50'],
            'asset_codes' => ['nullable', 'array'],
            'asset_codes.*' => ['string', 'max:50'],
            'inventory_item_id' => ['nullable', 'exists:inventory_items,id'],
            'inventory_item_ids' => ['nullable', 'array'],
            'inventory_item_ids.*' => ['integer', 'exists:inventory_items,id'],
        ], [
            'phone.required' => 'Nomor WhatsApp pelapor wajib disertakan.',
            'description.required' => 'Deskripsi kendala tidak boleh kosong.',
            'description.min' => 'Deskripsi kendala terlalu singkat (minimal 3 karakter).',
            'description.max' => 'Deskripsi kendala terlalu panjang (maksimal 4000 karakter).',
            'priority.in' => 'Prioritas harus salah satu dari: low, medium, high, atau urgent.',
            'authority.in' => 'Authority harus bernilai local atau central.',
            'inventory_item_id.exists' => 'ID aset inventaris tidak ditemukan di sistem.',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors()->first(), $validator->errors()->toArray());
        }

        $validated = $validator->validated();

        // Reject WhatsApp group JIDs & malformed phone numbers
        $phones = [];
        foreach (['phone', 'reporter_phone'] as $field) {
            $raw = $validated[$field] ?? null;
            if ($raw === null) {
                continue;
            }

            if (str_contains(strtolower($raw), '@g.us')) {
                return $this->validationErrorResponse(
                    'Pelaporan tiket hanya dapat dilakukan melalui chat pribadi, bukan dari grup WhatsApp.',
                    [$field => ['Nomor berasal dari grup WhatsApp (@g.us).']]
                );
            }

            $phones[$field] = VerifyLunaApiKey::normalizeWaPhone($raw);
            if (strlen($phones[$field]) < 9 || strlen($phones[$field]) > 15) {
                return $this->validationErrorResponse(
                    'Format nomor WhatsApp tidak valid. Pastikan mengirim nomor telepon yang benar.',
                    [$field => ['Format nomor WhatsApp tidak valid (harus 9–15 digit).']]
                );
            }
        }

        // `phone` boleh nomor bot; `reporter_phone` nomor pelapor asli, tidak dicek ke nomor yang diizinkan.
        $normalizedPhone = $phones['phone'];
        $reporterPhone = $phones['reporter_phone'] ?? $normalizedPhone;

        // Privacy Guardrail: prevent storing raw passwords/PINs from WhatsApp chat
        if ($this->containsSensitiveCredentials($validated['description'])) {
            $warning = '⚠️ Mohon jangan menuliskan kata sandi, PIN, atau kredensial rahasia Anda pada laporan WhatsApp. Silakan kirim ulang laporan kendala tanpa menyertakan kata sandi.';

            return response()->json([
                'success' => false,
                'message' => 'Laporan ditolak oleh Privacy Guardrail karena terdeteksi memuat kata sandi/PIN.',
                'luna_reply' => $warning,
                'errors' => ['description' => ['Terdeteksi informasi kredensial sensitif (password/PIN).']],
            ], 422);
        }

        // Ensure strict single-number check if configured
        $allowedNumberRaw = (string) (config('services.luna.allowed_wa_number') ?: Setting::where('key', 'luna_allowed_wa_number')->value('value'));
        $allowedNumber = VerifyLunaApiKey::normalizeWaPhone($allowedNumberRaw);

        if ($allowedNumber !== '' && ! hash_equals($allowedNumber, $normalizedPhone)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: Nomor WhatsApp ini tidak diizinkan untuk membuat tiket melalui Luna AI.',
            ], 403);
        }

        // Webhook retry / double-send deduplication (within 60 seconds)
        $recentDuplicate = Ticket::with(['user', 'assignee', 'inventoryItem', 'messages.user'])
            ->where('reporter_phone', $reporterPhone)
            ->where('source_channel', 'whatsapp_luna')
            ->where('description', trim($validated['description']))
            ->where('created_at', '>=', now()->subSeconds(60))
            ->latest()
            ->first();

        if ($recentDuplicate) {
            return $this->duplicateResponse($recentDuplicate);
        }

        $idempotencyKey = trim($validated['external_id'] ?? '');

        if ($idempotencyKey === '') {
            return $this->createTicket($validated, $reporterPhone);
        }

        // Same Idempotency-Key within 24 hours returns the existing ticket
        return Cache::lock("luna-ticket-idempotency:{$idempotencyKey}", 10)
            ->block(3, function () use ($validated, $reporterPhone, $idempotencyKey): JsonResponse {
                $existing = Ticket::with(['user', 'assignee', 'inventoryItem', 'messages.user'])
                    ->where('idempotency_key', $idempotencyKey)
                    ->where('created_at', '>=', now()->subDay())
                    ->latest('id')
                    ->first();

                return $existing
                    ? $this->duplicateResponse($existing)
                    : $this->createTicket($validated, $reporterPhone, $idempotencyKey);
            });
    }

    /**
     * Persist a new ticket from a validated Luna payload.
     *
     * @param  array<string, mixed>  $validated
     */
    private function createTicket(array $validated, string $reporterPhone, ?string $idempotencyKey = null): JsonResponse
    {
        $trimmedDescription = trim($validated['description']);
        $mainDescription = $this->mainDescription($trimmedDescription);
        $reporter = $this->lunaReporter();

        // Resolve linked inventory items by IDs or case-insensitive asset codes
        $warnings = [];
        $requestedIds = $validated['inventory_item_ids'] ?? [];
        if (! empty($validated['inventory_item_id'])) {
            $requestedIds[] = (int) $validated['inventory_item_id'];
        }
        $requestedIds = array_values(array_unique(array_map('intval', $requestedIds)));

        $requestedCodes = $validated['asset_codes'] ?? [];
        if (! empty($validated['asset_code'])) {
            $requestedCodes[] = $validated['asset_code'];
        }
        $requestedCodes = array_values(array_unique($requestedCodes));

        $linkedItems = collect();
        if (! empty($requestedIds)) {
            $linkedItems = $linkedItems->merge(InventoryItem::whereIn('id', $requestedIds)->get());
        }

        foreach ($requestedCodes as $code) {
            $matched = InventoryItem::whereRaw('UPPER(asset_code) = ?', [$code])->first();
            if ($matched) {
                $linkedItems->push($matched);
            } else {
                $warnings[] = "Kode aset '{$code}' tidak ditemukan di inventaris PuTI. Tiket tetap dibuat tanpa tautan aset tersebut.";
            }
        }

        $linkedItems = $linkedItems->unique('id')->values();
        $linkedItemIds = $linkedItems->pluck('id')->all();
        $linkedItem = $linkedItems->first();

        // Category & service_item classification:
        // Trust AI/payload classification when provided; only infer via LunaAiService when omitted.
        $category = isset($validated['category']) && trim($validated['category']) !== ''
            ? trim($validated['category'])
            : null;
        $serviceItem = isset($validated['service_item']) && trim($validated['service_item']) !== ''
            ? trim($validated['service_item'])
            : null;

        if ($category === null || $serviceItem === null) {
            $suggestion = $this->lunaAi->suggestService(($validated['title'] ?? '').' '.$mainDescription);
            if ($suggestion) {
                $category ??= $suggestion['category'];
                $serviceItem ??= $suggestion['service_item'];
            } else {
                $category ??= 'Lainnya';
                $serviceItem ??= 'Konsultasi / Kendala IT Lainnya';
            }
        }

        // Resolve authority: payload takes precedence -> Ticket::SERVICES lookup -> default 'local'
        $authority = $validated['authority']
            ?? (Ticket::SERVICES[$category][$serviceItem]['authority'] ?? 'local');

        $location = $validated['location'] ?? ($linkedItem?->display_location !== '-' ? $linkedItem?->display_location : null);
        $priority = $validated['priority'] ?? 'medium';
        $title = ! empty($validated['title'])
            ? trim($validated['title'])
            : Str::limit($mainDescription, 80, '...');

        // Deskripsi lengkap, bukan $mainDescription: Luna belum meneruskan pertanyaan klarifikasi,
        // jadi laporan pendek akan tertahan tanpa teknisi.
        $ambiguityMsg = $this->lunaAi->evaluateAmbiguity(
            $trimmedDescription,
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

            $catalogSla = ServiceCatalog::where('is_active', true)
                ->where('name', $serviceItem)
                ->value('sla_resolution_hours');

            $hours = $catalogSla ?: match ($priority) {
                'urgent' => 4,
                'high' => 12,
                'medium' => 24,
                default => 48,
            };
            $slaDueAt = $now->copy()->addHours((int) $hours);
        }

        $lunaReply = '';
        $ticket = DB::transaction(function () use (
            $reporter,
            $linkedItem,
            $linkedItemIds,
            $assignedTo,
            $category,
            $serviceItem,
            $authority,
            $scope,
            $status,
            $priority,
            $location,
            $title,
            $trimmedDescription,
            $reporterPhone,
            $idempotencyKey,
            $validated,
            $slaDueAt,
            $slaPausedAt,
            $ambiguityMsg,
            $now,
            &$lunaReply,
        ): Ticket {
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
                'description' => $trimmedDescription,
                'source_channel' => 'whatsapp_luna',
                'reporter_phone' => $reporterPhone,
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
                'message' => $trimmedDescription,
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

            if (! empty($linkedItemIds)) {
                $ticket->syncInventoryItems($linkedItemIds);
                $this->inventorySync->syncFromTicket($ticket, $reporter);
            }

            return $ticket;
        });

        $ticket->load(['user', 'assignee', 'inventoryItem', 'inventoryItems', 'messages.user']);

        $response = [
            'success' => true,
            'message' => "Tiket {$ticket->ticket_number} berhasil dibuat melalui WhatsApp Luna AI.",
            'luna_reply' => $lunaReply,
            'data' => new TicketResource($ticket),
        ];

        if (! empty($warnings)) {
            $response['warnings'] = $warnings;
        }

        return response()->json($response, 201);
    }

    /**
     * Show Ticket Status & Thread by Ticket Number
     */
    public function show(string $ticketNumber): JsonResponse
    {
        $normalizedNumber = $this->normalizeTicketNumber($ticketNumber);

        $ticket = Ticket::with(['user', 'assignee', 'inventoryItem', 'inventoryItems', 'messages.user'])
            ->whereRaw('UPPER(ticket_number) = ?', [$normalizedNumber])
            ->first();

        if (! $ticket) {
            return response()->json([
                'success' => false,
                'message' => "Tiket {$normalizedNumber} tidak ditemukan.",
                'luna_reply' => "Maaf, nomor tiket **{$normalizedNumber}** tidak ditemukan di sistem kami. Mohon periksa kembali nomor tiket Anda.",
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
        $normalizedNumber = $this->normalizeTicketNumber($ticketNumber);

        $ticket = Ticket::whereRaw('UPPER(ticket_number) = ?', [$normalizedNumber])->first();

        if (! $ticket) {
            return response()->json([
                'success' => false,
                'message' => "Tiket {$normalizedNumber} tidak ditemukan.",
                'luna_reply' => "Maaf, nomor tiket **{$normalizedNumber}** tidak ditemukan.",
            ], 404);
        }

        if ($ticket->isFinalStatus()) {
            return response()->json([
                'success' => false,
                'message' => 'Tiket ini sudah selesai atau dibatalkan dan tidak dapat menerima balasan baru.',
                'luna_reply' => "Tiket **{$ticket->ticket_number}** sudah berstatus **{$ticket->user_status_label}** dan tidak dapat menerima balasan baru. Silakan buat laporan baru jika masih ada kendala.",
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'string', 'max:80'],
            'message' => ['required', 'string', 'min:1', 'max:4000'],
        ], [
            'phone.required' => 'Nomor WhatsApp pengirim wajib disertakan.',
            'message.required' => 'Pesan balasan tidak boleh kosong.',
            'message.max' => 'Pesan balasan terlalu panjang (maksimal 4000 karakter).',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors()->first(), $validator->errors()->toArray());
        }

        $validated = $validator->validated();

        if ($this->containsSensitiveCredentials($validated['message'])) {
            return response()->json([
                'success' => false,
                'message' => 'Balasan ditolak oleh Privacy Guardrail karena terdeteksi memuat kata sandi/PIN.',
                'luna_reply' => '⚠️ Mohon jangan menuliskan kata sandi, PIN, atau kredensial rahasia Anda. Silakan kirim ulang balasan tanpa menyertakan kata sandi.',
                'errors' => ['message' => ['Terdeteksi informasi kredensial sensitif (password/PIN).']],
            ], 422);
        }

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $ticket->user_id,
            'sender_type' => 'user',
            'message' => trim($validated['message']),
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

        $ticket->load(['user', 'assignee', 'inventoryItem', 'inventoryItems', 'messages.user']);

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
        $normalizedNumber = $this->normalizeTicketNumber($ticketNumber);

        $ticket = Ticket::whereRaw('UPPER(ticket_number) = ?', [$normalizedNumber])->first();

        if (! $ticket) {
            return response()->json([
                'success' => false,
                'message' => "Tiket {$normalizedNumber} tidak ditemukan.",
                'luna_reply' => "Maaf, nomor tiket **{$normalizedNumber}** tidak ditemukan.",
            ], 404);
        }

        if ($ticket->status !== TicketStatus::Open->value) {
            return response()->json([
                'success' => false,
                'message' => 'Tiket hanya dapat dibatalkan selama berstatus Diterima (Open).',
                'luna_reply' => "Tiket **{$ticket->ticket_number}** tidak dapat dibatalkan karena sudah berstatus **{$ticket->user_status_label}**.",
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'string', 'max:80'],
            'status_note' => ['nullable', 'string', 'max:500'],
        ], [
            'phone.required' => 'Nomor WhatsApp pelapor wajib disertakan.',
            'status_note.max' => 'Catatan pembatalan maksimal 500 karakter.',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors()->first(), $validator->errors()->toArray());
        }

        $validated = $validator->validated();

        $now = now();
        DB::transaction(function () use ($ticket, $validated, $now): void {
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
        });

        $ticket->load(['user', 'assignee', 'inventoryItem', 'inventoryItems', 'messages.user']);

        return response()->json([
            'success' => true,
            'message' => "Tiket {$ticket->ticket_number} berhasil dibatalkan.",
            'data' => new TicketResource($ticket),
        ]);
    }

    /**
     * Normalize ticket number from WhatsApp text (strip '#', trim spaces, uppercase).
     */
    private function normalizeTicketNumber(string $ticketNumber): string
    {
        return strtoupper(ltrim(trim($ticketNumber), '#'));
    }

    /**
     * Normalize priority including case & Indonesian aliases.
     */
    private function normalizePriority(string $priority): string
    {
        return match (strtolower(trim($priority))) {
            'rendah', 'low' => 'low',
            'sedang', 'normal', 'medium' => 'medium',
            'tinggi', 'high' => 'high',
            'darurat', 'kritis', 'urgent' => 'urgent',
            default => strtolower(trim($priority)),
        };
    }

    /**
     * Detect potential raw passwords or PINs in WhatsApp messages.
     */
    private function containsSensitiveCredentials(string $text): bool
    {
        $key = '\b(?:password|pass|kata\s*sandi|pin\s*saya|kredensial)(?:\s*(?:saya|nya))?\b';
        // Kata sesudahnya yang berarti keluhan, bukan nilai sandi.
        $complaint = '(?!(?:lupa|salah|tidak|nggak|belum|sudah|gagal|error|expired|ditolak|terkunci|hilang|reset|ganti|minta|mohon|akun|email|igracias|office365)\b)';
        // Sesudah ":" "=" "adalah" apa pun dianggap nilai. Tanpa itu, hanya kata yang memuat angka/simbol.
        $explicit = '\s*(?:[:=]|adalah\b)\s*'.$complaint.'\S{4,}';
        $bare = '\s+'.$complaint.'(?=\S*[\d@#$%^&])\S{5,}';

        return (bool) preg_match('/'.$key.'(?:'.$explicit.'|'.$bare.')/i', $text);
    }

    /**
     * Consistent 422 JSON response for WhatsApp bot consumption.
     *
     * @param  array<string, list<string>>  $errors
     */
    private function validationErrorResponse(string $message, array $errors): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'luna_reply' => "Maaf, permintaan belum dapat diproses: {$message}",
            'errors' => $errors,
        ], 422);
    }

    /**
     * 200 response pointing to the ticket that already exists for a repeated request.
     */
    private function duplicateResponse(Ticket $ticket): JsonResponse
    {
        return response()->json([
            'success' => true,
            'duplicate' => true,
            'message' => "Tiket {$ticket->ticket_number} sudah tercatat sebelumnya (duplikat webhook diabaikan).",
            'luna_reply' => "Laporan Anda sudah tercatat dengan nomor tiket **{$ticket->ticket_number}** (Status: **{$ticket->user_status_label}**).",
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

        return $main !== '' ? $main : $normalized;
    }

    /**
     * Satu akun terkunci untuk semua tiket WhatsApp. Nomor HP dan NIM/NIP tidak dicocokkan ke akun:
     * nomor di profil bisa diubah sendiri dan NIM bisa diketik siapa pun.
     */
    private function lunaReporter(): User
    {
        // Dicari lewat email, bukan username: username bisa diambil siapa pun dari halaman profil.
        // withTrashed: akun yang terhapus admin tidak boleh menghentikan semua laporan WhatsApp.
        return User::withTrashed()->firstOrCreate(['email' => 'luna-whatsapp@simass.invalid'], [
            'name' => 'Pelapor WhatsApp (Luna)',
            'password' => Str::random(40),
            'is_active' => false,
        ]);
    }
}

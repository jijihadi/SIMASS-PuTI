<?php

namespace App\Models;

use App\Enums\TicketStatus;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

    /**
     * Customer-Centric Service Catalog:
     * Structured by user domain while mapping internal authority under the hood.
     */
    public const SERVICES = [
        'Jaringan & Internet' => [
            'Wi-Fi Kampus (Eduroam / TelU-Guest)' => ['authority' => 'local', 'icon' => 'wifi'],
            'Jaringan Kabel LAN Gedung / Ruangan' => ['authority' => 'local', 'icon' => 'cable'],
        ],
        'Akun & Akses Terpadu' => [
            'Akun iGracias & Single Sign-On (SSO)' => ['authority' => 'central', 'icon' => 'key-round'],
            'Email Kampus & Lisensi Microsoft 365' => ['authority' => 'central', 'icon' => 'mail'],
        ],
        'Aplikasi Pembelajaran' => [
            'LMS CeLOE & Perkuliahan Daring' => ['authority' => 'central', 'icon' => 'graduation-cap'],
            'Sistem Informasi Akademik Lokal' => ['authority' => 'local',   'icon' => 'layout-dashboard'],
        ],
        'Fasilitas & Lab IT' => [
            'Komputer Laboratorium / PC Kerja' => ['authority' => 'local', 'icon' => 'monitor'],
            'Proyektor / Perangkat Ruang Kelas' => ['authority' => 'local', 'icon' => 'projector'],
        ],
        'Lainnya' => [
            'Konsultasi / Kendala IT Lainnya' => ['authority' => 'local', 'icon' => 'help-circle'],
        ],
    ];

    protected $fillable = [
        'ticket_number',
        'user_id',
        'inventory_item_id',
        'assigned_to',
        'category',
        'service_item',
        'authority',
        'scope',
        'status',
        'priority',
        'location',
        'title',
        'description',
        'attachment',
        'central_ticket_ref',
        'override_notes',
        'cancel_reason',
        'transferred_unit',
        'status_note',
        'source_channel',
        'reporter_phone',
        'reporter_name',
        'sla_due_at',
        'sla_paused_at',
        'resolved_at',
    ];

    protected $casts = [
        'sla_due_at' => 'datetime',
        'sla_paused_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::created(function (self $ticket): void {
            if ($ticket->inventory_item_id) {
                $ticket->inventoryItems()->syncWithoutDetaching([$ticket->inventory_item_id]);
            }
        });

        static::updated(function (self $ticket): void {
            if ($ticket->wasChanged('inventory_item_id') && $ticket->inventory_item_id) {
                $ticket->inventoryItems()->syncWithoutDetaching([$ticket->inventory_item_id]);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function inventoryItems(): BelongsToMany
    {
        return $this->belongsToMany(InventoryItem::class, 'ticket_inventory_item')
            ->withTimestamps()
            ->orderBy('inventory_items.asset_code');
    }

    /**
     * Get all inventory items linked to this ticket (pivot with fallback to legacy primary column).
     *
     * @return Collection<int, InventoryItem>
     */
    public function linkedInventoryItems(): Collection
    {
        $items = $this->relationLoaded('inventoryItems')
            ? $this->inventoryItems
            : $this->inventoryItems()->get();

        if ($items->isEmpty() && $this->inventory_item_id) {
            $primary = $this->relationLoaded('inventoryItem')
                ? $this->inventoryItem
                : $this->inventoryItem()->first();

            if ($primary) {
                return new Collection([$primary]);
            }
        }

        return $items;
    }

    /**
     * Synchronize linked inventory assets and keep legacy primary inventory_item_id in sync.
     *
     * @param  array<int, int|string|null>  $itemIds
     */
    public function syncInventoryItems(array $itemIds): void
    {
        $uniqueIds = array_values(array_unique(array_filter(
            array_map(static fn (int|string|null $id): int => (int) $id, $itemIds),
            static fn (int $id): bool => $id > 0,
        )));

        $this->inventoryItems()->sync($uniqueIds);

        $primaryId = $uniqueIds[0] ?? null;
        if ($this->inventory_item_id !== $primaryId) {
            $this->forceFill(['inventory_item_id' => $primaryId])->saveQuietly();
        }

        $this->unsetRelation('inventoryItems');
        $this->unsetRelation('inventoryItem');
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(InventoryMaintenance::class, 'ticket_id')->orderBy('created_at', 'desc');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class)->orderBy('created_at', 'asc');
    }

    public function slaLogs(): HasMany
    {
        return $this->hasMany(TicketSlaLog::class)->orderBy('created_at', 'desc');
    }

    public function statusEnum(): ?TicketStatus
    {
        return TicketStatus::tryFrom($this->status);
    }

    public function needsClarification(): bool
    {
        return $this->status === TicketStatus::Open->value && $this->scope === 'needs_clarification';
    }

    public function isFinalStatus(): bool
    {
        return $this->statusEnum()?->isFinal() ?? false;
    }

    /**
     * @return list<TicketStatus>
     */
    public function allowedNextStatuses(): array
    {
        return $this->statusEnum()?->allowedTransitions() ?? [];
    }

    /**
     * Nama pelapor untuk ditampilkan. Tiket WhatsApp bisa menumpang akun Luna,
     * jadi nama yang dikirim Luna didahulukan.
     */
    public function getReporterDisplayNameAttribute(): string
    {
        return $this->reporter_name ?: ($this->user?->name ?? 'Pelapor');
    }

    /**
     * User-Friendly Status Label (Empathetic & Transparent)
     */
    public function getUserStatusLabelAttribute(): string
    {
        return $this->statusEnum()?->label() ?? ucfirst($this->status);
    }

    public function getUserStatusColorAttribute(): string
    {
        return $this->statusEnum()?->color() ?? 'gray';
    }

    public static function generateTicketNumber(): string
    {
        $prefix = 'TIC-'.now()->format('Ym').'-';
        $latest = self::where('ticket_number', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();

        $number = 1;
        if ($latest && preg_match('/-(\d+)$/', $latest->ticket_number, $matches)) {
            $number = ((int) $matches[1]) + 1;
        }

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $candidate = $prefix.str_pad((string) ($number + $attempt), 4, '0', STR_PAD_LEFT);
            if (! self::where('ticket_number', $candidate)->exists()) {
                return $candidate;
            }
        }

        return $prefix.strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    }
}

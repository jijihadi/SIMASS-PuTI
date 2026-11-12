<?php

namespace App\Models;

use App\Enums\InventoryCondition;
use App\Enums\InventoryStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryHistory extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'inventory_item_id',
        'user_id',
        'change_type',
        'old_location',
        'new_location',
        'old_status',
        'new_status',
        'old_condition',
        'new_condition',
        'notes',
    ];

    /**
     * The inventory item that owns this history record.
     */
    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    /**
     * The user who made the change.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Human-readable label for change type.
     */
    public function getChangeTypeLabelAttribute(): string
    {
        return match ($this->change_type) {
            'created' => 'Aset Didaftarkan',
            'mutation' => 'Mutasi Lokasi / Status / Kondisi',
            'status_condition' => 'Pembaruan Status / Kondisi',
            'maintenance' => 'Aktivitas Perawatan / Maintenance',
            'ticket_sync' => 'Sinkronisasi Tiket Insiden',
            'bulk_import' => 'Import Massal',
            default => ucfirst(str_replace('_', ' ', $this->change_type)),
        };
    }

    /**
     * Resolve status label.
     */
    public static function formatStatusLabel(?string $status): string
    {
        if (! $status) {
            return '-';
        }

        return InventoryStatus::tryFrom($status)?->label() ?? ucfirst(str_replace('_', ' ', $status));
    }

    /**
     * Resolve condition label.
     */
    public static function formatConditionLabel(?string $condition): string
    {
        if (! $condition) {
            return '-';
        }

        return InventoryCondition::tryFrom($condition)?->label() ?? ucfirst(str_replace('_', ' ', $condition));
    }
}

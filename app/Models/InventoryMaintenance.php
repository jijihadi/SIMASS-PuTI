<?php

namespace App\Models;

use App\Enums\MaintenanceStatus;
use Database\Factories\InventoryMaintenanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMaintenance extends Model
{
    /** @use HasFactory<InventoryMaintenanceFactory> */
    use HasFactory;

    protected $fillable = [
        'maintenance_code',
        'inventory_item_id',
        'ticket_id',
        'bulk_batch_id',
        'type',
        'status',
        'result_condition',
        'performed_by',
        'title',
        'action_notes',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function bulkBatch(): BelongsTo
    {
        return $this->belongsTo(BulkMaintenanceBatch::class, 'bulk_batch_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function statusEnum(): ?MaintenanceStatus
    {
        return MaintenanceStatus::tryFrom($this->status);
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->statusEnum()?->label() ?? ucfirst($this->status);
    }

    public function getStatusColorAttribute(): string
    {
        return $this->statusEnum()?->color() ?? 'gray';
    }

    public static function generateMaintenanceCode(): string
    {
        $prefix = 'MNT-'.now()->format('Ym').'-';
        $latest = self::where('maintenance_code', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();

        $number = 1;
        if ($latest && preg_match('/-(\d+)$/', $latest->maintenance_code, $matches)) {
            $number = ((int) $matches[1]) + 1;
        }

        return $prefix.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }
}

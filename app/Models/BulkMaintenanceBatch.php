<?php

namespace App\Models;

use App\Enums\MaintenanceStatus;
use Database\Factories\BulkMaintenanceBatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BulkMaintenanceBatch extends Model
{
    /** @use HasFactory<BulkMaintenanceBatchFactory> */
    use HasFactory;

    protected $fillable = [
        'batch_code',
        'title',
        'maintenance_type',
        'campus_location_id',
        'category_filter',
        'status',
        'scheduled_at',
        'completed_at',
        'performed_by',
        'notes',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function campusLocation(): BelongsTo
    {
        return $this->belongsTo(CampusLocation::class, 'campus_location_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(InventoryMaintenance::class, 'bulk_batch_id')->orderBy('id', 'asc');
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

    public static function generateBatchCode(): string
    {
        $prefix = 'BM-'.now()->format('Ym').'-';
        $latest = self::where('batch_code', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();

        $number = 1;
        if ($latest && preg_match('/-(\d+)$/', $latest->batch_code, $matches)) {
            $number = ((int) $matches[1]) + 1;
        }

        return $prefix.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }
}

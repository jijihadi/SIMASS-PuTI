<?php

namespace App\Models;

use App\Enums\InventoryAssetType;
use App\Enums\InventoryCondition;
use App\Enums\InventoryStatus;
use Database\Factories\InventoryItemFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    /** @use HasFactory<InventoryItemFactory> */
    use HasFactory;

    public const CATEGORIES = [
        'Komputer & PC Lab',
        'Perangkat Jaringan',
        'Proyektor & AV',
        'Server & Infrastruktur',
        'Digital & Lisensi Software',
        'Periferal & Lainnya',
    ];

    /**
     * Get active inventory categories from database.
     *
     * @return list<string>
     */
    public static function getCategories(): array
    {
        try {
            $dbCategories = InventoryCategory::where('is_active', true)->orderBy('id')->pluck('name')->all();
            if (! empty($dbCategories)) {
                return $dbCategories;
            }
        } catch (\Throwable) {
            // fallback if table does not exist
        }

        return self::CATEGORIES;
    }

    /**
     * Get active asset types from database.
     *
     * @return Collection<int, InventoryType>
     */
    public static function getAssetTypes(): Collection
    {
        return InventoryType::where('is_active', true)->orderBy('id')->get();
    }

    /**
     * Get active status lookups from database.
     *
     * @return Collection<int, InventoryStatusLookup>
     */
    public static function getStatusLookups(): Collection
    {
        return InventoryStatusLookup::where('is_active', true)->orderBy('id')->get();
    }

    /**
     * Get active condition lookups from database.
     *
     * @return Collection<int, InventoryConditionLookup>
     */
    public static function getConditionLookups(): Collection
    {
        return InventoryConditionLookup::where('is_active', true)->orderBy('id')->get();
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'asset_code',
        'name',
        'category',
        'asset_type',
        'brand_model',
        'serial_number',
        'building_id',
        'floor_id',
        'campus_location_id',
        'location_detail',
        'condition',
        'status',
        'last_maintained_at',
        'maintenance_cycle',
        'next_maintenance_date',
        'renewal_date',
        'billing_cycle',
        'access_url',
        'access_recipients',
        'notes',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'last_maintained_at' => 'datetime',
        'next_maintenance_date' => 'date',
        'renewal_date' => 'date',
    ];

    public function building(): BelongsTo
    {
        return $this->belongsTo(CampusLocation::class, 'building_id');
    }

    public function floor(): BelongsTo
    {
        return $this->belongsTo(CampusLocation::class, 'floor_id');
    }

    public function campusLocation(): BelongsTo
    {
        return $this->belongsTo(CampusLocation::class, 'campus_location_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'inventory_item_id')->orderBy('created_at', 'desc');
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(InventoryMaintenance::class, 'inventory_item_id')->orderBy('created_at', 'desc');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(InventoryHistory::class, 'inventory_item_id')->orderBy('id', 'desc');
    }

    public function conditionEnum(): ?InventoryCondition
    {
        return InventoryCondition::tryFrom($this->condition);
    }

    public function getConditionLabelAttribute(): string
    {
        return $this->conditionEnum()?->label() ?? ucfirst($this->condition);
    }

    public function getConditionColorAttribute(): string
    {
        return $this->conditionEnum()?->color() ?? 'gray';
    }

    public function getConditionBadgeClassAttribute(): string
    {
        return $this->conditionEnum()?->badgeClass()
            ?? 'bg-gray-100 text-gray-700 border-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-700';
    }

    public function statusEnum(): ?InventoryStatus
    {
        return InventoryStatus::tryFrom($this->status);
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->statusEnum()?->label() ?? ucfirst(str_replace('_', ' ', $this->status));
    }

    public function getStatusColorAttribute(): string
    {
        return $this->statusEnum()?->color() ?? 'gray';
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return $this->statusEnum()?->badgeClass()
            ?? 'bg-gray-100 text-gray-700 border-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-700';
    }

    public function assetTypeEnum(): ?InventoryAssetType
    {
        return InventoryAssetType::tryFrom($this->asset_type);
    }

    public function getAssetTypeLabelAttribute(): string
    {
        return $this->assetTypeEnum()?->label() ?? ucfirst($this->asset_type);
    }

    public function getAssetTypeIconAttribute(): string
    {
        return $this->assetTypeEnum()?->icon() ?? 'package';
    }

    public function getAssetTypeBadgeClassAttribute(): string
    {
        return $this->assetTypeEnum()?->badgeClass()
            ?? 'bg-gray-50 text-gray-700 dark:bg-gray-900 dark:text-gray-300 border border-gray-200 dark:border-gray-800';
    }

    public function getBillingCycleLabelAttribute(): ?string
    {
        return match ($this->billing_cycle) {
            'monthly' => 'Bulanan',
            'yearly' => 'Tahunan',
            default => $this->billing_cycle,
        };
    }

    public function getMaintenanceCycleLabelAttribute(): ?string
    {
        return match ($this->maintenance_cycle) {
            'monthly' => 'Bulanan',
            'quarterly' => 'Triwulan (3 Bulan)',
            'semester' => 'Semester (6 Bulan)',
            'yearly' => 'Tahunan',
            default => $this->maintenance_cycle,
        };
    }

    public function isRenewalOverdue(): bool
    {
        return $this->renewal_date !== null && $this->renewal_date->startOfDay()->isPast();
    }

    public function isRenewalComingSoon(): bool
    {
        if ($this->renewal_date === null || $this->isRenewalOverdue()) {
            return false;
        }

        $days = (int) now()->startOfDay()->diffInDays($this->renewal_date->startOfDay(), false);

        return $days <= 30;
    }

    public function renewalDaysRemaining(): ?int
    {
        if ($this->renewal_date === null) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->renewal_date->startOfDay(), false);
    }

    public function isMaintenanceOverdue(): bool
    {
        return $this->next_maintenance_date !== null && $this->next_maintenance_date->startOfDay()->isPast();
    }

    public function isMaintenanceComingSoon(): bool
    {
        if ($this->next_maintenance_date === null || $this->isMaintenanceOverdue()) {
            return false;
        }

        $days = (int) now()->startOfDay()->diffInDays($this->next_maintenance_date->startOfDay(), false);

        return $days <= 14;
    }

    public function maintenanceDaysRemaining(): ?int
    {
        if ($this->next_maintenance_date === null) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->next_maintenance_date->startOfDay(), false);
    }

    public function getDisplayLocationAttribute(): string
    {
        $parts = array_filter([
            $this->building?->name,
            $this->floor?->name,
            $this->campusLocation?->name,
            $this->location_detail,
        ]);

        if (empty($parts)) {
            return $this->asset_type === 'digital' ? 'Cloud / Digital' : '-';
        }

        return implode(' — ', $parts);
    }

    /**
     * Record a snapshot history for this inventory item.
     *
     * @param  array{location?: ?string, status?: ?string, condition?: ?string}  $oldState
     */
    public function recordHistory(
        string $changeType,
        array $oldState = [],
        ?string $notes = null,
        ?int $userId = null
    ): InventoryHistory {
        $currentLocation = $this->display_location;
        $currentStatus = $this->status;
        $currentCondition = $this->condition;

        return $this->histories()->create([
            'user_id' => $userId,
            'change_type' => $changeType,
            'old_location' => $oldState['location'] ?? null,
            'new_location' => $currentLocation,
            'old_status' => $oldState['status'] ?? null,
            'new_status' => $currentStatus,
            'old_condition' => $oldState['condition'] ?? null,
            'new_condition' => $currentCondition,
            'notes' => $notes,
        ]);
    }

    public static function generateAssetCode(): string
    {
        $prefix = 'INV-PUTI-'.now()->format('Y').'-';
        $latest = self::where('asset_code', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();

        $number = 1;
        if ($latest && preg_match('/-(\d+)$/', $latest->asset_code, $matches)) {
            $number = ((int) $matches[1]) + 1;
        }

        return $prefix.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }
}

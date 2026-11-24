<?php

namespace App\Observers;

use App\Events\InventoryItemUpdated;
use App\Models\InventoryItem;

class InventoryItemObserver
{
    public function created(InventoryItem $item): void
    {
        event(InventoryItemUpdated::fromModel($item, 'created'));
    }

    public function updated(InventoryItem $item): void
    {
        if (! $item->wasChanged([
            'name',
            'category',
            'condition',
            'status',
            'location_detail',
            'campus_location_id',
            'building_id',
            'floor_id',
            'next_maintenance_date',
            'renewal_date',
        ])) {
            return;
        }

        event(InventoryItemUpdated::fromModel($item, 'updated'));
    }

    public function deleted(InventoryItem $item): void
    {
        event(InventoryItemUpdated::fromModel($item, 'deleted'));
    }
}

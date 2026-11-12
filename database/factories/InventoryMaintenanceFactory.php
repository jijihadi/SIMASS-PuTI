<?php

namespace Database\Factories;

use App\Enums\MaintenanceStatus;
use App\Models\InventoryItem;
use App\Models\InventoryMaintenance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryMaintenance>
 */
class InventoryMaintenanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'maintenance_code' => 'MNT-'.now()->format('Ym').'-'.fake()->unique()->numerify('####'),
            'inventory_item_id' => InventoryItem::factory(),
            'type' => 'preventive',
            'status' => MaintenanceStatus::InProgress->value,
            'performed_by' => User::factory(),
            'title' => 'Pengecekan Rutin Perangkat',
            'action_notes' => 'Pembersihan debu dan update firmware.',
            'started_at' => now(),
        ];
    }
}

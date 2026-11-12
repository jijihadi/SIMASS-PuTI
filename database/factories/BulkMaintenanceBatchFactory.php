<?php

namespace Database\Factories;

use App\Enums\MaintenanceStatus;
use App\Models\BulkMaintenanceBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BulkMaintenanceBatch>
 */
class BulkMaintenanceBatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'batch_code' => 'BM-'.now()->format('Ym').'-'.fake()->unique()->numerify('####'),
            'title' => 'Bulk Maintenance Lab Komputer Pra-Ujian',
            'maintenance_type' => 'preventive',
            'status' => MaintenanceStatus::InProgress->value,
            'scheduled_at' => now(),
            'performed_by' => User::factory(),
        ];
    }
}

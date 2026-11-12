<?php

namespace Database\Factories;

use App\Enums\InventoryCondition;
use App\Enums\InventoryStatus;
use App\Models\InventoryItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryItem>
 */
class InventoryItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'asset_code' => 'INV-PUTI-'.now()->format('Y').'-'.fake()->unique()->numerify('####'),
            'name' => fake()->randomElement([
                'PC Lab Core i7 Rakitan',
                'Proyektor Epson EB-X500',
                'Access Point Ruijie RG-AP820',
                'Switch Managed Cisco 24-Port',
            ]).' #'.fake()->numerify('##'),
            'category' => fake()->randomElement(InventoryItem::CATEGORIES),
            'asset_type' => 'physical',
            'brand_model' => 'Standard Kampus PuTI',
            'serial_number' => strtoupper(fake()->bothify('SN-????-#####')),
            'location_detail' => 'Gedung A Lt. 2 R.201',
            'status' => InventoryStatus::Active->value,
            'condition' => InventoryCondition::Good->value,
        ];
    }

    public function maintenance(): static
    {
        return $this->state(fn () => [
            'condition' => InventoryCondition::Maintenance->value,
            'status' => InventoryStatus::Maintenance->value,
        ]);
    }

    public function damaged(): static
    {
        return $this->state(fn () => [
            'condition' => InventoryCondition::Damaged->value,
        ]);
    }

    public function digital(): static
    {
        return $this->state(fn () => [
            'asset_type' => 'digital',
            'category' => 'Digital & Lisensi Software',
            'billing_cycle' => 'yearly',
            'renewal_date' => now()->addYear(),
            'access_url' => 'https://admin.portal.example.com',
            'access_recipients' => 'Seluruh Dosen & Staf PuTI',
        ]);
    }
}

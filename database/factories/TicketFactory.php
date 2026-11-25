<?php

namespace Database\Factories;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_number' => 'TICK-'.now()->format('Ym').'-'.fake()->unique()->numerify('####'),
            'user_id' => User::factory(),
            'category' => 'Jaringan & Internet',
            'service_item' => 'Wi-Fi Kampus (Eduroam / TelU-Guest)',
            'authority' => 'local',
            'scope' => 'internal_surabaya',
            'status' => TicketStatus::Open->value,
            'priority' => 'medium',
            'location' => 'Gedung A Lt. 2 R.201',
            'title' => fake()->sentence(5),
            'description' => fake()->paragraph(2),
            'sla_due_at' => now()->addHours(24),
        ];
    }

    public function open(): static
    {
        return $this->state(fn () => [
            'status' => TicketStatus::Open->value,
            'scope' => 'internal_surabaya',
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn () => [
            'status' => TicketStatus::InProgress->value,
            'scope' => 'internal_surabaya',
        ]);
    }

    public function finished(): static
    {
        return $this->state(fn () => [
            'status' => TicketStatus::Finished->value,
            'resolved_at' => now(),
        ]);
    }

    public function canceled(): static
    {
        return $this->state(fn () => [
            'status' => TicketStatus::Canceled->value,
            'cancel_reason' => 'user_withdrawn',
            'resolved_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => TicketStatus::Failed->value,
            'status_note' => 'Perangkat membutuhkan penggantian suku cadang.',
            'resolved_at' => now(),
        ]);
    }

    public function redirected(): static
    {
        return $this->state(fn () => [
            'status' => TicketStatus::Redirected->value,
            'authority' => 'central',
            'scope' => 'escalated_central',
            'sla_paused_at' => now(),
        ]);
    }
}

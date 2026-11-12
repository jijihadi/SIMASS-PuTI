<?php

namespace App\Services;

use App\Enums\InventoryCondition;
use App\Enums\MaintenanceStatus;
use App\Enums\TicketStatus;
use App\Models\BulkMaintenanceBatch;
use App\Models\InventoryItem;
use App\Models\InventoryMaintenance;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;

class InventorySyncService
{
    public function __construct(
        public TicketAssignmentService $assignmentService
    ) {}

    /**
     * Synchronize InventoryItem condition and InventoryMaintenance log when a Ticket changes.
     */
    public function syncFromTicket(Ticket $ticket, ?User $actor = null, ?string $note = null): void
    {
        if (! $ticket->inventory_item_id) {
            return;
        }

        $item = InventoryItem::find($ticket->inventory_item_id);
        if (! $item) {
            return;
        }

        $statusEnum = $ticket->statusEnum() ?? TicketStatus::Open;
        $now = now();
        $performerId = $actor?->id ?? $ticket->assigned_to ?? $ticket->user_id;

        $oldState = [
            'location' => $item->display_location,
            'status' => $item->status,
            'condition' => $item->condition,
        ];

        $maintenance = InventoryMaintenance::where('ticket_id', $ticket->id)->latest('id')->first();

        if (in_array($statusEnum, [TicketStatus::InProgress, TicketStatus::Redirected], true)) {
            $item->update([
                'condition' => InventoryCondition::Maintenance->value,
            ]);
            $item->recordHistory(
                changeType: 'ticket_sync',
                oldState: $oldState,
                notes: "Status tiket diubah menjadi {$statusEnum->label()}: ".($note ?? $ticket->status_note ?? 'Perbaikan sedang berlangsung.'),
                userId: $performerId
            );

            if (! $maintenance) {
                InventoryMaintenance::create([
                    'maintenance_code' => InventoryMaintenance::generateMaintenanceCode(),
                    'inventory_item_id' => $item->id,
                    'ticket_id' => $ticket->id,
                    'type' => 'corrective',
                    'status' => MaintenanceStatus::InProgress->value,
                    'performed_by' => $performerId,
                    'title' => "Perbaikan Tiket #{$ticket->ticket_number}: {$ticket->title}",
                    'action_notes' => $note ?? $ticket->status_note,
                    'started_at' => $now,
                ]);
            } else {
                $maintenance->update([
                    'inventory_item_id' => $item->id,
                    'status' => MaintenanceStatus::InProgress->value,
                    'performed_by' => $performerId,
                    'action_notes' => $note ?? $ticket->status_note ?? $maintenance->action_notes,
                    'completed_at' => null,
                ]);
            }
        } elseif ($statusEnum === TicketStatus::Finished) {
            $item->update([
                'condition' => InventoryCondition::Good->value,
                'last_maintained_at' => $now,
            ]);
            $item->recordHistory(
                changeType: 'ticket_sync',
                oldState: $oldState,
                notes: 'Tiket diselesaikan. Kondisi aset pulih ke Normal: '.($note ?? $ticket->status_note ?? 'Kendala berhasil diperbaiki.'),
                userId: $performerId
            );

            if (! $maintenance) {
                InventoryMaintenance::create([
                    'maintenance_code' => InventoryMaintenance::generateMaintenanceCode(),
                    'inventory_item_id' => $item->id,
                    'ticket_id' => $ticket->id,
                    'type' => 'corrective',
                    'status' => MaintenanceStatus::Completed->value,
                    'result_condition' => InventoryCondition::Good->value,
                    'performed_by' => $performerId,
                    'title' => "Perbaikan Tiket #{$ticket->ticket_number}: {$ticket->title}",
                    'action_notes' => $note ?? $ticket->status_note ?? 'Kendala pada aset berhasil diperbaiki.',
                    'started_at' => $ticket->created_at ?? $now,
                    'completed_at' => $now,
                ]);
            } else {
                $maintenance->update([
                    'inventory_item_id' => $item->id,
                    'status' => MaintenanceStatus::Completed->value,
                    'result_condition' => InventoryCondition::Good->value,
                    'performed_by' => $performerId,
                    'action_notes' => $note ?? $ticket->status_note ?? $maintenance->action_notes,
                    'completed_at' => $now,
                ]);
            }
        } elseif ($statusEnum === TicketStatus::Failed) {
            $item->update([
                'condition' => InventoryCondition::Damaged->value,
            ]);
            $item->recordHistory(
                changeType: 'ticket_sync',
                oldState: $oldState,
                notes: 'Perbaikan tiket gagal. Aset ditandai Rusak/Bermasalah: '.($note ?? $ticket->status_note ?? 'Perbaikan belum berhasil.'),
                userId: $performerId
            );

            if (! $maintenance) {
                InventoryMaintenance::create([
                    'maintenance_code' => InventoryMaintenance::generateMaintenanceCode(),
                    'inventory_item_id' => $item->id,
                    'ticket_id' => $ticket->id,
                    'type' => 'corrective',
                    'status' => MaintenanceStatus::Failed->value,
                    'result_condition' => InventoryCondition::Damaged->value,
                    'performed_by' => $performerId,
                    'title' => "Perbaikan Tiket #{$ticket->ticket_number}: {$ticket->title}",
                    'action_notes' => $note ?? $ticket->status_note,
                    'started_at' => $ticket->created_at ?? $now,
                    'completed_at' => $now,
                ]);
            } else {
                $maintenance->update([
                    'inventory_item_id' => $item->id,
                    'status' => MaintenanceStatus::Failed->value,
                    'result_condition' => InventoryCondition::Damaged->value,
                    'performed_by' => $performerId,
                    'action_notes' => $note ?? $ticket->status_note ?? $maintenance->action_notes,
                    'completed_at' => $now,
                ]);
            }
        } elseif ($statusEnum === TicketStatus::Canceled) {
            if ($maintenance) {
                $maintenance->update([
                    'status' => MaintenanceStatus::Canceled->value,
                    'action_notes' => $note ?? $ticket->status_note ?? $maintenance->action_notes,
                    'completed_at' => $now,
                ]);
            }

            $hasOtherActiveMaintenance = InventoryMaintenance::where('inventory_item_id', $item->id)
                ->where('status', MaintenanceStatus::InProgress->value)
                ->exists();

            if (! $hasOtherActiveMaintenance && $item->condition === InventoryCondition::Maintenance->value) {
                $item->update([
                    'condition' => InventoryCondition::Good->value,
                ]);
                $item->recordHistory(
                    changeType: 'ticket_sync',
                    oldState: $oldState,
                    notes: 'Tiket dibatalkan. Kondisi aset dikembalikan ke Normal.',
                    userId: $performerId
                );
            }
        }
    }

    /**
     * Synchronize InventoryItem and optionally Ticket when an InventoryMaintenance record is saved.
     */
    public function syncFromMaintenance(
        InventoryMaintenance $maintenance,
        bool $autoResolveTicket = false,
        bool $spawnTicketOnDamage = false,
        ?User $actor = null
    ): ?Ticket {
        $item = $maintenance->inventoryItem;
        if (! $item) {
            return null;
        }

        $now = now();
        $statusEnum = $maintenance->statusEnum() ?? MaintenanceStatus::InProgress;
        $actorId = $actor?->id ?? $maintenance->performed_by;

        $oldState = [
            'location' => $item->display_location,
            'status' => $item->status,
            'condition' => $item->condition,
        ];

        if ($statusEnum === MaintenanceStatus::InProgress) {
            $item->update([
                'condition' => InventoryCondition::Maintenance->value,
            ]);
            $item->recordHistory(
                changeType: 'maintenance',
                oldState: $oldState,
                notes: "Aktivitas maintenance #{$maintenance->maintenance_code} ({$maintenance->title}) dimulai.",
                userId: $actorId
            );

            return null;
        }

        if (in_array($statusEnum, [MaintenanceStatus::Completed, MaintenanceStatus::Failed], true)) {
            $resultCondition = $maintenance->result_condition
                ?: ($statusEnum === MaintenanceStatus::Failed ? InventoryCondition::Damaged->value : InventoryCondition::Good->value);

            $itemUpdates = ['condition' => $resultCondition];
            if ($resultCondition === InventoryCondition::Good->value) {
                $itemUpdates['last_maintained_at'] = $now;
            }
            $item->update($itemUpdates);

            $item->recordHistory(
                changeType: 'maintenance',
                oldState: $oldState,
                notes: "Maintenance #{$maintenance->maintenance_code} selesai dengan kondisi: {$maintenance->result_condition_label}. Catatan: ".($maintenance->action_notes ?? '-'),
                userId: $actorId
            );

            // Auto-resolve linked ticket if maintenance succeeded
            if ($autoResolveTicket && $maintenance->ticket_id && $resultCondition === InventoryCondition::Good->value) {
                $ticket = Ticket::find($maintenance->ticket_id);
                if ($ticket && ! $ticket->isFinalStatus()) {
                    $ticket->update([
                        'status' => TicketStatus::Finished->value,
                        'status_note' => $maintenance->action_notes ?? 'Diselesaikan melalui modul Maintenance Inventaris.',
                        'resolved_at' => $now,
                        'sla_paused_at' => null,
                    ]);

                    TicketMessage::create([
                        'ticket_id' => $ticket->id,
                        'user_id' => $actor?->id ?? $maintenance->performed_by,
                        'sender_type' => 'system',
                        'message' => "🔧 **Sinkronisasi Inventaris:** Aset **{$item->asset_code} ({$item->name})** telah selesai diperbaiki pada Maintenance **#{$maintenance->maintenance_code}**. Status tiket otomatis ditandai **Selesai**.",
                    ]);
                }
            }

            // Spawn corrective ticket if asset is found damaged and no ticket is linked yet
            if ($spawnTicketOnDamage && ! $maintenance->ticket_id && $resultCondition === InventoryCondition::Damaged->value) {
                $reporterId = $actor?->id ?? $maintenance->performed_by ?? User::query()->value('id');
                $assigneeId = $this->assignmentService->assignTechnician()?->id ?? $reporterId;

                $ticket = Ticket::create([
                    'ticket_number' => Ticket::generateTicketNumber(),
                    'user_id' => $reporterId,
                    'inventory_item_id' => $item->id,
                    'assigned_to' => $assigneeId,
                    'category' => 'Fasilitas & Lab IT',
                    'service_item' => 'Komputer Laboratorium / PC Kerja',
                    'authority' => 'local',
                    'scope' => 'internal_surabaya',
                    'status' => TicketStatus::Open->value,
                    'priority' => 'high',
                    'location' => $item->display_location,
                    'title' => "🚨 Tindak Lanjut Kerusakan Aset {$item->asset_code} ({$item->name})",
                    'description' => $maintenance->action_notes ?: "Ditemukan kerusakan pada aset {$item->name} ({$item->asset_code}) saat kegiatan maintenance #{$maintenance->maintenance_code}.",
                    'source_channel' => $maintenance->bulk_batch_id ? 'bulk_maintenance' : 'web',
                    'sla_due_at' => $now->copy()->addHours(12),
                ]);

                TicketMessage::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $reporterId,
                    'sender_type' => 'system',
                    'message' => "🛠️ **Tiket Otomatis dari Inventaris:** Dibuat dari hasil pengecekan maintenance **#{$maintenance->maintenance_code}** pada aset **{$item->asset_code} ({$item->name})**.",
                ]);

                $maintenance->update(['ticket_id' => $ticket->id]);

                return $ticket;
            }
        }

        return null;
    }

    /**
     * Complete a BulkMaintenanceBatch and process per-item outcomes.
     *
     * @param  array<int|string, array{result_condition?: string, action_notes?: string|null}>  $itemOutcomes
     * @return list<Ticket>
     */
    public function completeBulkBatch(
        BulkMaintenanceBatch $batch,
        array $itemOutcomes,
        bool $spawnTicketOnDamage = true,
        ?User $actor = null
    ): array {
        $now = now();
        $spawnedTickets = [];

        foreach ($batch->maintenances()->with('inventoryItem')->get() as $maintenance) {
            $outcome = $itemOutcomes[$maintenance->id] ?? $itemOutcomes[$maintenance->inventory_item_id] ?? [];
            $resultCondition = $outcome['result_condition'] ?? InventoryCondition::Good->value;
            $actionNotes = $outcome['action_notes'] ?? $maintenance->action_notes ?? $batch->notes;

            $status = $resultCondition === InventoryCondition::Damaged->value
                ? MaintenanceStatus::Failed->value
                : MaintenanceStatus::Completed->value;

            $maintenance->update([
                'status' => $status,
                'result_condition' => $resultCondition,
                'action_notes' => $actionNotes,
                'performed_by' => $actor?->id ?? $batch->performed_by,
                'completed_at' => $now,
            ]);

            $spawned = $this->syncFromMaintenance(
                $maintenance,
                autoResolveTicket: true,
                spawnTicketOnDamage: $spawnTicketOnDamage,
                actor: $actor
            );

            if ($spawned) {
                $spawnedTickets[] = $spawned;
            }
        }

        $batch->update([
            'status' => MaintenanceStatus::Completed->value,
            'completed_at' => $now,
            'performed_by' => $actor?->id ?? $batch->performed_by,
        ]);

        return $spawnedTickets;
    }
}

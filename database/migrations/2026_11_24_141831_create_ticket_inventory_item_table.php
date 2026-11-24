<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('ticket_inventory_item')) {
            Schema::create('ticket_inventory_item', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
                $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['ticket_id', 'inventory_item_id']);
                $table->index('inventory_item_id');
            });
        }

        DB::table('tickets')
            ->whereNotNull('inventory_item_id')
            ->select(['id', 'inventory_item_id', 'created_at', 'updated_at'])
            ->orderBy('id')
            ->chunk(200, function ($tickets): void {
                $rows = [];
                $now = now();

                foreach ($tickets as $ticket) {
                    $rows[] = [
                        'ticket_id' => $ticket->id,
                        'inventory_item_id' => $ticket->inventory_item_id,
                        'created_at' => $ticket->created_at ?? $now,
                        'updated_at' => $ticket->updated_at ?? $now,
                    ];
                }

                if ($rows !== []) {
                    DB::table('ticket_inventory_item')->insertOrIgnore($rows);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_inventory_item');
    }
};

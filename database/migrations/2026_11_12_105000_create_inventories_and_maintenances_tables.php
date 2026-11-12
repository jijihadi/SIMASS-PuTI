<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('asset_code', 50)->unique()->index();
            $table->string('name');
            $table->string('category', 100)->index();
            $table->string('brand_model', 150)->nullable();
            $table->string('serial_number', 100)->nullable();
            $table->foreignId('campus_location_id')->nullable()->constrained('campus_locations')->nullOnDelete();
            $table->string('location_detail')->nullable();
            $table->string('condition', 30)->default('good')->index(); // good, maintenance, damaged, retired
            $table->dateTime('last_maintained_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('bulk_maintenance_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_code', 50)->unique()->index();
            $table->string('title');
            $table->string('maintenance_type', 30)->default('preventive'); // preventive, inspection, upgrade
            $table->foreignId('campus_location_id')->nullable()->constrained('campus_locations')->nullOnDelete();
            $table->string('category_filter', 100)->nullable();
            $table->string('status', 30)->default('in_progress')->index(); // scheduled, in_progress, completed, canceled
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_maintenances', function (Blueprint $table) {
            $table->id();
            $table->string('maintenance_code', 50)->unique()->index();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained('tickets')->nullOnDelete();
            $table->foreignId('bulk_batch_id')->nullable()->constrained('bulk_maintenance_batches')->cascadeOnDelete();
            $table->string('type', 30)->default('corrective'); // corrective, preventive, inspection
            $table->string('status', 30)->default('in_progress')->index(); // scheduled, in_progress, completed, failed, canceled
            $table->string('result_condition', 30)->nullable(); // good, damaged, retired
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('action_notes')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('inventory_item_id')
                ->nullable()
                ->after('user_id')
                ->constrained('inventory_items')
                ->nullOnDelete();
            $table->string('source_channel', 30)->default('web')->after('status_note');
            $table->string('reporter_phone', 30)->nullable()->after('source_channel');
            $table->string('reporter_name', 150)->nullable()->after('reporter_phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inventory_item_id');
            $table->dropColumn(['source_channel', 'reporter_phone', 'reporter_name']);
        });

        Schema::dropIfExists('inventory_maintenances');
        Schema::dropIfExists('bulk_maintenance_batches');
        Schema::dropIfExists('inventory_items');
    }
};

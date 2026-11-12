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
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->string('asset_type', 20)->default('physical')->index()->after('category');
            $table->string('status', 30)->default('active')->index()->after('condition');
            $table->foreignId('building_id')->nullable()->after('campus_location_id')->constrained('campus_locations')->nullOnDelete();
            $table->foreignId('floor_id')->nullable()->after('building_id')->constrained('campus_locations')->nullOnDelete();
            $table->date('renewal_date')->nullable()->index()->after('last_maintained_at');
            $table->string('billing_cycle', 20)->nullable()->after('renewal_date');
            $table->string('access_url', 500)->nullable()->after('billing_cycle');
            $table->text('access_recipients')->nullable()->after('access_url');
        });

        Schema::create('inventory_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('change_type', 50)->index();
            $table->string('old_location')->nullable();
            $table->string('new_location')->nullable();
            $table->string('old_status', 30)->nullable();
            $table->string('new_status', 30)->nullable();
            $table->string('old_condition', 30)->nullable();
            $table->string('new_condition', 30)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_histories');

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropForeign(['building_id']);
            $table->dropForeign(['floor_id']);
            $table->dropColumn([
                'asset_type',
                'status',
                'building_id',
                'floor_id',
                'renewal_date',
                'billing_cycle',
                'access_url',
                'access_recipients',
            ]);
        });
    }
};

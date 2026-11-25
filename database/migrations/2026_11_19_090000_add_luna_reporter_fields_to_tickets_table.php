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
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('reporter_status', 20)->nullable()->after('reporter_name');
            $table->string('unit_kerja', 150)->nullable()->after('reporter_status');
            $table->string('request_type', 50)->nullable()->after('unit_kerja');
            $table->string('idempotency_key', 100)->nullable()->index()->after('request_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['idempotency_key']);
            $table->dropColumn(['reporter_status', 'unit_kerja', 'request_type', 'idempotency_key']);
        });
    }
};

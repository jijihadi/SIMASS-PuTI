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
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('cancel_reason', 50)->nullable()->after('override_notes');
            $table->string('transferred_unit', 150)->nullable()->after('cancel_reason');
            $table->text('status_note')->nullable()->after('transferred_unit');
        });

        DB::table('tickets')
            ->where('status', 'pending_user')
            ->update([
                'status' => 'open',
                'scope' => 'needs_clarification',
            ]);

        DB::table('tickets')
            ->where('status', 'waiting_central')
            ->update([
                'status' => 'redirected',
                'scope' => 'escalated_central',
            ]);

        DB::table('tickets')
            ->whereIn('status', ['resolved', 'closed'])
            ->update([
                'status' => 'finished',
                'resolved_at' => DB::raw('COALESCE(resolved_at, updated_at)'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('tickets')
            ->where('status', 'open')
            ->where('scope', 'needs_clarification')
            ->update(['status' => 'pending_user']);

        DB::table('tickets')
            ->where('status', 'redirected')
            ->update(['status' => 'waiting_central']);

        DB::table('tickets')
            ->where('status', 'finished')
            ->update(['status' => 'resolved']);

        DB::table('tickets')
            ->whereIn('status', ['canceled', 'failed'])
            ->update(['status' => 'closed']);

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['cancel_reason', 'transferred_unit', 'status_note']);
        });
    }
};

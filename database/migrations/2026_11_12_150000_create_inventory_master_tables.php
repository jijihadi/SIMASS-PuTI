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
        Schema::create('inventory_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('code', 50)->nullable()->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('inventory_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('inventory_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->string('color', 30)->default('gray');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('inventory_conditions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->string('color', 30)->default('gray');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed initial default records into database
        $now = now();

        DB::table('inventory_categories')->insert([
            ['name' => 'Komputer & PC Lab', 'code' => 'PC', 'description' => 'Workstation, PC desktop, dan komputer laboratorium.', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Perangkat Jaringan', 'code' => 'NET', 'description' => 'Switch, router, access point, dan firewall.', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Proyektor & AV', 'code' => 'AV', 'description' => 'Proyektor kelas, layar, speaker, dan audio visual.', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Server & Infrastruktur', 'code' => 'SRV', 'description' => 'Server rack, UPS, NAS, dan infrastruktur data center.', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Digital & Lisensi Software', 'code' => 'LIC', 'description' => 'Lisensi OS, cloud storage, SaaS, dan langganan software.', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Periferal & Lainnya', 'code' => 'OTH', 'description' => 'Keyboard, mouse, printer, scanner, dan aksesoris IT.', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('inventory_types')->insert([
            ['code' => 'physical', 'name' => 'Fisik (Hardware / Lab)', 'description' => 'Perangkat keras yang memiliki wujud fisik dan lokasi di kampus.', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'digital', 'name' => 'Digital (Lisensi / Cloud)', 'description' => 'Aset digital berupa lisensi software, akun aplikasi, atau langganan cloud.', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('inventory_statuses')->insert([
            ['code' => 'active', 'name' => 'Aktif / Tersedia', 'color' => 'emerald', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'in_use', 'name' => 'Digunakan / Terpasang', 'color' => 'blue', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'spare', 'name' => 'Cadangan / Gudang', 'color' => 'purple', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'maintenance', 'name' => 'Dalam Perawatan', 'color' => 'amber', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'retired', 'name' => 'Nonaktif / Afkir', 'color' => 'gray', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('inventory_conditions')->insert([
            ['code' => 'good', 'name' => 'Baik / Normal', 'color' => 'emerald', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'maintenance', 'name' => 'Dalam Perbaikan', 'color' => 'amber', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'damaged', 'name' => 'Rusak / Bermasalah', 'color' => 'rose', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'retired', 'name' => 'Dihapus / Afkir', 'color' => 'gray', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_conditions');
        Schema::dropIfExists('inventory_statuses');
        Schema::dropIfExists('inventory_types');
        Schema::dropIfExists('inventory_categories');
    }
};

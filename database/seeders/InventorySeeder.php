<?php

namespace Database\Seeders;

use App\Models\CampusLocation;
use App\Models\InventoryCategory;
use App\Models\InventoryConditionLookup;
use App\Models\InventoryHistory;
use App\Models\InventoryItem;
use App\Models\InventoryStatusLookup;
use App\Models\InventoryType;
use App\Models\User;
use Illuminate\Database\Seeder;

class InventorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Master Categories
        $categories = [
            'Komputer & PC Lab',
            'Perangkat Jaringan',
            'Proyektor & AV',
            'Server & Infrastruktur',
            'Digital & Lisensi Software',
            'Periferal & Lainnya',
        ];

        foreach ($categories as $cat) {
            InventoryCategory::firstOrCreate(
                ['name' => $cat],
                ['is_active' => true]
            );
        }

        // 2. Master Asset Types
        $types = [
            [
                'code' => 'physical',
                'name' => 'Fisik',
                'description' => 'Aset berwujud / perangkat keras laboratorium dan kantor',
                'is_active' => true,
            ],
            [
                'code' => 'digital',
                'name' => 'Digital / Lisensi',
                'description' => 'Lisensi software, SaaS, domain, SSL & langganan cloud',
                'is_active' => true,
            ],
        ];

        foreach ($types as $type) {
            InventoryType::updateOrCreate(
                ['code' => $type['code']],
                $type
            );
        }

        // 3. Master Statuses
        $statuses = [
            ['code' => 'active', 'name' => 'Aktif', 'color' => 'emerald', 'is_active' => true],
            ['code' => 'in_use', 'name' => 'Sedang Digunakan', 'color' => 'blue', 'is_active' => true],
            ['code' => 'spare', 'name' => 'Cadangan / Gudang', 'color' => 'slate', 'is_active' => true],
            ['code' => 'maintenance', 'name' => 'Dalam Perawatan', 'color' => 'amber', 'is_active' => true],
            ['code' => 'retired', 'name' => 'Afkir / Non-Aktif', 'color' => 'red', 'is_active' => true],
        ];

        foreach ($statuses as $st) {
            InventoryStatusLookup::updateOrCreate(
                ['code' => $st['code']],
                $st
            );
        }

        // 4. Master Conditions
        $conditions = [
            ['code' => 'good', 'name' => 'Baik', 'color' => 'emerald', 'is_active' => true],
            ['code' => 'maintenance', 'name' => 'Perlu Perbaikan', 'color' => 'amber', 'is_active' => true],
            ['code' => 'damaged', 'name' => 'Rusak Ringan / Berat', 'color' => 'rose', 'is_active' => true],
            ['code' => 'retired', 'name' => 'Afkir', 'color' => 'slate', 'is_active' => true],
        ];

        foreach ($conditions as $cd) {
            InventoryConditionLookup::updateOrCreate(
                ['code' => $cd['code']],
                $cd
            );
        }

        // 5. Campus Locations (Buildings & Floors)
        $gkb = CampusLocation::firstOrCreate(
            ['name' => 'Gedung Kuliah Bersama (GKB)', 'type' => 'building'],
            ['code' => 'GKB']
        );

        $gkbL1 = CampusLocation::firstOrCreate(
            ['name' => 'Lantai 1', 'type' => 'floor', 'parent_id' => $gkb->id],
            ['code' => 'GKB-L1']
        );

        $gkbL2 = CampusLocation::firstOrCreate(
            ['name' => 'Lantai 2', 'type' => 'floor', 'parent_id' => $gkb->id],
            ['code' => 'GKB-L2']
        );

        $rektorat = CampusLocation::firstOrCreate(
            ['name' => 'Gedung Rektorat', 'type' => 'building'],
            ['code' => 'REK']
        );

        $rektoratL1 = CampusLocation::firstOrCreate(
            ['name' => 'Lantai 1', 'type' => 'floor', 'parent_id' => $rektorat->id],
            ['code' => 'REK-L1']
        );

        $dc = CampusLocation::firstOrCreate(
            ['name' => 'Pusat TI (PuTI) & Data Center', 'type' => 'building'],
            ['code' => 'PUTI-DC']
        );

        $dcL1 = CampusLocation::firstOrCreate(
            ['name' => 'Server Room & Lab Jaringan', 'type' => 'floor', 'parent_id' => $dc->id],
            ['code' => 'DC-L1']
        );

        // 6. Default User for initial audit trails
        $adminUser = User::first();
        $adminUserId = $adminUser?->id;

        // 7. Seed Sample Inventory Items (Physical & Digital)
        $items = [
            [
                'asset_code' => 'INV-PUTI-2026-0001',
                'name' => 'PC Lab Multimedia Workstation #01',
                'category' => 'Komputer & PC Lab',
                'asset_type' => 'physical',
                'brand_model' => 'Dell OptiPlex 7090 MT (Core i7 / 32GB / RTX 3060)',
                'serial_number' => 'DL-OPT-7090-001',
                'building_id' => $gkb->id,
                'floor_id' => $gkbL2->id,
                'location_detail' => 'Lab Komputer 204 Multimedia',
                'condition' => 'good',
                'status' => 'active',
                'notes' => 'Workstation untuk praktikum desain grafis dan komputasi visual.',
            ],
            [
                'asset_code' => 'INV-PUTI-2026-0002',
                'name' => 'Core Switch Cisco Catalyst 3850',
                'category' => 'Perangkat Jaringan',
                'asset_type' => 'physical',
                'brand_model' => 'Cisco Catalyst WS-C3850-48P-S',
                'serial_number' => 'CSCO-3850-8910',
                'building_id' => $dc->id,
                'floor_id' => $dcL1->id,
                'location_detail' => 'Rack A-01 Server PuTI',
                'condition' => 'good',
                'status' => 'in_use',
                'notes' => 'Switch distribusi utama backbone kampus.',
            ],
            [
                'asset_code' => 'INV-PUTI-2026-0003',
                'name' => 'Proyektor Ruang Sidang Utama',
                'category' => 'Proyektor & AV',
                'asset_type' => 'physical',
                'brand_model' => 'Epson EB-2250U 5000 Lumens WUXGA',
                'serial_number' => 'EPS-2250-9921',
                'building_id' => $rektorat->id,
                'floor_id' => $rektoratL1->id,
                'location_detail' => 'Ruang Sidang Senat Lt. 1',
                'condition' => 'good',
                'status' => 'active',
                'notes' => 'Digunakan berkala saat rapat rektorat dan senat.',
            ],
            [
                'asset_code' => 'INV-PUTI-2026-0004',
                'name' => 'Lisensi Zoom Enterprise (500-Host Large Meeting)',
                'category' => 'Digital & Lisensi Software',
                'asset_type' => 'digital',
                'brand_model' => 'Zoom Video Communications Enterprise Campus Plan',
                'serial_number' => 'ZOOM-ENT-PUTI-2026',
                'building_id' => null,
                'floor_id' => null,
                'location_detail' => 'Cloud / Zoom Admin Dashboard',
                'condition' => 'good',
                'status' => 'active',
                'renewal_date' => now()->addYear()->format('Y-m-d'),
                'billing_cycle' => 'yearly',
                'access_url' => 'https://zoom.us/signin',
                'access_recipients' => 'Hak akses super-admin dipegang Kepala PuTI. Akun host didistribusikan untuk Dekanat Fakultas, Biro Akademik, dan Ruang Sidang.',
                'notes' => 'Langganan tahunan untuk perkuliahan hybrid, wisuda, dan sidang terbuka.',
            ],
            [
                'asset_code' => 'INV-PUTI-2026-0005',
                'name' => 'Lisensi JetBrains All Products Educational Pack',
                'category' => 'Digital & Lisensi Software',
                'asset_type' => 'digital',
                'brand_model' => 'JetBrains Toolbox Commercial Classroom License',
                'serial_number' => 'JB-EDU-PUTI-772',
                'building_id' => null,
                'floor_id' => null,
                'location_detail' => 'JetBrains License Server / Cloud Portal',
                'condition' => 'good',
                'status' => 'active',
                'renewal_date' => now()->addMonths(6)->format('Y-m-d'),
                'billing_cycle' => 'yearly',
                'access_url' => 'https://account.jetbrains.com/organization',
                'access_recipients' => 'Diberikan ke Laboran Komputer, Dosen Pemrograman, dan Asisten Lab Informatika.',
                'notes' => 'Digunakan untuk software IDE PhpStorm, IntelliJ IDEA, PyCharm di seluruh PC Lab.',
            ],
        ];

        foreach ($items as $itemData) {
            $item = InventoryItem::firstOrCreate(
                ['asset_code' => $itemData['asset_code']],
                $itemData
            );

            // Record initial history record if empty
            if ($item->histories()->count() === 0) {
                InventoryHistory::create([
                    'inventory_item_id' => $item->id,
                    'user_id' => $adminUserId,
                    'change_type' => 'initial_seed',
                    'old_location' => null,
                    'new_location' => $item->display_location,
                    'old_status' => null,
                    'new_status' => $item->status,
                    'old_condition' => null,
                    'new_condition' => $item->condition,
                    'notes' => 'Inisialisasi aset awal dari InventorySeeder sistem.',
                ]);
            }
        }
    }
}

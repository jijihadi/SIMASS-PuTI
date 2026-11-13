@extends('layouts.app-layout')

@section('title', request()->routeIs('inventory.*') ? 'Kategori & Data Master — Inventaris PuTI' : 'Data Master — Admin Console')

@section('content')
    <div class="space-y-6" x-data="{
        tab: '{{ request()->query('tab', request()->routeIs('inventory.*') ? 'inventory-categories' : 'units') }}',
        unitModal: false,
        editUnitModal: false,
        editUnitData: { id: null, name: '', code: '' },
        locationModal: false,
        editLocationModal: false,
        editLocationData: { id: null, type: 'building', name: '', parent_id: '', code: '' },
        serviceModal: false,
        editServiceModal: false,
        editServiceData: { id: null, category: '', name: '', sla_resolution_hours: 24, is_active: true },
        categoryModal: false,
        editCategoryModal: false,
        editCategoryData: { id: null, name: '', code: '', description: '', is_active: true },
        typeModal: false,
        editTypeModal: false,
        editTypeData: { id: null, name: '', code: '', description: '', is_active: true },
        statusModal: false,
        editStatusModal: false,
        editStatusData: { id: null, name: '', code: '', color: 'gray', is_active: true },
        conditionModal: false,
        editConditionModal: false,
        editConditionData: { id: null, name: '', code: '', color: 'gray', is_active: true },
    }" id="masterDataTabs">

        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">{{ request()->routeIs('inventory.*') ? 'Kategori & Data Master Aset' : 'Data Master & SLA' }}</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ request()->routeIs('inventory.*') ? 'Kelola kategori inventaris, tipe, status, kondisi, serta data lokasi kampus untuk modul Inventaris PuTI.' : 'Kelola data unit kerja, lokasi kampus, target SLA layanan PuTI, serta kategori, tipe, status, dan kondisi inventaris.' }}</p>
            </div>
            <x-button variant="secondary" size="sm" icon="arrow-left" href="{{ request()->routeIs('inventory.*') ? route('inventory.index') : route('admin.dashboard') }}">
                {{ request()->routeIs('inventory.*') ? 'Kembali ke Inventaris' : 'Kembali ke Dashboard' }}
            </x-button>
        </div>

        {{-- Tabs --}}
        <div class="flex items-center gap-2 border-b border-gray-200 dark:border-gray-800 text-xs font-bold overflow-x-auto no-scrollbar">
            <button type="button" @click="tab = 'units'; switchTab('units')" id="btn-tab-units"
                :class="tab === 'units' ? 'border-telkom-600 text-telkom-700 dark:border-telkom-500 dark:text-telkom-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'"
                class="pb-3 border-b-2 transition shrink-0">
                Unit & Divisi ({{ $units->count() }})
            </button>
            <button type="button" @click="tab = 'locations'; switchTab('locations')" id="btn-tab-locations"
                :class="tab === 'locations' ? 'border-telkom-600 text-telkom-700 dark:border-telkom-500 dark:text-telkom-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'"
                class="pb-3 border-b-2 transition ml-4 shrink-0">
                Lokasi Kampus ({{ $locations->count() }})
            </button>
            <button type="button" @click="tab = 'services'; switchTab('services')" id="btn-tab-services"
                :class="tab === 'services' ? 'border-telkom-600 text-telkom-700 dark:border-telkom-500 dark:text-telkom-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'"
                class="pb-3 border-b-2 transition ml-4 shrink-0">
                Katalog Layanan & SLA ({{ $services->count() }})
            </button>
            <button type="button" @click="tab = 'inventory-categories'; switchTab('inventory-categories')" id="btn-tab-inventory-categories"
                :class="tab === 'inventory-categories' ? 'border-telkom-600 text-telkom-700 dark:border-telkom-500 dark:text-telkom-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'"
                class="pb-3 border-b-2 transition ml-4 shrink-0">
                Kategori Inventaris ({{ $inventoryCategories->count() }})
            </button>
            <button type="button" @click="tab = 'inventory-types'; switchTab('inventory-types')" id="btn-tab-inventory-types"
                :class="tab === 'inventory-types' ? 'border-telkom-600 text-telkom-700 dark:border-telkom-500 dark:text-telkom-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'"
                class="pb-3 border-b-2 transition ml-4 shrink-0">
                Tipe Aset ({{ $inventoryTypes->count() }})
            </button>
            <button type="button" @click="tab = 'inventory-statuses'; switchTab('inventory-statuses')" id="btn-tab-inventory-statuses"
                :class="tab === 'inventory-statuses' ? 'border-telkom-600 text-telkom-700 dark:border-telkom-500 dark:text-telkom-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'"
                class="pb-3 border-b-2 transition ml-4 shrink-0">
                Status Aset ({{ $inventoryStatuses->count() }})
            </button>
            <button type="button" @click="tab = 'inventory-conditions'; switchTab('inventory-conditions')" id="btn-tab-inventory-conditions"
                :class="tab === 'inventory-conditions' ? 'border-telkom-600 text-telkom-700 dark:border-telkom-500 dark:text-telkom-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'"
                class="pb-3 border-b-2 transition ml-4 shrink-0">
                Kondisi Aset ({{ $inventoryConditions->count() }})
            </button>
        </div>

        {{-- TAB 1: UNITS --}}
        <div x-show="tab === 'units'" id="pane-tab-units" class="space-y-4">
            <div class="flex justify-between items-center">
                <h3 class="font-bold text-sm text-gray-900 dark:text-white">Daftar Unit & Divisi Kerja</h3>
                <x-button variant="primary" icon="plus" @click="unitModal = true">Tambah Unit</x-button>
            </div>

            <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm overflow-hidden">
                <table class="w-full text-left text-xs text-gray-600 dark:text-gray-400">
                    <thead class="bg-gray-50 dark:bg-gray-800/50 text-gray-700 dark:text-gray-300 font-bold uppercase border-b border-gray-200 dark:border-gray-800">
                        <tr>
                            <th class="px-5 py-3.5">Nama Unit</th>
                            <th class="px-5 py-3.5">Kode</th>
                            <th class="px-5 py-3.5">Jumlah Akun</th>
                            <th class="px-5 py-3.5 text-center">AKSI</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($units as $u)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-850 transition-colors">
                                <td class="px-5 py-3.5 font-bold text-gray-900 dark:text-white">{{ $u->name }}</td>
                                <td class="px-5 py-3.5"><code>{{ $u->code ?? '-' }}</code></td>
                                <td class="px-5 py-3.5">{{ $u->users_count }} pengguna</td>
                                <td class="px-5 py-3.5 text-center whitespace-nowrap">
                                    <x-button type="button" variant="table-blue" icon="edit-3"
                                        @click="editUnitData = { id: {{ $u->id }}, name: '{{ addslashes($u->name) }}', code: '{{ addslashes($u->code ?? '') }}' }; editUnitModal = true"
                                        class="mr-2">
                                        Edit
                                    </x-button>
                                    <x-button type="button" variant="table-danger" icon="trash-2"
                                        onclick="confirmDelete('{{ route('admin.units.destroy', $u) }}', 'unit {{ addslashes($u->name) }}')">
                                        Hapus
                                    </x-button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-8 text-center text-gray-400">Belum ada unit kerja terdaftar.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- TAB 2: LOCATIONS --}}
        <div x-cloak x-show="tab === 'locations'" id="pane-tab-locations" class="space-y-4">
            <div class="flex justify-between items-center">
                <h3 class="font-bold text-sm text-gray-900 dark:text-white">Hierarki Lokasi Kampus</h3>
                <x-button variant="primary" icon="plus" @click="locationModal = true">Tambah Lokasi</x-button>
            </div>

            <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm overflow-hidden">
                <table class="w-full text-left text-xs text-gray-600 dark:text-gray-400">
                    <thead class="bg-gray-50 dark:bg-gray-800/50 text-gray-700 dark:text-gray-300 font-bold uppercase border-b border-gray-200 dark:border-gray-800">
                        <tr>
                            <th class="px-5 py-3.5">Tipe</th>
                            <th class="px-5 py-3.5">Nama Lokasi</th>
                            <th class="px-5 py-3.5">Induk</th>
                            <th class="px-5 py-3.5">Kode</th>
                            <th class="px-5 py-3.5 text-center">AKSI</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($locations as $l)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-850 transition-colors">
                                <td class="px-5 py-3.5">
                                    <span class="px-2 py-0.5 rounded text-[11px] font-semibold uppercase {{ $l->type === 'building' ? 'bg-blue-50 text-blue-700' : ($l->type === 'floor' ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700') }}">
                                        {{ $l->type }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 font-bold text-gray-900 dark:text-white">{{ $l->name }}</td>
                                <td class="px-5 py-3.5">{{ $l->parent?->name ?? '-' }}</td>
                                <td class="px-5 py-3.5"><code>{{ $l->code ?? '-' }}</code></td>
                                <td class="px-5 py-3.5 text-center whitespace-nowrap">
                                    <x-button type="button" variant="table-blue" icon="edit-3"
                                        @click="editLocationData = { id: {{ $l->id }}, type: '{{ $l->type }}', name: '{{ addslashes($l->name) }}', parent_id: '{{ $l->parent_id ?? '' }}', code: '{{ addslashes($l->code ?? '') }}' }; editLocationModal = true"
                                        class="mr-2">
                                        Edit
                                    </x-button>
                                    <x-button type="button" variant="table-danger" icon="trash-2"
                                        onclick="confirmDelete('{{ route('admin.locations.destroy', $l) }}', 'lokasi {{ addslashes($l->name) }}')">
                                        Hapus
                                    </x-button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-8 text-center text-gray-400">Belum ada lokasi kampus terdaftar.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- TAB 3: SERVICES & SLA --}}
        <div x-cloak x-show="tab === 'services'" id="pane-tab-services" class="space-y-4">
            <div class="flex justify-between items-center">
                <h3 class="font-bold text-sm text-gray-900 dark:text-white">Katalog Layanan & Target Resolusi SLA</h3>
                <x-button variant="primary" icon="plus" @click="serviceModal = true">Tambah Layanan</x-button>
            </div>

            <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm overflow-hidden">
                <table class="w-full text-left text-xs text-gray-600 dark:text-gray-400">
                    <thead class="bg-gray-50 dark:bg-gray-800/50 text-gray-700 dark:text-gray-300 font-bold uppercase border-b border-gray-200 dark:border-gray-800">
                        <tr>
                            <th class="px-5 py-3.5">Kategori</th>
                            <th class="px-5 py-3.5">Layanan</th>
                            <th class="px-5 py-3.5">Target SLA</th>
                            <th class="px-5 py-3.5">Status</th>
                            <th class="px-5 py-3.5 text-center">AKSI</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($services as $s)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-850 transition-colors">
                                <td class="px-5 py-3.5 font-semibold text-gray-700 dark:text-gray-300">{{ $s->category }}</td>
                                <td class="px-5 py-3.5 font-bold text-gray-900 dark:text-white">{{ $s->name }}</td>
                                <td class="px-5 py-3.5"><span class="px-2 py-0.5 rounded bg-amber-50 text-amber-700 font-semibold">{{ $s->sla_resolution_hours }} Jam</span></td>
                                <td class="px-5 py-3.5">
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $s->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500' }}">
                                        {{ $s->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-center whitespace-nowrap">
                                    <x-button type="button" variant="table-blue" icon="edit-3"
                                        @click="editServiceData = { id: {{ $s->id }}, category: '{{ addslashes($s->category) }}', name: '{{ addslashes($s->name) }}', sla_resolution_hours: {{ $s->sla_resolution_hours }}, is_active: {{ $s->is_active ? 'true' : 'false' }} }; editServiceModal = true"
                                        class="mr-2">
                                        Edit
                                    </x-button>
                                    <x-button type="button" variant="table-danger" icon="trash-2"
                                        onclick="confirmDelete('{{ route('admin.services.destroy', $s) }}', 'layanan {{ addslashes($s->name) }}')">
                                        Hapus
                                    </x-button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-8 text-center text-gray-400">Belum ada katalog layanan terdaftar.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- TAB 4: INVENTORY CATEGORIES --}}
        <div x-show="tab === 'inventory-categories'" id="pane-tab-inventory-categories" class="space-y-4">
            <div class="flex justify-between items-center">
                <h3 class="font-bold text-sm text-gray-900 dark:text-white">Daftar Kategori Inventaris</h3>
                <x-button variant="primary" icon="plus" @click="categoryModal = true">Tambah Kategori</x-button>
            </div>

            <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm overflow-hidden">
                <table class="w-full text-left text-xs text-gray-600 dark:text-gray-400">
                    <thead class="bg-gray-50 dark:bg-gray-800/50 text-gray-700 dark:text-gray-300 font-bold uppercase border-b border-gray-200 dark:border-gray-800">
                        <tr>
                            <th class="px-5 py-3.5">Nama Kategori</th>
                            <th class="px-5 py-3.5">Kode</th>
                            <th class="px-5 py-3.5">Deskripsi</th>
                            <th class="px-5 py-3.5">Status</th>
                            <th class="px-5 py-3.5">Jumlah Aset</th>
                            <th class="px-5 py-3.5 text-center">AKSI</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($inventoryCategories as $cat)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-850 transition-colors">
                                <td class="px-5 py-3.5 font-bold text-gray-900 dark:text-white">{{ $cat->name }}</td>
                                <td class="px-5 py-3.5 font-mono">{{ $cat->code ?? '-' }}</td>
                                <td class="px-5 py-3.5">{{ $cat->description ?? '-' }}</td>
                                <td class="px-5 py-3.5">
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $cat->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500' }}">
                                        {{ $cat->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 font-bold text-purple-600">{{ $cat->items_count }}</td>
                                <td class="px-5 py-3.5 text-center whitespace-nowrap">
                                    <x-button type="button" variant="table-blue" icon="edit-3"
                                        @click="editCategoryData = { id: {{ $cat->id }}, name: '{{ addslashes($cat->name) }}', code: '{{ addslashes($cat->code ?? '') }}', description: '{{ addslashes($cat->description ?? '') }}', is_active: {{ $cat->is_active ? 'true' : 'false' }} }; editCategoryModal = true"
                                        class="mr-2">
                                        Edit
                                    </x-button>
                                    <form action="{{ route('admin.inventory-categories.destroy', $cat) }}" method="POST" class="inline"
                                        onsubmit="return confirm('Apakah Anda yakin ingin menghapus kategori ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <x-button type="submit" variant="table-danger" icon="trash-2">Hapus</x-button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-8 text-center text-gray-500">Belum ada kategori inventaris.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- TAB 5: INVENTORY TYPES --}}
        <div x-show="tab === 'inventory-types'" id="pane-tab-inventory-types" class="space-y-4">
            <div class="flex justify-between items-center">
                <h3 class="font-bold text-sm text-gray-900 dark:text-white">Daftar Tipe Aset Inventaris</h3>
                <x-button variant="primary" icon="plus" @click="typeModal = true">Tambah Tipe</x-button>
            </div>

            <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm overflow-hidden">
                <table class="w-full text-left text-xs text-gray-600 dark:text-gray-400">
                    <thead class="bg-gray-50 dark:bg-gray-800/50 text-gray-700 dark:text-gray-300 font-bold uppercase border-b border-gray-200 dark:border-gray-800">
                        <tr>
                            <th class="px-5 py-3.5">Nama Tipe</th>
                            <th class="px-5 py-3.5">Kode (Slug)</th>
                            <th class="px-5 py-3.5">Deskripsi</th>
                            <th class="px-5 py-3.5">Status</th>
                            <th class="px-5 py-3.5">Jumlah Aset</th>
                            <th class="px-5 py-3.5 text-center">AKSI</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($inventoryTypes as $type)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-850 transition-colors">
                                <td class="px-5 py-3.5 font-bold text-gray-900 dark:text-white">{{ $type->name }}</td>
                                <td class="px-5 py-3.5 font-mono"><code>{{ $type->code }}</code></td>
                                <td class="px-5 py-3.5">{{ $type->description ?? '-' }}</td>
                                <td class="px-5 py-3.5">
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $type->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500' }}">
                                        {{ $type->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 font-bold text-purple-600">{{ $type->items_count }}</td>
                                <td class="px-5 py-3.5 text-center whitespace-nowrap">
                                    <x-button type="button" variant="table-blue" icon="edit-3"
                                        @click="editTypeData = { id: {{ $type->id }}, name: '{{ addslashes($type->name) }}', code: '{{ addslashes($type->code) }}', description: '{{ addslashes($type->description ?? '') }}', is_active: {{ $type->is_active ? 'true' : 'false' }} }; editTypeModal = true"
                                        class="mr-2">
                                        Edit
                                    </x-button>
                                    <form action="{{ route('admin.inventory-types.destroy', $type) }}" method="POST" class="inline"
                                        onsubmit="return confirm('Apakah Anda yakin ingin menghapus tipe inventaris ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <x-button type="submit" variant="table-danger" icon="trash-2">Hapus</x-button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-8 text-center text-gray-500">Belum ada tipe inventaris.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- TAB 6: INVENTORY STATUSES --}}
        <div x-show="tab === 'inventory-statuses'" id="pane-tab-inventory-statuses" class="space-y-4">
            <div class="flex justify-between items-center">
                <h3 class="font-bold text-sm text-gray-900 dark:text-white">Daftar Status Aset Inventaris</h3>
                <x-button variant="primary" icon="plus" @click="statusModal = true">Tambah Status</x-button>
            </div>

            <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm overflow-hidden">
                <table class="w-full text-left text-xs text-gray-600 dark:text-gray-400">
                    <thead class="bg-gray-50 dark:bg-gray-800/50 text-gray-700 dark:text-gray-300 font-bold uppercase border-b border-gray-200 dark:border-gray-800">
                        <tr>
                            <th class="px-5 py-3.5">Nama Status</th>
                            <th class="px-5 py-3.5">Kode (Slug)</th>
                            <th class="px-5 py-3.5">Warna Aksen</th>
                            <th class="px-5 py-3.5">Status Aktif</th>
                            <th class="px-5 py-3.5">Jumlah Aset</th>
                            <th class="px-5 py-3.5 text-center">AKSI</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($inventoryStatuses as $st)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-850 transition-colors">
                                <td class="px-5 py-3.5 font-bold text-gray-900 dark:text-white">{{ $st->name }}</td>
                                <td class="px-5 py-3.5 font-mono"><code>{{ $st->code }}</code></td>
                                <td class="px-5 py-3.5">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full font-semibold border text-[11px] bg-{{ $st->color }}-50 text-{{ $st->color }}-700 border-{{ $st->color }}-200">
                                        <span class="w-2 h-2 rounded-full bg-{{ $st->color }}-500"></span>
                                        {{ $st->color }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5">
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $st->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500' }}">
                                        {{ $st->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 font-bold text-purple-600">{{ $st->items_count }}</td>
                                <td class="px-5 py-3.5 text-center whitespace-nowrap">
                                    <x-button type="button" variant="table-blue" icon="edit-3"
                                        @click="editStatusData = { id: {{ $st->id }}, name: '{{ addslashes($st->name) }}', code: '{{ addslashes($st->code) }}', color: '{{ addslashes($st->color) }}', is_active: {{ $st->is_active ? 'true' : 'false' }} }; editStatusModal = true"
                                        class="mr-2">
                                        Edit
                                    </x-button>
                                    <form action="{{ route('admin.inventory-statuses.destroy', $st) }}" method="POST" class="inline"
                                        onsubmit="return confirm('Apakah Anda yakin ingin menghapus status ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <x-button type="submit" variant="table-danger" icon="trash-2">Hapus</x-button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-8 text-center text-gray-500">Belum ada status inventaris.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- TAB 7: INVENTORY CONDITIONS --}}
        <div x-show="tab === 'inventory-conditions'" id="pane-tab-inventory-conditions" class="space-y-4">
            <div class="flex justify-between items-center">
                <h3 class="font-bold text-sm text-gray-900 dark:text-white">Daftar Kondisi Fisik Inventaris</h3>
                <x-button variant="primary" icon="plus" @click="conditionModal = true">Tambah Kondisi</x-button>
            </div>

            <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm overflow-hidden">
                <table class="w-full text-left text-xs text-gray-600 dark:text-gray-400">
                    <thead class="bg-gray-50 dark:bg-gray-800/50 text-gray-700 dark:text-gray-300 font-bold uppercase border-b border-gray-200 dark:border-gray-800">
                        <tr>
                            <th class="px-5 py-3.5">Nama Kondisi</th>
                            <th class="px-5 py-3.5">Kode (Slug)</th>
                            <th class="px-5 py-3.5">Warna Aksen</th>
                            <th class="px-5 py-3.5">Status Aktif</th>
                            <th class="px-5 py-3.5">Jumlah Aset</th>
                            <th class="px-5 py-3.5 text-center">AKSI</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($inventoryConditions as $cd)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-850 transition-colors">
                                <td class="px-5 py-3.5 font-bold text-gray-900 dark:text-white">{{ $cd->name }}</td>
                                <td class="px-5 py-3.5 font-mono"><code>{{ $cd->code }}</code></td>
                                <td class="px-5 py-3.5">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full font-semibold border text-[11px] bg-{{ $cd->color }}-50 text-{{ $cd->color }}-700 border-{{ $cd->color }}-200">
                                        <span class="w-2 h-2 rounded-full bg-{{ $cd->color }}-500"></span>
                                        {{ $cd->color }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5">
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $cd->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500' }}">
                                        {{ $cd->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 font-bold text-purple-600">{{ $cd->items_count }}</td>
                                <td class="px-5 py-3.5 text-center whitespace-nowrap">
                                    <x-button type="button" variant="table-blue" icon="edit-3"
                                        @click="editConditionData = { id: {{ $cd->id }}, name: '{{ addslashes($cd->name) }}', code: '{{ addslashes($cd->code) }}', color: '{{ addslashes($cd->color) }}', is_active: {{ $cd->is_active ? 'true' : 'false' }} }; editConditionModal = true"
                                        class="mr-2">
                                        Edit
                                    </x-button>
                                    <form action="{{ route('admin.inventory-conditions.destroy', $cd) }}" method="POST" class="inline"
                                        onsubmit="return confirm('Apakah Anda yakin ingin menghapus kondisi ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <x-button type="submit" variant="table-danger" icon="trash-2">Hapus</x-button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-8 text-center text-gray-500">Belum ada kondisi inventaris.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ================= MODALS TAMBAH & EDIT ================= --}}

        {{-- 1. Modal Tambah Unit --}}
        <div x-show="unitModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" x-cloak>
            <div @click.away="unitModal = false" class="bg-white dark:bg-gray-900 rounded-2xl max-w-md w-full p-6 shadow-xl border border-gray-200 dark:border-gray-800 space-y-4">
                <h3 class="font-bold text-base text-gray-900 dark:text-white">Tambah Unit Kerja Baru</h3>
                <form action="{{ route('admin.units.store') }}" method="POST" class="space-y-3 text-xs">
                    @csrf
                    <div>
                        <label class="block font-bold mb-1 text-gray-700 dark:text-gray-300">Nama Unit *</label>
                        <input type="text" name="name" required placeholder="Contoh: PuTI Surabaya"
                            class="w-full text-xs rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 p-2.5 text-gray-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block font-bold mb-1 text-gray-700 dark:text-gray-300">Kode Unit</label>
                        <input type="text" name="code" placeholder="Contoh: PUTI"
                            class="w-full text-xs rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 p-2.5 text-gray-900 dark:text-white">
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <x-button type="button" variant="secondary" size="sm" @click="unitModal = false">Batal</x-button>
                        <x-button type="submit" variant="primary" size="sm">Simpan Unit</x-button>
                    </div>
                </form>
            </div>
        </div>

        {{-- 1b. Modal Edit Unit --}}
        <div x-show="editUnitModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" x-cloak>
            <div @click.away="editUnitModal = false" class="bg-white dark:bg-gray-900 rounded-2xl max-w-md w-full p-6 shadow-xl border border-gray-200 dark:border-gray-800 space-y-4">
                <h3 class="font-bold text-base text-gray-900 dark:text-white">Edit Unit Kerja</h3>
                <form :action="'{{ url('admin/units') }}/' + editUnitData.id" method="POST" class="space-y-3 text-xs">
                    @csrf
                    @method('PUT')
                    <div>
                        <label class="block font-bold mb-1 text-gray-700 dark:text-gray-300">Nama Unit *</label>
                        <input type="text" name="name" x-model="editUnitData.name" required
                            class="w-full text-xs rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 p-2.5 text-gray-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block font-bold mb-1 text-gray-700 dark:text-gray-300">Kode Unit</label>
                        <input type="text" name="code" x-model="editUnitData.code"
                            class="w-full text-xs rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 p-2.5 text-gray-900 dark:text-white">
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <x-button type="button" variant="secondary" size="sm" @click="editUnitModal = false">Batal</x-button>
                        <x-button type="submit" variant="primary" size="sm">Simpan Perubahan</x-button>
                    </div>
                </form>
            </div>
        </div>

        {{-- 2. Modal Tambah Lokasi --}}
        <div x-show="locationModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" x-cloak>
            <div @click.away="locationModal = false" class="bg-white dark:bg-gray-900 rounded-2xl max-w-md w-full p-6 shadow-xl border border-gray-200 dark:border-gray-800 space-y-4">
                <h3 class="font-bold text-base text-gray-900 dark:text-white">Tambah Lokasi Kampus</h3>
                <form action="{{ route('admin.locations.store') }}" method="POST" class="space-y-3 text-xs">
                    @csrf
                    <div>
                        <label class="block font-bold mb-1 text-gray-700 dark:text-gray-300">Tipe Lokasi *</label>
                        <select name="type" required class="w-full text-xs rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 p-2.5 text-gray-900 dark:text-white">
                            <option value="building">Gedung (Building)</option>
                            <option value="floor">Lantai (Floor)</option>
                            <option value="room">Ruangan / Lab (Room)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold mb-1 text-gray-700 dark:text-gray-300">Nama Lokasi *</label>
                        <input type="text" name="name" required placeholder="Contoh: Gedung Kuliah Bersama (GKB)"
                            class="w-full text-xs rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 p-2.5 text-gray-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block font-bold mb-1 text-gray-700 dark:text-gray-300">Lokasi Induk (Opsional)</label>
                        <select name="parent_id" class="w-full text-xs rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 p-2.5 text-gray-900 dark:text-white">
                            <option value="">-- Tidak ada induk (Tingkat Gedung) --</option>
                            @foreach ($locations as $pl)
                                <option value="{{ $pl->id }}">{{ $pl->name }} ({{ $pl->type }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold mb-1 text-gray-700 dark:text-gray-300">Kode Lokasi</label>
                        <input type="text" name="code" placeholder="Contoh: GKB"
                            class="w-full text-xs rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 p-2.5 text-gray-900 dark:text-white">
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <x-button type="button" variant="secondary" size="sm" @click="locationModal = false">Batal</x-button>
                        <x-button type="submit" variant="primary" size="sm">Simpan Lokasi</x-button>
                    </div>
                </form>
            </div>
        </div>

        {{-- 2b. Modal Edit Lokasi --}}
        <div x-show="editLocationModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" x-cloak>
            <div @click.away="editLocationModal = false" class="bg-white dark:bg-gray-900 rounded-2xl max-w-md w-full p-6 shadow-xl border border-gray-200 dark:border-gray-800 space-y-4">
                <h3 class="font-bold text-base text-gray-900 dark:text-white">Edit Lokasi Kampus</h3>
                <form :action="'{{ url('admin/locations') }}/' + editLocationData.id" method="POST" class="space-y-3 text-xs">
                    @csrf
                    @method('PUT')
                    <div>
                        <label class="block font-bold mb-1 text-gray-700 dark:text-gray-300">Tipe Lokasi *</label>
                        <select name="type" x-model="editLocationData.type" required class="w-full text-xs rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 p-2.5 text-gray-900 dark:text-white">
                            <option value="building">Gedung (Building)</option>
                            <option value="floor">Lantai (Floor)</option>
                            <option value="room">Ruangan / Lab (Room)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold mb-1 text-gray-700 dark:text-gray-300">Nama Lokasi *</label>
                        <input type="text" name="name" x-model="editLocationData.name" required
                            class="w-full text-xs rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 p-2.5 text-gray-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block font-bold mb-1 text-gray-700 dark:text-gray-300">Lokasi Induk</label>
                        <select name="parent_id" x-model="editLocationData.parent_id" class="w-full text-xs rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 p-2.5 text-gray-900 dark:text-white">
                            <option value="">-- Tidak ada induk (Tingkat Gedung) --</option>
                            @foreach ($locations as $pl)
                                <option value="{{ $pl->id }}">{{ $pl->name }} ({{ $pl->type }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold mb-1 text-gray-700 dark:text-gray-300">Kode Lokasi</label>
                        <input type="text" name="code" x-model="editLocationData.code"
                            class="w-full text-xs rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 p-2.5 text-gray-900 dark:text-white">
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <x-button type="button" variant="secondary" size="sm" @click="editLocationModal = false">Batal</x-button>
                        <x-button type="submit" variant="primary" size="sm">Simpan Perubahan</x-button>
                    </div>
                </form>
            </div>
        </div>

        {{-- 3. Modal Tambah Layanan --}}
        <div x-show="serviceModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" x-cloak>
            <div @click.away="serviceModal = false" class="bg-white dark:bg-gray-900 rounded-2xl max-w-md w-full p-6 shadow-xl border border-gray-200 dark:border-gray-800 space-y-4">
                <h3 class="font-bold text-base text-gray-900 dark:text-white">Tambah Katalog Layanan PuTI</h3>
                <form action="{{ route('admin.services.store') }}" method="POST" class="space-y-3 text-xs">
                    @csrf
                    <div>
                        <label class="block font-bold mb-1 text-gray-700 dark:text-gray-300">Kategori Layanan *</label>
                        <input type="text" name="category" required placeholder="Contoh: Jaringan & Akses Internet"
                            class="w-full text-xs rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 p-2.5 text-gray-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block font-bold mb-1 text-gray-700 dark:text-gray-300">Nama Layanan *</label>
                        <input type="text" name="name" required placeholder="Contoh: Perbaikan Port LAN Ruangan"
                            class="w-full text-xs rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 p-2.5 text-gray-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block font-bold mb-1 text-gray-700 dark:text-gray-300">Target Resolusi SLA (Jam) *</label>
                        <input type="number" name="sla_resolution_hours" required value="24" min="1"
                            class="w-full text-xs rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 p-2.5 text-gray-900 dark:text-white">
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <x-button type="button" variant="secondary" size="sm" @click="serviceModal = false">Batal</x-button>
                        <x-button type="submit" variant="primary" size="sm">Simpan Layanan</x-button>
                    </div>
                </form>
            </div>
        </div>

        {{-- 3b. Modal Edit Layanan --}}
        <div x-show="editServiceModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" x-cloak>
            <div @click.away="editServiceModal = false" class="bg-white dark:bg-gray-900 rounded-2xl max-w-md w-full p-6 shadow-xl border border-gray-200 dark:border-gray-800 space-y-4">
                <h3 class="font-bold text-base text-gray-900 dark:text-white">Edit Layanan & SLA</h3>
                <form :action="'{{ url('admin/services') }}/' + editServiceData.id" method="POST" class="space-y-3 text-xs">
                    @csrf
                    @method('PUT')
                    <div>
                        <label class="block font-bold mb-1 text-gray-700 dark:text-gray-300">Kategori Layanan *</label>
                        <input type="text" name="category" x-model="editServiceData.category" required
                            class="w-full text-xs rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 p-2.5 text-gray-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block font-bold mb-1 text-gray-700 dark:text-gray-300">Nama Layanan *</label>
                        <input type="text" name="name" x-model="editServiceData.name" required
                            class="w-full text-xs rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 p-2.5 text-gray-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block font-bold mb-1 text-gray-700 dark:text-gray-300">Target Resolusi SLA (Jam) *</label>
                        <input type="number" name="sla_resolution_hours" x-model="editServiceData.sla_resolution_hours" required min="1"
                            class="w-full text-xs rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 p-2.5 text-gray-900 dark:text-white">
                    </div>
                    <div class="flex items-center gap-2 pt-1">
                        <input type="checkbox" name="is_active" id="edit_srv_is_active" value="1" :checked="editServiceData.is_active"
                            class="rounded border-gray-300 text-telkom-600 focus:ring-telkom-500">
                        <label for="edit_srv_is_active" class="font-semibold text-gray-700 dark:text-gray-300">Layanan Aktif</label>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <x-button type="button" variant="secondary" size="sm" @click="editServiceModal = false">Batal</x-button>
                        <x-button type="submit" variant="primary" size="sm">Simpan Perubahan</x-button>
                    </div>
                </form>
            </div>
        </div>

        {{-- 4. Modal Tambah Kategori Inventaris --}}
        <div x-show="categoryModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" x-cloak>
            <div @click.away="categoryModal = false" class="bg-white dark:bg-gray-900 rounded-2xl max-w-md w-full p-6 shadow-xl border border-gray-200 dark:border-gray-800 space-y-4">
                <h3 class="font-bold text-base text-gray-900 dark:text-white">Tambah Kategori Inventaris Baru</h3>
                <form action="{{ route('admin.inventory-categories.store') }}" method="POST" class="space-y-3 text-xs">
                    @csrf
                    <div>
                        <label class="block font-bold mb-1 text-gray-700 dark:text-gray-300">Nama Kategori *</label>
                        <input type="text" name="name" required placeholder="Contoh: Drone & Robotik"
                            class="w-full text-xs rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 p-2.5 text-gray-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block font-bold mb-1 text-gray-700 dark:text-gray-300">Kode Kategori</label>
                        <input type="text" name="code" placeholder="Contoh: ROB"
                            class="w-full text-xs rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 p-2.5 text-gray-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block font-bold mb-1 text-gray-700 dark:text-gray-300">Deskripsi</label>
                        <textarea name="description" rows="2" placeholder="Keterangan singkat kategori..."
                            class="w-full text-xs rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 p-2.5 text-gray-900 dark:text-white"></textarea>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <x-button type="button" variant="secondary" size="sm" @click="categoryModal = false">Batal</x-button>
                        <x-button type="submit" variant="primary" size="sm">Simpan Kategori</x-button>
                    </div>
                </form>
            </div>
        </div>

        {{-- 4b. Modal Edit Kategori Inventaris --}}
        <div x-show="editCategoryModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" x-cloak>
            <div @click.away="editCategoryModal = false" class="bg-white dark:bg-gray-900 rounded-2xl max-w-md w-full p-6 shadow-xl border border-gray-200 dark:border-gray-800 space-y-4">
                <h3 class="font-bold text-base text-gray-900 dark:text-white">Edit Kategori Inventaris</h3>
                <form :action="'{{ url('admin/inventory-categories') }}/' + editCategoryData.id" method="POST" class="space-y-3 text-xs">
                    @csrf
                    @method('PUT')
                    <div>
                        <label class="block font-bold mb-1 text-gray-700 dark:text-gray-300">Nama Kategori *</label>
                        <input type="text" name="name" x-model="editCategoryData.name" required
                            class="w-full text-xs rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 p-2.5 text-gray-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block font-bold mb-1 text-gray-700 dark:text-gray-300">Kode Kategori</label>
                        <input type="text" name="code" x-model="editCategoryData.code"
                            class="w-full text-xs rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 p-2.5 text-gray-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block font-bold mb-1 text-gray-700 dark:text-gray-300">Deskripsi</label>
                        <textarea name="description" x-model="editCategoryData.description" rows="2"
                            class="w-full text-xs rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 p-2.5 text-gray-900 dark:text-white"></textarea>
                    </div>
                    <div class="flex items-center gap-2 pt-1">
                        <input type="checkbox" name="is_active" id="edit_cat_is_active" value="1" :checked="editCategoryData.is_active"
                            class="rounded border-gray-300 text-telkom-600 focus:ring-telkom-500">
                        <label for="edit_cat_is_active" class="font-semibold text-gray-700 dark:text-gray-300">Kategori Aktif</label>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <x-button type="button" variant="secondary" size="sm" @click="editCategoryModal = false">Batal</x-button>
                        <x-button type="submit" variant="primary" size="sm">Simpan Perubahan</x-button>
                    </div>
                </form>
            </div>
        </div>

        {{-- 5. Modal Tambah Tipe Inventaris --}}
        <div x-show="typeModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" x-cloak>
            <div @click.away="typeModal = false" class="bg-white dark:bg-gray-900 rounded-2xl max-w-md w-full p-6 shadow-xl border border-gray-200 dark:border-gray-800 space-y-4">
                <h3 class="font-bold text-base text-gray-900 dark:text-white">Tambah Tipe Aset Baru</h3>
                <form action="{{ route('admin.inventory-types.store') }}" method="POST" class="space-y-3 text-xs">
                    @csrf
                    <div>
                        <label class="block font-bold mb-1 text-gray-700 dark:text-gray-300">Nama Tipe *</label>
                        <input type="text" name="name" required placeholder="Contoh: Virtual / VM"
                            class="w-full text-xs rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 p-2.5 text-gray-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block font-bold mb-1 text-gray-700 dark:text-gray-300">Kode (Slug) *</label>
                        <input type="text" name="code" required placeholder="Contoh: virtual"
                            class="w-full text-xs rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 p-2.5 text-gray-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block font-bold mb-1 text-gray-700 dark:text-gray-300">Deskripsi</label>
                        <textarea name="description" rows="2" placeholder="Keterangan singkat..."
                            class="w-full text-xs rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 p-2.5 text-gray-900 dark:text-white"></textarea>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <x-button type="button" variant="secondary" size="sm" @click="typeModal = false">Batal</x-button>
                        <x-button type="submit" variant="primary" size="sm">Simpan Tipe</x-button>
                    </div>
                </form>
            </div>
        </div>

        {{-- 5b. Modal Edit Tipe Inventaris --}}
        <div x-show="editTypeModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" x-cloak>
            <div @click.away="editTypeModal = false" class="bg-white dark:bg-gray-900 rounded-2xl max-w-md w-full p-6 shadow-xl border border-gray-200 dark:border-gray-800 space-y-4">
                <h3 class="font-bold text-base text-gray-900 dark:text-white">Edit Tipe Aset</h3>
                <form :action="'{{ url('admin/inventory-types') }}/' + editTypeData.id" method="POST" class="space-y-3 text-xs">
                    @csrf
                    @method('PUT')
                    <div>
                        <label class="block font-bold mb-1 text-gray-700 dark:text-gray-300">Nama Tipe *</label>
                        <input type="text" name="name" x-model="editTypeData.name" required
                            class="w-full text-xs rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 p-2.5 text-gray-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block font-bold mb-1 text-gray-700 dark:text-gray-300">Kode (Slug) *</label>
                        <input type="text" name="code" x-model="editTypeData.code" required
                            class="w-full text-xs rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 p-2.5 text-gray-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block font-bold mb-1 text-gray-700 dark:text-gray-300">Deskripsi</label>
                        <textarea name="description" x-model="editTypeData.description" rows="2"
                            class="w-full text-xs rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 p-2.5 text-gray-900 dark:text-white"></textarea>
                    </div>
                    <div class="flex items-center gap-2 pt-1">
                        <input type="checkbox" name="is_active" id="edit_type_is_active" value="1" :checked="editTypeData.is_active"
                            class="rounded border-gray-300 text-telkom-600 focus:ring-telkom-500">
                        <label for="edit_type_is_active" class="font-semibold text-gray-700 dark:text-gray-300">Tipe Aktif</label>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <x-button type="button" variant="secondary" size="sm" @click="editTypeModal = false">Batal</x-button>
                        <x-button type="submit" variant="primary" size="sm">Simpan Perubahan</x-button>
                    </div>
                </form>
            </div>
        </div>

        {{-- 6. Modal Tambah Status Inventaris --}}
        <div x-show="statusModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" x-cloak>
            <div @click.away="statusModal = false" class="bg-white dark:bg-gray-900 rounded-2xl max-w-md w-full p-6 shadow-xl border border-gray-200 dark:border-gray-800 space-y-4">
                <h3 class="font-bold text-base text-gray-900 dark:text-white">Tambah Status Inventaris</h3>
                <form action="{{ route('admin.inventory-statuses.store') }}" method="POST" class="space-y-3 text-xs">
                    @csrf
                    <div>
                        <label class="block font-bold mb-1 text-gray-700 dark:text-gray-300">Nama Status *</label>
                        <input type="text" name="name" required placeholder="Contoh: Dipinjamkan"
                            class="w-full text-xs rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 p-2.5 text-gray-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block font-bold mb-1 text-gray-700 dark:text-gray-300">Kode (Slug) *</label>
                        <input type="text" name="code" required placeholder="Contoh: borrowed"
                            class="w-full text-xs rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 p-2.5 text-gray-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block font-bold mb-1 text-gray-700 dark:text-gray-300">Warna Aksen *</label>
                        <select name="color" required class="w-full text-xs rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 p-2.5 text-gray-900 dark:text-white">
                            <option value="emerald">Emerald (Hijau)</option>
                            <option value="blue">Blue (Biru)</option>
                            <option value="purple">Purple (Ungu)</option>
                            <option value="amber">Amber (Kuning/Oranye)</option>
                            <option value="rose">Rose (Merah)</option>
                            <option value="gray">Gray (Abu-abu)</option>
                        </select>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <x-button type="button" variant="secondary" size="sm" @click="statusModal = false">Batal</x-button>
                        <x-button type="submit" variant="primary" size="sm">Simpan Status</x-button>
                    </div>
                </form>
            </div>
        </div>

        {{-- 6b. Modal Edit Status Inventaris --}}
        <div x-show="editStatusModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" x-cloak>
            <div @click.away="editStatusModal = false" class="bg-white dark:bg-gray-900 rounded-2xl max-w-md w-full p-6 shadow-xl border border-gray-200 dark:border-gray-800 space-y-4">
                <h3 class="font-bold text-base text-gray-900 dark:text-white">Edit Status Inventaris</h3>
                <form :action="'{{ url('admin/inventory-statuses') }}/' + editStatusData.id" method="POST" class="space-y-3 text-xs">
                    @csrf
                    @method('PUT')
                    <div>
                        <label class="block font-bold mb-1 text-gray-700 dark:text-gray-300">Nama Status *</label>
                        <input type="text" name="name" x-model="editStatusData.name" required
                            class="w-full text-xs rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 p-2.5 text-gray-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block font-bold mb-1 text-gray-700 dark:text-gray-300">Kode (Slug) *</label>
                        <input type="text" name="code" x-model="editStatusData.code" required
                            class="w-full text-xs rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 p-2.5 text-gray-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block font-bold mb-1 text-gray-700 dark:text-gray-300">Warna Aksen *</label>
                        <select name="color" x-model="editStatusData.color" required class="w-full text-xs rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 p-2.5 text-gray-900 dark:text-white">
                            <option value="emerald">Emerald (Hijau)</option>
                            <option value="blue">Blue (Biru)</option>
                            <option value="purple">Purple (Ungu)</option>
                            <option value="amber">Amber (Kuning/Oranye)</option>
                            <option value="rose">Rose (Merah)</option>
                            <option value="gray">Gray (Abu-abu)</option>
                        </select>
                    </div>
                    <div class="flex items-center gap-2 pt-1">
                        <input type="checkbox" name="is_active" id="edit_st_is_active" value="1" :checked="editStatusData.is_active"
                            class="rounded border-gray-300 text-telkom-600 focus:ring-telkom-500">
                        <label for="edit_st_is_active" class="font-semibold text-gray-700 dark:text-gray-300">Status Aktif</label>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <x-button type="button" variant="secondary" size="sm" @click="editStatusModal = false">Batal</x-button>
                        <x-button type="submit" variant="primary" size="sm">Simpan Perubahan</x-button>
                    </div>
                </form>
            </div>
        </div>

        {{-- 7. Modal Tambah Kondisi Inventaris --}}
        <div x-show="conditionModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" x-cloak>
            <div @click.away="conditionModal = false" class="bg-white dark:bg-gray-900 rounded-2xl max-w-md w-full p-6 shadow-xl border border-gray-200 dark:border-gray-800 space-y-4">
                <h3 class="font-bold text-base text-gray-900 dark:text-white">Tambah Kondisi Fisik</h3>
                <form action="{{ route('admin.inventory-conditions.store') }}" method="POST" class="space-y-3 text-xs">
                    @csrf
                    <div>
                        <label class="block font-bold mb-1 text-gray-700 dark:text-gray-300">Nama Kondisi *</label>
                        <input type="text" name="name" required placeholder="Contoh: Hilang"
                            class="w-full text-xs rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 p-2.5 text-gray-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block font-bold mb-1 text-gray-700 dark:text-gray-300">Kode (Slug) *</label>
                        <input type="text" name="code" required placeholder="Contoh: lost"
                            class="w-full text-xs rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 p-2.5 text-gray-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block font-bold mb-1 text-gray-700 dark:text-gray-300">Warna Aksen *</label>
                        <select name="color" required class="w-full text-xs rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 p-2.5 text-gray-900 dark:text-white">
                            <option value="emerald">Emerald (Hijau)</option>
                            <option value="blue">Blue (Biru)</option>
                            <option value="purple">Purple (Ungu)</option>
                            <option value="amber">Amber (Kuning/Oranye)</option>
                            <option value="rose">Rose (Merah)</option>
                            <option value="gray">Gray (Abu-abu)</option>
                        </select>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <x-button type="button" variant="secondary" size="sm" @click="conditionModal = false">Batal</x-button>
                        <x-button type="submit" variant="primary" size="sm">Simpan Kondisi</x-button>
                    </div>
                </form>
            </div>
        </div>

        {{-- 7b. Modal Edit Kondisi Inventaris --}}
        <div x-show="editConditionModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" x-cloak>
            <div @click.away="editConditionModal = false" class="bg-white dark:bg-gray-900 rounded-2xl max-w-md w-full p-6 shadow-xl border border-gray-200 dark:border-gray-800 space-y-4">
                <h3 class="font-bold text-base text-gray-900 dark:text-white">Edit Kondisi Fisik</h3>
                <form :action="'{{ url('admin/inventory-conditions') }}/' + editConditionData.id" method="POST" class="space-y-3 text-xs">
                    @csrf
                    @method('PUT')
                    <div>
                        <label class="block font-bold mb-1 text-gray-700 dark:text-gray-300">Nama Kondisi *</label>
                        <input type="text" name="name" x-model="editConditionData.name" required
                            class="w-full text-xs rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 p-2.5 text-gray-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block font-bold mb-1 text-gray-700 dark:text-gray-300">Kode (Slug) *</label>
                        <input type="text" name="code" x-model="editConditionData.code" required
                            class="w-full text-xs rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 p-2.5 text-gray-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block font-bold mb-1 text-gray-700 dark:text-gray-300">Warna Aksen *</label>
                        <select name="color" x-model="editConditionData.color" required class="w-full text-xs rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 p-2.5 text-gray-900 dark:text-white">
                            <option value="emerald">Emerald (Hijau)</option>
                            <option value="blue">Blue (Biru)</option>
                            <option value="purple">Purple (Ungu)</option>
                            <option value="amber">Amber (Kuning/Oranye)</option>
                            <option value="rose">Rose (Merah)</option>
                            <option value="gray">Gray (Abu-abu)</option>
                        </select>
                    </div>
                    <div class="flex items-center gap-2 pt-1">
                        <input type="checkbox" name="is_active" id="edit_cd_is_active" value="1" :checked="editConditionData.is_active"
                            class="rounded border-gray-300 text-telkom-600 focus:ring-telkom-500">
                        <label for="edit_cd_is_active" class="font-semibold text-gray-700 dark:text-gray-300">Kondisi Aktif</label>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <x-button type="button" variant="secondary" size="sm" @click="editConditionModal = false">Batal</x-button>
                        <x-button type="submit" variant="primary" size="sm">Simpan Perubahan</x-button>
                    </div>
                </form>
            </div>
        </div>

    </div>

    @push('scripts')
        <script>
            function switchTab(name) {
                const tabs = [
                    'units',
                    'locations',
                    'services',
                    'inventory-categories',
                    'inventory-types',
                    'inventory-statuses',
                    'inventory-conditions'
                ];

                tabs.forEach(t => {
                    const pane = document.getElementById('pane-tab-' + t);
                    const btn = document.getElementById('btn-tab-' + t);
                    if (pane) {
                        if (t === name) {
                            pane.style.removeProperty('display');
                            pane.classList.remove('hidden');
                        } else {
                            pane.style.display = 'none';
                        }
                    }
                    if (btn) {
                        if (t === name) {
                            btn.classList.add('border-telkom-600', 'text-telkom-700', 'dark:border-telkom-500',
                                'dark:text-telkom-400');
                            btn.classList.remove('border-gray-900', 'text-gray-900', 'dark:border-white',
                                'dark:text-white', 'border-transparent', 'text-gray-500');
                        } else {
                            btn.classList.remove('border-telkom-600', 'text-telkom-700', 'dark:border-telkom-500',
                                'dark:text-telkom-400', 'border-gray-900', 'text-gray-900', 'dark:border-white',
                                'dark:text-white');
                            btn.classList.add('border-transparent', 'text-gray-500');
                        }
                    }
                });
            }

            document.addEventListener('DOMContentLoaded', function() {
                const urlParams = new URLSearchParams(window.location.search);
                const initialTab = urlParams.get('tab') || 'units';
                switchTab(initialTab);
            });
        </script>
    @endpush
@endsection

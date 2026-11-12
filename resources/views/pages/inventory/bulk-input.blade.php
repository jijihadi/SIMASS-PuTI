@extends('layouts.app-layout')

@section('title', 'Bulk Input Aset Inventaris')
@section('subtitle', 'Entri banyak aset secara serentak dari master data database dengan fitur copy baris instan')

@section('content')
<div x-data="{
    showDetails: {},
    toggleDetail(idx) {
        this.showDetails[idx] = !this.showDetails[idx];
    },
    bulkRows: [
        { name: '', category: '{{ $categories[0] ?? 'Komputer & PC Lab' }}', asset_type: 'physical', brand_model: '', serial_number: '', building_id: '', floor_id: '', location_detail: '', condition: 'good', status: 'active', notes: '' },
        { name: '', category: '{{ $categories[0] ?? 'Komputer & PC Lab' }}', asset_type: 'physical', brand_model: '', serial_number: '', building_id: '', floor_id: '', location_detail: '', condition: 'good', status: 'active', notes: '' },
        { name: '', category: '{{ $categories[0] ?? 'Komputer & PC Lab' }}', asset_type: 'physical', brand_model: '', serial_number: '', building_id: '', floor_id: '', location_detail: '', condition: 'good', status: 'active', notes: '' }
    ],
    addEmptyRow() {
        this.bulkRows.push({
            name: '',
            category: '{{ $categories[0] ?? 'Komputer & PC Lab' }}',
            asset_type: 'physical',
            brand_model: '',
            serial_number: '',
            building_id: '',
            floor_id: '',
            location_detail: '',
            condition: 'good',
            status: 'active',
            notes: ''
        });
    },
    copyRow(index) {
        const source = this.bulkRows[index];
        let nextName = source.name;
        
        // Smart name number increment if ends with numbers (e.g. PC 01 -> PC 02)
        const match = source.name.match(/^(.*?)(\d+)$/);
        if (match) {
            const prefix = match[1];
            const num = parseInt(match[2], 10) + 1;
            const padLength = match[2].length;
            nextName = prefix + String(num).padStart(padLength, '0');
        }

        const clone = {
            name: nextName,
            category: source.category,
            asset_type: source.asset_type,
            brand_model: source.brand_model,
            serial_number: '', // serial number is unique per hardware
            building_id: source.building_id,
            floor_id: source.floor_id,
            location_detail: source.location_detail,
            condition: source.condition,
            status: source.status,
            notes: source.notes
        };

        this.bulkRows.splice(index + 1, 0, clone);
        if (this.showDetails[index]) {
            this.showDetails[index + 1] = true;
        }
    },
    removeRow(index) {
        if (this.bulkRows.length > 1) {
            this.bulkRows.splice(index, 1);
            delete this.showDetails[index];
        } else {
            alert('Minimal harus ada 1 baris input.');
        }
    },
    clearAll() {
        if (confirm('Kosongkan semua baris dan mulai ulang?')) {
            this.bulkRows = [
                { name: '', category: '{{ $categories[0] ?? 'Komputer & PC Lab' }}', asset_type: 'physical', brand_model: '', serial_number: '', building_id: '', floor_id: '', location_detail: '', condition: 'good', status: 'active', notes: '' }
            ];
            this.showDetails = {};
        }
    }
}" class="space-y-5">

  @if($errors->any())
    <div class="p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900 text-rose-700 dark:text-rose-400 text-sm font-semibold space-y-1">
      <p class="font-bold flex items-center gap-1.5">
        <i data-lucide="alert-octagon" class="w-4 h-4"></i>
        <span>Terdapat data yang belum lengkap pada formulir:</span>
      </p>
      <ul class="list-disc list-inside text-xs pl-2">
        @foreach($errors->all() as $err)
          <li>{{ $err }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <!-- Top Hero Header -->
  <div class="bg-white dark:bg-gray-900 p-5 rounded-3xl border border-gray-200 dark:border-gray-800 shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
    <div>
      <div class="flex items-center gap-2 text-blue-600 dark:text-blue-400 font-bold text-xs uppercase tracking-wider">
        <i data-lucide="table" class="w-4 h-4"></i>
        <span>Formulir Entri Massal Terintegrasi Database</span>
      </div>
      <h2 class="text-xl font-bold tracking-tight text-gray-900 dark:text-white mt-1">Bulk Input Aset Inventaris</h2>
      <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
        Master data kategori, tipe, status, dan kondisi termuat langsung dari database. Gunakan tombol <strong class="text-blue-600">Salin</strong> untuk menduplikasi baris tanpa perlu scroll horizontal.
      </p>
    </div>

    <div class="flex items-center gap-2 shrink-0">
      <a href="{{ route('inventory.index') }}" class="px-4 py-2 rounded-2xl border border-gray-200 dark:border-gray-700 text-xs font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 transition inline-flex items-center gap-2">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>
        <span>Kembali ke Katalog</span>
      </a>
    </div>
  </div>

  <!-- Form Container -->
  <form action="{{ route('inventory.bulk-store') }}" method="POST" class="space-y-4">
    @csrf

    <!-- Action Toolbar above Table -->
    <div class="bg-white dark:bg-gray-900 p-3.5 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm flex flex-wrap items-center justify-between gap-3 text-xs">
      <div class="flex items-center gap-2">
        <button type="button" @click="addEmptyRow()"
          class="px-3.5 py-1.5 rounded-xl bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-800 dark:text-gray-200 font-semibold transition inline-flex items-center gap-1.5 shadow-sm">
          <i data-lucide="plus" class="w-4 h-4 text-emerald-600"></i>
          <span>Tambah Baris Kosong</span>
        </button>

        <button type="button" @click="copyRow(bulkRows.length - 1)"
          class="px-3.5 py-1.5 rounded-xl bg-blue-50 dark:bg-blue-950/40 hover:bg-blue-100 border border-blue-200 dark:border-blue-900 text-blue-700 dark:text-blue-300 font-semibold transition inline-flex items-center gap-1.5 shadow-sm">
          <i data-lucide="copy" class="w-4 h-4"></i>
          <span>Duplikasi Baris Terakhir</span>
        </button>

        <button type="button" @click="clearAll()"
          class="px-3 py-1.5 rounded-xl text-gray-500 hover:text-rose-600 hover:bg-rose-50 transition inline-flex items-center gap-1">
          <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
          <span>Reset Baris</span>
        </button>
      </div>

      <div class="flex items-center gap-3">
        <span class="text-xs text-gray-500 font-medium">
          Total: <strong class="text-blue-600 font-bold" x-text="bulkRows.length"></strong> baris aset
        </span>
        <button type="submit"
          class="px-5 py-2 rounded-xl bg-telkom-600 hover:bg-telkom-700 text-white font-semibold transition shadow-md inline-flex items-center gap-2">
          <i data-lucide="check-circle" class="w-4 h-4"></i>
          <span>Simpan Semua Aset (<span x-text="bulkRows.length"></span>)</span>
        </button>
      </div>
    </div>

    <!-- Multi-Row Table (FIT SCREEN - ZERO HORIZONTAL OVERFLOW) -->
    <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm">
      <table class="w-full text-left text-xs">
        <thead class="bg-gray-50 dark:bg-gray-800/70 text-gray-600 dark:text-gray-400 font-semibold border-b border-gray-200 dark:border-gray-800">
          <tr>
            <th class="py-2.5 px-2 w-8 text-center">#</th>
            <th class="py-2.5 px-2">Nama Aset *</th>
            <th class="py-2.5 px-2 w-48">Kategori *</th>
            <th class="py-2.5 px-2 w-28">Tipe *</th>
            <th class="py-2.5 px-2 w-36">Gedung</th>
            <th class="py-2.5 px-2 w-28">Lantai</th>
            <th class="py-2.5 px-2 w-32">Kondisi</th>
            <th class="py-2.5 px-2 w-32">Status</th>
            <th class="py-2.5 px-2 w-20 text-center">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800 text-gray-700 dark:text-gray-300">
          <template x-for="(row, idx) in bulkRows" :key="idx">
            <tr class="hover:bg-gray-50/70 dark:hover:bg-gray-850/40 transition">
              <td class="py-2 px-2 text-center font-mono font-bold text-gray-400" x-text="idx + 1"></td>

              <!-- Nama Aset + Optional Detail Toggle -->
              <td class="py-2 px-2">
                <input type="text" :name="'items[' + idx + '][name]'" x-model="row.name" required placeholder="Nama aset..."
                  class="w-full px-2 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-xs focus:ring-1 focus:ring-telkom-500">
                <div class="mt-1 flex items-center gap-2 text-[10px]">
                  <button type="button" @click="toggleDetail(idx)"
                    :class="showDetails[idx] ? 'text-blue-600 font-bold' : 'text-gray-400 hover:text-gray-600'"
                    class="inline-flex items-center gap-0.5">
                    <span x-text="showDetails[idx] ? '− Tutup Detail (SN/Merk/Ruangan)' : '+ Detail (SN/Merk/Ruangan)'"></span>
                  </button>
                  <span x-show="row.serial_number" class="text-purple-600 font-mono" x-text="'SN: ' + row.serial_number"></span>
                </div>

                <!-- Collapsible detail fields within row -->
                <div x-show="showDetails[idx]" class="mt-2 p-2 bg-gray-100 dark:bg-gray-800/80 rounded-xl space-y-1.5 border border-gray-200 dark:border-gray-700">
                  <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 text-[11px]">
                    <div>
                      <label class="block text-gray-500 font-medium">Merk / Model</label>
                      <input type="text" :name="'items[' + idx + '][brand_model]'" x-model="row.brand_model" placeholder="Dell / Epson"
                        class="w-full px-2 py-1 rounded border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-900 text-xs">
                    </div>
                    <div>
                      <label class="block text-gray-500 font-medium">Serial Number (SN)</label>
                      <input type="text" :name="'items[' + idx + '][serial_number]'" x-model="row.serial_number" placeholder="SN-xxxx"
                        class="w-full px-2 py-1 rounded border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-900 text-xs font-mono">
                    </div>
                    <div>
                      <label class="block text-gray-500 font-medium">Detail Ruangan / Meja</label>
                      <input type="text" :name="'items[' + idx + '][location_detail]'" x-model="row.location_detail" placeholder="Lab 201 Meja 01"
                        class="w-full px-2 py-1 rounded border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-900 text-xs">
                    </div>
                  </div>
                </div>
              </td>

              <!-- Kategori (From DB master table) -->
              <td class="py-2 px-2">
                <select :name="'items[' + idx + '][category]'" x-model="row.category" required
                  class="w-full px-2 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-xs truncate">
                  @foreach($categories as $cat)
                    <option value="{{ $cat }}">{{ $cat }}</option>
                  @endforeach
                </select>
              </td>

              <!-- Tipe (From DB master table) -->
              <td class="py-2 px-2">
                <select :name="'items[' + idx + '][asset_type]'" x-model="row.asset_type" required
                  class="w-full px-2 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-xs">
                  @foreach($assetTypes as $at)
                    <option value="{{ $at->code }}">{{ $at->code === 'digital' ? 'Digital' : 'Fisik' }}</option>
                  @endforeach
                </select>
              </td>

              <!-- Gedung (From DB campus_locations) -->
              <td class="py-2 px-2">
                <select :name="'items[' + idx + '][building_id]'" x-model="row.building_id"
                  class="w-full px-2 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-xs truncate">
                  <option value="">- Gedung -</option>
                  @foreach($buildings as $b)
                    <option value="{{ $b->id }}">{{ $b->name }}</option>
                  @endforeach
                </select>
              </td>

              <!-- Lantai (From DB campus_locations) -->
              <td class="py-2 px-2">
                <select :name="'items[' + idx + '][floor_id]'" x-model="row.floor_id"
                  class="w-full px-2 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-xs truncate">
                  <option value="">- Lantai -</option>
                  @foreach($floors as $f)
                    <option value="{{ $f->id }}">{{ $f->name }}</option>
                  @endforeach
                </select>
              </td>

              <!-- Kondisi (From DB master table) -->
              <td class="py-2 px-2">
                <select :name="'items[' + idx + '][condition]'" x-model="row.condition" required
                  class="w-full px-2 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-xs font-semibold">
                  @foreach($conditions as $c)
                    <option value="{{ $c->code }}">{{ $c->name }}</option>
                  @endforeach
                </select>
              </td>

              <!-- Status (From DB master table) -->
              <td class="py-2 px-2">
                <select :name="'items[' + idx + '][status]'" x-model="row.status" required
                  class="w-full px-2 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-xs font-semibold">
                  @foreach($statuses as $s)
                    <option value="{{ $s->code }}">{{ $s->name }}</option>
                  @endforeach
                </select>
              </td>

              <!-- Aksi: Copy & Delete -->
              <td class="py-2 px-2 text-center">
                <div class="flex items-center justify-center gap-1">
                  <button type="button" @click="copyRow(idx)" title="Copy / Gandakan baris ini"
                    class="p-1.5 rounded-lg bg-blue-50 dark:bg-blue-950/60 hover:bg-blue-100 text-blue-600 dark:text-blue-400 transition">
                    <i data-lucide="copy" class="w-3.5 h-3.5"></i>
                  </button>
                  <button type="button" @click="removeRow(idx)" title="Hapus baris ini"
                    class="p-1.5 rounded-lg bg-rose-50 dark:bg-rose-950/60 hover:bg-rose-100 text-rose-600 dark:text-rose-400 transition">
                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                  </button>
                </div>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <!-- Bottom Actions Bar -->
    <div class="bg-white dark:bg-gray-900 p-4 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm flex items-center justify-between">
      <button type="button" @click="addEmptyRow()"
        class="px-4 py-2 rounded-xl border border-dashed border-gray-300 dark:border-gray-700 text-xs font-semibold hover:bg-gray-50 inline-flex items-center gap-1.5">
        <i data-lucide="plus" class="w-4 h-4"></i>
        <span>Tambah Baris Baru</span>
      </button>

      <div class="flex items-center gap-3">
        <a href="{{ route('inventory.index') }}" class="px-4 py-2 rounded-xl border border-gray-200 text-xs font-semibold text-gray-600">
          Batal
        </a>
        <button type="submit"
          class="px-6 py-2.5 rounded-xl bg-telkom-600 hover:bg-telkom-700 text-white text-xs font-semibold shadow-md transition inline-flex items-center gap-2">
          <i data-lucide="check" class="w-4 h-4"></i>
          <span>Simpan Semua Aset (<span x-text="bulkRows.length"></span> Baris)</span>
        </button>
      </div>
    </div>
  </form>

</div>
@endsection

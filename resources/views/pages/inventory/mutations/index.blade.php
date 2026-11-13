@extends('layouts.app-layout')

@section('title', 'Mutasi Lokasi & Status Aset')
@section('subtitle', 'Pindahkan lokasi gedung, lantai, serta perbarui status dan kondisi fisik aset inventaris dengan riwayat audit')

@section('content')
<div x-data="{
    selectedItems: [],
    selectAll: false,
    toggleAll() {
        this.selectAll = !this.selectAll;
        if (this.selectAll) {
            this.selectedItems = Array.from(document.querySelectorAll('.item-checkbox')).map(el => el.value);
        } else {
            this.selectedItems = [];
        }
    }
}" class="space-y-6">

  @if(session('success'))
    <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-900 text-emerald-700 dark:text-emerald-400 text-sm font-semibold flex items-center gap-2">
      <i data-lucide="check-circle" class="w-5 h-5 shrink-0"></i>
      <span>{{ session('success') }}</span>
    </div>
  @endif

  @if($errors->any())
    <div class="p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900 text-rose-700 dark:text-rose-400 text-sm font-semibold space-y-1">
      <p class="font-bold flex items-center gap-1.5">
        <i data-lucide="alert-octagon" class="w-4 h-4"></i>
        <span>Terdapat kendala pada isian mutasi:</span>
      </p>
      <ul class="list-disc list-inside text-xs pl-2">
        @foreach($errors->all() as $err)
          <li>{{ $err }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <!-- Top Hero Header -->
  <div class="bg-white dark:bg-gray-900 p-6 rounded-3xl border border-gray-200 dark:border-gray-800 shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
    <div>
      <div class="flex items-center gap-2 text-blue-600 dark:text-blue-400 font-bold text-xs uppercase tracking-wider">
        <i data-lucide="arrow-right-left" class="w-4 h-4"></i>
        <span>Manajemen Mutasi Inventaris</span>
      </div>
      <h2 class="text-xl font-bold tracking-tight text-gray-900 dark:text-white mt-1">Mutasi Lokasi, Status & Kondisi Aset</h2>
      <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Filter barang per gedung atau lantai, pilih aset yang ingin direlokasi, dan sistem akan mencatat riwayat perubahannya secara otomatis.</p>
    </div>

    <x-button variant="secondary" size="sm" icon="arrow-left" href="{{ route('inventory.index') }}" class="shrink-0">
      Kembali ke Katalog
    </x-button>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- Left 2 Cols: Filter & Asset Selection Table -->
    <div class="lg:col-span-2 space-y-4">

      <!-- Filter Bar -->
      <div class="bg-white dark:bg-gray-900 rounded-3xl border border-gray-200 dark:border-gray-800 p-4 shadow-sm text-xs">
        <form action="{{ route('inventory.mutations.index') }}" method="GET" class="grid grid-cols-1 sm:grid-cols-4 gap-3">
          <div>
            <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Filter Gedung</label>
            <select name="building_id" class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
              <option value="">-- Semua Gedung --</option>
              @foreach($buildings as $bld)
                <option value="{{ $bld->id }}" {{ request('building_id') == $bld->id ? 'selected' : '' }}>{{ $bld->name }}</option>
              @endforeach
            </select>
          </div>

          <div>
            <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Filter Lantai</label>
            <select name="floor_id" class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
              <option value="">-- Semua Lantai --</option>
              @foreach($floors as $flr)
                <option value="{{ $flr->id }}" {{ request('floor_id') == $flr->id ? 'selected' : '' }}>{{ $flr->name }}</option>
              @endforeach
            </select>
          </div>

          <div>
            <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Kategori</label>
            <select name="category" class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
              <option value="">-- Semua Kategori --</option>
              @foreach($categories as $cat)
                <option value="{{ $cat }}" {{ request('category') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
              @endforeach
            </select>
          </div>

          <div class="flex items-end gap-2">
            <x-button type="submit" variant="primary" size="sm" class="flex-1">
              Terapkan Filter
            </x-button>
            <x-button variant="ghost" size="sm" href="{{ route('inventory.mutations.index') }}">
              Reset
            </x-button>
          </div>
        </form>
      </div>

      <!-- Assets Selection Table -->
      <div class="bg-white dark:bg-gray-900 rounded-3xl border border-gray-200 dark:border-gray-800 shadow-sm overflow-hidden">
        <div class="p-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
          <div>
            <h3 class="font-bold text-sm text-gray-900 dark:text-white">Pilih Aset untuk Dimutasi</h3>
            <p class="text-xs text-gray-500">Centang satu atau banyak barang yang akan dipindahkan posisinya</p>
          </div>
          <div class="text-xs text-gray-500">
            Terpilih: <strong class="text-blue-600 font-bold" x-text="selectedItems.length">0</strong> aset
          </div>
        </div>

        @if($items->isEmpty())
          <div class="p-12 text-center text-xs text-gray-500">
            <i data-lucide="package-search" class="w-8 h-8 mx-auto mb-2 text-gray-400"></i>
            <p class="font-semibold text-gray-700 dark:text-gray-300">Tidak ada aset fisik ditemukan pada filter ini.</p>
          </div>
        @else
          <div class="overflow-x-auto max-h-[500px]">
            <table class="w-full text-left text-xs">
              <thead class="bg-gray-50 dark:bg-gray-800/60 sticky top-0 font-semibold text-gray-600 dark:text-gray-400">
                <tr>
                  <th class="px-4 py-3 w-10 text-center">
                    <input type="checkbox" @click="toggleAll()" :checked="selectAll" class="rounded border-gray-300 text-telkom-600">
                  </th>
                  <th class="px-4 py-3">Kode Aset</th>
                  <th class="px-4 py-3">Nama & Model</th>
                  <th class="px-4 py-3">Lokasi Saat Ini</th>
                  <th class="px-4 py-3">Status</th>
                  <th class="px-4 py-3">Kondisi</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 dark:divide-gray-800 text-gray-700 dark:text-gray-300">
                @foreach($items as $item)
                  <tr class="hover:bg-gray-50 dark:hover:bg-gray-850/50 transition">
                    <td class="px-4 py-3.5 text-center">
                      <input type="checkbox" value="{{ $item->id }}" x-model="selectedItems" class="item-checkbox rounded border-gray-300 text-telkom-600">
                    </td>
                    <td class="px-4 py-3.5 font-mono font-bold text-purple-600">{{ $item->asset_code }}</td>
                    <td class="px-4 py-3.5">
                      <p class="font-semibold text-gray-900 dark:text-white">{{ $item->name }}</p>
                      @if($item->brand_model)
                        <p class="text-[11px] text-gray-400">{{ $item->brand_model }}</p>
                      @endif
                    </td>
                    <td class="px-4 py-3.5 font-medium">{{ $item->display_location }}</td>
                    <td class="px-4 py-3.5">
                      <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-gray-100 dark:bg-gray-800">
                        {{ $item->status_label }}
                      </span>
                    </td>
                    <td class="px-4 py-3.5">
                      <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-gray-100 dark:bg-gray-800">
                        {{ $item->condition_label }}
                      </span>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
          <div class="p-3 border-t border-gray-100 dark:border-gray-800 text-xs">
            {{ $items->links() }}
          </div>
        @endif
      </div>

    </div>

    <!-- Right Col: Form Input Mutasi -->
    <div class="space-y-6">

      <!-- Form Mutasi Box -->
      <div class="bg-white dark:bg-gray-900 rounded-3xl border border-gray-200 dark:border-gray-800 p-6 shadow-sm space-y-4">
        <h3 class="font-bold text-base text-gray-900 dark:text-white border-b border-gray-100 dark:border-gray-800 pb-3 flex items-center gap-2">
          <i data-lucide="edit-3" class="w-4 h-4 text-blue-600"></i>
          <span>Formulir Mutasi & Pemindahan</span>
        </h3>

        <form action="{{ route('inventory.mutate') }}" method="POST" class="space-y-4 text-xs">
          @csrf

          <!-- Hidden Selected Item IDs -->
          <template x-for="id in selectedItems" :key="id">
            <input type="hidden" name="item_ids[]" :value="id">
          </template>

          <div class="bg-blue-50 dark:bg-blue-950/40 p-3 rounded-2xl border border-blue-200 dark:border-blue-900">
            <p class="font-bold text-blue-900 dark:text-blue-300">
              Total Target: <span x-text="selectedItems.length">0</span> aset terpilih
            </p>
            <p class="text-[11px] text-blue-700 dark:text-blue-400 mt-0.5" x-show="selectedItems.length === 0">
              Silakan centang barang di tabel sebelah kiri terlebih dahulu.
            </p>
          </div>

          <div class="space-y-3">
            <p class="font-bold text-gray-800 dark:text-gray-200">1. Target Lokasi Baru</p>
            <div>
              <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Gedung Baru</label>
              <select name="new_building_id" class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                <option value="">-- Tetap (Tidak Berubah) --</option>
                @foreach($buildings as $b)
                  <option value="{{ $b->id }}">{{ $b->name }}</option>
                @endforeach
              </select>
            </div>

            <div>
              <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Lantai Baru</label>
              <select name="new_floor_id" class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                <option value="">-- Tetap (Tidak Berubah) --</option>
                @foreach($floors as $f)
                  <option value="{{ $f->id }}">{{ $f->name }}</option>
                @endforeach
              </select>
            </div>

            <div>
              <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Detail Ruangan / Posisi Baru</label>
              <input type="text" name="new_location_detail" placeholder="Contoh: Lab Multimedia Meja 01 s/d 10"
                class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
            </div>
          </div>

          <div class="space-y-3 border-t border-dashed border-gray-200 dark:border-gray-800 pt-3">
            <p class="font-bold text-gray-800 dark:text-gray-200">2. Status & Kondisi Baru</p>
            <div class="grid grid-cols-2 gap-2">
              <div>
                <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Status Baru</label>
                <select name="new_status" class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                  <option value="">-- Tetap --</option>
                  @foreach($statuses as $st)
                    <option value="{{ $st->value }}">{{ $st->label() }}</option>
                  @endforeach
                </select>
              </div>
              <div>
                <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Kondisi Baru</label>
                <select name="new_condition" class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                  <option value="">-- Tetap --</option>
                  @foreach($conditions as $cd)
                    <option value="{{ $cd->value }}">{{ $cd->label() }}</option>
                  @endforeach
                </select>
              </div>
            </div>
          </div>

          <div class="border-t border-dashed border-gray-200 dark:border-gray-800 pt-3">
            <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Alasan / Catatan Mutasi <span class="text-red-500">*</span></label>
            <textarea name="mutation_notes" required rows="3" placeholder="Contoh: Pemindahan ruang lab praktikum semester genap / peremajaan unit..."
              class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800"></textarea>
            <p class="text-[11px] text-gray-400 mt-1">Dicatat secara permanen pada log riwayat audit aset.</p>
          </div>

          <x-button type="submit" variant="primary" size="md" icon="arrow-right-left" x-bind:disabled="selectedItems.length === 0" class="w-full">
            Terapkan Mutasi Aset
          </x-button>
        </form>
      </div>

      <!-- Recent Mutations Activity -->
      <div class="bg-white dark:bg-gray-900 rounded-3xl border border-gray-200 dark:border-gray-800 p-6 shadow-sm space-y-4">
        <h3 class="font-bold text-sm text-gray-900 dark:text-white border-b border-gray-100 dark:border-gray-800 pb-3 flex items-center gap-2">
          <i data-lucide="history" class="w-4 h-4 text-purple-600"></i>
          <span>Log Mutasi Terkini</span>
        </h3>

        @if($recentMutations->isEmpty())
          <p class="text-xs text-gray-500 py-4 text-center">Belum ada riwayat mutasi perpindahan.</p>
        @else
          <div class="space-y-3 text-xs max-h-96 overflow-y-auto">
            @foreach($recentMutations as $mut)
              <div class="p-3 rounded-2xl bg-gray-50 dark:bg-gray-800/60 border border-gray-200/60 dark:border-gray-700 space-y-1">
                <div class="flex items-center justify-between">
                  <span class="font-mono font-bold text-purple-600">{{ $mut->inventoryItem?->asset_code }}</span>
                  <span class="text-[10px] text-gray-400">{{ $mut->created_at->diffForHumans() }}</span>
                </div>
                <p class="font-semibold text-gray-900 dark:text-white">{{ $mut->inventoryItem?->name }}</p>
                @if($mut->old_location || $mut->new_location)
                  <p class="text-[11px] text-gray-600 dark:text-gray-300">
                    <span class="line-through text-gray-400">{{ $mut->old_location ?: '-' }}</span>
                    <i data-lucide="arrow-right" class="w-3 h-3 inline mx-0.5 text-gray-400"></i>
                    <strong class="text-gray-900 dark:text-white">{{ $mut->new_location ?: '-' }}</strong>
                  </p>
                @endif
                <p class="text-[11px] text-gray-500 bg-white dark:bg-gray-900 p-1.5 rounded-lg border border-gray-100 dark:border-gray-800 mt-1">
                  {{ $mut->notes }}
                </p>
              </div>
            @endforeach
          </div>
        @endif
      </div>

    </div>

  </div>

</div>
@endsection


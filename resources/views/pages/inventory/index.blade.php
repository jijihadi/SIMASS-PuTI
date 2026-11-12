@extends('layouts.app-layout')

@section('title', 'Manajemen Inventaris Aset IT PuTI')
@section('subtitle', 'Pencatatan aset fisik & lisensi digital, import massal, dan sinkronisasi tiket')

@section('content')
<div x-data="{
    openAddModal: false,
    openExcelModal: false,
    newAssetType: 'physical'
}" class="space-y-6">

  @if(session('success'))
    <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-900 text-emerald-700 dark:text-emerald-400 text-sm font-semibold flex items-center gap-2">
      <i data-lucide="check-circle" class="w-5 h-5 shrink-0"></i>
      <span>{{ session('success') }}</span>
    </div>
  @endif

  @if(session('warning'))
    <div class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-900 text-amber-700 dark:text-amber-400 text-sm font-semibold flex items-center gap-2">
      <i data-lucide="alert-triangle" class="w-5 h-5 shrink-0"></i>
      <span>{{ session('warning') }}</span>
    </div>
  @endif

  @if(session('error'))
    <div class="p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900 text-rose-700 dark:text-rose-400 text-sm font-semibold flex items-center gap-2">
      <i data-lucide="x-circle" class="w-5 h-5 shrink-0"></i>
      <span>{{ session('error') }}</span>
    </div>
  @endif

  <!-- Top Action Bar -->
  <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-white dark:bg-gray-900 p-6 rounded-3xl border border-gray-200 dark:border-gray-800 shadow-sm">
    <div>
      <h2 class="text-xl font-bold tracking-tight text-gray-900 dark:text-white">Inventaris Aset Fisik & Digital</h2>
      <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
        Kelola perangkat keras dan lisensi digital/software dengan pemantauan status serta kondisi terkini.
      </p>
    </div>
    <div class="flex flex-wrap items-center gap-2.5 w-full sm:w-auto">
      <button type="button" @click="openExcelModal = true"
        class="px-4 py-2.5 rounded-2xl border border-purple-200 dark:border-purple-800 bg-purple-50 dark:bg-purple-950/40 text-xs font-semibold text-purple-700 dark:text-purple-300 hover:bg-purple-100 transition inline-flex items-center gap-2 shadow-sm">
        <i data-lucide="file-spreadsheet" class="w-4 h-4"></i>
        <span>Import Excel</span>
      </button>

      <a href="{{ route('inventory.bulk-input') }}"
        class="px-4 py-2.5 rounded-2xl border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-950/40 text-xs font-semibold text-blue-700 dark:text-blue-300 hover:bg-blue-100 transition inline-flex items-center gap-2 shadow-sm">
        <i data-lucide="table" class="w-4 h-4"></i>
        <span>Bulk Input</span>
      </a>

      <button type="button" @click="openAddModal = true"
        class="px-4 py-2.5 rounded-2xl bg-telkom-600 hover:bg-telkom-700 text-white text-xs font-semibold shadow-sm transition inline-flex items-center gap-2">
        <i data-lucide="plus-circle" class="w-4 h-4"></i>
        <span>+ Tambah Aset</span>
      </button>
    </div>
  </div>

  <!-- KPI Stats -->
  <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
    <div class="bg-white dark:bg-gray-900 rounded-2xl p-4 border border-gray-200 dark:border-gray-800 shadow-sm">
      <div class="flex items-center justify-between text-gray-500 text-xs font-semibold uppercase">
        <span>Total Aset</span>
        <i data-lucide="package" class="w-4 h-4"></i>
      </div>
      <p class="text-2xl font-extrabold text-gray-900 dark:text-white mt-1">{{ $stats['total'] }}</p>
    </div>

    <div class="bg-white dark:bg-gray-900 rounded-2xl p-4 border border-gray-200 dark:border-gray-800 shadow-sm">
      <div class="flex items-center justify-between text-blue-600 dark:text-blue-400 text-xs font-semibold uppercase">
        <span>Aset Fisik</span>
        <i data-lucide="cpu" class="w-4 h-4"></i>
      </div>
      <p class="text-2xl font-extrabold text-gray-900 dark:text-white mt-1">{{ $stats['physical'] }}</p>
    </div>

    <div class="bg-white dark:bg-gray-900 rounded-2xl p-4 border border-gray-200 dark:border-gray-800 shadow-sm">
      <div class="flex items-center justify-between text-purple-600 dark:text-purple-400 text-xs font-semibold uppercase">
        <span>Lisensi Digital</span>
        <i data-lucide="cloud" class="w-4 h-4"></i>
      </div>
      <p class="text-2xl font-extrabold text-gray-900 dark:text-white mt-1">{{ $stats['digital'] }}</p>
    </div>

    <div class="bg-white dark:bg-gray-900 rounded-2xl p-4 border border-gray-200 dark:border-gray-800 shadow-sm">
      <div class="flex items-center justify-between text-emerald-600 dark:text-emerald-400 text-xs font-semibold uppercase">
        <span>Baik / Normal</span>
        <i data-lucide="check-circle-2" class="w-4 h-4"></i>
      </div>
      <p class="text-2xl font-extrabold text-gray-900 dark:text-white mt-1">{{ $stats['good'] }}</p>
    </div>

    <div class="bg-white dark:bg-gray-900 rounded-2xl p-4 border border-gray-200 dark:border-gray-800 shadow-sm">
      <div class="flex items-center justify-between text-amber-600 dark:text-amber-400 text-xs font-semibold uppercase">
        <span>Dalam Perbaikan</span>
        <i data-lucide="wrench" class="w-4 h-4"></i>
      </div>
      <p class="text-2xl font-extrabold text-gray-900 dark:text-white mt-1">{{ $stats['maintenance'] }}</p>
    </div>

    <div class="bg-white dark:bg-gray-900 rounded-2xl p-4 border border-gray-200 dark:border-gray-800 shadow-sm">
      <div class="flex items-center justify-between text-rose-600 dark:text-rose-400 text-xs font-semibold uppercase">
        <span>Rusak / Kendala</span>
        <i data-lucide="alert-octagon" class="w-4 h-4"></i>
      </div>
      <p class="text-2xl font-extrabold text-gray-900 dark:text-white mt-1">{{ $stats['damaged'] }}</p>
    </div>
  </div>

  <!-- Filter Bar -->
  <div class="bg-white dark:bg-gray-900 rounded-3xl border border-gray-200 dark:border-gray-800 p-5 shadow-sm">
    <form action="{{ route('inventory.index') }}" method="GET" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 text-xs">
      <div>
        <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Pencarian</label>
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Kode, nama, SN, lisensi..."
          class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
      </div>

      <div>
        <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Tipe Aset</label>
        <select name="asset_type" class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
          <option value="">-- Semua Tipe --</option>
          <option value="physical" {{ request('asset_type') === 'physical' ? 'selected' : '' }}>Fisik (Hardware)</option>
          <option value="digital" {{ request('asset_type') === 'digital' ? 'selected' : '' }}>Digital (Lisensi / Cloud)</option>
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

      <div>
        <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Gedung</label>
        <select name="building_id" class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
          <option value="">-- Semua Gedung --</option>
          @foreach($buildings as $bld)
            <option value="{{ $bld->id }}" {{ request('building_id') == $bld->id ? 'selected' : '' }}>{{ $bld->name }}</option>
          @endforeach
        </select>
      </div>

      <div>
        <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Lantai</label>
        <select name="floor_id" class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
          <option value="">-- Semua Lantai --</option>
          @foreach($floors as $flr)
            <option value="{{ $flr->id }}" {{ request('floor_id') == $flr->id ? 'selected' : '' }}>{{ $flr->name }}</option>
          @endforeach
        </select>
      </div>

      <div class="flex items-end gap-2">
        <button type="submit" class="flex-1 py-2 px-3 rounded-xl bg-telkom-600 hover:bg-telkom-700 text-white font-semibold transition">
          Filter
        </button>
        <a href="{{ route('inventory.index') }}" class="py-2 px-3 rounded-xl border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 font-semibold hover:bg-gray-50 dark:hover:bg-gray-800">
          Reset
        </a>
      </div>
    </form>
  </div>

  <!-- Assets Table -->
  <div class="bg-white dark:bg-gray-900 rounded-3xl border border-gray-200 dark:border-gray-800 overflow-hidden shadow-sm">
    @if($items->isEmpty())
      <div class="p-12 text-center">
        <div class="w-12 h-12 rounded-2xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center mx-auto mb-3 text-gray-400">
          <i data-lucide="inbox" class="w-6 h-6"></i>
        </div>
        <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">Belum ada aset inventaris yang sesuai dengan filter.</p>
        <p class="text-xs text-gray-500 mt-1">Tambahkan aset baru melalui tombol di atas.</p>
      </div>
    @else
      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead class="bg-gray-50 dark:bg-gray-800/60 text-gray-600 dark:text-gray-400 text-xs font-semibold uppercase">
            <tr>
              <th class="px-5 py-3.5">Kode & Tipe</th>
              <th class="px-5 py-3.5">Nama & Info Aset</th>
              <th class="px-4 py-3.5">Kategori</th>
              <th class="px-4 py-3.5">Lokasi / Detail Akses</th>
              <th class="px-4 py-3.5">Status</th>
              <th class="px-4 py-3.5">Kondisi</th>
              <th class="px-4 py-3.5 text-center">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-gray-800 text-gray-700 dark:text-gray-300">
            @foreach($items as $item)
              @php
                $condBadge = match($item->condition) {
                  'good' => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-400 dark:border-emerald-900',
                  'maintenance' => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-400 dark:border-amber-900',
                  'damaged' => 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/40 dark:text-rose-400 dark:border-rose-900',
                  default => 'bg-gray-100 text-gray-700 border-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-700',
                };

                $statusBadge = match($item->status) {
                  'active' => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-400 dark:border-emerald-900',
                  'in_use' => 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/40 dark:text-blue-400 dark:border-blue-900',
                  'spare' => 'bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-950/40 dark:text-purple-400 dark:border-purple-900',
                  'maintenance' => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-400 dark:border-amber-900',
                  default => 'bg-gray-100 text-gray-700 border-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-700',
                };
              @endphp
              <tr class="hover:bg-gray-50/70 dark:hover:bg-gray-850/50 transition">
                <td class="px-5 py-4">
                  <div class="font-mono font-bold text-xs text-purple-600 dark:text-purple-400">{{ $item->asset_code }}</div>
                  <span class="inline-flex items-center gap-1 mt-1 px-2 py-0.5 rounded text-[11px] font-semibold {{ $item->asset_type === 'digital' ? 'bg-purple-50 text-purple-700 dark:bg-purple-950/50 dark:text-purple-300' : 'bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300' }}">
                    <i data-lucide="{{ $item->asset_type === 'digital' ? 'cloud' : 'cpu' }}" class="w-3 h-3"></i>
                    {{ $item->asset_type_label }}
                  </span>
                </td>
                <td class="px-5 py-4">
                  <p class="font-semibold text-gray-900 dark:text-white leading-tight">{{ $item->name }}</p>
                  @if($item->brand_model || $item->serial_number)
                    <p class="text-xs text-gray-500 mt-0.5">{{ $item->brand_model }} {{ $item->serial_number ? "• SN: {$item->serial_number}" : '' }}</p>
                  @endif

                  @if($item->asset_type === 'digital')
                    <div class="mt-1 flex flex-wrap items-center gap-2 text-xs">
                      @if($item->billing_cycle)
                        <span class="px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-800 text-[11px] text-gray-600 dark:text-gray-300">
                          Siklus: {{ $item->billing_cycle_label }}
                        </span>
                      @endif
                      @if($item->renewal_date)
                        <span class="px-2 py-0.5 rounded-full {{ $item->renewal_date->isPast() ? 'bg-rose-100 text-rose-700' : 'bg-emerald-50 text-emerald-700' }} text-[11px]">
                          Perbarui: {{ $item->renewal_date->format('d M Y') }}
                        </span>
                      @endif
                    </div>
                  @endif
                </td>
                <td class="px-4 py-4 text-xs">{{ $item->category }}</td>
                <td class="px-4 py-4 text-xs">
                  @if($item->asset_type === 'digital')
                    @if($item->access_url)
                      <a href="{{ $item->access_url }}" target="_blank" class="text-blue-600 hover:underline flex items-center gap-1 truncate max-w-xs">
                        <i data-lucide="external-link" class="w-3 h-3 shrink-0"></i>
                        <span class="truncate">{{ $item->access_url }}</span>
                      </a>
                    @else
                      <span class="text-gray-400">Akses internal / cloud</span>
                    @endif
                    @if($item->access_recipients)
                      <p class="text-[11px] text-gray-500 mt-1 line-clamp-1">Penerima: {{ $item->access_recipients }}</p>
                    @endif
                  @else
                    <p class="font-medium text-gray-800 dark:text-gray-200">{{ $item->display_location }}</p>
                  @endif
                </td>
                <td class="px-4 py-4">
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $statusBadge }}">
                    {{ $item->status_label }}
                  </span>
                </td>
                <td class="px-4 py-4">
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $condBadge }}">
                    {{ $item->condition_label }}
                  </span>
                </td>
                <td class="px-4 py-4 text-center">
                  <a href="{{ route('inventory.show', $item) }}" class="px-3 py-1.5 rounded-xl bg-gray-100 dark:bg-gray-800 hover:bg-telkom-600 hover:text-white text-xs font-semibold transition inline-flex items-center gap-1.5">
                    <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                    <span>Detail & Riwayat</span>
                  </a>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <div class="p-4 border-t border-gray-100 dark:border-gray-800">
        {{ $items->links() }}
      </div>
    @endif
  </div>

  <!-- MODAL 1: Tambah Aset Baru (Fisik / Digital) -->
  <div x-show="openAddModal" x-cloak class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div @click.away="openAddModal = false" class="bg-white dark:bg-gray-900 w-full max-w-2xl rounded-3xl border border-gray-200 dark:border-gray-800 p-6 shadow-2xl space-y-4 max-h-[90vh] overflow-y-auto">
      <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-3">
        <div>
          <h3 class="font-bold text-base text-gray-900 dark:text-white">Tambah Aset Inventaris Baru</h3>
          <p class="text-xs text-gray-500 mt-0.5">Pilih tipe aset fisik (hardware) atau lisensi digital/software</p>
        </div>
        <button type="button" @click="openAddModal = false" class="text-gray-400 hover:text-gray-600">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>
      </div>

      <form action="{{ route('inventory.store') }}" method="POST" class="space-y-4 text-xs">
        @csrf

        <!-- Asset Type Selector -->
        <div>
          <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1.5">Tipe Aset <span class="text-red-500">*</span></label>
          <div class="grid grid-cols-2 gap-3">
            <label :class="newAssetType === 'physical' ? 'border-telkom-600 bg-red-50/50 dark:bg-red-950/20 text-telkom-700 dark:text-telkom-300 font-bold' : 'border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400'"
              class="flex items-center gap-3 p-3 rounded-2xl border cursor-pointer transition">
              <input type="radio" name="asset_type" value="physical" x-model="newAssetType" class="text-telkom-600 focus:ring-telkom-500">
              <div class="flex items-center gap-2">
                <i data-lucide="cpu" class="w-4 h-4"></i>
                <span>Aset Fisik (Hardware / Lab)</span>
              </div>
            </label>

            <label :class="newAssetType === 'digital' ? 'border-purple-600 bg-purple-50/50 dark:bg-purple-950/20 text-purple-700 dark:text-purple-300 font-bold' : 'border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400'"
              class="flex items-center gap-3 p-3 rounded-2xl border cursor-pointer transition">
              <input type="radio" name="asset_type" value="digital" x-model="newAssetType" class="text-purple-600 focus:ring-purple-500">
              <div class="flex items-center gap-2">
                <i data-lucide="cloud" class="w-4 h-4"></i>
                <span>Lisensi Digital / Software</span>
              </div>
            </label>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Kode Aset (Kosongkan = Auto)</label>
            <input type="text" name="asset_code" placeholder="INV-PUTI-2026-xxxx"
              class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 font-mono">
          </div>
          <div>
            <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Kategori <span class="text-red-500">*</span></label>
            <select name="category" required class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
              @foreach($categories as $cat)
                <option value="{{ $cat }}" :selected="newAssetType === 'digital' && '{{ $cat }}' === 'Digital & Lisensi Software'">{{ $cat }}</option>
              @endforeach
            </select>
          </div>
        </div>

        <div>
          <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Nama Aset <span class="text-red-500">*</span></label>
          <input type="text" name="name" required placeholder="Contoh: PC Lab RPL 01 / Lisensi Microsoft 365"
            class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Status Operasional <span class="text-red-500">*</span></label>
            <select name="status" required class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
              @foreach($statuses as $st)
                <option value="{{ $st->value }}">{{ $st->label() }}</option>
              @endforeach
            </select>
          </div>
          <div>
            <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Kondisi Awal <span class="text-red-500">*</span></label>
            <select name="condition" required class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
              @foreach($conditions as $cd)
                <option value="{{ $cd->value }}">{{ $cd->label() }}</option>
              @endforeach
            </select>
          </div>
        </div>

        <!-- Conditional Fields: FISIK -->
        <template x-if="newAssetType === 'physical'">
          <div class="space-y-3 pt-2 border-t border-dashed border-gray-200 dark:border-gray-800">
            <p class="font-bold text-gray-800 dark:text-gray-200 text-xs">Spesifikasi & Lokasi Fisik</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Merk / Model</label>
                <input type="text" name="brand_model" placeholder="Dell Optiplex 7090 / Cisco Switch"
                  class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
              </div>
              <div>
                <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Serial Number (SN)</label>
                <input type="text" name="serial_number" placeholder="SN-DELL-xxxx"
                  class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 font-mono">
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Gedung Kampus</label>
                <select name="building_id" class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                  <option value="">-- Pilih Gedung --</option>
                  @foreach($buildings as $b)
                    <option value="{{ $b->id }}">{{ $b->name }}</option>
                  @endforeach
                </select>
              </div>
              <div>
                <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Lantai</label>
                <select name="floor_id" class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                  <option value="">-- Pilih Lantai --</option>
                  @foreach($floors as $f)
                    <option value="{{ $f->id }}">{{ $f->name }}</option>
                  @endforeach
                </select>
              </div>
            </div>

            <div>
              <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Detail Ruangan / Posisi</label>
              <input type="text" name="location_detail" placeholder="Ruang Lab Multimedia Meja 12"
                class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
            </div>
          </div>
        </template>

        <!-- Conditional Fields: DIGITAL / LISENSI -->
        <template x-if="newAssetType === 'digital'">
          <div class="space-y-3 pt-2 border-t border-dashed border-gray-200 dark:border-gray-800">
            <p class="font-bold text-purple-700 dark:text-purple-300 text-xs">Detail Lisensi & Akses Digital</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Siklus Pembayaran Lisensi</label>
                <select name="billing_cycle" class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                  <option value="yearly">Tahunan (Yearly)</option>
                  <option value="monthly">Bulanan (Monthly)</option>
                </select>
              </div>
              <div>
                <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Tanggal Kapan Harus Diperbarui</label>
                <input type="date" name="renewal_date" class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
              </div>
            </div>

            <div>
              <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Alamat Akses / URL Portal</label>
              <input type="url" name="access_url" placeholder="https://admin.microsoft.com atau https://zoom.us"
                class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
            </div>

            <div>
              <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Akses Diberikan ke Siapa Saja (Penerima)</label>
              <textarea name="access_recipients" rows="2" placeholder="Contoh: Seluruh Dosen Tetap, Akun admin.puti@telkomuniversity.ac.id, dsb."
                class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800"></textarea>
            </div>
          </div>
        </template>

        <div>
          <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Catatan Tambahan</label>
          <textarea name="notes" rows="2" placeholder="Catatan pembelian, vendor, garansi..."
            class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800"></textarea>
        </div>

        <div class="pt-3 border-t border-gray-100 dark:border-gray-800 flex items-center justify-end gap-2">
          <button type="button" @click="openAddModal = false"
            class="px-4 py-2 rounded-xl border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 font-semibold">
            Batal
          </button>
          <button type="submit" class="px-5 py-2 rounded-xl bg-telkom-600 hover:bg-telkom-700 text-white font-semibold shadow-sm">
            Simpan Aset
          </button>
        </div>
      </form>
    </div>
  </div>


  <!-- MODAL 3: Import Excel (.xlsx / .csv) -->
  <div x-show="openExcelModal" x-cloak class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div @click.away="openExcelModal = false" class="bg-white dark:bg-gray-900 w-full max-w-2xl rounded-3xl border border-gray-200 dark:border-gray-800 p-6 shadow-2xl space-y-4">
      <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-3">
        <div>
          <h3 class="font-bold text-base text-gray-900 dark:text-white flex items-center gap-2">
            <i data-lucide="file-spreadsheet" class="w-5 h-5 text-purple-600"></i>
            <span>Import Berkas Excel / CSV</span>
          </h3>
          <p class="text-xs text-gray-500 mt-0.5">Unggah berkas spreadsheet dengan format kolom standar</p>
        </div>
        <button type="button" @click="openExcelModal = false" class="text-gray-400 hover:text-gray-600">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>
      </div>

      <div class="bg-purple-50 dark:bg-purple-950/30 border border-purple-200 dark:border-purple-800 rounded-2xl p-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 text-xs">
        <div>
          <p class="font-bold text-purple-900 dark:text-purple-300">Gunakan Template Resmi PuTI</p>
          <p class="text-purple-700 dark:text-purple-400 mt-0.5">Mendukung aset fisik & lisensi digital (tanggal pembaruan, URL akses, dan penerima hak akses).</p>
        </div>
        <a href="{{ route('inventory.template') }}" class="px-3.5 py-2 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-semibold transition shrink-0 inline-flex items-center gap-1.5 shadow-sm">
          <i data-lucide="download" class="w-4 h-4"></i>
          <span>Unduh Template CSV</span>
        </a>
      </div>

      <form action="{{ route('inventory.import') }}" method="POST" enctype="multipart/form-data" class="space-y-4 text-xs">
        @csrf
        <div class="border-2 border-dashed border-gray-300 dark:border-gray-700 rounded-2xl p-8 text-center hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
          <i data-lucide="upload-cloud" class="w-10 h-10 text-gray-400 mx-auto mb-2"></i>
          <p class="font-semibold text-gray-700 dark:text-gray-300">Pilih berkas Excel (.xlsx) atau (.csv)</p>
          <p class="text-[11px] text-gray-400 mt-1">Maksimal 10 MB per file</p>
          <input type="file" name="file" required accept=".xlsx,.csv,.txt" class="mt-4 text-xs file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100">
        </div>

        <div class="pt-3 border-t border-gray-100 dark:border-gray-800 flex items-center justify-end gap-2">
          <button type="button" @click="openExcelModal = false" class="px-4 py-2 rounded-xl border border-gray-200 dark:border-gray-700 text-gray-600">Batal</button>
          <button type="submit" class="px-5 py-2 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-semibold shadow-sm inline-flex items-center gap-1.5">
            <i data-lucide="check" class="w-4 h-4"></i>
            <span>Mulai Impor File</span>
          </button>
        </div>
      </form>
    </div>
  </div>

</div>
@endsection

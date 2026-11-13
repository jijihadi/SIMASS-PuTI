@extends('layouts.app-layout')

@section('title', 'Buat Batch Bulk Maintenance')
@section('subtitle', 'Pilih kumpulan aset yang akan menjalani pemeliharaan atau inspeksi massal')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">

  <div>
    <x-button variant="secondary" size="sm" icon="arrow-left" href="{{ route('inventory.bulk.index') }}">
      Kembali ke Daftar Bulk Maintenance
    </x-button>
  </div>

  <div class="bg-white dark:bg-gray-900 rounded-3xl border border-gray-200 dark:border-gray-800 p-6 sm:p-8 shadow-sm">
    <form action="{{ route('inventory.bulk.store') }}" method="POST" class="space-y-6 text-xs">
      @csrf

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Judul Batch Maintenance <span class="text-red-500">*</span></label>
          <input type="text" name="title" required placeholder="Contoh: Maintenance Rutin PC Lab Komputer 1 Pra-UAS"
            class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
        </div>
        <div>
          <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Jenis Kegiatan <span class="text-red-500">*</span></label>
          <select name="maintenance_type" required class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
            <option value="preventive">Preventive Maintenance (Perawatan Berkala)</option>
            <option value="inspection">Inspection / Audit Kondisi Fisik</option>
            <option value="upgrade">Upgrade Spesifikasi / Software</option>
          </select>
        </div>
      </div>

      <div>
        <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Catatan Umum Batch</label>
        <textarea name="notes" rows="2" placeholder="Catatan pekerjaan yang dilakukan pada seluruh unit..."
          class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800"></textarea>
      </div>

      <div class="space-y-3">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
          <label class="font-bold text-sm text-gray-900 dark:text-white">Pilih Aset yang Akan Di-maintenance ({{ $items->count() }} tersedia)</label>
          <div class="flex items-center gap-2">
            <button type="button" onclick="document.querySelectorAll('.asset-checkbox').forEach(cb => cb.checked = true)"
              class="text-xs font-semibold text-telkom-600 hover:underline">
              Pilih Semua
            </button>
            <span class="text-gray-300">|</span>
            <button type="button" onclick="document.querySelectorAll('.asset-checkbox').forEach(cb => cb.checked = false)"
              class="text-xs font-semibold text-gray-500 hover:underline">
              Batal Pilih
            </button>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 bg-gray-50 dark:bg-gray-800/40 p-3 rounded-2xl border border-gray-200/60 dark:border-gray-700">
          <select onchange="window.location.search = '?category=' + this.value" class="px-2.5 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-xs">
            <option value="">-- Filter Kategori --</option>
            @foreach($categories as $cat)
              <option value="{{ $cat }}" {{ request('category') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
            @endforeach
          </select>
          <select onchange="window.location.search = '?building_id=' + this.value" class="px-2.5 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-xs">
            <option value="">-- Filter Gedung --</option>
            @foreach($buildings as $b)
              <option value="{{ $b->id }}" {{ request('building_id') == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
            @endforeach
          </select>
          <select onchange="window.location.search = '?floor_id=' + this.value" class="px-2.5 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-xs">
            <option value="">-- Filter Lantai --</option>
            @foreach($floors as $f)
              <option value="{{ $f->id }}" {{ request('floor_id') == $f->id ? 'selected' : '' }}>{{ $f->name }}</option>
            @endforeach
          </select>
        </div>

        <div class="max-h-96 overflow-y-auto border border-gray-200 dark:border-gray-800 rounded-2xl divide-y divide-gray-100 dark:divide-gray-800">
          @foreach($items as $item)
            <label class="flex items-center justify-between p-3.5 hover:bg-gray-50 dark:hover:bg-gray-800/50 cursor-pointer">
              <div class="flex items-center gap-3">
                <input type="checkbox" name="item_ids[]" value="{{ $item->id }}" class="asset-checkbox rounded text-telkom-600">
                <div>
                  <span class="font-mono font-bold text-purple-600">{{ $item->asset_code }}</span>
                  <span class="font-semibold text-gray-900 dark:text-white ml-2">{{ $item->name }}</span>
                  <span class="text-gray-500 ml-2">({{ $item->display_location }})</span>
                </div>
              </div>
              <span class="px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-800 text-[10px] font-bold">{{ $item->condition_label }}</span>
            </label>
          @endforeach
        </div>
      </div>

      <div class="pt-4 border-t border-gray-100 dark:border-gray-800 flex items-center justify-end gap-3">
        <x-button variant="ghost" size="md" href="{{ route('inventory.bulk.index') }}">Batal</x-button>
        <x-button type="submit" variant="primary" size="md">
          Mulai Batch Bulk Maintenance
        </x-button>
      </div>
    </form>
  </div>

</div>
@endsection


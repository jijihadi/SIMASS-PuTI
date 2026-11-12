@extends('layouts.app-layout')

@section('title', 'Maintenance & Bulk Maintenance Terpadu')
@section('subtitle', 'Pencatatan perawatan rutin, perbaikan satuan, dan inspeksi massal tersinkronisasi dengan e-Ticket')

@section('content')
<div x-data="{ currentTab: '{{ $activeTab ?? 'single' }}' }" class="space-y-6">

  @if(session('success'))
    <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-900 text-emerald-700 dark:text-emerald-400 text-sm font-semibold flex items-center gap-2">
      <i data-lucide="check-circle" class="w-5 h-5 shrink-0"></i>
      <span>{{ session('success') }}</span>
    </div>
  @endif

  <!-- Top Header & Unified Tab Bar -->
  <div class="bg-white dark:bg-gray-900 p-6 rounded-3xl border border-gray-200 dark:border-gray-800 shadow-sm space-y-4">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
      <div>
        <h2 class="text-xl font-bold tracking-tight text-gray-900 dark:text-white">Pusat Maintenance Aset PuTI</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Kelola perawatan satuan dan batch inspeksi massal dalam satu fitur terpadu.</p>
      </div>

      <div class="flex items-center gap-2">
        <a href="{{ route('inventory.index') }}" class="px-4 py-2 rounded-2xl border border-gray-200 dark:border-gray-700 text-xs font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 transition inline-flex items-center gap-1.5">
          <i data-lucide="arrow-left" class="w-4 h-4"></i>
          <span>Ke Katalog Inventaris</span>
        </a>
      </div>
    </div>

    <!-- Unified Tab Switcher -->
    <div class="flex items-center gap-3 border-t border-gray-100 dark:border-gray-800 pt-4 text-xs font-semibold">
      <button type="button" @click="currentTab = 'single'"
        :class="currentTab === 'single' ? 'bg-telkom-600 text-white shadow-sm' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:bg-gray-200'"
        class="px-4 py-2 rounded-xl transition inline-flex items-center gap-2">
        <i data-lucide="wrench" class="w-4 h-4"></i>
        <span>1. Maintenance Satuan</span>
        <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-white/20">{{ $maintenances->total() }}</span>
      </button>

      <button type="button" @click="currentTab = 'bulk'"
        :class="currentTab === 'bulk' ? 'bg-purple-600 text-white shadow-sm' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:bg-gray-200'"
        class="px-4 py-2 rounded-xl transition inline-flex items-center gap-2">
        <i data-lucide="layers" class="w-4 h-4"></i>
        <span>2. Bulk Maintenance Massal</span>
        <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-white/20">{{ $batches->total() }}</span>
      </button>
    </div>
  </div>

  <!-- TAB 1: Single Maintenance -->
  <div x-show="currentTab === 'single'" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Log Form -->
    <div class="bg-white dark:bg-gray-900 rounded-3xl border border-gray-200 dark:border-gray-800 p-6 shadow-sm space-y-4 h-fit">
      <h3 class="font-bold text-base text-gray-900 dark:text-white border-b border-gray-100 dark:border-gray-800 pb-3 flex items-center gap-2">
        <i data-lucide="plus-circle" class="w-4 h-4 text-telkom-600"></i>
        <span>Catat Maintenance Satuan</span>
      </h3>

      <form action="{{ route('inventory.maintenances.store') }}" method="POST" class="space-y-3 text-xs">
        @csrf

        <div>
          <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Pilih Aset <span class="text-red-500">*</span></label>
          <select name="inventory_item_id" required class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
            <option value="">-- Pilih Aset --</option>
            @foreach($inventoryItems as $item)
              <option value="{{ $item->id }}">{{ $item->asset_code }} — {{ $item->name }} ({{ $item->condition_label }})</option>
            @endforeach
          </select>
        </div>

        <div>
          <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Tautkan ke Tiket Aktif (Opsional)</label>
          <select name="ticket_id" class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
            <option value="">-- Tanpa Tiket / Mandiri --</option>
            @foreach($activeTickets as $ticket)
              <option value="{{ $ticket->id }}">{{ $ticket->ticket_number }} — {{ $ticket->title }}</option>
            @endforeach
          </select>
        </div>

        <div class="grid grid-cols-2 gap-2">
          <div>
            <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Jenis</label>
            <select name="type" required class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
              <option value="preventive">Preventive (Rutin)</option>
              <option value="corrective">Corrective (Perbaikan)</option>
              <option value="inspection">Inspection (Pengecekan)</option>
            </select>
          </div>
          <div>
            <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Status</label>
            <select name="status" required class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
              <option value="in_progress">Sedang Dikerjakan</option>
              <option value="completed">Selesai (Baik)</option>
              <option value="failed">Gagal / Masih Rusak</option>
            </select>
          </div>
        </div>

        <div>
          <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Kondisi Hasil Pengecekan</label>
          <select name="result_condition" class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
            <option value="good">Baik / Normal</option>
            <option value="damaged">Rusak / Bermasalah</option>
            <option value="retired">Dihapus / Afkir</option>
          </select>
        </div>

        <div>
          <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Judul Kegiatan <span class="text-red-500">*</span></label>
          <input type="text" name="title" required placeholder="Contoh: Penggantian Pasta Pendingin & Pembersihan RAM"
            class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
        </div>

        <div>
          <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Catatan Tindakan</label>
          <textarea name="action_notes" rows="2" placeholder="Detail tindakan teknis..." class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800"></textarea>
        </div>

        <div class="space-y-1.5 pt-1">
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" name="auto_resolve_ticket" value="1" checked class="rounded text-telkom-600">
            <span>Otomatis tandai Tiket Selesai jika kondisi Baik</span>
          </label>
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" name="spawn_ticket_on_damage" value="1" checked class="rounded text-telkom-600">
            <span>Otomatis buat Tiket Baru jika hasil Rusak</span>
          </label>
        </div>

        <button type="submit" class="w-full py-2.5 rounded-xl bg-telkom-600 hover:bg-telkom-700 text-white font-semibold shadow-sm transition">
          Simpan & Sinkronkan
        </button>
      </form>
    </div>

    <!-- Single List -->
    <div class="lg:col-span-2 bg-white dark:bg-gray-900 rounded-3xl border border-gray-200 dark:border-gray-800 p-6 shadow-sm space-y-4">
      <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-3">
        <h3 class="font-bold text-base text-gray-900 dark:text-white">Daftar Aktivitas Maintenance Satuan</h3>
      </div>

      @if($maintenances->isEmpty())
        <p class="text-xs text-gray-500 py-8 text-center">Belum ada riwayat maintenance satuan tercatat.</p>
      @else
        <div class="space-y-3">
          @foreach($maintenances as $mnt)
            <div class="p-4 rounded-2xl bg-gray-50 dark:bg-gray-800/60 border border-gray-200/70 dark:border-gray-700 space-y-2 text-xs">
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                  <span class="font-mono font-bold text-purple-600">{{ $mnt->maintenance_code }}</span>
                  <span class="px-2 py-0.5 rounded bg-gray-200 dark:bg-gray-700 text-[10px] font-bold uppercase">{{ $mnt->type }}</span>
                </div>
                <span class="font-bold text-gray-800 dark:text-gray-200">{{ $mnt->status_label }}</span>
              </div>

              <p class="font-bold text-sm text-gray-900 dark:text-white">{{ $mnt->title }}</p>
              <p class="text-gray-600 dark:text-gray-300">
                Aset: <strong>{{ $mnt->inventoryItem?->asset_code }} — {{ $mnt->inventoryItem?->name }}</strong>
                @if($mnt->ticket)
                  • Tiket: <a href="{{ route('ticket.show', $mnt->ticket) }}" class="font-mono font-bold text-telkom-600 hover:underline">{{ $mnt->ticket->ticket_number }}</a>
                @endif
              </p>

              @if($mnt->status === 'in_progress')
                <form action="{{ route('inventory.maintenances.update', $mnt) }}" method="POST" class="pt-2 border-t border-gray-200 dark:border-gray-700 flex flex-wrap items-center gap-2">
                  @csrf
                  @method('PATCH')
                  <input type="hidden" name="status" value="completed">
                  <select name="result_condition" class="px-2.5 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-xs">
                    <option value="good">Hasil: Baik / Normal</option>
                    <option value="damaged">Hasil: Masih Rusak</option>
                  </select>
                  <input type="text" name="action_notes" placeholder="Catatan tindakan penyelesaian..." class="flex-1 px-2.5 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-xs">
                  <input type="hidden" name="auto_resolve_ticket" value="1">
                  <input type="hidden" name="spawn_ticket_on_damage" value="1">
                  <button type="submit" class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold">
                    Selesaikan
                  </button>
                </form>
              @endif
            </div>
          @endforeach
        </div>
        <div class="pt-4">
          {{ $maintenances->links() }}
        </div>
      @endif
    </div>
  </div>

  <!-- TAB 2: Bulk Maintenance Batches -->
  <div x-show="currentTab === 'bulk'" class="space-y-6">
    <!-- Action Banner -->
    <div class="bg-gradient-to-r from-purple-800 to-indigo-800 text-white rounded-3xl p-6 shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
      <div>
        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/20 text-xs font-bold uppercase tracking-wider backdrop-blur-sm">
          <i data-lucide="layers" class="w-3.5 h-3.5"></i>
          <span>Inspeksi & Pemeliharaan Massal</span>
        </span>
        <h3 class="text-xl font-bold mt-2">Penugasan Bulk Maintenance</h3>
        <p class="text-xs text-purple-200 mt-1">Periksa puluhan perangkat lab komputer sekaligus dan perbarui kondisi seluruh aset dalam 1 formulir serentak.</p>
      </div>

      <a href="{{ route('inventory.bulk.create') }}" class="px-4 py-2.5 rounded-2xl bg-white text-purple-900 hover:bg-purple-50 text-xs font-bold transition inline-flex items-center gap-2 shadow-sm shrink-0">
        <i data-lucide="plus-circle" class="w-4 h-4"></i>
        <span>+ Buat Penugasan Bulk Baru</span>
      </a>
    </div>

    <!-- Batches Grid -->
    <div class="bg-white dark:bg-gray-900 rounded-3xl border border-gray-200 dark:border-gray-800 p-6 shadow-sm space-y-4">
      <h3 class="font-bold text-base text-gray-900 dark:text-white border-b border-gray-100 dark:border-gray-800 pb-3">
        Daftar Batch Bulk Maintenance
      </h3>

      @if($batches->isEmpty())
        <div class="p-12 text-center text-xs text-gray-500">
          <p class="font-semibold text-gray-700 dark:text-gray-300">Belum ada kegiatan Bulk Maintenance yang dibuat.</p>
          <a href="{{ route('inventory.bulk.create') }}" class="text-purple-600 font-bold hover:underline mt-2 inline-block">+ Mulai Penugasan Bulk Sekarang</a>
        </div>
      @else
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          @foreach($batches as $batch)
            <div class="p-5 rounded-2xl bg-gray-50 dark:bg-gray-800/60 border border-gray-200/80 dark:border-gray-700 space-y-3 text-xs">
              <div class="flex items-center justify-between">
                <span class="font-mono font-bold text-purple-600">{{ $batch->batch_code }}</span>
                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold {{ $batch->status === 'completed' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                  {{ $batch->status_label }}
                </span>
              </div>

              <div>
                <h4 class="font-bold text-sm text-gray-900 dark:text-white">{{ $batch->title }}</h4>
                <p class="text-gray-500 mt-0.5">Tipe: <strong class="text-gray-700 dark:text-gray-300">{{ $batch->type_label }}</strong></p>
              </div>

              <div class="flex items-center justify-between text-gray-500 text-[11px] border-t border-gray-200/60 dark:border-gray-700 pt-2">
                <span>Total Aset: <strong class="text-purple-600">{{ $batch->maintenances_count }} barang</strong></span>
                <span>Petugas: {{ $batch->performer?->name ?? '-' }}</span>
              </div>

              <a href="{{ route('inventory.bulk.show', $batch) }}" class="w-full py-2 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 hover:bg-purple-600 hover:text-white font-semibold transition text-center block">
                Buka Lembar Pengecekan & Checklist
              </a>
            </div>
          @endforeach
        </div>
        <div class="pt-4">
          {{ $batches->links() }}
        </div>
      @endif
    </div>
  </div>

</div>
@endsection

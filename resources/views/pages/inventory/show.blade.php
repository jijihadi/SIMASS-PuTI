@extends('layouts.app-layout')

@section('title', 'Detail Aset ' . $item->asset_code)
@section('subtitle', $item->name)

@section('content')
<div x-data="{ assetType: '{{ $item->asset_type }}' }" class="space-y-6">

  <div class="flex items-center justify-between gap-3">
    <x-button variant="secondary" size="sm" icon="arrow-left" href="{{ route('inventory.index') }}">
      Kembali ke Daftar Inventaris
    </x-button>
    <span id="inventoryShowLiveBadge"
      class="hidden inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-900">
      <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
      <span>Live Sync</span>
    </span>
  </div>

  @if(session('success'))
    <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-900 text-emerald-700 dark:text-emerald-400 text-sm font-semibold flex items-center gap-2">
      <i data-lucide="check-circle" class="w-5 h-5 shrink-0"></i>
      <span>{{ session('success') }}</span>
    </div>
  @endif

  <!-- Top Hero Card for Digital / License -->
  @if($item->asset_type === 'digital')
    <div class="bg-gradient-to-r from-purple-900 to-indigo-900 text-white rounded-3xl p-6 shadow-md">
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
          <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/20 text-xs font-bold uppercase tracking-wider backdrop-blur-sm">
            <i data-lucide="cloud" class="w-3.5 h-3.5"></i>
            <span>Lisensi Digital PuTI</span>
          </span>
          <h2 class="text-xl font-extrabold mt-2">{{ $item->name }}</h2>
          <p class="text-xs text-purple-200 mt-1 font-mono">Kode Aset: {{ $item->asset_code }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
          @if($item->access_url)
            <a href="{{ $item->access_url }}" target="_blank"
              class="px-4 py-2 rounded-2xl bg-white text-purple-900 hover:bg-purple-50 text-xs font-bold transition inline-flex items-center gap-2 shadow-sm">
              <i data-lucide="external-link" class="w-4 h-4"></i>
              <span>Akses Portal / Layanan</span>
            </a>
          @endif
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-6 pt-5 border-t border-white/10 text-xs">
        <div>
          <p class="text-purple-300 font-semibold uppercase text-[10px]">Siklus Pembayaran</p>
          <p class="font-bold text-sm mt-0.5">{{ $item->billing_cycle_label ?? 'Tidak ditentukan' }}</p>
        </div>
        <div>
          <p class="text-purple-300 font-semibold uppercase text-[10px]">Tanggal Pembaruan Lisensi</p>
          <p class="font-bold text-sm mt-0.5">
            {{ $item->renewal_date ? $item->renewal_date->format('d M Y') : 'Tanpa batas waktu / belum diset' }}
            @if($item->renewal_date && $item->renewal_date->isPast())
              <span class="text-rose-300 text-xs font-normal">(Kadaluarsa)</span>
            @endif
          </p>
        </div>
        <div>
          <p class="text-purple-300 font-semibold uppercase text-[10px]">Penerima Hak Akses</p>
          <p class="font-medium text-xs mt-0.5">{{ $item->access_recipients ?: 'Belum dicatat' }}</p>
        </div>
      </div>
    </div>
  @endif

  <!-- Top Hero Card for Physical Asset with Maintenance Schedule -->
  @if($item->asset_type === 'physical' && ($item->maintenance_cycle || $item->next_maintenance_date))
    <div class="bg-gradient-to-r from-blue-900 to-slate-900 text-white rounded-3xl p-6 shadow-md">
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
          <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/20 text-xs font-bold uppercase tracking-wider backdrop-blur-sm">
            <i data-lucide="wrench" class="w-3.5 h-3.5"></i>
            <span>Jadwal Maintenance & Servis Berkala</span>
          </span>
          <h2 class="text-xl font-extrabold mt-2">{{ $item->name }}</h2>
          <p class="text-xs text-blue-200 mt-1 font-mono">Kode Aset: {{ $item->asset_code }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
          @if($item->isMaintenanceOverdue())
            <span class="px-3.5 py-1.5 rounded-full bg-rose-500/90 text-white text-xs font-bold flex items-center gap-1.5 shadow-sm">
              <i data-lucide="alert-octagon" class="w-4 h-4"></i>
              <span>Jadwal Terlewat! Segera Lakukan Maintenance</span>
            </span>
          @elseif($item->isMaintenanceComingSoon())
            <span class="px-3.5 py-1.5 rounded-full bg-amber-500 text-gray-950 text-xs font-bold flex items-center gap-1.5 shadow-sm animate-pulse">
              <i data-lucide="clock" class="w-4 h-4"></i>
              <span>Jatuh Tempo Maintenance (H-{{ $item->maintenanceDaysRemaining() }})</span>
            </span>
          @endif
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-6 pt-5 border-t border-white/10 text-xs">
        <div>
          <p class="text-blue-300 font-semibold uppercase text-[10px]">Siklus Maintenance</p>
          <p class="font-bold text-sm mt-0.5">{{ $item->maintenance_cycle_label ?? 'Tidak ditentukan' }}</p>
        </div>
        <div>
          <p class="text-blue-300 font-semibold uppercase text-[10px]">Jadwal Rawat Berikutnya</p>
          <p class="font-bold text-sm mt-0.5">
            {{ $item->next_maintenance_date ? $item->next_maintenance_date->format('d M Y') : 'Belum diset' }}
          </p>
        </div>
        <div>
          <p class="text-blue-300 font-semibold uppercase text-[10px]">Lokasi Aset</p>
          <p class="font-medium text-xs mt-0.5">{{ $item->display_location }}</p>
        </div>
      </div>
    </div>
  @endif

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Left Column: Asset Info & Edit Form -->
    <div class="bg-white dark:bg-gray-900 rounded-3xl border border-gray-200 dark:border-gray-800 p-6 shadow-sm space-y-4">
      <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-3">
        <div>
          <div class="flex items-center gap-2">
            <span class="font-mono text-xs font-bold text-purple-600 dark:text-purple-400">{{ $item->asset_code }}</span>
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold {{ $item->asset_type_badge_class }}">
              <i data-lucide="{{ $item->asset_type_icon }}" class="w-3 h-3"></i>
              {{ $item->asset_type_label }}
            </span>
          </div>
          <h3 class="font-bold text-base text-gray-900 dark:text-white mt-0.5">{{ $item->name }}</h3>
        </div>
        <span class="px-2.5 py-1 rounded-full text-xs font-bold border {{ $item->status_badge_class }}">
          {{ $item->status_label }}
        </span>
      </div>

      <form action="{{ route('inventory.update', $item) }}" method="POST" class="space-y-3.5 text-xs">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-2 gap-2">
          <div>
            <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Tipe Aset</label>
            <select name="asset_type" x-model="assetType" class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
              <option value="physical" {{ $item->asset_type === 'physical' ? 'selected' : '' }}>Fisik</option>
              <option value="digital" {{ $item->asset_type === 'digital' ? 'selected' : '' }}>Digital</option>
            </select>
          </div>
          <div>
            <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Kategori</label>
            <select name="category" class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
              @foreach($categories as $cat)
                <option value="{{ $cat }}" {{ $item->category === $cat ? 'selected' : '' }}>{{ $cat }}</option>
              @endforeach
            </select>
          </div>
        </div>

        <div>
          <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Nama Aset <span class="text-red-500">*</span></label>
          <input type="text" name="name" value="{{ $item->name }}" required
            class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
        </div>

        <div class="grid grid-cols-2 gap-2">
          <div>
            <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Status Operasional</label>
            <select name="status" class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
              @foreach($statuses as $st)
                <option value="{{ $st->value }}" {{ $item->status === $st->value ? 'selected' : '' }}>{{ $st->label() }}</option>
              @endforeach
            </select>
          </div>
          <div>
            <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Kondisi</label>
            <select name="condition" class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
              @foreach($conditions as $cd)
                <option value="{{ $cd->value }}" {{ $item->condition === $cd->value ? 'selected' : '' }}>{{ $cd->label() }}</option>
              @endforeach
            </select>
          </div>
        </div>

        <!-- Fisik Fields -->
        <template x-if="assetType === 'physical'">
          <div class="space-y-3 pt-2 border-t border-dashed border-gray-200 dark:border-gray-800">
            <div class="grid grid-cols-2 gap-2">
              <div>
                <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Merk / Model</label>
                <input type="text" name="brand_model" value="{{ $item->brand_model }}"
                  class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
              </div>
              <div>
                <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Serial Number</label>
                <input type="text" name="serial_number" value="{{ $item->serial_number }}"
                  class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 font-mono">
              </div>
            </div>

            <div class="grid grid-cols-2 gap-2">
              <div>
                <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Gedung</label>
                <select name="building_id" class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                  <option value="">-- Pilih Gedung --</option>
                  @foreach($buildings as $b)
                    <option value="{{ $b->id }}" {{ $item->building_id == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                  @endforeach
                </select>
              </div>
              <div>
                <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Lantai</label>
                <select name="floor_id" class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                  <option value="">-- Pilih Lantai --</option>
                  @foreach($floors as $f)
                    <option value="{{ $f->id }}" {{ $item->floor_id == $f->id ? 'selected' : '' }}>{{ $f->name }}</option>
                  @endforeach
                </select>
              </div>
            </div>

            <div>
              <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Detail Ruangan / Meja</label>
              <input type="text" name="location_detail" value="{{ $item->location_detail }}"
                class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
            </div>

            <div class="grid grid-cols-2 gap-2 pt-1">
              <div>
                <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Siklus Maintenance</label>
                <select name="maintenance_cycle" class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                  <option value="">-- Tanpa Rutin --</option>
                  <option value="monthly" {{ $item->maintenance_cycle === 'monthly' ? 'selected' : '' }}>Bulanan</option>
                  <option value="quarterly" {{ $item->maintenance_cycle === 'quarterly' ? 'selected' : '' }}>Triwulan (3 Bulan)</option>
                  <option value="semester" {{ $item->maintenance_cycle === 'semester' ? 'selected' : '' }}>Semester (6 Bulan)</option>
                  <option value="yearly" {{ $item->maintenance_cycle === 'yearly' ? 'selected' : '' }}>Tahunan</option>
                </select>
              </div>
              <div>
                <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Jadwal Rawat Berikutnya</label>
                <input type="date" name="next_maintenance_date" value="{{ $item->next_maintenance_date?->format('Y-m-d') }}"
                  class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
              </div>
            </div>
          </div>
        </template>

        <!-- Digital Fields -->
        <template x-if="assetType === 'digital'">
          <div class="space-y-3 pt-2 border-t border-dashed border-gray-200 dark:border-gray-800">
            <div class="grid grid-cols-2 gap-2">
              <div>
                <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Siklus Lisensi</label>
                <select name="billing_cycle" class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                  <option value="yearly" {{ $item->billing_cycle === 'yearly' ? 'selected' : '' }}>Tahunan (Yearly)</option>
                  <option value="monthly" {{ $item->billing_cycle === 'monthly' ? 'selected' : '' }}>Bulanan (Monthly)</option>
                </select>
              </div>
              <div>
                <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Tanggal Diperbarui</label>
                <input type="date" name="renewal_date" value="{{ $item->renewal_date?->format('Y-m-d') }}"
                  class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
              </div>
            </div>

            <div>
              <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Alamat Akses / URL</label>
              <input type="url" name="access_url" value="{{ $item->access_url }}" placeholder="https://..."
                class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
            </div>

            <div>
              <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Penerima Hak Akses</label>
              <textarea name="access_recipients" rows="2" class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">{{ $item->access_recipients }}</textarea>
            </div>
          </div>
        </template>

        <div>
          <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Catatan Tambahan</label>
          <textarea name="notes" rows="2" class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">{{ $item->notes }}</textarea>
        </div>

        <div>
          <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Alasan Perubahan (Dicatat di Histori)</label>
          <input type="text" name="history_notes" placeholder="Contoh: Penggantian nomor seri / relokasi meja"
            class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
        </div>

        <x-button type="submit" variant="primary" size="md" class="w-full">
          Simpan Pembaruan Aset
        </x-button>
      </form>

      <!-- Delete Action -->
      <form action="{{ route('inventory.destroy', $item) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus aset ini secara permanen?')">
        @csrf
        @method('DELETE')
        <x-button type="submit" variant="danger" size="sm" class="w-full">
          Hapus Aset Ini
        </x-button>
      </form>
    </div>

    <!-- Right 2 Columns: Audit Histories, Tickets, Maintenances -->
    <div class="lg:col-span-2 space-y-6">

      <!-- Riwayat Perubahan Lokasi, Status & Kondisi (inventory_histories) -->
      <div class="bg-white dark:bg-gray-900 rounded-3xl border border-gray-200 dark:border-gray-800 p-6 shadow-sm space-y-4">
        <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-3">
          <div class="flex items-center gap-2">
            <i data-lucide="history" class="w-4 h-4 text-purple-600"></i>
            <h3 class="font-bold text-sm text-gray-900 dark:text-white">Riwayat Perubahan Lokasi, Status & Kondisi ({{ $item->histories->count() }})</h3>
          </div>
          <span class="text-[11px] text-gray-400">Audit Log Otomatis</span>
        </div>

        @if($item->histories->isEmpty())
          <p class="text-xs text-gray-500 py-3 text-center">Belum ada riwayat mutasi atau perubahan tercatat pada aset ini.</p>
        @else
          <div class="relative pl-6 border-l-2 border-purple-200 dark:border-purple-900 space-y-4 text-xs">
            @foreach($item->histories as $history)
              <div class="relative group">
                <!-- Dot icon -->
                <div class="absolute -left-[31px] top-1 w-3.5 h-3.5 rounded-full bg-purple-600 border-2 border-white dark:border-gray-900"></div>

                <div class="p-3.5 rounded-2xl bg-gray-50 dark:bg-gray-800/60 border border-gray-200/70 dark:border-gray-700 space-y-1.5">
                  <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="px-2 py-0.5 rounded-full bg-purple-100 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 font-bold text-[11px]">
                      {{ $history->change_type_label }}
                    </span>
                    <span class="text-[11px] text-gray-400">
                      {{ $history->created_at->format('d M Y, H:i') }} • Oleh {{ $history->user?->name ?? 'Sistem' }}
                    </span>
                  </div>

                  @if($history->old_location || $history->new_location)
                    <div class="text-[11px] text-gray-700 dark:text-gray-300">
                      <span class="font-semibold text-gray-500">Lokasi:</span>
                      <span class="line-through text-gray-400">{{ $history->old_location ?: '-' }}</span>
                      <i data-lucide="arrow-right" class="w-3 h-3 inline mx-1 text-gray-400"></i>
                      <span class="font-bold text-gray-900 dark:text-white">{{ $history->new_location ?: '-' }}</span>
                    </div>
                  @endif

                  @if($history->old_status || $history->new_status)
                    <div class="text-[11px] text-gray-700 dark:text-gray-300">
                      <span class="font-semibold text-gray-500">Status:</span>
                      <span class="line-through text-gray-400">{{ \App\Models\InventoryHistory::formatStatusLabel($history->old_status) }}</span>
                      <i data-lucide="arrow-right" class="w-3 h-3 inline mx-1 text-gray-400"></i>
                      <span class="font-bold text-gray-900 dark:text-white">{{ \App\Models\InventoryHistory::formatStatusLabel($history->new_status) }}</span>
                    </div>
                  @endif

                  @if($history->old_condition || $history->new_condition)
                    <div class="text-[11px] text-gray-700 dark:text-gray-300">
                      <span class="font-semibold text-gray-500">Kondisi:</span>
                      <span class="line-through text-gray-400">{{ \App\Models\InventoryHistory::formatConditionLabel($history->old_condition) }}</span>
                      <i data-lucide="arrow-right" class="w-3 h-3 inline mx-1 text-gray-400"></i>
                      <span class="font-bold text-gray-900 dark:text-white">{{ \App\Models\InventoryHistory::formatConditionLabel($history->new_condition) }}</span>
                    </div>
                  @endif

                  @if($history->notes)
                    <p class="text-gray-600 dark:text-gray-400 text-[11px] bg-white dark:bg-gray-900/60 p-2 rounded-xl border border-gray-100 dark:border-gray-800">
                      {{ $history->notes }}
                    </p>
                  @endif
                </div>
              </div>
            @endforeach
          </div>
        @endif
      </div>

      <!-- Linked Tickets -->
      <div class="bg-white dark:bg-gray-900 rounded-3xl border border-gray-200 dark:border-gray-800 p-6 shadow-sm space-y-4">
        <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-3">
          <h3 class="font-bold text-sm text-gray-900 dark:text-white">Riwayat Tiket Terkait ({{ $item->tickets->count() }})</h3>
        </div>
        @if($item->tickets->isEmpty())
          <p class="text-xs text-gray-500 py-2">Belum ada tiket yang ditautkan ke aset ini.</p>
        @else
          <div class="space-y-2.5 text-xs">
            @foreach($item->tickets as $ticket)
              <div class="p-3.5 rounded-2xl bg-gray-50 dark:bg-gray-800/60 border border-gray-200/70 dark:border-gray-700 flex items-center justify-between gap-4">
                <div>
                  <a href="{{ route('ticket.show', $ticket) }}" class="font-mono font-bold text-telkom-600 hover:underline">{{ $ticket->ticket_number }}</a>
                  <p class="font-semibold text-gray-900 dark:text-white mt-0.5">{{ $ticket->title }}</p>
                  <p class="text-[11px] text-gray-500">Status: <strong>{{ $ticket->user_status_label }}</strong> • Teknisi: {{ $ticket->assignee?->name ?? '-' }}</p>
                </div>
                <a href="{{ route('ticket.show', $ticket) }}" class="px-3 py-1.5 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 font-semibold hover:bg-gray-50">
                  Buka Tiket
                </a>
              </div>
            @endforeach
          </div>
        @endif
      </div>

      <!-- Maintenance History -->
      <div class="bg-white dark:bg-gray-900 rounded-3xl border border-gray-200 dark:border-gray-800 p-6 shadow-sm space-y-4">
        <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-3">
          <h3 class="font-bold text-sm text-gray-900 dark:text-white">Riwayat Maintenance & Perawatan ({{ $item->maintenances->count() }})</h3>
          <a href="{{ route('inventory.maintenances.index') }}" class="text-xs font-semibold text-purple-600 hover:underline">+ Tambah Maintenance</a>
        </div>
        @if($item->maintenances->isEmpty())
          <p class="text-xs text-gray-500 py-2">Belum ada catatan maintenance pada aset ini.</p>
        @else
          <div class="space-y-2.5 text-xs">
            @foreach($item->maintenances as $mnt)
              <div class="p-3.5 rounded-2xl bg-gray-50 dark:bg-gray-800/60 border border-gray-200/70 dark:border-gray-700 space-y-1">
                <div class="flex items-center justify-between">
                  <span class="font-mono font-bold text-purple-600">{{ $mnt->maintenance_code }} ({{ strtoupper($mnt->type) }})</span>
                  <span class="font-bold text-[11px] px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700">{{ $mnt->status_label }}</span>
                </div>
                <p class="font-semibold text-gray-900 dark:text-white">{{ $mnt->title }}</p>
                @if($mnt->action_notes)
                  <p class="text-gray-600 dark:text-gray-400">{{ $mnt->action_notes }}</p>
                @endif
                <p class="text-[11px] text-gray-400">Petugas: {{ $mnt->performer?->name ?? '-' }} • Dimulai: {{ $mnt->started_at ? $mnt->started_at->format('d M Y, H:i') : '-' }}</p>
              </div>
            @endforeach
          </div>
        @endif
      </div>

    </div>
  </div>

</div>

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', () => {
    if (!window.SimassRealtime) return;

    const currentItemId = @js($item->id);
    const badge = document.getElementById('inventoryShowLiveBadge');

    window.SimassRealtime.subscribeInventory({
      onConnectionChange(connected) {
        if (badge) badge.classList.toggle('hidden', !connected);
      },
      onItemUpdated(e) {
        if (!e || String(e.id) !== String(currentItemId)) return;

        if (e.action === 'deleted') {
          if (typeof window.showToast === 'function') {
            window.showToast(`Aset ${e.asset_code || ''} baru saja dihapus oleh operator lain.`, 'warning', 5000);
          }
          return;
        }

        if (typeof window.showToast === 'function') {
          window.showToast(
            `Aset ${e.asset_code || ''} diperbarui (${e.status_label || e.status} • ${e.condition_label || e.condition})`,
            'info',
            4500
          );
        }

        const isEditingForm = document.activeElement && document.activeElement.matches('input, textarea, select');
        if (!isEditingForm) {
          setTimeout(() => window.location.reload(), 1200);
        }
      },
    });
  });
</script>
@endpush
@endsection

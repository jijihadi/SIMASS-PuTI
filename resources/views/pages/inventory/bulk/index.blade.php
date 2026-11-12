@extends('layouts.app-layout')

@section('title', 'Bulk Maintenance Inventaris')
@section('subtitle', 'Pemeliharaan massal perangkat laboratorium, jaringan, dan ruang kelas dengan sinkronisasi otomatis ke e-Ticket')

@section('content')
<div class="space-y-6">

  @if(session('success'))
    <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-900 text-emerald-700 dark:text-emerald-400 text-sm font-semibold flex items-center gap-2">
      <i data-lucide="check-circle" class="w-5 h-5 shrink-0"></i>
      <span>{{ session('success') }}</span>
    </div>
  @endif

  <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-white dark:bg-gray-900 p-6 rounded-3xl border border-gray-200 dark:border-gray-800 shadow-sm">
    <div>
      <h2 class="text-xl font-bold text-gray-900 dark:text-white">Batch Bulk Maintenance</h2>
      <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Pilih banyak aset sekaligus untuk maintenance serentak dan otomatis buat tiket untuk perangkat yang ditemukan rusak.</p>
    </div>
    <a href="{{ route('inventory.bulk.create') }}" class="px-5 py-2.5 rounded-2xl bg-telkom-600 hover:bg-telkom-700 text-white text-xs font-semibold shadow-sm transition inline-flex items-center gap-2">
      <i data-lucide="plus-circle" class="w-4 h-4"></i>
      <span>Buat Batch Bulk Maintenance</span>
    </a>
  </div>

  <div class="bg-white dark:bg-gray-900 rounded-3xl border border-gray-200 dark:border-gray-800 overflow-hidden shadow-sm">
    @if($batches->isEmpty())
      <div class="p-12 text-center">
        <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">Belum ada batch Bulk Maintenance.</p>
      </div>
    @else
      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead class="bg-gray-50 dark:bg-gray-800/60 text-gray-600 dark:text-gray-400 text-xs font-semibold uppercase">
            <tr>
              <th class="px-6 py-3.5">Kode Batch</th>
              <th class="px-6 py-3.5">Judul Kegiatan</th>
              <th class="px-6 py-3.5">Tipe</th>
              <th class="px-6 py-3.5">Jumlah Aset</th>
              <th class="px-6 py-3.5">Status</th>
              <th class="px-6 py-3.5">Petugas</th>
              <th class="px-6 py-3.5 text-center">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-gray-800 text-gray-700 dark:text-gray-300">
            @foreach($batches as $batch)
              <tr>
                <td class="px-6 py-4 font-mono font-bold text-xs text-purple-600">{{ $batch->batch_code }}</td>
                <td class="px-6 py-4 font-semibold text-gray-900 dark:text-white">{{ $batch->title }}</td>
                <td class="px-6 py-4 text-xs uppercase">{{ $batch->maintenance_type }}</td>
                <td class="px-6 py-4 text-xs font-bold">{{ $batch->maintenances_count }} unit</td>
                <td class="px-6 py-4 text-xs font-semibold">{{ $batch->status_label }}</td>
                <td class="px-6 py-4 text-xs">{{ $batch->performer?->name ?? '-' }}</td>
                <td class="px-6 py-4 text-center">
                  <a href="{{ route('inventory.bulk.show', $batch) }}" class="px-3 py-1.5 rounded-xl bg-gray-100 dark:bg-gray-800 hover:bg-telkom-600 hover:text-white text-xs font-semibold transition">
                    Buka Eksekusi Batch
                  </a>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <div class="p-4 border-t border-gray-100 dark:border-gray-800">
        {{ $batches->links() }}
      </div>
    @endif
  </div>

</div>
@endsection


@extends('layouts.app-layout')

@section('title', 'Eksekusi Bulk Maintenance ' . $batch->batch_code)
@section('subtitle', $batch->title)

@section('content')
<div class="space-y-6">

  <div class="flex items-center justify-between">
    <x-button variant="secondary" size="sm" icon="arrow-left" href="{{ route('inventory.bulk.index') }}">
      Kembali ke Daftar Bulk Maintenance
    </x-button>
    <span class="px-3 py-1 rounded-full text-xs font-bold bg-purple-50 text-purple-700 border border-purple-200">
      Status Batch: {{ $batch->status_label }}
    </span>
  </div>

  @if(session('success'))
    <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-900 text-emerald-700 dark:text-emerald-400 text-sm font-semibold flex items-center gap-2">
      <i data-lucide="check-circle" class="w-5 h-5 shrink-0"></i>
      <span>{{ session('success') }}</span>
    </div>
  @endif

  <div class="bg-white dark:bg-gray-900 rounded-3xl border border-gray-200 dark:border-gray-800 p-6 shadow-sm space-y-6">
    <div class="border-b border-gray-100 dark:border-gray-800 pb-4">
      <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ $batch->title }} ({{ $batch->batch_code }})</h2>
      <p class="text-xs text-gray-500 mt-1">
        Tipe: <strong class="uppercase">{{ $batch->maintenance_type }}</strong> • Petugas: <strong>{{ $batch->performer?->name ?? '-' }}</strong>
      </p>
    </div>

    <form action="{{ route('inventory.bulk.complete', $batch) }}" method="POST" class="space-y-6 text-xs">
      @csrf
      @method('PATCH')

      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead class="bg-gray-50 dark:bg-gray-800/60 text-gray-600 dark:text-gray-400 uppercase font-semibold">
            <tr>
              <th class="px-4 py-3">Kode Aset</th>
              <th class="px-4 py-3">Nama Perangkat</th>
              <th class="px-4 py-3">Hasil Kondisi</th>
              <th class="px-4 py-3">Catatan Per-Unit</th>
              <th class="px-4 py-3">Tiket Terkait</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            @foreach($batch->maintenances as $mnt)
              <tr>
                <td class="px-4 py-3 font-mono font-bold text-purple-600">
                  {{ $mnt->inventoryItem?->asset_code }}
                </td>
                <td class="px-4 py-3 font-semibold text-gray-900 dark:text-white">
                  {{ $mnt->inventoryItem?->name }}
                </td>
                <td class="px-4 py-3">
                  <select name="outcomes[{{ $mnt->id }}][result_condition]"
                    {{ $batch->status === 'completed' ? 'disabled' : '' }}
                    class="px-3 py-1.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                    <option value="good" {{ ($mnt->result_condition ?? 'good') === 'good' ? 'selected' : '' }}>Baik / Normal</option>
                    <option value="damaged" {{ $mnt->result_condition === 'damaged' ? 'selected' : '' }}>Rusak / Bermasalah</option>
                    <option value="retired" {{ $mnt->result_condition === 'retired' ? 'selected' : '' }}>Dihapus / Afkir</option>
                  </select>
                </td>
                <td class="px-4 py-3">
                  <input type="text" name="outcomes[{{ $mnt->id }}][action_notes]"
                    value="{{ $mnt->action_notes }}"
                    {{ $batch->status === 'completed' ? 'disabled' : '' }}
                    placeholder="Catatan kondisi unit ini..."
                    class="w-full px-3 py-1.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                </td>
                <td class="px-4 py-3">
                  @if($mnt->ticket)
                    <a href="{{ route('ticket.show', $mnt->ticket) }}" class="font-mono font-bold text-telkom-600 hover:underline">
                      {{ $mnt->ticket->ticket_number }}
                    </a>
                  @else
                    <span class="text-gray-400">-</span>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      @if($batch->status !== 'completed')
        <div class="pt-4 border-t border-gray-100 dark:border-gray-800 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
          <label class="inline-flex items-center gap-2 font-semibold text-gray-700 dark:text-gray-300 cursor-pointer">
            <input type="checkbox" name="spawn_ticket_on_damage" value="1" checked class="rounded text-telkom-600">
            <span>Otomatis buat e-Ticket perbaikan untuk setiap unit yang ditandai <strong>Rusak / Bermasalah</strong></span>
          </label>

          <x-button type="submit" variant="primary" size="md">
            Selesaikan & Sinkronkan Batch
          </x-button>
        </div>
      @endif
    </form>
  </div>

</div>
@endsection


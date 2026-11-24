@extends('layouts.app-layout')

@section('title', 'Audit Log — Admin Console')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white flex items-center gap-2 flex-wrap">
                <span>Audit Log Administratif</span>
                <span id="auditLogLiveBadge"
                    class="hidden inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-900">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span>Live Stream</span>
                </span>
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Catatan seluruh mutasi data, pembuatan akun, dan aksi sistem.</p>
        </div>
        <x-button variant="secondary" size="sm" icon="arrow-left" href="{{ route('admin.dashboard') }}">
            Kembali ke Dashboard
        </x-button>
    </div>

    {{-- Search Bar --}}
    <div class="flex items-center justify-between bg-white dark:bg-gray-900 p-4 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm">
        <form method="GET" action="{{ route('admin.audit-logs.index') }}" class="flex items-center gap-2 w-full max-w-md">
            <div class="relative w-full">
                <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="text" name="search" value="{{ $search }}" placeholder="Cari aksi, pengguna, atau IP..." class="w-full pl-9 pr-3 py-1.5 text-xs rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100">
            </div>
            <x-button type="submit" variant="primary" size="sm">
                Cari
            </x-button>
        </form>
    </div>

    {{-- Audit Logs Table --}}
    <div data-audit-table-card class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-gray-600 dark:text-gray-400">
                <thead class="bg-gray-50 dark:bg-gray-800/50 text-gray-700 dark:text-gray-300 font-bold uppercase border-b border-gray-200 dark:border-gray-800">
                    <tr>
                        <th class="px-5 py-3.5">Waktu</th>
                        <th class="px-5 py-3.5">Aktor</th>
                        <th class="px-5 py-3.5">Aksi</th>
                        <th class="px-5 py-3.5">Target Model</th>
                        <th class="px-5 py-3.5">Perubahan Nilai</th>
                        <th class="px-5 py-3.5">IP Address</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($logs as $log)
                        <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/20">
                            <td class="px-5 py-3.5 whitespace-nowrap text-gray-500 font-mono text-[11px]">
                                {{ $log->created_at ? $log->created_at->format('Y-m-d H:i:s') : '-' }}
                            </td>
                            <td class="px-5 py-3.5 font-semibold text-gray-900 dark:text-white">
                                {{ $log->user?->name ?? 'System / Anonymous' }}
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="px-2 py-0.5 rounded text-[11px] font-mono font-bold
                                    {{ str_contains($log->action, 'delete') || str_contains($log->action, 'deactivate') ? 'bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-400' : (str_contains($log->action, 'create') || str_contains($log->action, 'activate') ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400' : 'bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-400') }}">
                                    {{ $log->action }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 font-mono text-[11px]">
                                {{ class_basename($log->model_type) }} #{{ $log->model_id }}
                            </td>
                            <td class="px-5 py-3.5 max-w-xs truncate text-[11px] font-mono text-gray-500">
                                @if ($log->new_values)
                                    <code>{{ json_encode($log->new_values) }}</code>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 font-mono text-[11px] text-gray-500">
                                {{ $log->ip_address ?? '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-8 text-center text-gray-400">
                                Belum ada riwayat audit log tercatat.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($logs->hasPages())
            <div class="p-4 border-t border-gray-100 dark:border-gray-800">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        if (!window.SimassRealtime) return;

        const badge = document.getElementById('auditLogLiveBadge');
        let refreshTimer = null;

        function refreshAuditTable() {
            clearTimeout(refreshTimer);
            refreshTimer = setTimeout(() => {
                fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(res => res.ok ? res.text() : null)
                    .then(html => {
                        if (!html) return;
                        const doc = new DOMParser().parseFromString(html, 'text/html');
                        const nextCard = doc.querySelector('[data-audit-table-card]');
                        const currCard = document.querySelector('[data-audit-table-card]');
                        if (nextCard && currCard) {
                            currCard.innerHTML = nextCard.innerHTML;
                            if (window.createIcons) window.createIcons();
                        }
                    })
                    .catch(() => {});
            }, 300);
        }

        window.SimassRealtime.subscribeAdminAudit({
            onConnectionChange(connected) {
                if (badge) badge.classList.toggle('hidden', !connected);
            },
            onAuditRecorded(e) {
                if (typeof window.showToast === 'function' && e?.action) {
                    window.showToast(`Audit baru: ${e.actor_name || 'Sistem'} — ${e.action} (${e.model_label || ''})`, 'info', 3500);
                }
                refreshAuditTable();
            },
        });
    });
</script>
@endpush
@endsection

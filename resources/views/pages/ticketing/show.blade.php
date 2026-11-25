@extends('layouts.app-layout')

@section('title', 'Detail Tiket #' . $ticket->ticket_number)
@section('subtitle', $ticket->title)

@section('content')
    <div class="space-y-6">

        <!-- Top Navigation & Flash Alerts -->
        <div class="flex items-center justify-between">
            <a href="{{ $isStaff ? route('ticket.tasks') : route('ticket.my-tickets') }}"
                class="inline-flex items-center gap-2 text-sm font-semibold text-gray-500 hover:text-gray-900 dark:hover:text-white transition">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                <span>{{ $isStaff ? 'Kembali ke Board' : 'Kembali ke Tiket Saya' }}</span>
            </a>
            <span class="text-xs text-gray-400">Diajukan: {{ $ticket->created_at->translatedFormat('d F Y, H:i') }}</span>
        </div>

        @if (session('success'))
            <div
                class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-900 text-emerald-700 dark:text-emerald-400 text-sm font-semibold flex items-center gap-2">
                <i data-lucide="check-circle" class="w-5 h-5 shrink-0"></i>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if ($errors->any())
            <div
                class="p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900 text-rose-700 dark:text-rose-400 text-xs font-semibold space-y-1">
                @foreach ($errors->all() as $error)
                    <p class="flex items-center gap-2"><i data-lucide="alert-triangle" class="w-4 h-4 shrink-0"></i>
                        {{ $error }}</p>
                @endforeach
            </div>
        @endif

        <!-- Supportive Status Banners -->
        @if ($ticket->status === 'redirected')
            <div
                class="p-5 rounded-3xl bg-gradient-to-r from-orange-50 to-amber-50 dark:from-orange-950/40 dark:to-amber-950/30 border border-orange-200 dark:border-orange-900/50 flex items-start gap-4">
                <div
                    class="w-10 h-10 rounded-2xl bg-orange-600 text-white flex items-center justify-center shrink-0 shadow-sm mt-0.5">
                    <i data-lucide="network" class="w-5 h-5"></i>
                </div>
                <div class="text-sm">
                    <h4 class="font-bold text-orange-900 dark:text-orange-200">Dialihkan ke Sistem Terpusat (Bandung)</h4>
                    <p class="text-orange-800/80 dark:text-orange-300/80 text-xs mt-1 leading-relaxed">
                        Kendala ini memerlukan wewenang/sinkronisasi teknis pada sistem terpusat Telkom University.
                        Tim PuTI Surabaya bertindak sebagai pendamping Anda dan sedang mengawal tiket ini hingga selesai.
                        @if ($ticket->central_ticket_ref)
                            <span
                                class="block mt-1.5 font-mono font-bold text-xs text-orange-900 dark:text-orange-100 bg-orange-100/70 dark:bg-orange-900/60 px-2.5 py-1 rounded-lg w-fit">
                                No. Rujukan Helpdesk Pusat: #{{ $ticket->central_ticket_ref }}
                            </span>
                        @endif
                    </p>
                </div>
            </div>
        @elseif($ticket->needsClarification())
            <div
                class="p-5 rounded-3xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-900/60 flex items-start gap-4">
                <div
                    class="w-10 h-10 rounded-2xl bg-amber-500 text-white flex items-center justify-center shrink-0 shadow-sm mt-0.5">
                    <i data-lucide="alert-circle" class="w-5 h-5"></i>
                </div>
                <div class="text-sm">
                    <h4 class="font-bold text-amber-900 dark:text-amber-200">Menunggu Informasi Tambahan dari Anda</h4>
                    <p class="text-amber-800/80 dark:text-amber-300/80 text-xs mt-1 leading-relaxed">
                        Teknisi atau asisten virtual kami membutuhkan detail tambahan (seperti nama ruangan atau gejala
                        spesifik) agar penanganan dapat dilanjutkan. Silakan berikan balasan Anda pada kolom pesan di bawah.
                    </p>
                </div>
            </div>
        @elseif($ticket->status === 'failed')
            <div
                class="p-5 rounded-3xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/60 flex items-start gap-4">
                <div
                    class="w-10 h-10 rounded-2xl bg-rose-600 text-white flex items-center justify-center shrink-0 shadow-sm mt-0.5">
                    <i data-lucide="alert-octagon" class="w-5 h-5"></i>
                </div>
                <div class="text-sm">
                    <h4 class="font-bold text-rose-900 dark:text-rose-200">Kendala Belum Berhasil Diselesaikan (Gagal
                        Ditangani)</h4>
                    <p class="text-rose-800/80 dark:text-rose-300/80 text-xs mt-1 leading-relaxed">
                        Penanganan telah dilakukan namun kendala belum sepenuhnya teratasi. Teknisi dapat membuka kembali
                        (reopen) tiket ini untuk penanganan lanjutan.
                        @if ($ticket->status_note)
                            <span
                                class="block mt-1.5 font-medium text-xs text-rose-900 dark:text-rose-100 bg-rose-100/70 dark:bg-rose-900/60 px-2.5 py-1 rounded-lg w-fit">
                                Catatan: {{ $ticket->status_note }}
                            </span>
                        @endif
                    </p>
                </div>
            </div>
        @elseif($ticket->status === 'canceled')
            <div
                class="p-5 rounded-3xl bg-gray-100 dark:bg-gray-800/80 border border-gray-200 dark:border-gray-700 flex items-start gap-4">
                <div
                    class="w-10 h-10 rounded-2xl bg-gray-600 text-white flex items-center justify-center shrink-0 shadow-sm mt-0.5">
                    <i data-lucide="ban" class="w-5 h-5"></i>
                </div>
                <div class="text-sm">
                    <h4 class="font-bold text-gray-900 dark:text-gray-200">Tiket Dibatalkan</h4>
                    <p class="text-gray-600 dark:text-gray-400 text-xs mt-1 leading-relaxed">
                        @if ($ticket->cancel_reason === 'transferred_unit' && $ticket->transferred_unit)
                            Tiket ini telah dialihkan penanganannya ke unit kampus terkait:
                            <strong>{{ $ticket->transferred_unit }}</strong>.
                        @else
                            Tiket ini telah dibatalkan atau diselesaikan secara mandiri oleh pelapor.
                        @endif
                        @if ($ticket->status_note)
                            <span
                                class="block mt-1 text-gray-700 dark:text-gray-300 italic">"{{ $ticket->status_note }}"</span>
                        @endif
                    </p>
                </div>
            </div>
        @endif

        <!-- Main 2-Column Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Left/Main Column: Conversation Thread (2 Cols) -->
            <div class="lg:col-span-2 space-y-6">

                <!-- Thread Container -->
                <div
                    class="bg-white dark:bg-gray-900 rounded-3xl border border-gray-200 dark:border-gray-800 p-6 shadow-sm space-y-6">
                    <div class="border-b border-gray-100 dark:border-gray-800 pb-4 flex items-center justify-between">
                        <h3 class="font-bold text-base text-gray-900 dark:text-white flex items-center gap-2">
                            <i data-lucide="messages-square" class="w-5 h-5 text-telkom-600 dark:text-telkom-400"></i>
                            <span>Percakapan & Histori Penanganan</span>
                        </h3>
                        <span class="text-xs text-gray-400">{{ $ticket->messages->count() }} aktivitas</span>
                    </div>

                    <!-- Messages List -->
                    <div class="space-y-4">
                        @foreach ($ticket->messages as $msg)
                            @if ($msg->sender_type === 'system')
                                <!-- System Log Message -->
                                <div
                                    class="my-3 px-4 py-2.5 rounded-2xl bg-gray-50 dark:bg-gray-850/60 border border-gray-200/60 dark:border-gray-800 text-xs text-gray-600 dark:text-gray-400 text-center font-medium">
                                    {!! nl2br(e($msg->message)) !!}
                                    <span
                                        class="block text-[10px] text-gray-400 mt-0.5">{{ $msg->created_at->format('d M Y, H:i') }}</span>
                                </div>
                            @elseif($msg->is_internal)
                                <!-- Internal Note (Staff Only) -->
                                @if ($isStaff)
                                    <div
                                        class="p-4 rounded-2xl bg-amber-50/90 dark:bg-amber-950/30 border border-amber-300 dark:border-amber-800 text-sm space-y-1.5 shadow-sm">
                                        <div
                                            class="flex items-center justify-between text-xs font-bold text-amber-800 dark:text-amber-300">
                                            <span class="flex items-center gap-1.5">
                                                <i data-lucide="lock" class="w-3.5 h-3.5"></i> Catatan Internal Khusus Staf
                                                (Oleh: {{ $msg->user?->name ?? 'Staf' }})
                                            </span>
                                            <span
                                                class="font-normal text-amber-600 dark:text-amber-400 text-[10px]">{{ $msg->created_at->diffForHumans() }}</span>
                                        </div>
                                        <p class="text-xs text-amber-900 dark:text-amber-200 leading-relaxed">
                                            {!! nl2br(e($msg->message)) !!}</p>
                                    </div>
                                @endif
                            @elseif($msg->sender_type === 'ai_luna')
                                <!-- Luna AI Message -->
                                <div class="flex items-start gap-3 max-w-[88%]">
                                    <div
                                        class="w-9 h-9 rounded-2xl bg-telkom-50 dark:bg-telkom-950/60 text-telkom-600 dark:text-telkom-400 flex items-center justify-center shrink-0 border border-telkom-200 dark:border-telkom-900 shadow-sm mt-0.5">
                                        <i data-lucide="bot" class="w-5 h-5"></i>
                                    </div>
                                    <div
                                        class="bg-gray-50 dark:bg-gray-800/80 p-4 rounded-3xl rounded-tl-sm border border-gray-200 dark:border-gray-700/60 text-sm text-gray-800 dark:text-gray-200 leading-relaxed shadow-sm">
                                        <div class="flex items-center gap-2 mb-1">
                                            <span class="font-bold text-xs text-telkom-600 dark:text-telkom-400">Luna
                                                AI</span>
                                            <span
                                                class="text-[9px] px-1.5 py-0.5 rounded bg-telkom-100 dark:bg-telkom-900 text-telkom-700 dark:text-telkom-300 font-semibold">Asisten
                                                Virtual</span>
                                            <span
                                                class="text-[10px] text-gray-400 ml-auto">{{ $msg->created_at->format('H:i') }}</span>
                                        </div>
                                        <div class="text-xs space-y-1">
                                            {!! nl2br(e($msg->message)) !!}
                                        </div>
                                    </div>
                                </div>
                            @elseif($msg->sender_type === 'staff')
                                <!-- Staff Technician Message -->
                                <div class="flex items-start gap-3 max-w-[88%]">
                                    <div
                                        class="w-9 h-9 rounded-2xl bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 flex items-center justify-center shrink-0 border border-purple-200 dark:border-purple-900 shadow-sm mt-0.5">
                                        <i data-lucide="user-check" class="w-5 h-5"></i>
                                    </div>
                                    <div
                                        class="bg-purple-50/50 dark:bg-purple-950/20 p-4 rounded-3xl rounded-tl-sm border border-purple-200 dark:border-purple-900 text-sm text-gray-800 dark:text-gray-200 leading-relaxed shadow-sm">
                                        <div class="flex items-center gap-2 mb-1">
                                            <span
                                                class="font-bold text-xs text-purple-700 dark:text-purple-300">{{ $msg->user?->name ?? 'Teknisi PuTI' }}</span>
                                            <span
                                                class="text-[9px] px-1.5 py-0.5 rounded bg-purple-100 dark:bg-purple-900 text-purple-700 dark:text-purple-300 font-semibold">Teknisi
                                                PuTI</span>
                                            <span
                                                class="text-[10px] text-gray-400 ml-auto">{{ $msg->created_at->format('H:i') }}</span>
                                        </div>
                                        <p class="text-xs text-gray-700 dark:text-gray-300 leading-relaxed">
                                            {!! nl2br(e($msg->message)) !!}</p>
                                        @if ($msg->attachment)
                                            <div class="mt-2 pt-2 border-t border-purple-100 dark:border-purple-900/60">
                                                <a href="{{ asset('storage/' . $msg->attachment) }}" target="_blank"
                                                    class="inline-flex items-center gap-1.5 text-xs text-purple-600 dark:text-purple-400 hover:underline">
                                                    <i data-lucide="paperclip" class="w-3.5 h-3.5"></i> Lihat Lampiran
                                                </a>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @else
                                <!-- User Message -->
                                <div class="flex items-start justify-end gap-3 max-w-[88%] ml-auto">
                                    <div
                                        class="bg-telkom-600 text-white p-4 rounded-3xl rounded-tr-sm text-sm leading-relaxed shadow-md">
                                        <div
                                            class="flex items-center justify-between gap-4 mb-1 text-telkom-100 text-[10px]">
                                            <span class="font-bold">{{ $msg->user?->name ?? 'Pelapor' }}</span>
                                            <span>{{ $msg->created_at->format('H:i') }}</span>
                                        </div>
                                        <p class="text-xs text-white leading-relaxed">{!! nl2br(e($msg->message)) !!}</p>
                                        @if ($msg->attachment)
                                            <div class="mt-2 pt-2 border-t border-telkom-500">
                                                <a href="{{ asset('storage/' . $msg->attachment) }}" target="_blank"
                                                    class="inline-flex items-center gap-1.5 text-xs text-telkom-100 hover:text-white underline">
                                                    <i data-lucide="paperclip" class="w-3.5 h-3.5"></i> Unduh Lampiran
                                                </a>
                                            </div>
                                        @endif
                                    </div>
                                    <div
                                        class="w-9 h-9 rounded-2xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-gray-600 dark:text-gray-300 shrink-0 mt-0.5">
                                        <i data-lucide="user" class="w-5 h-5"></i>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>

                    <!-- Reply Box Form -->
                    @if (!$ticket->isFinalStatus())
                        <div class="pt-6 border-t border-gray-100 dark:border-gray-800">
                            <form action="{{ route('ticket.reply', $ticket) }}" method="POST"
                                enctype="multipart/form-data" class="space-y-4">
                                @csrf

                                <div>
                                    <label for="message"
                                        class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1.5">
                                        Kirim Balasan / Tanggapan
                                    </label>
                                    <textarea name="message" id="message" rows="3" required placeholder="Ketik tanggapan Anda di sini..."
                                        class="w-full px-4 py-3 rounded-2xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm focus:outline-none focus:ring-2 focus:ring-telkom-500 transition leading-relaxed"></textarea>
                                </div>

                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                    <div class="flex items-center gap-3">
                                        <label
                                            class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-xs text-gray-600 dark:text-gray-300 hover:bg-gray-100 transition">
                                            <i data-lucide="paperclip" class="w-3.5 h-3.5"></i>
                                            <span>Tambah Lampiran</span>
                                            <input type="file" name="attachment" class="hidden"
                                                accept=".jpg,.jpeg,.png,.webp,.heic,.heif,.pdf">
                                        </label>

                                        @if ($isStaff)
                                            <label
                                                class="inline-flex items-center gap-1.5 text-xs text-amber-700 dark:text-amber-400 font-semibold cursor-pointer">
                                                <input type="checkbox" name="is_internal" value="1"
                                                    class="rounded text-amber-600 focus:ring-amber-500">
                                                <span>Catatan Internal (Privat)</span>
                                            </label>
                                        @endif
                                    </div>

                                    <button type="submit"
                                        class="inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-2xl bg-telkom-600 hover:bg-telkom-700 text-white text-xs font-semibold shadow-md transition">
                                        <i data-lucide="send" class="w-3.5 h-3.5"></i>
                                        <span>Kirim Pesan</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    @else
                        <div class="p-4 rounded-2xl bg-gray-100 dark:bg-gray-800 text-center text-xs text-gray-500">
                            Tiket ini telah {{ $ticket->status === 'finished' ? 'selesai ditangani' : 'dibatalkan' }}.
                            Tidak dapat menambahkan balasan baru.
                        </div>
                    @endif

                </div>
            </div>

            <!-- Right Column: Sidebar Metadata & Controls (1 Col) -->
            <div class="space-y-6">

                <!-- Ticket Metadata Card -->
                <div
                    class="bg-white dark:bg-gray-900 rounded-3xl border border-gray-200 dark:border-gray-800 p-6 shadow-sm space-y-4">
                    <h3
                        class="font-bold text-sm text-gray-900 dark:text-white border-b border-gray-100 dark:border-gray-800 pb-3">
                        Informasi Tiket</h3>

                    <div class="space-y-3 text-xs">
                        <!-- Status -->
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500">Status Saat Ini:</span>
                            @php
                                $badgeStyles = [
                                    'blue' =>
                                        'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/40 dark:text-blue-400 dark:border-blue-900',
                                    'amber' =>
                                        'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-400 dark:border-amber-900',
                                    'purple' =>
                                        'bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-950/40 dark:text-purple-400 dark:border-purple-900',
                                    'orange' =>
                                        'bg-orange-50 text-orange-700 border-orange-200 dark:bg-orange-950/40 dark:text-orange-400 dark:border-orange-900',
                                    'emerald' =>
                                        'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-400 dark:border-emerald-900',
                                    'rose' =>
                                        'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/40 dark:text-rose-400 dark:border-rose-900',
                                    'gray' =>
                                        'bg-gray-100 text-gray-700 border-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-700',
                                ];
                                $badgeStyle = $badgeStyles[$ticket->user_status_color] ?? $badgeStyles['gray'];
                            @endphp
                            <span
                                class="inline-flex items-center px-2.5 py-1 rounded-full font-bold border {{ $badgeStyle }}">
                                {{ $ticket->user_status_label }}
                            </span>
                        </div>

                        <!-- Layanan -->
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500">Layanan:</span>
                            <span
                                class="font-semibold text-gray-900 dark:text-white text-right">{{ $ticket->service_item }}</span>
                        </div>

                        <!-- Prioritas -->
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500">Urgensi:</span>
                            <span
                                class="font-semibold capitalize text-gray-800 dark:text-gray-200">{{ $ticket->priority }}</span>
                        </div>

                        <!-- Lokasi -->
                        @if ($ticket->location)
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500">Lokasi:</span>
                                <span
                                    class="font-semibold text-gray-900 dark:text-white text-right">{{ $ticket->location }}</span>
                            </div>
                        @endif

                        @if ($ticket->transferred_unit)
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500">Unit Tujuan:</span>
                                <span
                                    class="font-semibold text-gray-900 dark:text-white text-right">{{ $ticket->transferred_unit }}</span>
                            </div>
                        @endif

                        <!-- Aset Inventaris Tertaut -->
                        @php
                            $linkedAssets = $ticket->linkedInventoryItems();
                            $linkedAssetIds = $linkedAssets->pluck('id')->all();
                        @endphp
                        @if ($linkedAssets->isNotEmpty())
                            <div class="pt-3 border-t border-gray-100 dark:border-gray-800 space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-500">Aset Inventaris Tertaut ({{ $linkedAssets->count() }}):</span>
                                </div>
                                <div class="space-y-2">
                                    @foreach ($linkedAssets as $linkedAsset)
                                        <div
                                            class="p-2.5 rounded-xl bg-gray-50 dark:bg-gray-800/60 border border-gray-100 dark:border-gray-800 space-y-1">
                                            <div class="flex items-center justify-between gap-2">
                                                <a href="{{ route('inventory.show', $linkedAsset) }}"
                                                    class="font-mono font-bold text-purple-600 dark:text-purple-400 hover:underline">
                                                    {{ $linkedAsset->asset_code }}
                                                </a>
                                                <span
                                                    class="font-semibold text-gray-800 dark:text-gray-200">{{ $linkedAsset->condition_label }}</span>
                                            </div>
                                            <p class="font-semibold text-gray-900 dark:text-white leading-snug">
                                                {{ $linkedAsset->name }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <!-- Pelapor -->
                        <div class="pt-3 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between">
                            <span class="text-gray-500">Pelapor:</span>
                            <span class="font-semibold text-gray-900 dark:text-white">{{ $ticket->user->name }}</span>
                        </div>

                        <!-- Teknisi -->
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500">Teknisi PIC:</span>
                            <span class="font-semibold text-telkom-600 dark:text-telkom-400">
                                {{ $ticket->assignee?->name ?? 'Belum Ditugaskan' }}
                            </span>
                        </div>

                        <!-- SLA Target -->
                        @if ($ticket->sla_due_at)
                            <div class="pt-3 border-t border-gray-100 dark:border-gray-800">
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-500">Target SLA:</span>
                                    <span
                                        class="font-semibold {{ $ticket->sla_due_at->isPast() && !in_array($ticket->status, ['finished', 'canceled', 'failed']) ? 'text-red-500' : 'text-gray-700 dark:text-gray-300' }}">
                                        {{ $ticket->sla_due_at->format('d M, H:i') }}
                                    </span>
                                </div>
                                @if ($ticket->sla_paused_at)
                                    <p class="text-[10px] text-amber-600 mt-1 flex items-center gap-1">
                                        <i data-lucide="pause-circle" class="w-3 h-3"></i> Timer SLA sedang dijeda
                                    </p>
                                @endif
                            </div>
                        @endif
                    </div>

                    <!-- Reporter Self-Cancel Action (Only for Ticket Owner while status === 'open') -->
                    @if (auth()->id() === $ticket->user_id && $ticket->status === 'open')
                        <div class="pt-3 border-t border-gray-100 dark:border-gray-800">
                            <button type="button"
                                onclick="document.getElementById('modalSelfCancel').classList.remove('hidden')"
                                class="w-full py-2 rounded-xl border border-rose-200 dark:border-rose-900 text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 text-xs font-semibold transition flex items-center justify-center gap-1.5">
                                <i data-lucide="x-circle" class="w-3.5 h-3.5"></i>
                                <span>Batalkan Tiket Saya</span>
                            </button>
                        </div>
                    @endif
                </div>

                <!-- Technician Actions (Staff Only) -->
                @if ($isStaff)
                    @php
                        $nextStatuses = $ticket->allowedNextStatuses();
                        $defaultNext = $nextStatuses[0]->value ?? $ticket->status;
                    @endphp
                    <div
                        class="bg-white dark:bg-gray-900 rounded-3xl border border-gray-200 dark:border-gray-800 p-6 shadow-sm space-y-4">
                        <h3
                            class="font-bold text-sm text-purple-700 dark:text-purple-400 border-b border-gray-100 dark:border-gray-800 pb-3 flex items-center gap-1.5">
                            <i data-lucide="shield" class="w-4 h-4"></i>
                            <span>Kontrol Teknisi PuTI</span>
                        </h3>

                        @if (empty($nextStatuses))
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Tiket berstatus <strong>{{ $ticket->user_status_label }}</strong> bersifat final dan tidak
                                dapat diubah lagi.
                            </p>
                        @else
                            <!-- Update Status Form -->
                            <form action="{{ route('ticket.update-status', $ticket) }}" method="POST" class="space-y-3"
                                x-data="{ selectedStatus: '{{ old('status', $defaultNext) }}', cancelReason: '{{ old('cancel_reason', 'transferred_unit') }}' }">
                                @csrf
                                @method('PATCH')

                                <div>
                                    <label for="status"
                                        class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                                        Ubah Status Pengerjaan
                                    </label>
                                    <select name="status" id="status" x-model="selectedStatus"
                                        class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-xs font-medium">
                                        @foreach ($nextStatuses as $nextStatus)
                                            <option value="{{ $nextStatus->value }}">{{ $nextStatus->label() }}
                                                ({{ ucfirst(str_replace('_', ' ', $nextStatus->value)) }})</option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Redirected: Central Ticket Reference -->
                                <div x-show="selectedStatus === 'redirected'" x-cloak>
                                    <label for="central_ticket_ref"
                                        class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                                        No. Rujukan Helpdesk Pusat (Opsional)
                                    </label>
                                    <input type="text" name="central_ticket_ref" id="central_ticket_ref"
                                        value="{{ old('central_ticket_ref', $ticket->central_ticket_ref) }}"
                                        placeholder="PST-2026-xxx"
                                        class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-xs font-mono">
                                </div>

                                <!-- Canceled: Reason & Transferred Campus Unit -->
                                <div x-show="selectedStatus === 'canceled'" x-cloak class="space-y-3">
                                    <div>
                                        <label for="cancel_reason"
                                            class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                                            Alasan Pembatalan <span class="text-red-500">*</span>
                                        </label>
                                        <select name="cancel_reason" id="cancel_reason" x-model="cancelReason"
                                            class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-xs font-medium">
                                            <option value="transferred_unit">Dialihkan ke Unit Lain di Kampus</option>
                                            <option value="user_withdrawn">Dibatalkan / Diselesaikan Pelapor</option>
                                        </select>
                                    </div>

                                    <div x-show="cancelReason === 'transferred_unit'" x-cloak>
                                        <label for="transferred_unit"
                                            class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                                            Nama Unit Kampus Tujuan <span class="text-red-500">*</span>
                                        </label>
                                        <input type="text" name="transferred_unit" id="transferred_unit"
                                            value="{{ old('transferred_unit', $ticket->transferred_unit) }}"
                                            placeholder="Contoh: Logistik / Sarpras / BAA / Keuangan"
                                            class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-xs">
                                    </div>
                                </div>

                                <!-- Status Note (Required for Failed, Optional for others) -->
                                <div>
                                    <label for="status_note"
                                        class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                                        Catatan Status <span x-show="selectedStatus === 'failed'"
                                            class="text-red-500">*</span>
                                    </label>
                                    <textarea name="status_note" id="status_note" rows="2"
                                        placeholder="Tambahkan catatan penanganan atau alasan gagal..."
                                        class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-xs">{{ old('status_note') }}</textarea>
                                </div>

                                <button type="submit"
                                    class="w-full py-2 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-semibold text-xs shadow-sm transition">
                                    Simpan Perubahan Status
                                </button>
                            </form>

                            <!-- Manual Override Trigger -->
                            <div class="pt-3 border-t border-gray-100 dark:border-gray-800 space-y-2">
                                <button type="button"
                                    onclick="document.getElementById('modalOverride').classList.remove('hidden')"
                                    class="w-full py-2 rounded-xl border border-amber-300 dark:border-amber-800 text-amber-700 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-950/40 text-xs font-semibold transition flex items-center justify-center gap-1.5">
                                    <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
                                    <span>Manual Override Wewenang</span>
                                </button>
                            </div>
                        @endif

                        <!-- Link Inventory Asset Form -->
                        <div class="pt-3 border-t border-gray-100 dark:border-gray-800">
                            <form action="{{ route('ticket.link-inventory', $ticket) }}" method="POST"
                                class="space-y-3">
                                @csrf
                                @method('PATCH')
                                <div class="space-y-1.5">
                                    <label for="link_inventory_item_ids"
                                        class="block text-xs font-semibold text-gray-700 dark:text-gray-300">
                                        Sinkronisasi Aset Inventaris
                                    </label>
                                    <select name="inventory_item_ids[]" id="link_inventory_item_ids" multiple
                                        placeholder="Pilih satu atau lebih aset inventaris..."
                                        class="w-full px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-xs">
                                        @foreach ($inventoryItems ?? [] as $asset)
                                            <option value="{{ $asset->id }}"
                                                data-selection-label="{{ $asset->asset_code }}"
                                                title="{{ $asset->asset_code }} — {{ $asset->name }} ({{ $asset->condition_label }})"
                                                {{ in_array($asset->id, $linkedAssetIds ?? [], true) ? 'selected' : '' }}>
                                                {{ $asset->asset_code }} — {{ $asset->name }}
                                                ({{ $asset->condition_label }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <button type="submit"
                                    class="w-full py-2 rounded-xl border border-purple-200 dark:border-purple-800 text-purple-700 dark:text-purple-300 hover:bg-purple-50 dark:hover:bg-purple-950/40 text-xs font-semibold transition">
                                    Simpan Tautan Aset
                                </button>
                            </form>
                        </div>
                    </div>
                @endif

                <!-- SLA Audit Logs -->
                @if ($ticket->slaLogs->isNotEmpty())
                    <div
                        class="bg-white dark:bg-gray-900 rounded-3xl border border-gray-200 dark:border-gray-800 p-6 shadow-sm space-y-3">
                        <h3
                            class="font-bold text-xs uppercase tracking-wider text-gray-500 border-b border-gray-100 dark:border-gray-800 pb-2">
                            Riwayat Jeda SLA (Audit Log)
                        </h3>
                        <div class="space-y-2 text-xs">
                            @foreach ($ticket->slaLogs as $log)
                                <div
                                    class="p-2.5 rounded-xl bg-gray-50 dark:bg-gray-800/50 border border-gray-150 dark:border-gray-750">
                                    <p class="font-semibold text-gray-800 dark:text-gray-200">{{ $log->reason }}</p>
                                    <div class="text-[10px] text-gray-500 mt-1 flex items-center justify-between">
                                        <span>Mulai: {{ $log->paused_at->format('d/m H:i') }}</span>
                                        @if ($log->resumed_at)
                                            <span
                                                class="text-emerald-600 font-bold">+{{ round($log->duration_minutes / 60, 1) }}
                                                jam</span>
                                        @else
                                            <span class="text-amber-600 font-bold animate-pulse">Masih Berjalan</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

            </div>

        </div>

    </div>

    <!-- Modal Reporter Self-Cancel (Owner Only while Open) -->
    @if (auth()->id() === $ticket->user_id && $ticket->status === 'open')
        <div id="modalSelfCancel"
            class="hidden fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
            <div
                class="bg-white dark:bg-gray-900 w-full max-w-md rounded-3xl border border-gray-200 dark:border-gray-800 p-6 shadow-2xl space-y-4">
                <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-3">
                    <h3 class="font-bold text-base text-gray-900 dark:text-white flex items-center gap-2">
                        <i data-lucide="x-circle" class="w-4 h-4 text-rose-600"></i>
                        <span>Batalkan Tiket Bantuan</span>
                    </h3>
                    <button type="button" onclick="document.getElementById('modalSelfCancel').classList.add('hidden')"
                        class="text-gray-400 hover:text-gray-600">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <form action="{{ route('ticket.cancel', $ticket) }}" method="POST" class="space-y-4 text-xs">
                    @csrf
                    @method('PATCH')

                    <p class="text-gray-600 dark:text-gray-400 leading-relaxed">
                        Apakah kendala Anda sudah teratasi sendiri? Tiket yang dibatalkan bersifat final dan tidak akan
                        ditindaklanjuti oleh teknisi.
                    </p>

                    <div>
                        <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Alasan Pembatalan
                            (Opsional)</label>
                        <textarea name="status_note" rows="3"
                            placeholder="Contoh: Sudah berhasil login kembali setelah restart perangkat..."
                            class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-xs"></textarea>
                    </div>

                    <div class="pt-3 border-t border-gray-100 dark:border-gray-800 flex items-center justify-end gap-2">
                        <button type="button"
                            onclick="document.getElementById('modalSelfCancel').classList.add('hidden')"
                            class="px-4 py-2 rounded-xl border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 font-semibold hover:bg-gray-50">
                            Kembali
                        </button>
                        <button type="submit"
                            class="px-5 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-semibold shadow-sm">
                            Ya, Batalkan Tiket
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Modal Manual Override Scope (Staff Only) -->
    @if ($isStaff && !$ticket->isFinalStatus())
        <div id="modalOverride"
            class="hidden fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
            <div
                class="bg-white dark:bg-gray-900 w-full max-w-md rounded-3xl border border-gray-200 dark:border-gray-800 p-6 shadow-2xl space-y-4">
                <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-3">
                    <h3 class="font-bold text-base text-gray-900 dark:text-white flex items-center gap-2">
                        <i data-lucide="refresh-cw" class="w-4 h-4 text-amber-600"></i>
                        <span>Manual Override Wewenang</span>
                    </h3>
                    <button type="button" onclick="document.getElementById('modalOverride').classList.add('hidden')"
                        class="text-gray-400 hover:text-gray-600">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <form action="{{ route('ticket.override-scope', $ticket) }}" method="POST" class="space-y-4 text-xs">
                    @csrf
                    @method('PATCH')

                    <div>
                        <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Pilih Arah Pengalihan
                            Wewenang</label>
                        <select name="scope" required
                            class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-xs">
                            <option value="escalated_central"
                                {{ $ticket->scope !== 'escalated_central' ? 'selected' : '' }}>
                                Alihkan ke Koordinasi Sistem Terpusat (Bandung)
                            </option>
                            <option value="internal_surabaya"
                                {{ $ticket->scope === 'escalated_central' ? 'selected' : '' }}>
                                Tarik Kembali untuk Penanganan Lokal (Surabaya)
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">No. Rujukan Helpdesk Pusat
                            (Jika Ada)</label>
                        <input type="text" name="central_ticket_ref" value="{{ $ticket->central_ticket_ref }}"
                            placeholder="PST-xxxx"
                            class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-xs font-mono">
                    </div>

                    <div>
                        <label class="block font-bold text-gray-700 dark:text-gray-300 mb-1">Catatan Alasan Pengalihan
                            <span class="text-red-500">*</span></label>
                        <textarea name="override_notes" rows="3" required
                            placeholder="Jelaskan alasan pengalihan wewenang berdasarkan temuan teknis lapangan..."
                            class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-xs"></textarea>
                    </div>

                    <div class="pt-3 border-t border-gray-100 dark:border-gray-800 flex items-center justify-end gap-2">
                        <button type="button" onclick="document.getElementById('modalOverride').classList.add('hidden')"
                            class="px-4 py-2 rounded-xl border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 font-semibold hover:bg-gray-50">
                            Batal
                        </button>
                        <button type="submit"
                            class="px-5 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-semibold shadow-sm">
                            Simpan Override
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

@endsection

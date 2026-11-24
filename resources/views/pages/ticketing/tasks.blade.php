@extends('layouts.app-layout')

@section('title', 'Command Center — Tugas Teknisi PuTI')
@section('subtitle', 'Papan kendali antrean tiket bantuan dan pemantauan SLA operasional IT PuTI Surabaya')

@section('content')
    <div class="space-y-6">

        <!-- Header Bar with Audio Engine Controls -->
        <div
            class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 bg-white dark:bg-gray-900 p-6 rounded-3xl border border-gray-200 dark:border-gray-800 shadow-sm">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2 flex-wrap">
                    <i data-lucide="radio" class="w-5 h-5 text-telkom-600 animate-pulse"></i>
                    <span>Live Dispatch Board</span>
                    <span id="nocRealtimeBadge"
                        class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400">
                        <span id="nocRealtimeDot" class="w-2 h-2 rounded-full bg-amber-500"></span>
                        <span id="nocRealtimeLabel">Polling</span>
                    </span>
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    Pantau aliran tiket masuk secara berkala. Notifikasi suara otomatis berbunyi saat ada tiket baru.
                </p>
            </div>

            <!-- Audio Settings Controls -->
            <div class="flex items-center gap-3 w-full md:w-auto">
                <button type="button" id="btnToggleSound"
                    class="flex-1 md:flex-none inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-2xl border text-xs font-semibold transition">
                    <i data-lucide="volume-2" id="soundIcon" class="w-4 h-4"></i>
                    <span id="soundLabel">Audio: Aktif</span>
                </button>

                <div class="relative" id="testSoundDropdownContainer">
                    <button type="button" id="btnTestSoundMenu" onclick="toggleTestSoundMenu(event)"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2.5 rounded-2xl bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 text-xs font-semibold active:scale-[0.98] transition duration-150">
                        <i data-lucide="play" class="w-3.5 h-3.5"></i>
                        <span>Tes Suara</span>
                        <i data-lucide="chevron-down" class="w-3.5 h-3.5 opacity-70"></i>
                    </button>

                    <div id="testSoundMenu"
                        class="hidden opacity-0 scale-[0.97] transition duration-150 ease-out origin-top-right absolute right-0 mt-2 w-64 rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-xl z-50 p-1.5 space-y-0.5 text-xs">
                        <div class="px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-gray-400">
                            Pilih Kasus Uji Suara
                        </div>
                        <button type="button" onclick="testAudio('ticket')"
                            class="w-full flex items-center gap-2.5 px-3 py-2 rounded-xl text-left text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                            <span class="w-2 h-2 rounded-full bg-blue-500 shrink-0"></span>
                            <span class="font-semibold">Tiket Baru Masuk</span>
                        </button>
                        <button type="button" onclick="testAudio('urgent')"
                            class="w-full flex items-center gap-2.5 px-3 py-2 rounded-xl text-left text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                            <span class="w-2 h-2 rounded-full bg-orange-500 shrink-0"></span>
                            <span class="font-semibold">Tiket Mendesak (Urgent)</span>
                        </button>
                        <button type="button" onclick="testAudio('status')"
                            class="w-full flex items-center gap-2.5 px-3 py-2 rounded-xl text-left text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                            <span class="w-2 h-2 rounded-full bg-purple-500 shrink-0"></span>
                            <span class="font-semibold">Tiket Dikerjakan / Update</span>
                        </button>
                        <div class="my-1 border-t border-gray-100 dark:border-gray-800"></div>
                        <button type="button" onclick="testAudio('outage')"
                            class="w-full flex items-center gap-2.5 px-3 py-2 rounded-xl text-left text-rose-700 dark:text-rose-300 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition">
                            <span class="w-2 h-2 rounded-full bg-rose-500 shrink-0"></span>
                            <span class="font-semibold">NING: Outage Kritis</span>
                        </button>
                        <button type="button" onclick="testAudio('latency')"
                            class="w-full flex items-center gap-2.5 px-3 py-2 rounded-xl text-left text-amber-700 dark:text-amber-300 hover:bg-amber-50 dark:hover:bg-amber-950/40 transition">
                            <span class="w-2 h-2 rounded-full bg-amber-500 shrink-0"></span>
                            <span class="font-semibold">NING: Latensi Tinggi</span>
                        </button>
                        <button type="button" onclick="testAudio('restored')"
                            class="w-full flex items-center gap-2.5 px-3 py-2 rounded-xl text-left text-emerald-700 dark:text-emerald-300 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 transition">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 shrink-0"></span>
                            <span class="font-semibold">NING: Perangkat Pulih</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Live Network Alert Banner (NING Webhook) -->
        <div id="nocNetworkAlertBanner"
            class="hidden rounded-2xl border p-4 shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 transition duration-150">
            <div class="flex items-start gap-3">
                <div id="nocNetworkAlertIconWrap" class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0">
                    <i data-lucide="wifi-off" class="w-5 h-5"></i>
                </div>
                <div>
                    <p id="nocNetworkAlertTitle" class="text-sm font-bold"></p>
                    <p id="nocNetworkAlertMessage" class="text-xs mt-0.5 opacity-90"></p>
                </div>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <a id="nocNetworkAlertLink" href="#"
                    class="hidden px-3 py-1.5 rounded-xl bg-white/90 dark:bg-gray-900/90 text-xs font-bold shadow-sm hover:opacity-90 transition">
                    Buka Tiket
                </a>
                <button type="button" onclick="document.getElementById('nocNetworkAlertBanner').classList.add('hidden')"
                    class="p-1.5 rounded-lg opacity-70 hover:opacity-100 transition" aria-label="Tutup alert">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>
        </div>

        <!-- Filter Tabs Bar -->
        <div data-noc-tabs class="flex items-center gap-2 overflow-x-auto pb-2 text-xs font-semibold">
            @php
                $tabList = [
                    'all' => ['label' => 'Semua Antrean Aktif', 'count' => $counts['all']],
                    'unassigned' => ['label' => 'Belum Ditugaskan', 'count' => $counts['unassigned']],
                    'open' => ['label' => 'Diterima', 'count' => $counts['open']],
                    'in_progress' => ['label' => 'Sedang Dikerjakan', 'count' => $counts['in_progress']],
                    'redirected' => ['label' => 'Dialihkan ke Pusat', 'count' => $counts['redirected']],
                    'failed' => ['label' => 'Gagal Ditangani', 'count' => $counts['failed']],
                    'finished' => ['label' => 'Selesai', 'count' => $counts['finished']],
                    'canceled' => ['label' => 'Dibatalkan', 'count' => $counts['canceled']],
                ];
            @endphp

            @foreach ($tabList as $key => $meta)
                <a href="{{ route('ticket.tasks', ['tab' => $key]) }}"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-2xl whitespace-nowrap transition {{ $tab === $key ? 'bg-telkom-600 text-white shadow-sm' : 'bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-800 hover:bg-gray-50' }}">
                    <span>{{ $meta['label'] }}</span>
                    <span
                        class="px-2 py-0.5 rounded-full text-[10px] {{ $tab === $key ? 'bg-white/20 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400' }}">
                        {{ $meta['count'] }}
                    </span>
                </a>
            @endforeach
        </div>

        <!-- Ticket Grid List -->
        @if ($tickets->isEmpty())
            <div data-noc-list
                class="bg-white dark:bg-gray-900 rounded-3xl border border-gray-200 dark:border-gray-800 p-12 text-center shadow-sm">
                <div
                    class="w-14 h-14 rounded-3xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-gray-400 mx-auto mb-3">
                    <i data-lucide="check-circle" class="w-7 h-7 text-emerald-500"></i>
                </div>
                <h3 class="text-base font-bold text-gray-900 dark:text-white">Tidak ada antrean pada kategori ini</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Semua tiket pada filter terpilih telah selesai atau
                    belum ada tiket baru.</p>
            </div>
        @else
            <div data-noc-list class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                @foreach ($tickets as $ticket)
                    <div
                        class="bg-white dark:bg-gray-900 rounded-3xl border border-gray-200 dark:border-gray-800 p-5 shadow-sm hover:border-telkom-500 dark:hover:border-telkom-500 transition flex flex-col justify-between space-y-4">

                        <div>
                            <!-- Header Card -->
                            <div class="flex items-center justify-between gap-2 mb-2">
                                <span class="font-mono text-xs font-bold text-telkom-600 dark:text-telkom-400">
                                    {{ $ticket->ticket_number }}
                                </span>
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
                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold border {{ $badgeStyle }}">
                                    {{ $ticket->user_status_label }}
                                </span>
                            </div>

                            <!-- Title & Service -->
                            <h3 class="font-bold text-sm text-gray-900 dark:text-white line-clamp-2 leading-snug">
                                {{ $ticket->title }}
                            </h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 flex items-center gap-1.5">
                                <i data-lucide="tag" class="w-3.5 h-3.5 text-gray-400"></i>
                                <span>{{ $ticket->service_item }}</span>
                            </p>

                            @if ($ticket->location)
                                <p
                                    class="text-xs text-gray-600 dark:text-gray-300 mt-1 flex items-center gap-1.5 font-medium">
                                    <i data-lucide="map-pin" class="w-3.5 h-3.5 text-telkom-500"></i>
                                    <span>{{ $ticket->location }}</span>
                                </p>
                            @endif
                        </div>

                        <!-- Footer Card Details -->
                        <div class="pt-4 border-t border-gray-100 dark:border-gray-800 text-xs space-y-2.5">
                            <div class="flex items-center justify-between text-gray-500">
                                <span>Pelapor: <strong>{{ $ticket->user->name }}</strong></span>
                                <span class="text-[10px]">{{ $ticket->created_at->diffForHumans() }}</span>
                            </div>

                            <div class="flex items-center justify-between text-gray-500">
                                <span>Teknisi PIC:</span>
                                <span class="font-semibold text-gray-900 dark:text-white">
                                    {{ $ticket->assignee?->name ?? 'Belum Ditugaskan' }}
                                </span>
                            </div>

                            <div class="pt-1 flex items-center justify-between gap-2">
                                <a href="{{ route('ticket.show', $ticket) }}"
                                    class="w-full inline-flex items-center justify-center gap-1.5 py-2 rounded-xl bg-gray-100 hover:bg-telkom-600 hover:text-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 text-xs font-semibold transition">
                                    <span>Buka Detail & Aksi</span>
                                    <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                                </a>
                            </div>
                        </div>

                    </div>
                @endforeach
            </div>

            <!-- Pagination -->
            <div class="p-4 bg-white dark:bg-gray-900 rounded-3xl border border-gray-200 dark:border-gray-800 shadow-sm">
                {{ $tickets->links() }}
            </div>
        @endif

    </div>

    @push('scripts')
        <script>
            // -------------------------------------------------------------
            // Live Audio Engine (Web Audio API Chime + SpeechSynthesis)
            // -------------------------------------------------------------
            let isSoundActive = localStorage.getItem('noc_sound_active') !== 'false';
            let lastSeenTicketId = {{ $tickets->first()?->id ?? 0 }};

            const btnToggle = document.getElementById('btnToggleSound');
            const soundIcon = document.getElementById('soundIcon');
            const soundLabel = document.getElementById('soundLabel');

            function updateSoundUI() {
                if (isSoundActive) {
                    btnToggle.className =
                        'flex-1 md:flex-none inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-2xl border border-emerald-300 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 text-xs font-semibold shadow-sm transition';
                    soundLabel.textContent = 'Audio Alert: Aktif';
                } else {
                    btnToggle.className =
                        'flex-1 md:flex-none inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-2xl border border-gray-300 bg-gray-100 dark:bg-gray-800 text-gray-500 text-xs font-semibold transition';
                    soundLabel.textContent = 'Audio Alert: Hening';
                }
            }

            btnToggle.addEventListener('click', () => {
                isSoundActive = !isSoundActive;
                localStorage.setItem('noc_sound_active', isSoundActive);
                updateSoundUI();
            });
            updateSoundUI();

            // Synthetic Melodic Chimes per Alert Case using Browser Web Audio API (Zero external file dependency!)
            function playChime(type = 'ticket') {
                try {
                    const AudioCtx = window.AudioContext || window.webkitAudioContext;
                    if (!AudioCtx) return;
                    const ctx = new AudioCtx();

                    const playTone = (freq, start, duration, wave = 'sine', volume = 0.15) => {
                        const osc = ctx.createOscillator();
                        const gain = ctx.createGain();
                        osc.type = wave;
                        osc.frequency.setValueAtTime(freq, ctx.currentTime + start);
                        gain.gain.setValueAtTime(volume, ctx.currentTime + start);
                        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + start + duration);
                        osc.connect(gain);
                        gain.connect(ctx.destination);
                        osc.start(ctx.currentTime + start);
                        osc.stop(ctx.currentTime + start + duration);
                    };

                    if (type === 'urgent') {
                        playTone(880.00, 0, 0.18, 'triangle', 0.18);
                        playTone(1174.66, 0.16, 0.18, 'triangle', 0.18);
                        playTone(1760.00, 0.32, 0.40, 'triangle', 0.20);
                    } else if (type === 'outage') {
                        playTone(880.00, 0, 0.18, 'sawtooth', 0.12);
                        playTone(659.25, 0.18, 0.18, 'sawtooth', 0.12);
                        playTone(880.00, 0.38, 0.18, 'sawtooth', 0.12);
                        playTone(659.25, 0.56, 0.28, 'sawtooth', 0.12);
                    } else if (type === 'latency') {
                        playTone(587.33, 0, 0.20, 'triangle', 0.16);
                        playTone(587.33, 0.24, 0.28, 'triangle', 0.16);
                    } else if (type === 'restored') {
                        playTone(523.25, 0, 0.18, 'sine', 0.14);
                        playTone(659.25, 0.14, 0.18, 'sine', 0.14);
                        playTone(783.99, 0.28, 0.18, 'sine', 0.14);
                        playTone(1046.50, 0.42, 0.40, 'sine', 0.16);
                    } else if (type === 'status') {
                        playTone(783.99, 0, 0.18, 'sine', 0.12);
                        playTone(1046.50, 0.16, 0.30, 'sine', 0.12);
                    } else {
                        // Default 2-tone melodic chime: C6 (1046Hz) then E6 (1318Hz)
                        playTone(1046.50, 0, 0.35, 'sine', 0.15);
                        playTone(1318.51, 0.20, 0.50, 'sine', 0.15);
                    }
                } catch (e) {
                    console.warn('AudioContext not allowed or not supported', e);
                }
            }

            // Text-To-Speech Callout in Indonesian
            function speakAnnouncement(text) {
                if ('speechSynthesis' in window) {
                    window.speechSynthesis.cancel(); // stop any active speech
                    const utterance = new SpeechSynthesisUtterance(text);
                    utterance.lang = 'id-ID';
                    utterance.rate = 1.05;
                    utterance.pitch = 1.0;
                    window.speechSynthesis.speak(utterance);
                }
            }

            function hideTestSoundMenu() {
                const menu = document.getElementById('testSoundMenu');
                if (!menu || menu.classList.contains('hidden')) return;
                menu.classList.add('opacity-0', 'scale-[0.97]');
                setTimeout(() => menu.classList.add('hidden'), 150);
            }

            window.toggleTestSoundMenu = function(event) {
                event.stopPropagation();
                const menu = document.getElementById('testSoundMenu');
                if (!menu) return;
                if (menu.classList.contains('hidden')) {
                    menu.classList.remove('hidden');
                    void menu.offsetWidth;
                    menu.classList.remove('opacity-0', 'scale-[0.97]');
                } else {
                    hideTestSoundMenu();
                }
            };

            document.addEventListener('click', (event) => {
                const container = document.getElementById('testSoundDropdownContainer');
                if (container && !container.contains(event.target)) {
                    hideTestSoundMenu();
                }
            });

            // Test Audio Handler per Case
            window.testAudio = function(caseType = 'ticket') {
                hideTestSoundMenu();
                const scripts = {
                    ticket: {
                        delay: 450,
                        text: 'Tiket baru masuk. Layanan Wi-Fi Kampus. Koneksi terputus di Gedung A lantai 2.',
                        toast: 'Tes suara: Tiket Baru Masuk',
                        toastType: 'info',
                    },
                    urgent: {
                        delay: 550,
                        text: 'Perhatian! Tiket prioritas mendesak masuk. Layanan Jaringan Kabel LAN. Switch utama lantai 3 mati total.',
                        toast: 'Tes suara: Tiket Mendesak (Urgent)',
                        toastType: 'warning',
                    },
                    status: {
                        delay: 400,
                        text: 'Pembaruan tiket. Tiket sedang dikerjakan oleh teknisi PuTI Surabaya.',
                        toast: 'Tes suara: Tiket Dikerjakan / Update',
                        toastType: 'info',
                    },
                    outage: {
                        delay: 750,
                        text: 'Peringatan kritis jaringan NING. Gangguan jaringan terdeteksi pada perangkat Switch Core Gedung A.',
                        toast: 'Tes suara: NING Outage Kritis',
                        toastType: 'error',
                    },
                    latency: {
                        delay: 500,
                        text: 'Peringatan jaringan NING. Latensi tinggi terdeteksi pada Access Point Perpustakaan lantai 2.',
                        toast: 'Tes suara: NING Latensi Tinggi',
                        toastType: 'warning',
                    },
                    restored: {
                        delay: 650,
                        text: 'Informasi jaringan NING. Perangkat Switch Core Gedung A telah kembali pulih dan normal.',
                        toast: 'Tes suara: NING Perangkat Pulih',
                        toastType: 'success',
                    },
                };

                const selected = scripts[caseType] || scripts.ticket;
                playChime(caseType);
                if (typeof window.showToast === 'function') {
                    window.showToast(selected.toast, selected.toastType, 3000);
                }
                setTimeout(() => {
                    speakAnnouncement(selected.text);
                }, selected.delay);
            };

            let isRealtimeConnected = false;
            let boardRefreshTimer = null;

            function scheduleBoardRefresh() {
                clearTimeout(boardRefreshTimer);
                boardRefreshTimer = setTimeout(() => {
                    fetch(window.location.href, {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        })
                        .then(res => res.ok ? res.text() : null)
                        .then(html => {
                            if (!html) return;
                            const doc = new DOMParser().parseFromString(html, 'text/html');
                            const nextTabs = doc.querySelector('[data-noc-tabs]');
                            const currTabs = document.querySelector('[data-noc-tabs]');
                            if (nextTabs && currTabs) {
                                currTabs.innerHTML = nextTabs.innerHTML;
                            }

                            const nextList = doc.querySelector('[data-noc-list]');
                            const currList = document.querySelector('[data-noc-list]');
                            if (nextList && currList) {
                                currList.replaceWith(nextList);
                            }

                            if (window.createIcons) window.createIcons();
                        })
                        .catch(() => {});
                }, 250);
            }

            function updateRealtimeBadge(connected) {
                isRealtimeConnected = Boolean(connected);
                const badge = document.getElementById('nocRealtimeBadge');
                const dot = document.getElementById('nocRealtimeDot');
                const label = document.getElementById('nocRealtimeLabel');
                if (!badge || !dot || !label) return;

                if (connected) {
                    badge.className =
                        'inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-900';
                    dot.className = 'w-2 h-2 rounded-full bg-emerald-500 animate-pulse';
                    label.textContent = 'Live';
                } else {
                    badge.className =
                        'inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400';
                    dot.className = 'w-2 h-2 rounded-full bg-amber-500';
                    label.textContent = 'Polling';
                }
            }

            document.addEventListener('DOMContentLoaded', () => {
                if (!window.SimassRealtime) return;

                window.SimassRealtime.subscribeNoc({
                    onConnectionChange: updateRealtimeBadge,
                    onTicketCreated(ticket) {
                        if (ticket?.id && ticket.id > lastSeenTicketId) {
                            lastSeenTicketId = ticket.id;
                        }

                        if (isSoundActive) {
                            const chimeType = ticket?.priority === 'urgent' ? 'urgent' : 'ticket';
                            playChime(chimeType);
                            setTimeout(() => {
                                const cat = ticket?.service_item || ticket?.category || 'Layanan PuTI';
                                const title = ticket?.title || '';
                                const prefix = chimeType === 'urgent'
                                    ? 'Perhatian! Tiket prioritas mendesak masuk.'
                                    : 'Tiket baru masuk.';
                                speakAnnouncement(`${prefix} Layanan ${cat}. ${title}`);
                            }, chimeType === 'urgent' ? 550 : 450);
                        }

                        if (typeof window.showToast === 'function') {
                            window.showToast(
                                `Tiket baru #${ticket?.ticket_number || ticket?.id}: ${ticket?.title || ''}`,
                                'info', 4500);
                        }

                        scheduleBoardRefresh();
                    },
                    onTicketUpdated() {
                        scheduleBoardRefresh();
                    },
                    onNetworkAlert(alert) {
                        if (!alert) return;
                        const banner = document.getElementById('nocNetworkAlertBanner');
                        const iconWrap = document.getElementById('nocNetworkAlertIconWrap');
                        const titleEl = document.getElementById('nocNetworkAlertTitle');
                        const msgEl = document.getElementById('nocNetworkAlertMessage');
                        const linkEl = document.getElementById('nocNetworkAlertLink');

                        const styles = {
                            critical: {
                                banner: 'rounded-2xl border border-rose-300 dark:border-rose-800 bg-rose-50 dark:bg-rose-950/50 text-rose-900 dark:text-rose-100 p-4 shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 transition duration-150',
                                icon: 'w-9 h-9 rounded-xl bg-rose-100 dark:bg-rose-900/60 text-rose-600 dark:text-rose-300 flex items-center justify-center shrink-0',
                                toast: 'error',
                            },
                            warning: {
                                banner: 'rounded-2xl border border-amber-300 dark:border-amber-800 bg-amber-50 dark:bg-amber-950/50 text-amber-900 dark:text-amber-100 p-4 shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 transition duration-150',
                                icon: 'w-9 h-9 rounded-xl bg-amber-100 dark:bg-amber-900/60 text-amber-600 dark:text-amber-300 flex items-center justify-center shrink-0',
                                toast: 'warning',
                            },
                            resolved: {
                                banner: 'rounded-2xl border border-emerald-300 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-950/50 text-emerald-900 dark:text-emerald-100 p-4 shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 transition duration-150',
                                icon: 'w-9 h-9 rounded-xl bg-emerald-100 dark:bg-emerald-900/60 text-emerald-600 dark:text-emerald-300 flex items-center justify-center shrink-0',
                                toast: 'success',
                            },
                        };

                        const theme = styles[alert.severity] || styles.warning;
                        if (banner && iconWrap && titleEl && msgEl) {
                            banner.className = theme.banner;
                            iconWrap.className = theme.icon;
                            titleEl.textContent =
                                `${alert.label || alert.event_type} — ${alert.device_name || 'Perangkat Jaringan'}`;
                            msgEl.textContent = alert.message || '';

                            if (linkEl) {
                                const safeUrl = typeof alert.ticket_url === 'string' && (alert.ticket_url
                                    .startsWith('/') || alert.ticket_url.startsWith('http://') || alert
                                    .ticket_url.startsWith('https://'));
                                if (safeUrl) {
                                    linkEl.href = alert.ticket_url;
                                    linkEl.textContent = alert.ticket_number ? `Buka ${alert.ticket_number}` :
                                        'Buka Tiket';
                                    linkEl.classList.remove('hidden');
                                } else {
                                    linkEl.classList.add('hidden');
                                }
                            }
                            if (window.createIcons) window.createIcons();
                        }

                        if (isSoundActive) {
                            const alertChimeMap = { critical: 'outage', warning: 'latency', resolved: 'restored' };
                            const alertChime = alertChimeMap[alert.severity] || 'latency';
                            playChime(alertChime);
                            setTimeout(() => {
                                speakAnnouncement(
                                    `Peringatan jaringan NING. ${alert.label || ''}. ${alert.device_name || ''}.`
                                );
                            }, alertChime === 'outage' ? 750 : 550);
                        }

                        if (typeof window.showToast === 'function') {
                            window.showToast(`${alert.label || alert.event_type}: ${alert.device_name || ''}`,
                                theme.toast, 5000);
                        }

                        scheduleBoardRefresh();
                    },
                });
            });

            // Fallback Polling for New Tickets (Every 15 Seconds when WebSocket disconnected)
            setInterval(() => {
                if (isRealtimeConnected) return;
                fetch('{{ route('ticket.tasks-poll') }}')
                    .then(res => res.json())
                    .then(data => {
                        if (data.latest_id && data.latest_id > lastSeenTicketId) {
                            lastSeenTicketId = data.latest_id;

                            if (isSoundActive) {
                                playChime();
                                setTimeout(() => {
                                    const callout =
                                        `Tiket baru masuk. Layanan ${data.latest_cat}. ${data.latest_title}`;
                                    speakAnnouncement(callout);
                                }, 450);
                            }

                            // Visual toast or indicator: reload gently if user is on 'all' or 'unassigned' tab
                            const currentTab = '{{ $tab }}';
                            if (currentTab === 'all' || currentTab === 'unassigned') {
                                setTimeout(() => window.location.reload(), 2500);
                            }
                        }
                    })
                    .catch(() => {});
            }, 15000);
        </script>
    @endpush
@endsection

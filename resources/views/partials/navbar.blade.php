<header
    class="sticky top-0 z-30 bg-white/80 dark:bg-gray-900/80 backdrop-blur-xl border-b border-gray-200 dark:border-gray-800">
    <div class="flex items-center justify-between px-4 lg:px-8 h-16">
        <!-- Left -->
        <div class="flex items-center gap-3">
            <!-- Mobile logo -->
            <a href="{{ route('portal') }}" class="lg:hidden flex items-center gap-2 hover:opacity-80 transition-opacity">
                <div class="w-9 h-9 rounded-lg bg-white p-1 flex items-center justify-center shrink-0 shadow-sm">
                    <img src="{{ asset('logo-puti.webp') }}" class="w-full h-full object-contain" alt="PuTI Logo">
                </div>
                <div>
                    <div class="text-sm font-bold">SIMASS</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Kampus Surabaya</div>
                </div>
            </a>
            <!-- Page title (desktop) -->
            <div class="hidden lg:block">
                <h1 id="pageTitle" class="text-xl font-bold">@yield('title', 'Dashboard')</h1>
            </div>
        </div>

        <!-- Right -->
        <div class="flex items-center gap-2">
            <!-- Language Selector Dropdown -->
            <div class="relative" id="langDropdownContainer">
                <button type="button" onclick="toggleLangDropdown(event)"
                    class="p-2.5 rounded-xl bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 transition flex items-center gap-2 text-gray-700 dark:text-gray-300"
                    title="Ubah Bahasa">
                    <img id="currentLangFlag" src="https://flagcdn.com/w20/id.png"
                        srcset="https://flagcdn.com/w40/id.png 2x" class="w-5 h-3.5 object-cover rounded-sm shadow-sm"
                        alt="ID">
                    <span id="currentLangLabel" class="text-xs font-bold hidden sm:inline">ID</span>
                    <i data-lucide="chevron-down" class="w-3 h-3 text-gray-400"></i>
                </button>

                <!-- Dropdown Menu -->
                <div id="langDropdownMenu"
                    class="absolute right-0 mt-2 w-32 bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-2xl shadow-xl py-1.5 hidden opacity-0 scale-95 transition-all duration-200 z-50">
                    <button
                        onclick="changeLang('id', 'https://flagcdn.com/w20/id.png', 'https://flagcdn.com/w40/id.png 2x', 'ID')"
                        class="w-full flex items-center gap-3 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition font-semibold text-left">
                        <img src="https://flagcdn.com/w20/id.png" class="w-5 h-3.5 object-cover rounded-sm shadow-sm"
                            alt="ID">
                        <span>ID</span>
                    </button>
                    <button
                        onclick="changeLang('en', 'https://flagcdn.com/w20/gb.png', 'https://flagcdn.com/w40/gb.png 2x', 'EN')"
                        class="w-full flex items-center gap-3 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition font-semibold text-left">
                        <img src="https://flagcdn.com/w20/gb.png" class="w-5 h-3.5 object-cover rounded-sm shadow-sm"
                            alt="EN">
                        <span>EN</span>
                    </button>
                </div>
            </div>

            <!-- Dark mode -->
            <button type="button" onclick="toggleDark()"
                class="p-2.5 rounded-xl bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 transition flex items-center justify-center">
                <i data-lucide="sun" class="w-5 h-5 theme-toggle-sun text-amber-500"></i>
                <i data-lucide="moon" class="w-5 h-5 theme-toggle-moon text-indigo-400"></i>
            </button>

            <!-- Notif -->
            @auth
                <div class="relative" id="notifDropdownContainer">
                    <button type="button" id="notifBellBtn" onclick="toggleNotifDropdown(event)" aria-label="Notifikasi"
                        aria-expanded="false"
                        class="relative p-2.5 rounded-xl bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 active:scale-[0.98] transition duration-150 flex items-center justify-center">
                        <i data-lucide="bell" class="w-5 h-5 text-gray-700 dark:text-gray-300"></i>
                        <span id="notifUnreadDot"
                            class="hidden absolute top-2 right-2 w-2 h-2 bg-telkom-600 rounded-full ring-2 ring-white dark:ring-gray-900"></span>
                    </button>

                    <div id="notifDropdownMenu"
                        class="hidden opacity-0 scale-[0.97] transition duration-150 ease-out origin-top-right absolute right-0 mt-2 w-80 max-w-[calc(100vw-2rem)] rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-xl z-50 overflow-hidden">
                        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-gray-800">
                            <span class="text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-200">Notifikasi Live</span>
                            <button type="button" onclick="clearNotifications()"
                                class="text-[11px] font-semibold text-gray-400 hover:text-telkom-600 dark:hover:text-telkom-400 transition">
                                Bersihkan
                            </button>
                        </div>
                        <div id="notifList" class="max-h-72 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-800">
                            <div id="notifEmptyState" class="px-4 py-6 text-center text-xs text-gray-400">
                                Belum ada notifikasi baru.
                            </div>
                        </div>
                    </div>
                </div>
            @endauth

            <!-- Avatar (mobile) -->
            <a href="{{ route('profile') }}" class="lg:hidden">
                @php
                    $userName = auth()->user()->name ?? 'User';
                    $words = explode(' ', $userName);
                    $initials = strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : ''));
                @endphp
                <div
                    class="w-9 h-9 rounded-full ring-2 ring-telkom-100 dark:ring-telkom-900 flex items-center justify-center bg-gray-100 dark:bg-gray-800 text-telkom-600 dark:text-telkom-400 font-bold text-sm">
                    {{ $initials }}
                </div>
            </a>
        </div>
    </div>
</header>

@push('scripts')
    <script>
        function toggleLangDropdown(event) {
            event.stopPropagation();
            const dropdown = document.getElementById('langDropdownMenu');

            if (dropdown.classList.contains('hidden')) {
                dropdown.classList.remove('hidden');
                void dropdown.offsetWidth;
                dropdown.classList.remove('opacity-0', 'scale-95');
            } else {
                hideLangDropdown();
            }
        }

        function hideLangDropdown() {
            const dropdown = document.getElementById('langDropdownMenu');
            dropdown.classList.add('opacity-0', 'scale-95');
            setTimeout(() => {
                dropdown.classList.add('hidden');
            }, 200);
        }

        function changeLang(lang, flagSrc, flagSrcset, label) {
            const flagImg = document.getElementById('currentLangFlag');
            flagImg.src = flagSrc;
            flagImg.srcset = flagSrcset;
            document.getElementById('currentLangLabel').innerText = label;
            hideLangDropdown();
        }

        function toggleNotifDropdown(event) {
            event.stopPropagation();
            const dropdown = document.getElementById('notifDropdownMenu');
            const btn = document.getElementById('notifBellBtn');
            const dot = document.getElementById('notifUnreadDot');
            if (!dropdown) return;

            if (dropdown.classList.contains('hidden')) {
                dropdown.classList.remove('hidden');
                void dropdown.offsetWidth;
                dropdown.classList.remove('opacity-0', 'scale-[0.97]');
                if (btn) btn.setAttribute('aria-expanded', 'true');
                if (dot) dot.classList.add('hidden');
            } else {
                hideNotifDropdown();
            }
        }

        function hideNotifDropdown() {
            const dropdown = document.getElementById('notifDropdownMenu');
            const btn = document.getElementById('notifBellBtn');
            if (!dropdown || dropdown.classList.contains('hidden')) return;
            dropdown.classList.add('opacity-0', 'scale-[0.97]');
            if (btn) btn.setAttribute('aria-expanded', 'false');
            setTimeout(() => {
                dropdown.classList.add('hidden');
            }, 150);
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(event) {
            const langContainer = document.getElementById('langDropdownContainer');
            const langDropdown = document.getElementById('langDropdownMenu');
            if (langContainer && langDropdown && !langContainer.contains(event.target) && !langDropdown.classList.contains('hidden')) {
                hideLangDropdown();
            }

            const notifContainer = document.getElementById('notifDropdownContainer');
            const notifDropdown = document.getElementById('notifDropdownMenu');
            if (notifContainer && notifDropdown && !notifContainer.contains(event.target) && !notifDropdown.classList.contains('hidden')) {
                hideNotifDropdown();
            }
        });

        @auth
        (function() {
            const userId = @js(auth()->id());
            const storageKey = `simass_notif_${userId}`;
            let notifications = [];

            try {
                const raw = sessionStorage.getItem(storageKey);
                const parsed = raw ? JSON.parse(raw) : [];
                if (Array.isArray(parsed)) notifications = parsed.slice(0, 10);
            } catch (_) {}

            function isSafeUrl(url) {
                if (typeof url !== 'string' || !url.trim()) return false;
                return url.startsWith('/') || url.startsWith('http://') || url.startsWith('https://');
            }

            function renderNotifications(showDot = false) {
                const list = document.getElementById('notifList');
                const dot = document.getElementById('notifUnreadDot');
                if (!list) return;

                list.replaceChildren();

                if (notifications.length === 0) {
                    const empty = document.createElement('div');
                    empty.id = 'notifEmptyState';
                    empty.className = 'px-4 py-6 text-center text-xs text-gray-400';
                    empty.textContent = 'Belum ada notifikasi baru.';
                    list.appendChild(empty);
                    if (dot) dot.classList.add('hidden');
                    return;
                }

                if (showDot && dot) {
                    dot.classList.remove('hidden');
                }

                notifications.forEach((item) => {
                    const wrapper = isSafeUrl(item.url) ? document.createElement('a') : document.createElement('div');
                    if (wrapper.tagName === 'A') {
                        wrapper.href = item.url;
                    }
                    wrapper.className = 'block px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-800/70 transition';

                    const top = document.createElement('div');
                    top.className = 'flex items-center justify-between gap-2';

                    const titleEl = document.createElement('span');
                    titleEl.className = 'text-xs font-bold text-gray-900 dark:text-white truncate';
                    titleEl.textContent = item.title || 'Notifikasi';

                    const timeEl = document.createElement('span');
                    timeEl.className = 'text-[10px] text-gray-400 shrink-0';
                    timeEl.textContent = item.time || '';

                    top.appendChild(titleEl);
                    top.appendChild(timeEl);

                    const bodyEl = document.createElement('p');
                    bodyEl.className = 'text-xs text-gray-500 dark:text-gray-400 mt-0.5 line-clamp-2';
                    bodyEl.textContent = item.body || '';

                    wrapper.appendChild(top);
                    wrapper.appendChild(bodyEl);
                    list.appendChild(wrapper);
                });
            }

            function pushNotification(title, body, url, ticketIdForSuppression, toastType = 'info') {
                const now = new Date();
                const time = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
                notifications.unshift({ title, body, url, time });
                notifications = notifications.slice(0, 10);

                try {
                    sessionStorage.setItem(storageKey, JSON.stringify(notifications));
                } catch (_) {}

                renderNotifications(true);

                const isSameTicketPage = ticketIdForSuppression
                    && String(window.SimassActiveTicketId || '') === String(ticketIdForSuppression);

                if (!isSameTicketPage && typeof window.showToast === 'function') {
                    window.showToast(`${title}${body ? ' — ' + body : ''}`, toastType, 4500);
                }
            }

            window.clearNotifications = function() {
                notifications = [];
                try {
                    sessionStorage.removeItem(storageKey);
                } catch (_) {}
                renderNotifications(false);
            };

            document.addEventListener('DOMContentLoaded', function() {
                renderNotifications(false);
                if (!window.SimassRealtime) return;

                window.SimassRealtime.subscribeUser(userId, {
                    onTicketCreated(e) {
                        pushNotification(
                            `Tiket Baru #${e.ticket_number || e.id}`,
                            e.title || 'Tiket ditugaskan ke Anda',
                            e.ticket_url || null,
                            e.id,
                            'info'
                        );
                    },
                    onMessage(e) {
                        const sender = e.sender_name || 'Pembaruan';
                        const snippet = (e.message || '').slice(0, 90);
                        pushNotification(
                            `Balasan #${e.ticket_number || e.ticket_id} (${sender})`,
                            snippet || 'Pesan baru diterima',
                            e.ticket_url || null,
                            e.ticket_id,
                            'info'
                        );
                    },
                    onStatusUpdated(e) {
                        const label = e.status?.label || e.status?.value || 'Diperbarui';
                        pushNotification(
                            `Status #${e.ticket_number || e.id}`,
                            `Berubah menjadi: ${label}`,
                            e.ticket_url || null,
                            e.id,
                            e.status?.value === 'finished' ? 'success' : 'info'
                        );
                    },
                });
            });
        })();
        @endauth
    </script>
@endpush

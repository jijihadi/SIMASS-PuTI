@php
    if (request()->routeIs('presence.*') || request()->routeIs('holiday.*') || request()->routeIs('export-pdf')) {
        session(['active_module' => 'Presensi']);
    } elseif (request()->routeIs('ticket.*')) {
        session(['active_module' => 'Ticketing']);
    } elseif (request()->routeIs('inventory.*')) {
        session(['active_module' => 'Inventaris']);
    } elseif (request()->routeIs('admin.*')) {
        session(['active_module' => 'Admin']);
    }

    $currentApp = session('active_module', 'Presensi');

    $moduleThemes = [
        'Presensi' => [
            'key' => 'presensi',
            'title' => 'SIMASS Presensi',
            'color_hex' => '#b6252a',
            'color_hover' => 'rgba(182, 37, 42, 0.08)',
            'shadow_hex' => 'rgba(182, 37, 42, 0.35)',
            'dot' => 'bg-[#b6252a]',
            'back_hover' => 'hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/40 dark:hover:text-red-400',
            'avatar_ring' => 'ring-red-100 dark:ring-red-900',
            'avatar_text' => 'text-red-600 dark:text-red-400',
            'avatar_bg' => 'bg-red-50 dark:bg-red-950/50',
            'subheading' => 'text-red-700/80 dark:text-red-400/80 font-bold',
        ],
        'Ticketing' => [
            'key' => 'ticketing',
            'title' => 'e-Ticket PuTI',
            'color_hex' => '#059669',
            'color_hover' => 'rgba(5, 150, 105, 0.08)',
            'shadow_hex' => 'rgba(5, 150, 105, 0.35)',
            'dot' => 'bg-emerald-600',
            'back_hover' => 'hover:text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 dark:hover:text-emerald-400',
            'avatar_ring' => 'ring-emerald-100 dark:ring-emerald-900',
            'avatar_text' => 'text-emerald-600 dark:text-emerald-400',
            'avatar_bg' => 'bg-emerald-50 dark:bg-emerald-950/50',
            'subheading' => 'text-emerald-700/80 dark:text-emerald-400/80 font-bold',
        ],
        'Inventaris' => [
            'key' => 'inventaris',
            'title' => 'Inventaris PuTI',
            'color_hex' => '#7c3aed',
            'color_hover' => 'rgba(124, 58, 237, 0.08)',
            'shadow_hex' => 'rgba(124, 58, 237, 0.35)',
            'dot' => 'bg-purple-600',
            'back_hover' => 'hover:text-purple-600 hover:bg-purple-50 dark:hover:bg-purple-950/40 dark:hover:text-purple-400',
            'avatar_ring' => 'ring-purple-100 dark:ring-purple-900',
            'avatar_text' => 'text-purple-600 dark:text-purple-400',
            'avatar_bg' => 'bg-purple-50 dark:bg-purple-950/50',
            'subheading' => 'text-purple-700/80 dark:text-purple-400/80 font-bold',
        ],
        'Admin' => [
            'key' => 'admin',
            'title' => 'Admin Console',
            'color_hex' => '#1e293b',
            'color_hover' => 'rgba(30, 41, 59, 0.08)',
            'shadow_hex' => 'rgba(30, 41, 59, 0.35)',
            'dot' => 'bg-slate-700',
            'back_hover' => 'hover:text-slate-900 hover:bg-slate-100 dark:hover:bg-slate-800 dark:hover:text-slate-200',
            'avatar_ring' => 'ring-slate-200 dark:ring-slate-700',
            'avatar_text' => 'text-slate-700 dark:text-slate-200',
            'avatar_bg' => 'bg-slate-100 dark:bg-slate-800',
            'subheading' => 'text-slate-700 dark:text-slate-300 font-bold',
        ],
    ];

    $currentTheme = $moduleThemes[$currentApp] ?? $moduleThemes['Presensi'];

    $menuGroups = [
        'Admin' => [
            ['icon' => 'layout-dashboard', 'item' => 'Dashboard', 'route' => 'admin.dashboard'],
            ['icon' => 'layout-grid', 'item' => 'Modul Portal', 'route' => 'admin.modules.index', 'active' => ['admin.modules.*']],
            ['icon' => 'users', 'item' => 'Manajemen Akun', 'route' => 'admin.users.index', 'active' => ['admin.users.*']],
            ['icon' => 'database', 'item' => 'Data Master & SLA', 'route' => 'admin.master-data.index', 'active' => ['admin.master-data.*']],
            ['icon' => 'history', 'item' => 'Audit Log', 'route' => 'admin.audit-logs.index', 'active' => ['admin.audit-logs.*']],
        ],
        'Presensi' => [
            [
                'icon' => 'layout-grid',
                'item' => 'Dashboard',
                'route' => 'presence.dashboard',
                'permission' => 'view-presence',
            ],
            [
                'icon' => 'check-square',
                'item' => 'Presensi',
                'route' => 'presence.index',
                'permission' => 'create-presence',
            ],
            [
                'icon' => 'file-text',
                'item' => 'Daftar Presensi',
                'route' => 'presence.list',
                'permission' => 'view-presence',
            ],
            [
                'icon' => 'clock',
                'item' => 'Overtime',
                'route' => 'presence.overtime',
                'permission' => 'manage-overtime',
            ],
            [
                'icon' => 'history',
                'item' => 'History Presensi',
                'route' => 'presence.history',
                'permission' => 'view-presence-history',
            ],
            [
                'icon' => 'users',
                'item' => 'Student Staff',
                'route' => 'presence.student-staff',
                'permission' => 'manage-presence',
            ],
            [
                'icon' => 'calendar',
                'item' => 'Kalender',
                'route' => 'presence.calendar',
                'permission' => 'view-presence',
            ],
            [
                'icon' => 'calendar-off',
                'item' => 'Hari Libur',
                'route' => 'holiday.index',
                'permission' => 'manage-holiday',
            ],
        ],
        'Ticketing' => [
            ['icon' => 'ticket', 'item' => 'Daftar Tiket', 'route' => 'ticket.index', 'active' => ['ticket.index', 'ticket.show']],
            ['icon' => 'plus-circle', 'item' => 'Pengajuan Tiket', 'route' => 'ticket.create', 'active' => ['ticket.create']],
            ['icon' => 'user-check', 'item' => 'Tiket Saya', 'route' => 'ticket.my-tickets', 'active' => ['ticket.my-tickets']],
            ['icon' => 'clipboard-list', 'item' => 'Task Management', 'route' => 'ticket.tasks', 'active' => ['ticket.tasks']],
            ['icon' => 'history', 'item' => 'History Tiket', 'route' => 'ticket.history', 'active' => ['ticket.history']],
            ['icon' => 'bot', 'item' => 'Luna AI', 'route' => 'ticket.luna', 'active' => ['ticket.luna']],
        ],
        'Inventaris' => [
            ['icon' => 'package', 'item' => 'Daftar Inventaris', 'route' => 'inventory.index', 'active' => ['inventory.index', 'inventory.bulk-input', 'inventory.template', 'inventory.show']],
            ['icon' => 'arrow-right-left', 'item' => 'Mutasi Lokasi', 'route' => 'inventory.mutations.index', 'active' => ['inventory.mutations.*']],
            ['icon' => 'wrench', 'item' => 'Maintenance & Bulk', 'route' => 'inventory.maintenances.index', 'active' => ['inventory.maintenances.*', 'inventory.bulk.*']],
        ],
        'Lainnya' => [
            [
                'icon' => 'shield-check',
                'item' => 'Role & Permission',
                'route' => 'role-permission',
                'permission' => 'manage-roles',
            ],
            ['icon' => 'user', 'item' => 'Profil', 'route' => 'profile'],
            ['icon' => 'settings', 'item' => 'Pengaturan', 'route' => 'settings', 'permission' => 'manage-roles'],
        ],
    ];
@endphp

<aside id="sidebar"
    data-module="{{ $currentTheme['key'] }}"
    style="--sidebar-accent: {{ $currentTheme['color_hex'] }}; --sidebar-accent-shadow: {{ $currentTheme['shadow_hex'] }}; --sidebar-accent-hover: {{ $currentTheme['color_hover'] }};"
    class="hidden lg:flex fixed inset-y-0 left-0 w-64 bg-white dark:bg-gray-900 border-r border-gray-200 dark:border-gray-800 flex-col z-40 transition-colors duration-200">

    <style>
        #sidebar .nav-item.active {
            background-color: var(--sidebar-accent, #b6252a) !important;
            color: #ffffff !important;
            box-shadow: 0 4px 14px -3px var(--sidebar-accent-shadow, rgba(182, 37, 42, 0.35)) !important;
        }
        #sidebar .nav-item.active svg {
            color: #ffffff !important;
        }
        #sidebar .nav-item:not(.active):hover {
            background-color: var(--sidebar-accent-hover, rgba(0, 0, 0, 0.04));
        }
    </style>

    <!-- Logo & Module Brand -->
    <div class="px-6 py-5 border-b border-gray-100 dark:border-gray-800">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-white dark:bg-gray-800 p-1.5 flex items-center justify-center shrink-0 shadow-sm border border-gray-100 dark:border-gray-700/60">
                <img src="{{ asset('logo-puti.webp') }}" class="w-full h-full object-contain" alt="PuTI Logo">
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-1.5">
                    <p class="font-bold text-sm leading-tight text-gray-900 dark:text-white truncate">
                        {{ $currentTheme['title'] }}
                    </p>
                    <span class="w-2 h-2 rounded-full shrink-0 {{ $currentTheme['dot'] }}" title="{{ $currentTheme['title'] }}"></span>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Kampus Surabaya</p>
            </div>
        </div>
    </div>

    <!-- Kembali ke Portal -->
    <div class="px-3 pt-3 pb-1">
        <a href="{{ route('portal') }}"
            class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-semibold text-gray-600 dark:text-gray-400 {{ $currentTheme['back_hover'] }} border border-dashed border-gray-200 dark:border-gray-800 transition">
            <i data-lucide="arrow-left" class="w-4 h-4 shrink-0"></i>
            <span>Kembali ke Portal Hub</span>
        </a>
    </div>

    <!-- Menu -->
    <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto no-scrollbar">
        @foreach ($menuGroups as $groupLabel => $items)
            @if ($currentApp && $groupLabel !== $currentApp && $groupLabel !== 'Lainnya')
                @continue
            @endif
            <p
                class="px-3 py-2 {{ $loop->first ? '' : 'mt-4' }} text-xs uppercase tracking-wider {{ $groupLabel === $currentApp ? $currentTheme['subheading'] : 'text-gray-400 font-semibold' }}">
                {{ $groupLabel }}
            </p>
            @foreach ($items as $item)
                @if (!isset($item['permission']) || auth()->user()?->can($item['permission']))
                    @php
                        $isActive = isset($item['active'])
                            ? request()->routeIs(...(array) $item['active'])
                            : request()->routeIs($item['route']);
                    @endphp
                    <a href="{{ route($item['route']) }}"
                        class="nav-item {{ $isActive ? 'active' : '' }} w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-gray-700 dark:text-gray-300 transition">
                        <i data-lucide="{{ $item['icon'] }}" class="w-5 h-5 shrink-0"></i>
                        <span class="truncate">{{ $item['item'] }}</span>
                    </a>
                @endif
            @endforeach
        @endforeach
    </nav>

    <!-- User -->
    <div class="p-3 border-t border-gray-100 dark:border-gray-800 space-y-1">
        <a href="{{ route('profile') }}"
            class="flex items-center gap-3 p-2 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-800 transition cursor-pointer">
            @php
                $userName = auth()->user()->name;
                $words = explode(' ', $userName);
                $initials = strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : ''));
            @endphp
            <div
                class="w-9 h-9 rounded-full ring-2 {{ $currentTheme['avatar_ring'] }} shrink-0 flex items-center justify-center {{ $currentTheme['avatar_bg'] }} {{ $currentTheme['avatar_text'] }} font-bold text-sm">
                {{ $initials }}
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold truncate leading-tight">{{ auth()->user()->name }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 truncate mt-0.5">
                    {{ auth()->user()->position ?? (auth()->user()->getRoleNames()->first() ?? '-') }}</p>
            </div>
            <i data-lucide="chevron-right" class="w-4 h-4 text-gray-400 shrink-0"></i>
        </a>
    </div>
</aside>

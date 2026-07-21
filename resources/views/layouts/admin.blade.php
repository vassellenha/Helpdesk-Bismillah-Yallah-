<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') · {{ config('helpdesk.product') }}</title>
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
</head>
<body class="min-h-screen bg-gray-50 font-sans text-gray-900 antialiased">
    <header class="sticky top-0 z-30 border-b border-gray-200 bg-white">
        <div class="flex items-center justify-between px-6 py-3">
            <div class="flex items-center gap-8">
                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-700 text-sm font-bold text-white">AK</span>
                    <span class="leading-tight">
                        <span class="block text-sm font-bold text-gray-900">{{ config('helpdesk.company') }}</span>
                        <span class="block text-xs text-gray-400">{{ config('helpdesk.product') }}</span>
                    </span>
                </a>
                <nav class="hidden items-center gap-1 lg:flex">
                    @foreach (config('helpdesk.admin_nav') as $item)
                        <a
                            href="{{ route($item['route']) }}"
                            class="rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs($item['route']) ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-50' }}"
                        >
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </nav>
            </div>
            <div class="flex items-center gap-4">
                <a href="{{ route('portal.index') }}" class="hidden items-center gap-1.5 text-sm font-medium text-gray-500 hover:text-gray-700 sm:flex">
                    <span aria-hidden="true">←</span> Kembali ke Portal
                </a>
                <div data-react="NotificationBell" data-props="{{ json_encode(['notifications' => $notifications ?? []]) }}"></div>
                <div data-react="UserMenu" data-props="{{ json_encode(['name' => $currentUser['name'] ?? '', 'title' => $currentUser['title'] ?? '', 'initials' => $currentUser['initials'] ?? '']) }}"></div>
            </div>
        </div>
    </header>

    <main class="px-6 py-8">
        @yield('content')
    </main>

    @php
        $switcherRoles = collect(config('helpdesk.roles'))
            ->map(fn ($r) => ['key' => $r['key'], 'initials' => $r['initials'], 'label' => $r['label'], 'url' => route($r['links'][0]['route'])])
            ->values();
    @endphp
    <div
        data-react="RoleSwitcher"
        data-props="{{ json_encode(['roles' => $switcherRoles, 'current' => $role ?? null, 'portalUrl' => route('portal.index')]) }}"
    ></div>
</body>
</html>

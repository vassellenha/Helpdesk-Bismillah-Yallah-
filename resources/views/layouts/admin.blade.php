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
<body class="min-h-screen bg-gray-50 dark:bg-panel-0 font-sans text-gray-900 dark:text-ink-1 antialiased">
    <header class="sticky top-3 z-30 mx-3 rounded-2xl border border-black/5 dark:border-white/10 bg-white/65 dark:bg-white/[0.06] shadow-[0_8px_32px_-8px_rgba(0,0,0,0.18)] dark:shadow-[0_8px_32px_-8px_rgba(0,0,0,0.6)] backdrop-blur-lg backdrop-saturate-150 transition-all duration-200 md:mx-6">
        <div class="flex items-center justify-between px-6 py-3">
            <div class="flex items-center gap-8">
                <a href="{{ route('admin.dashboard') }}" class="flex items-center">
                    @include('partials.brand-lockup')
                </a>
                <nav class="hidden items-center gap-1 lg:flex">
                    @foreach (config('helpdesk.admin_nav') as $item)
                        <a
                            href="{{ route($item['route']) }}"
                            class="rounded-lg px-3 py-2 text-sm font-medium transition-all duration-200 ease-out {{ request()->routeIs($item['route']) ? 'bg-blue-50 dark:bg-accent-soft text-blue-700 dark:text-accent-text' : 'text-gray-600 dark:text-ink-2 hover:bg-white/60 dark:hover:bg-white/10' }}"
                        >
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </nav>
            </div>
            <div class="flex items-center gap-4">
                <div data-react="LanguageSwitcher"></div>
                <div data-react="UserMenu" data-props="{{ json_encode(['name' => $currentUser['name'] ?? '', 'title' => $currentUser['title'] ?? '', 'initials' => $currentUser['initials'] ?? '', 'profileUrl' => route('admin.profile')]) }}"></div>
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

<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') · {{ config('helpdesk.product') }}</title>
    @viteReactRefresh
    @include('partials.translations')
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
</head>
<body class="min-h-screen bg-gray-50 dark:bg-panel-0 font-sans text-gray-900 dark:text-ink-1 antialiased">
    <div class="flex min-h-screen">
        <aside class="hidden w-64 shrink-0 flex-col border-r border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-1 lg:flex">
            <div class="flex items-center px-6 py-5">
                @include('partials.brand-lockup')
            </div>

            <nav class="flex-1 space-y-1 px-3 py-2">
                @foreach (config('helpdesk.roles') as $r)
                    <a
                        href="{{ route($r['links'][0]['route']) }}"
                        class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium {{ ($role ?? null) === $r['key'] ? 'bg-blue-50 dark:bg-accent-soft text-blue-700 dark:text-accent-text' : 'text-gray-600 dark:text-ink-2 hover:bg-gray-50 dark:hover:bg-panel-hover' }}"
                    >
                        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-gray-100 dark:bg-panel-3 text-xs font-bold text-gray-500 dark:text-ink-2">
                            {{ $r['initials'] }}
                        </span>
                        {{ $r['label'] }}
                    </a>
                @endforeach
            </nav>

            <div class="border-t border-gray-100 dark:border-edge px-6 py-4">
                <a href="{{ route('portal.index') }}" class="text-xs font-medium text-gray-400 dark:text-ink-3 hover:text-gray-600">
                    ← Kembali ke Mockup Review
                </a>
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="flex items-center justify-between border-b border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-1 px-6 py-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-ink-3">
                        {{ config('helpdesk.roles')[$role]['label'] ?? '' }} Workspace
                    </p>
                    <h1 class="text-lg font-bold text-gray-900 dark:text-ink-1">@yield('title')</h1>
                </div>
                <div class="flex items-center gap-4">
                    <div data-react="LanguageSwitcher"></div>
                    <div data-react="NotificationBell" data-props="{{ json_encode(['notifications' => $notifications ?? []]) }}"></div>
                    @if(isset($currentUser) && isset($profileUrl))
                        <div
                            data-react="UserMenu"
                            data-props="{{ json_encode(['name' => $currentUser['name'], 'title' => $currentUser['title'], 'initials' => $currentUser['initials'], 'profileUrl' => $profileUrl]) }}"
                        ></div>
                    @else
                        <div class="flex items-center gap-2">
                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-gray-100 dark:bg-panel-3 text-sm font-semibold text-gray-600 dark:text-ink-2">
                                {{ strtoupper(substr(config('helpdesk.roles')[$role]['label'] ?? 'U', 0, 1)) }}
                            </span>
                            <span class="hidden text-sm font-medium text-gray-700 dark:text-ink-2 sm:block">
                                {{ config('helpdesk.roles')[$role]['label'] ?? '' }}
                            </span>
                        </div>
                    @endif
                </div>
            </header>

            <main class="flex-1 px-6 py-6">
                @yield('content')
            </main>
        </div>
    </div>

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

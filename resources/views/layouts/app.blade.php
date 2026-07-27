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
    <div class="flex min-h-screen">
        <aside class="hidden w-64 shrink-0 flex-col border-r border-gray-200 bg-white lg:flex">
            <div class="flex items-center gap-2 px-6 py-5">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-700 text-sm font-bold text-white">AK</span>
                <div>
                    <p class="text-sm font-bold leading-tight text-gray-900">{{ config('helpdesk.company') }}</p>
                    <p class="text-xs text-gray-400">{{ config('helpdesk.product') }}</p>
                </div>
            </div>

            <nav class="flex-1 space-y-1 px-3 py-2">
                @foreach (config('helpdesk.roles') as $r)
                    <a
                        href="{{ route($r['links'][0]['route']) }}"
                        class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium {{ ($role ?? null) === $r['key'] ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-50' }}"
                    >
                        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-gray-100 text-xs font-bold text-gray-500">
                            {{ $r['initials'] }}
                        </span>
                        {{ $r['label'] }}
                    </a>
                @endforeach
            </nav>

            <div class="border-t border-gray-100 px-6 py-4">
                <a href="{{ route('portal.index') }}" class="text-xs font-medium text-gray-400 hover:text-gray-600">
                    ← Kembali ke Mockup Review
                </a>
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="flex items-center justify-between border-b border-gray-200 bg-white px-6 py-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">
                        {{ config('helpdesk.roles')[$role]['label'] ?? '' }} Workspace
                    </p>
                    <h1 class="text-lg font-bold text-gray-900">@yield('title')</h1>
                </div>
                <div class="flex items-center gap-4">
                    <div data-react="NotificationBell" data-props="{{ json_encode(['notifications' => $notifications ?? []]) }}"></div>
                    @if(isset($currentUser) && isset($profileUrl))
                        <div
                            data-react="UserMenu"
                            data-props="{{ json_encode(['name' => $currentUser['name'], 'title' => $currentUser['title'], 'initials' => $currentUser['initials'], 'profileUrl' => $profileUrl]) }}"
                        ></div>
                    @else
                        <div class="flex items-center gap-2">
                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-gray-100 text-sm font-semibold text-gray-600">
                                {{ strtoupper(substr(config('helpdesk.roles')[$role]['label'] ?? 'U', 0, 1)) }}
                            </span>
                            <span class="hidden text-sm font-medium text-gray-700 sm:block">
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

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') · {{ config('helpdesk.product') }}</title>
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
</head>
<body class="min-h-screen bg-gray-50 dark:bg-panel-0 font-sans text-gray-900 dark:text-ink-1 antialiased">
    <div class="flex min-h-screen flex-col">
        <header class="sticky top-3 z-20 mx-3 flex h-[62px] items-center gap-6 rounded-2xl border border-black/5 dark:border-white/10 bg-white/65 dark:bg-white/[0.06] px-7 shadow-[0_8px_32px_-8px_rgba(0,0,0,0.18)] dark:shadow-[0_8px_32px_-8px_rgba(0,0,0,0.6)] backdrop-blur-lg backdrop-saturate-150 transition-all duration-200 md:mx-6">
            @include('partials.brand-lockup')

            <nav class="flex items-center gap-1">
                <a
                    href="{{ route('dashboard.support') }}"
                    class="flex items-center gap-2 rounded-[10px] px-3.5 py-2 text-[13px] font-semibold transition-all duration-200 ease-out {{ request()->routeIs('dashboard.support') ? 'bg-blue-50 dark:bg-accent-soft text-blue-700 dark:text-accent-text' : 'text-gray-600 dark:text-ink-2 hover:bg-white/60 dark:hover:bg-white/10 hover:text-gray-900 dark:hover:text-ink-1' }}"
                >
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="18" rx="2"/><path d="M9 9h6M9 13h6M9 17h3"/></svg>
                    Dashboard
                </a>
                <a
                    href="{{ route('support.tickets') }}"
                    class="flex items-center gap-2 rounded-[10px] px-3.5 py-2 text-[13px] font-semibold transition-all duration-200 ease-out {{ request()->routeIs('support.tickets*') ? 'bg-blue-50 dark:bg-accent-soft text-blue-700 dark:text-accent-text' : 'text-gray-600 dark:text-ink-2 hover:bg-white/60 dark:hover:bg-white/10 hover:text-gray-900 dark:hover:text-ink-1' }}"
                >
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 0 0 4v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3a2 2 0 0 0 0-4Z"/><path d="M14 5v14"/></svg>
                    My Tickets
                </a>
            </nav>

            <div class="flex-1"></div>

            @if(($agentSwitcher['options'] ?? collect())->count() > 1)
                <form method="POST" action="{{ $agentSwitcher['switchUrl'] }}" class="flex items-center gap-2">
                    @csrf
                    <label for="agent_id" class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-ink-3">Bertindak sebagai</label>
                    <select
                        id="agent_id"
                        name="agent_id"
                        onchange="this.form.submit()"
                        class="rounded-[10px] border border-gray-200 dark:border-edge-strong bg-gray-50 dark:bg-panel-3 px-2.5 py-1.5 text-[13px] font-semibold text-gray-700 dark:text-ink-2 outline-none focus:border-blue-400"
                    >
                        @foreach($agentSwitcher['options'] as $opt)
                            <option value="{{ $opt->id }}" @selected($opt->id === $agentSwitcher['currentAgentId'])>{{ $opt->name }}</option>
                        @endforeach
                    </select>
                </form>
            @endif

            <div data-react="LanguageSwitcher"></div>

            <div
                data-react="ApproverTopNav"
                data-props="{{ json_encode(['notifications' => $notifications ?? [], 'user' => $currentUser ?? [], 'inboxUrl' => route('dashboard.support'), 'ticketsUrl' => route('support.tickets'), 'markAllReadUrl' => route('support.notifications.read-all'), 'profileUrl' => route('support.profile')]) }}"
            ></div>
        </header>

        <main class="mx-auto flex w-full max-w-[1280px] flex-1 flex-col gap-8 px-7 py-7">
            @yield('content')
        </main>
    </div>

    @php
        $switcherRoles = collect(config('helpdesk.roles'))
            ->map(fn ($r) => ['key' => $r['key'], 'initials' => $r['initials'], 'label' => $r['label'], 'url' => route($r['links'][0]['route'])])
            ->values();
    @endphp
    <div
        data-react="RoleSwitcher"
        data-props="{{ json_encode(['roles' => $switcherRoles, 'current' => $role ?? 'support', 'portalUrl' => route('portal.index')]) }}"
    ></div>
</body>
</html>

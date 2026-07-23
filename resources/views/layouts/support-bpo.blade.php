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
<body class="min-h-screen bg-gray-50 font-sans text-gray-900 antialiased">
    <div class="flex min-h-screen flex-col">
        <header class="sticky top-0 z-20 flex h-[62px] items-center gap-6 border-b border-gray-200 bg-white px-7">
            <div class="flex items-center gap-2.5">
                <span class="flex h-8 w-8 items-center justify-center rounded-[10px] bg-blue-600 text-sm font-extrabold text-white">A</span>
                <div class="leading-tight">
                    <p class="text-sm font-bold text-gray-900">Adhi Helpdesk</p>
                    <p class="text-[10px] text-gray-400">Enterprise ITSM</p>
                </div>
            </div>

            <nav class="flex items-center gap-1">
                <a
                    href="{{ route('dashboard.support-bpo') }}"
                    class="flex items-center gap-2 rounded-[10px] px-3.5 py-2 text-[13px] font-semibold {{ request()->routeIs('dashboard.support-bpo') ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}"
                >
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="18" rx="2"/><path d="M9 9h6M9 13h6M9 17h3"/></svg>
                    Dashboard
                </a>
                <a
                    href="{{ route('support-bpo.tickets') }}"
                    class="flex items-center gap-2 rounded-[10px] px-3.5 py-2 text-[13px] font-semibold {{ request()->routeIs('support-bpo.tickets*') ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}"
                >
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 0 0 4v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3a2 2 0 0 0 0-4Z"/><path d="M14 5v14"/></svg>
                    My Tickets
                </a>
            </nav>

            <div class="flex-1"></div>

            @if(($agentSwitcher['options'] ?? collect())->count() > 1)
                <form method="POST" action="{{ $agentSwitcher['switchUrl'] }}" class="flex items-center gap-2">
                    @csrf
                    <label for="agent_id" class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">Bertindak sebagai</label>
                    <select
                        id="agent_id"
                        name="agent_id"
                        onchange="this.form.submit()"
                        class="rounded-[10px] border border-gray-200 bg-gray-50 px-2.5 py-1.5 text-[13px] font-semibold text-gray-700 outline-none focus:border-blue-400"
                    >
                        @foreach($agentSwitcher['options'] as $opt)
                            <option value="{{ $opt->id }}" @selected($opt->id === $agentSwitcher['currentAgentId'])>{{ $opt->name }}</option>
                        @endforeach
                    </select>
                </form>
            @endif

            <div
                data-react="ApproverTopNav"
                data-props="{{ json_encode(['notifications' => $notifications ?? [], 'user' => $currentUser ?? [], 'inboxUrl' => route('dashboard.support-bpo'), 'ticketsUrl' => route('support-bpo.tickets'), 'markAllReadUrl' => route('support-bpo.notifications.read-all')]) }}"
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
        data-props="{{ json_encode(['roles' => $switcherRoles, 'current' => $role ?? 'support-bpo', 'portalUrl' => route('portal.index')]) }}"
    ></div>
</body>
</html>

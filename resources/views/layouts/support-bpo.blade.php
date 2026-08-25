<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    @include('partials.theme-boot')
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('partials.favicon')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') · {{ config('helpdesk.product') }}</title>
    @viteReactRefresh
    @include('partials.translations', ['groups' => ['support']])
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    @include('partials.priority-registry')
</head>
<body class="min-h-screen bg-gray-50 dark:bg-panel-0 font-sans text-gray-900 dark:text-ink-1 antialiased">
    <div class="flex min-h-screen flex-col">
        <header class="sticky top-2 z-20 mx-2 flex min-h-[62px] items-center gap-3 rounded-2xl sm:top-3 sm:mx-3 sm:gap-6 border border-black/5 dark:border-white/10 bg-white/65 dark:bg-white/[0.06] px-3 sm:px-7 shadow-[0_8px_32px_-8px_rgba(0,0,0,0.18)] dark:shadow-[0_8px_32px_-8px_rgba(0,0,0,0.6)] backdrop-blur-lg backdrop-saturate-150 transition-all duration-200 md:mx-6">
            @include('partials.brand-lockup')

            <nav class="flex min-w-0 items-center gap-1 overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                <a
                    href="{{ route('dashboard.support-bpo') }}"
                    class="flex shrink-0 items-center gap-2 whitespace-nowrap rounded-[10px] px-2.5 py-2 text-[13px] font-semibold sm:px-3.5 transition-all duration-200 ease-out {{ request()->routeIs('dashboard.support-bpo') ? 'bg-blue-50 dark:bg-accent-soft text-blue-700 dark:text-accent-text' : 'text-gray-600 dark:text-ink-2 hover:bg-white/60 dark:hover:bg-white/10 hover:text-gray-900 dark:hover:text-ink-1' }}"
                >
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="18" rx="2"/><path d="M9 9h6M9 13h6M9 17h3"/></svg>
                    <span class="hidden sm:inline">{{ __('support.nav.dashboard') }}</span>
                </a>
                <a
                    href="{{ route('support-bpo.tickets') }}"
                    class="flex shrink-0 items-center gap-2 whitespace-nowrap rounded-[10px] px-2.5 py-2 text-[13px] font-semibold sm:px-3.5 transition-all duration-200 ease-out {{ request()->routeIs('support-bpo.tickets*') ? 'bg-blue-50 dark:bg-accent-soft text-blue-700 dark:text-accent-text' : 'text-gray-600 dark:text-ink-2 hover:bg-white/60 dark:hover:bg-white/10 hover:text-gray-900 dark:hover:text-ink-1' }}"
                >
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 0 0 4v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3a2 2 0 0 0 0-4Z"/><path d="M14 5v14"/></svg>
                    <span class="hidden sm:inline">{{ __('support.nav.my_tickets') }}</span>
                </a>
            </nav>

            <div class="flex-1"></div>

            <div
                data-react="ApproverTopNav"
                data-props="{{ json_encode(['notifications' => $notifications['items'] ?? [], 'unreadCount' => $notifications['unreadCount'] ?? 0, 'user' => $currentUser ?? [], 'inboxUrl' => route('dashboard.support-bpo'), 'ticketsUrl' => route('support-bpo.tickets'), 'allNotificationsUrl' => route('support-bpo.notifications'), 'markAllReadUrl' => route('support-bpo.notifications.read-all'), 'profileUrl' => route('support-bpo.profile')]) }}"
            ></div>
        </header>

        <main class="mx-auto flex w-full max-w-[1280px] flex-1 flex-col gap-6 px-4 py-5 sm:gap-8 sm:px-7 sm:py-7">
            @yield('content')
        </main>
    </div>

    @include('partials.role-switcher')
</body>
</html>

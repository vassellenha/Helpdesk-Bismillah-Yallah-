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
    @include('partials.translations', ['groups' => ['admin']])
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    @include('partials.priority-registry')
</head>
<body class="min-h-screen bg-gray-50 dark:bg-panel-0 font-sans text-gray-900 dark:text-ink-1 antialiased">
    <header class="sticky top-3 z-30 mx-3 rounded-2xl border border-black/5 dark:border-white/10 bg-white/65 dark:bg-white/[0.06] shadow-[0_8px_32px_-8px_rgba(0,0,0,0.18)] dark:shadow-[0_8px_32px_-8px_rgba(0,0,0,0.6)] backdrop-blur-lg backdrop-saturate-150 transition-all duration-200 md:mx-6">
        <div class="flex items-center justify-between gap-3 px-3 py-3 sm:px-4 md:px-5">
            {{-- min-w-0 lets this cluster shrink instead of forcing the row wider
                 than the header, which is what pushed the nav labels onto a
                 second line at 100% zoom. --}}
            <div class="flex min-w-0 items-center gap-4 xl:gap-6">
                <a href="{{ route('admin.dashboard') }}" class="flex shrink-0 items-center">
                    @include('partials.brand-lockup')
                </a>
                @include('partials.admin-nav', ['navClass' => 'hidden min-w-0 items-center gap-0.5 overflow-x-auto lg:flex'])
            </div>
            <div class="flex shrink-0 items-center gap-2 sm:gap-3">
                {{-- Layar Admin tidak punya lonceng, jadi posisinya disamakan
                     dengan layout lain: tepat di kiri avatar pengguna. --}}
                <div data-react="ThemeToggle"></div>
                <div data-react="UserMenu" data-props="{{ json_encode(['name' => $currentUser['name'] ?? '', 'title' => $currentUser['title'] ?? '', 'initials' => $currentUser['initials'] ?? '', 'profileUrl' => route('admin.profile'), 'dashboardUrl' => route('admin.dashboard')]) }}"></div>
            </div>
        </div>

        {{-- Di bawah lg, menunya turun jadi barisnya sendiri dan digeser
             menyamping. Tujuh menu tidak muat berdampingan dengan logo di layar
             HP, tapi menyembunyikannya sama saja membuat Admin terkurung di satu
             halaman — itu keadaan sebelumnya. --}}
        @include('partials.admin-nav', ['navClass' => 'flex items-center gap-0.5 overflow-x-auto border-t border-black/5 dark:border-white/10 px-3 py-2 sm:px-4 lg:hidden'])
    </header>

    <main class="px-4 py-5 sm:px-6 sm:py-8">
        @yield('content')
    </main>

    @include('partials.role-switcher')
</body>
</html>

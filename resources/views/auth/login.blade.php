<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('partials.favicon')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Masuk · {{ config('helpdesk.product') }}</title>
    @viteReactRefresh
    @include('partials.translations')
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
</head>
<body class="min-h-screen bg-gray-50 dark:bg-panel-0 font-sans text-gray-900 dark:text-ink-1 antialiased">
    <div class="flex min-h-screen items-center justify-center px-4 py-10">
        <div class="w-full max-w-md">
            <div class="mb-6 flex items-center gap-2.5">
                <img src="{{ asset('images/logo.png') }}" alt="Helpdesk" class="h-10 w-10 rounded-xl object-cover">
                <p class="text-sm font-bold text-gray-900 dark:text-ink-1">Helpdesk</p>
            </div>

            <div class="rounded-2xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 p-6 shadow-sm">
                <h1 class="text-xl font-extrabold tracking-tight text-gray-900 dark:text-ink-1">Masuk</h1>
                <p class="mt-1 text-[13px] text-gray-500 dark:text-ink-2">
                    Gunakan akun portal SINTA. Helpdesk tidak menyimpan password Anda.
                </p>

                @if (session('sso_error'))
                    <p class="mt-4 rounded-lg bg-red-50 dark:bg-bad-soft p-3 text-[13px] text-red-700 dark:text-bad-text">
                        {{ session('sso_error') }}
                    </p>
                @endif

                @if (session('sso_success'))
                    <p class="mt-4 rounded-lg bg-emerald-50 dark:bg-ok-soft p-3 text-[13px] text-emerald-800 dark:text-ok-text">
                        {{ session('sso_success') }}
                    </p>
                @endif

                @if ($currentUser)
                    <div class="mt-4 rounded-lg bg-blue-50 dark:bg-accent-soft p-3 text-[13px] text-blue-800 dark:text-accent-text">
                        Sudah masuk sebagai <span class="font-bold">{{ $currentUser['name'] }}</span> ({{ $currentUser['email'] }}).
                        <form method="POST" action="{{ route('sso.logout') }}" class="mt-2">
                            @csrf
                            <button type="submit" class="rounded-lg border border-blue-300 dark:border-edge-strong px-3 py-1.5 text-xs font-bold hover:bg-white/60 dark:hover:bg-panel-hover">
                                Keluar
                            </button>
                        </form>
                    </div>
                @endif

                @if ($isMock)
                    <div class="mt-5 rounded-lg bg-amber-50 dark:bg-warn-soft p-3 text-[12px] leading-relaxed text-amber-800 dark:text-warn-text">
                        <span class="font-bold">Mode MOCK.</span> Portal SINTA belum tersambung, jadi login disimulasikan
                        lokal — pilih pegawai di bawah. Set <code class="rounded bg-white/60 dark:bg-panel-2 px-1">SSO_DRIVER=oidc</code>
                        di <code class="rounded bg-white/60 dark:bg-panel-2 px-1">.env</code> untuk memakai SINTA sungguhan.
                    </div>

                    <form method="GET" action="{{ route('sso.redirect') }}" class="mt-4">
                        <label for="as" class="mb-1.5 block text-[13px] font-medium text-gray-700 dark:text-ink-2">Masuk sebagai</label>
                        <select
                            id="as"
                            name="as"
                            required
                            class="w-full rounded-lg border border-gray-200 dark:border-edge-strong bg-gray-50 dark:bg-panel-3 px-3 py-2.5 text-sm focus:border-blue-400 focus:bg-white dark:focus:bg-panel-hover focus:outline-none"
                        >
                            @foreach ($employees as $e)
                                <option value="{{ $e['nip'] }}">
                                    {{ $e['name'] }} — {{ $e['jabatan'] ?: 'tanpa jabatan' }} ({{ implode(', ', $e['roles']) ?: 'tanpa role' }})
                                </option>
                            @endforeach
                        </select>

                        <button type="submit" class="mt-4 w-full rounded-lg bg-blue-700 dark:bg-blue-500 px-4 py-2.5 text-sm font-bold text-white hover:bg-blue-800 dark:hover:bg-blue-400">
                            Masuk dengan SINTA (simulasi)
                        </button>
                    </form>
                @else
                    <a
                        href="{{ $redirectUrl }}"
                        class="mt-5 flex w-full items-center justify-center gap-2 rounded-lg bg-blue-700 dark:bg-blue-500 px-4 py-2.5 text-sm font-bold text-white hover:bg-blue-800 dark:hover:bg-blue-400"
                    >
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" /><path d="M10 17l5-5-5-5" /><path d="M15 12H3" />
                        </svg>
                        Masuk dengan Portal SINTA
                    </a>
                @endif

                <p class="mt-5 border-t border-gray-100 dark:border-edge pt-4 text-[12px] leading-relaxed text-gray-400 dark:text-ink-3">
                    Akun helpdesk dibuat lewat sinkronisasi data pegawai, bukan saat login. Kalau akun Anda belum ada,
                    hubungi Administrator untuk menjalankan sinkronisasi.
                </p>
            </div>

            <a href="{{ route('portal.index') }}" class="mt-4 block text-center text-[12px] font-medium text-gray-400 dark:text-ink-3 hover:text-gray-600">
                Lanjut tanpa masuk (mode mockup) →
            </a>
        </div>
    </div>
</body>
</html>

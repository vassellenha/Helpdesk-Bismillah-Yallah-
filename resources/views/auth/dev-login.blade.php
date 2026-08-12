@extends('layouts.portal')

@section('title', $isAdminPortal ? 'Masuk Administrator' : 'Masuk')

@section('content')
<div class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-6 py-16">
    <p class="text-sm font-semibold tracking-wide text-blue-700 dark:text-accent-text">
        {{ strtoupper(config('helpdesk.company')) }} · {{ strtoupper(config('helpdesk.product')) }}
    </p>

    <h1 class="mt-4 text-2xl font-extrabold text-gray-900 dark:text-ink-1">
        {{ $isAdminPortal ? 'Masuk sebagai Administrator' : 'Masuk ke Helpdesk' }}
    </h1>
    <p class="mt-2 text-sm leading-relaxed text-gray-500 dark:text-ink-3">
        {{ $isAdminPortal
            ? 'Pintu khusus konsol admin. Hanya akun yang memegang role Administrator yang diterima di sini.'
            : 'Masuk dengan email kantor Anda. Layar yang terbuka mengikuti role yang Anda pegang.' }}
    </p>

    {{-- Semua penolakan dikirim lewat bag `email` — email tidak ketemu, akun
         nonaktif, dan role yang tidak memenuhi. Ketiganya soal "akun ini", jadi
         ditampilkan sebagai satu blok, bukan tersebar per kolom. --}}
    @if ($errors->any())
        <div class="mt-6 rounded-xl bg-red-50 dark:bg-bad-soft p-4 text-sm font-medium text-red-700 dark:text-bad-text">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ $action }}" class="mt-6 space-y-4">
        @csrf

        <div>
            <label for="email" class="block text-[13px] font-semibold text-gray-700 dark:text-ink-2">
                Email
            </label>
            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email') }}"
                required
                autofocus
                autocomplete="username"
                placeholder="nama@adhi.co.id"
                class="mt-1.5 w-full rounded-xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 px-3.5 py-2.5 text-sm text-gray-900 dark:text-ink-1 outline-none focus:border-blue-400"
            >
        </div>

        <label class="flex items-center gap-2 text-[13px] text-gray-600 dark:text-ink-2">
            <input type="checkbox" name="remember" value="1" class="rounded border-gray-300 dark:border-edge-strong">
            Ingat saya
        </label>

        <button
            type="submit"
            class="w-full rounded-xl bg-blue-700 dark:bg-blue-500 px-5 py-2.5 text-sm font-bold text-white hover:bg-blue-800 dark:hover:bg-blue-400"
        >
            Masuk
        </button>
    </form>

    {{-- SENGAJA tidak ada tautan ke pintu lain di sini.
         Konsol admin dicapai dengan mengetik /admin/login langsung — tidak
         ditawarkan dari halaman ini, jadi pegawai biasa tidak dipancing mencoba
         pintu yang akan menolak mereka. Tautan balik "masuk sebagai pegawai
         biasa" ikut hilang karena pasangannya sudah tidak ada. --}}

    {{-- Peringatan lingkungan, bukan hiasan. Halaman ini tidak meminta password
         sama sekali, jadi orang yang melihatnya perlu tahu persis apa yang
         sedang mereka pakai — dan bahwa ini tidak pernah ada di produksi. --}}
    <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 dark:border-amber-500/30 dark:bg-amber-500/10 p-3.5">
        <p class="text-[11px] leading-relaxed text-amber-800 dark:text-amber-300">
            <strong>Mode pengembangan.</strong> Masuk di sini tidak memakai password,
            jadi identitas apa pun bisa dipakai siapa saja. Hanya aktif di lingkungan
            pengembangan — di produksi, helpdesk masuk lewat SSO SINTA.
        </p>
    </div>
</div>
@endsection

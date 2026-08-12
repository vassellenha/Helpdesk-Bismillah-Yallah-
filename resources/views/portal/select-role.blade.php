@extends('layouts.portal')

@section('title', 'Pilih Role')

@section('content')
<div class="mx-auto max-w-6xl px-6 py-16">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-sm font-semibold tracking-wide text-blue-700 dark:text-accent-text">
                {{ strtoupper(config('helpdesk.company')) }} · {{ strtoupper(config('helpdesk.product')) }}
            </p>
            <h1 class="mt-2 text-4xl font-extrabold text-gray-900 dark:text-ink-1">Pilih Role</h1>
        </div>

        @if ($user)
            {{-- Satu-satunya jalan keluar yang pasti ada di setiap layar: dua
                 layout (team-lead, konsol EVA) tidak punya menu profil sendiri,
                 dan keduanya menautkan balik ke halaman ini. --}}
            <form method="POST" action="{{ route('logout') }}" class="flex items-center gap-3">
                @csrf
                <span class="text-right text-[13px]">
                    <span class="block font-bold text-gray-900 dark:text-ink-1">{{ $user->name }}</span>
                    <span class="block text-gray-400 dark:text-ink-3">{{ $user->email }}</span>
                </span>
                <button type="submit" class="rounded-xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 px-4 py-2.5 text-[13px] font-semibold text-red-600 dark:text-bad-text hover:bg-red-50 dark:hover:bg-bad-soft">
                    Keluar
                </button>
            </form>
        @endif
    </div>

    <p class="mt-4 max-w-3xl text-gray-500 dark:text-ink-3">
        @if (count($roles) > 1)
            Kartu di bawah adalah role yang Anda pegang. Setelah masuk ke salah satu screen, gunakan tombol
            <span class="font-semibold text-gray-700 dark:text-ink-2">"⇄ Switch Role"</span>
            di pojok kanan bawah untuk berpindah kapan saja.
        @elseif (count($roles) === 1)
            Anda memegang satu role. Kartu di bawah membuka layarnya.
        @else
            Akun Anda belum diberi role apa pun, jadi belum ada layar yang bisa dibuka.
            Hubungi Administrator Helpdesk untuk meminta akses.
        @endif
    </p>

    <div class="mt-10 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($roles as $role)
            <a href="{{ route($role['links'][0]['route']) }}" class="flex flex-col rounded-2xl border border-gray-100 dark:border-edge bg-white dark:bg-panel-2 p-6 shadow-sm transition hover:border-gray-300 hover:shadow-md">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 dark:bg-accent-soft text-sm font-bold text-blue-700 dark:text-accent-text">
                    {{ $role['initials'] }}
                </span>
                <h2 class="mt-4 text-lg font-bold text-gray-900 dark:text-ink-1">{{ $role['label'] }}</h2>
                <p class="mt-2 flex-1 text-sm text-gray-500 dark:text-ink-3">{{ $role['description'] }}</p>
                <span class="mt-5 text-sm font-semibold text-blue-700 dark:text-accent-text">
                    {{ $role['cta'] }}
                </span>
            </a>
        @endforeach
    </div>

</div>
@endsection

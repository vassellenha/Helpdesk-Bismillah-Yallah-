@extends('layouts.portal')

@section('title', 'Mockup Review — Pilih Role')

@section('content')
<div class="mx-auto max-w-6xl px-6 py-16">
    <p class="text-sm font-semibold tracking-wide text-blue-700">
        {{ strtoupper(config('helpdesk.company')) }} · {{ strtoupper(config('helpdesk.product')) }}
    </p>
    <h1 class="mt-2 text-4xl font-extrabold text-gray-900">Mockup Review — Pilih Role</h1>
    <p class="mt-4 max-w-3xl text-gray-500">
        Setiap kartu di bawah membuka tampilan mulai untuk role tersebut. Setelah masuk ke salah satu
        screen, gunakan tombol
        <span class="font-semibold text-gray-700">"⇄ Switch Role"</span>
        di pojok kanan bawah untuk berpindah ke role atau screen lain kapan saja.
    </p>

    <div class="mt-10 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($roles as $role)
            <a href="{{ route($role['links'][0]['route']) }}" class="flex flex-col rounded-2xl border border-gray-100 bg-white p-6 shadow-sm transition hover:border-gray-300 hover:shadow-md">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-sm font-bold text-blue-700">
                    {{ $role['initials'] }}
                </span>
                <h2 class="mt-4 text-lg font-bold text-gray-900">{{ $role['label'] }}</h2>
                <p class="mt-2 flex-1 text-sm text-gray-500">{{ $role['description'] }}</p>
                <span class="mt-5 text-sm font-semibold text-blue-700">
                    {{ $role['cta'] }}
                </span>
            </a>
        @endforeach
    </div>

    <p class="mt-10 text-sm text-gray-400">Mockup interaktif — bukan aplikasi produksi. Data yang ditampilkan bersifat dummy.</p>
</div>
@endsection

@extends('layouts.admin')

@section('title', $title)

@section('content')
<div class="flex min-h-[50vh] flex-col items-center justify-center rounded-2xl border border-dashed border-gray-300 dark:border-edge-strong bg-white dark:bg-panel-2 text-center">
    <h1 class="text-xl font-bold text-gray-900 dark:text-ink-1">{{ $title }}</h1>
    <p class="mt-2 max-w-md text-sm text-gray-500 dark:text-ink-3">Halaman ini belum tersedia pada mockup. Gunakan menu lain untuk melihat tampilan yang sudah dibuat.</p>
</div>
@endsection

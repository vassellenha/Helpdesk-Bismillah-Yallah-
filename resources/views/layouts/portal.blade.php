<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{--
     | Dibutuhkan widget EVA: apiFetch membaca token dari sini untuk setiap
     | POST. Layout ini sebelumnya tidak punya baris ini karena halamannya
     | murni baca-saja — tanpa token, setiap pertanyaan dibalas 419 dan
     | pesannya tidak menyebut CSRF sama sekali.
    --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    @viteReactRefresh
    @include('partials.translations')
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
</head>
<body class="min-h-screen bg-[#eef0fb] dark:bg-panel-0 font-sans text-gray-900 dark:text-ink-1 antialiased">
    @yield('content')

    @include('eva._assistant-widget')
</body>
</html>

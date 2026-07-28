<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
</head>
<body class="min-h-screen bg-[#eef0fb] dark:bg-panel-0 font-sans text-gray-900 dark:text-ink-1 antialiased">
    @yield('content')
</body>
</html>

<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') · {{ config('helpdesk.product') }}</title>
    @viteReactRefresh
    @include('partials.translations', ['groups' => ['teamlead']])
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
</head>
<body class="min-h-screen bg-gray-50 dark:bg-panel-0 font-sans text-gray-900 dark:text-ink-1 antialiased">
    {{-- The Team Lead workspace renders its own full-width header (brand + 8-tab
         nav + notifications/profile) so the tabs sit inline with the logo, matching
         the mockup. The layout only provides the page shell and role switcher. --}}
    @yield('content')

    @php
        $switcherRoles = collect(config('helpdesk.roles'))
            ->map(fn ($r) => ['key' => $r['key'], 'initials' => $r['initials'], 'label' => $r['label'], 'url' => route($r['links'][0]['route'])])
            ->values();
    @endphp
    <div
        data-react="RoleSwitcher"
        data-props="{{ json_encode(['roles' => $switcherRoles, 'current' => $role ?? 'team-lead', 'portalUrl' => route('portal.index')]) }}"
    ></div>
</body>
</html>

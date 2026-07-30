<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') · EVA Knowledge</title>
    {{--
        Memulihkan keadaan sidebar SEBELUM <body> dirender.

        EVA bukan SPA — tiap menu adalah pemuatan halaman penuh. Kalau kelasnya
        baru dipasang setelah bundel JS jalan, sidebar tampil lebar dulu lalu
        menyempit di SETIAP perpindahan menu. Kedipan itu bukan cuma jelek: ia
        menggeser seluruh isi layar tepat saat mata mulai membaca.

        Sengaja skrip mentah di <head>, bukan lewat bundel: apa pun yang
        di-bundel pasti jalan setelah HTML terurai, jadi kedipannya tidak
        mungkin dihindari dari sana. Kelasnya di <html> karena <body> belum ada
        saat baris ini dijalankan.
    --}}
    <script>
        try {
            if (localStorage.getItem('eva.sidebar.collapsed') === '1') {
                document.documentElement.classList.add('eva-sidebar-collapsed');
            }
        } catch (e) {
            // Mode privat memblokir localStorage — sidebar cukup terbuka seperti biasa.
        }
    </script>
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
</head>
{{--
    Layout sendiri, bukan layouts/app.blade.php: sidebar role milik tim dan
    sidebar 13 menu EVA tidak boleh saling menumpuk.

    Kelas `eva-app` yang mengaktifkan seluruh token design system EVA. Token itu
    di-scope ke kelas ini (bukan :root) supaya halaman tim tidak ikut berubah —
    jangan hapus.
--}}
<body class="eva-app" style="margin:0">
    <div style="display:flex;min-height:100vh;width:100%">
        @include('eva._sidebar')

        <main style="flex:1;min-width:0;background:var(--canvas)">
            @yield('content')
        </main>
    </div>

    {{--
        Di layout, bukan di tiap view: konsol ini 13 layar dan bukan SPA, jadi
        tombol yang ditempel per halaman pasti ada yang terlewat.

        Aman di pojok kanan bawah karena widget EVA (yang menempati pojok yang
        sama, lihat `.eva-widget` di eva.css) hanya dipasang di halaman
        karyawan, tidak di konsol.
    --}}
    @include('eva._role-switcher')
</body>
</html>

{{--
    Tombol "⇄ Switch Role" untuk konsol EVA.

    Komponennya milik tim (resources/js/components/RoleSwitcher.jsx) dan dipakai
    apa adanya — konsol ini tidak membuat tombol tandingan dengan bentuk sendiri.
    Tombol yang sama di pojok yang sama adalah seluruh gunanya: pindah ke layar
    karyawan untuk melihat jawaban EVA dari sisi mereka, lalu kembali, tanpa
    mengetik URL.

    Blok $switcherRoles di bawah ini kembar dengan yang ada di tujuh layout tim.
    SENGAJA disalin, bukan diekstrak jadi partial bersama lalu di-include dari
    sana: repo ini klon repo tim, dan menyentuh tujuh berkas milik mereka demi
    menghemat satu blok berarti tujuh bentrokan pada pull berikutnya.

    `current` dikunci 'eva' — layout konsol tidak punya variabel $role seperti
    layout tim, dan memang tidak butuh: setiap layar di sini milik peran EVA
    Knowledge, yang sudah terdaftar di config/helpdesk.php sehingga panelnya
    menampilkan daftar peran yang persis sama dengan halaman lain.
--}}
@php
    $switcherRoles = collect(config('helpdesk.roles'))
        ->map(fn ($r) => ['key' => $r['key'], 'initials' => $r['initials'], 'label' => $r['label'], 'url' => route($r['links'][0]['route'])])
        ->values();
@endphp
<div
    data-react="RoleSwitcher"
    data-props="{{ json_encode(['roles' => $switcherRoles, 'current' => 'eva', 'portalUrl' => route('portal.index')]) }}"
></div>

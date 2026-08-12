{{--
    Tombol switch role — HANYA muncul untuk orang yang memang punya lebih dari
    satu role.

    Daftarnya datang dari komposer di AppServiceProvider dan berisi role yang
    BENAR-BENAR dipegang user yang sedang masuk, bukan seluruh role yang ada di
    config seperti sebelumnya. Dulu tombol ini menampilkan ketujuhnya kepada
    siapa pun, karena memang belum ada yang bisa ditanyai "kamu siapa".

    Ambang satu bukan sekadar kerapian tampilan: bagi pemegang satu role,
    tombolnya tidak menawarkan tujuan apa pun selain layar yang sedang dibuka.

    `$role` diisi masing-masing layout untuk menandai entri yang sedang aktif.
--}}
@php($entries = $roleSwitcherEntries ?? collect())

@if ($entries->count() > 1)
    <div
        data-react="RoleSwitcher"
        data-props="{{ json_encode([
            'roles' => $entries,
            'current' => $role ?? null,
            'portalUrl' => route('portal.index'),
        ]) }}"
    ></div>
@endif

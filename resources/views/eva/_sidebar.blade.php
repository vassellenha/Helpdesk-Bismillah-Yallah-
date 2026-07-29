{{--
    Sidebar EVA. Active state dibaca dari route saat ini, bukan dari state
    klien — tiap layar adalah route nyata (bukan SPA seperti mockup), jadi
    menyimpan "view aktif" di JavaScript hanya akan menduplikasi apa yang sudah
    diketahui URL.

    Gayanya hidup di `resources/css/eva.css` (kelas `.eva-sidebar`, `.eva-nav-*`),
    BUKAN sebagai style inline seperti sebelumnya. Alasannya bukan kerapian:
    keadaan tertutup harus bisa menimpa padding dan display tiap menu, dan aturan
    CSS tidak bisa menimpa style inline tanpa `!important` di mana-mana.

    Lebar 280px, bukan 236px: setelah ikon masuk, label terpanjang
    ("Ticket Recommendation") bersama lencana SOON tidak lagi muat satu baris.
    Melebarkan sidebar lebih baik daripada memaksa label membungkus — menu dua
    baris membuat daftar 13 menu jadi sulit dipindai.

    Sticky, bukan fixed: `position:fixed` mencabut sidebar dari alur layout,
    sehingga lebarnya harus dibayar ulang dengan margin di `<main>` — dua angka
    yang wajib selalu sama dan pasti suatu saat lupa disamakan. Sticky tetap
    menempati kolomnya sendiri, jadi lebarnya cukup ditulis sekali (dan kini
    cukup diubah sekali juga, lewat --eva-sidebar-width).

    `align-self:flex-start` wajib: induknya `display:flex` dengan
    `align-items:stretch` bawaan, yang meregangkan aside setinggi konten dan
    membuat sticky diam-diam tidak berefek — tidak ada galat, hanya tidak
    menempel.

    `overflow-y:auto` untuk layar pendek: 13 menu bisa lebih tinggi dari
    viewport, dan tanpa ini menu paling bawah tidak akan pernah bisa dijangkau.
--}}
@php
    $navGroups = config('eva.nav');
@endphp

<aside id="eva-sidebar" class="eva-sidebar">
    <div class="eva-brand">
        <div class="eva-brand-mark">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
        </div>
        <div class="eva-brand-text">
            <div style="font-size:14px;font-weight:700;letter-spacing:-.2px;line-height:1.1">{{ config('eva.brand.title') }}</div>
            <div style="font-size:11px;color:var(--slate-500);font-weight:500">{{ config('eva.brand.subtitle') }}</div>
        </div>

        {{--
            Tombolnya tinggal di dalam sidebar, bukan mengambang di atas konten:
            saat tertutup ia tetap terlihat di rail, jadi tidak ada keadaan di
            mana sidebar tertutup dan cara membukanya ikut hilang.
        --}}
        <button
            type="button"
            class="eva-sidebar-toggle"
            data-eva-sidebar-toggle
            aria-controls="eva-sidebar"
            aria-expanded="true"
            aria-label="Tutup sidebar"
            title="Tutup sidebar"
        >
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
        </button>
    </div>

    @foreach ($navGroups as $group)
        @if ($group['group'])
            <div class="eva-nav-group">{{ $group['group'] }}</div>
            <span class="eva-nav-group-divider" aria-hidden="true"></span>
        @endif

        @foreach ($group['items'] as $item)
            @php
                $isActive = $item['route'] && request()->routeIs($item['route']);
                $classes = 'eva-nav-item'
                    .($isActive ? ' eva-nav-item--active' : '')
                    .($item['route'] ? '' : ' eva-nav-item--muted');
            @endphp

            {{--
                `title` bukan hiasan: saat sidebar tertutup, label satu-satunya
                penanda menu ikut hilang, dan ikon Lucide saja tidak cukup untuk
                membedakan 13 menu. Ini yang membuat rail tetap bisa dipakai.
            --}}
            <a
                href="{{ $item['route'] ? route($item['route']) : route('eva.placeholder', $item['key']) }}"
                class="{{ $classes }}"
                title="{{ $item['label'] }}"
                @if ($isActive) aria-current="page" @endif
            >
                @include('eva._icon', ['key' => $item['key']])
                <span class="eva-nav-label">{{ $item['label'] }}</span>

                {{-- Layar iterasi berikutnya: tetap ditampilkan supaya bentuk konsol jujur, tapi ditandai belum aktif. --}}
                @unless ($item['route'])
                    <span class="eva-nav-soon">SOON</span>
                @endunless
            </a>
        @endforeach
    @endforeach

    <div class="eva-sidebar-footer">
        <a href="{{ route('portal.index') }}" class="eva-nav-item eva-nav-item--muted" title="Kembali ke Portal">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex:none" aria-hidden="true"><path d="M19 12H5"/><path d="M12 19l-7-7 7-7"/></svg>
            <span class="eva-nav-label">Kembali ke Portal</span>
        </a>
    </div>
</aside>

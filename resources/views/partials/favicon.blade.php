{{--
    Favicon Adhi Karya — dipakai di semua layout lewat @include, supaya satu
    sumber file yang diubah kalau logonya berganti lagi.

    SVG untuk browser modern (tajam di semua ukuran), PNG 512 dan .ico sebagai
    fallback untuk browser/tab lama yang belum dukung rel="icon" bertipe SVG.
--}}
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
<link rel="icon" type="image/svg+xml" href="{{ asset('images/adhi-karya-logo.svg') }}">
<link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">

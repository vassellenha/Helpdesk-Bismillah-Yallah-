{{--
    Ikon menu EVA.

    Path SVG-nya disalin apa adanya dari mockup (`Mockup Helpdesk/eva/console.html`)
    — set ikon Lucide, stroke-based, viewBox 24×24. Yang disalin hanya data
    path-nya, bukan markup pembungkusnya.

    Sengaja inline, bukan berkas .svg terpisah atau paket ikon: tiga belas ikon
    tidak sebanding dengan satu dependensi baru di package.json milik tim, dan
    stroke="currentColor" membuat ikon otomatis ikut warna active state menu
    tanpa perlu varian file per warna.

    Dipakai: @include('eva._icon', ['key' => 'coverage'])
--}}
@php
    $paths = [
        'coverage' => '<rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/>',

        'articles' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',

        'faq' => '<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/>',

        'documents' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 13h6M9 17h4"/>',

        'taxonomy' => '<path d="M3 5a2 2 0 0 1 2-2h3l2 2h4a2 2 0 0 1 2 2v1"/><path d="M8 13h3M8 18h3"/><path d="M11 13v5"/><rect width="7" height="4" x="14" y="11" rx="1"/><rect width="7" height="4" x="14" y="16" rx="1"/>',

        'unanswered' => '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22z"/><path d="M9.5 9a2.5 2.5 0 0 1 4.6 1.2c0 1.5-2.1 2.3-2.1 2.3"/><path d="M12 16h.01"/>',

        'recommendation' => '<circle cx="6" cy="19" r="3"/><path d="M9 19h8.5a3.5 3.5 0 0 0 0-7h-11a3.5 3.5 0 0 1 0-7H15"/><circle cx="18" cy="5" r="3"/>',

        'training' => '<rect width="18" height="18" x="3" y="3" rx="2"/><path d="M9 9h6v6H9z"/><path d="M9 1v3M15 1v3M9 20v3M15 20v3M20 9h3M20 14h3M1 9h3M1 14h3"/>',

        'preview' => '<path d="M8 10h.01M12 10h.01M16 10h.01M21 11.5a8.38 8.38 0 0 1-8.5 8.4 9 9 0 0 1-3.9-.9L3 21l1.9-5.6A8.38 8.38 0 0 1 4 11.5 8.5 8.5 0 0 1 12.5 3 8.38 8.38 0 0 1 21 11.5z"/>',

        'conversations' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',

        'analytics' => '<path d="M3 3v18h18"/><path d="M18 17V9M13 17V5M8 17v-3"/>',

        'ratings' => '<path d="M11.5 3.5l2.4 4.9 5.4.8-3.9 3.8.9 5.4-4.8-2.5-4.8 2.5.9-5.4L3.7 9.2l5.4-.8z"/>',

        'search-settings' => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',

        'apps' => '<rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/>',
    ];
@endphp

<svg
    width="17" height="17" viewBox="0 0 24 24" fill="none"
    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
    style="flex:none"
    aria-hidden="true"
>{!! $paths[$key] ?? '' !!}</svg>

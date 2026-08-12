<?php

/*
| Menu sidebar EVA Knowledge Admin Console.
|
| Terpisah dari config/helpdesk.php milik tim: sidebar ini bukan navigasi role
| Helpdesk, melainkan konsol tersendiri dengan 13 menunya. Menumpuk keduanya di
| satu layout menghasilkan dua sidebar.
|
| `route` bernilai null berarti layar itu belum dibangun — sidebar tetap
| menampilkannya (supaya struktur konsol terlihat utuh) tapi mengarah ke
| halaman placeholder, bukan menyembunyikannya seolah tidak ada.
*/

return [
    /*
    | OCR & pembacaan PDF (poppler + Tesseract, on-premise).
    |
    | Path binari SENGAJA bisa diatur: PHP kerap berjalan dengan PATH minimal
    | (PHP-FPM di server, Herd di macOS) sehingga /opt/homebrew/bin dan
    | sejenisnya tidak terlihat. Dibiarkan null berarti "cari sendiri" —
    | OcrBinaries akan menelusuri PATH lalu direktori yang lazim.
    */
    'ocr' => [
        'pdfinfo' => env('EVA_PDFINFO_PATH'),
        'pdftotext' => env('EVA_PDFTOTEXT_PATH'),
        'pdftoppm' => env('EVA_PDFTOPPM_PATH'),
        'tesseract' => env('EVA_TESSERACT_PATH'),

        // "ind" wajib ada; tanpa itu Tesseract memakai Inggris dan hasil pada
        // teks Indonesia berantakan. "eng" disertakan karena SOP TI penuh
        // istilah Inggris yang bercampur di kalimat yang sama.
        'languages' => env('EVA_OCR_LANGUAGES', 'ind+eng'),

        // 300 dpi ambang lazim OCR: di bawahnya akurasi turun tajam, di atasnya
        // hanya menambah waktu tanpa perbaikan berarti.
        'dpi' => (int) env('EVA_OCR_DPI', 300),
        'max_pages' => (int) env('EVA_OCR_MAX_PAGES', 30),
        'timeout' => (int) env('EVA_OCR_TIMEOUT', 120),
    ],

    /*
    | Umur maksimal dokumen berstatus `processing` sebelum disapu jadi `failed`
    | oleh `eva:sweep-stuck-documents`. Harus TETAP di atas timeout job
    | IndexDocument (900 detik) supaya OCR panjang yang masih berjalan tidak
    | ikut divonis gagal.
    */
    'stuck_after_minutes' => (int) env('EVA_STUCK_AFTER_MINUTES', 30),

    /*
    | Masa simpan log percakapan dan pertanyaan tak terjawab, dalam hari.
    | Disapu oleh `eva:purge-expired-logs`.
    |
    | Ini penghapusan PERMANEN dan tidak ada tempat sampahnya. Menurunkan
    | angkanya berarti memperpendek jendela kerja admin untuk menutup celah
    | materi — pertanyaan yang belum sempat dijawab akan hilang, bukan
    | ditandai. Naikkan kalau tim belum sempat mengejar backlog-nya.
    */
    'log_retention_days' => (int) env('EVA_LOG_RETENTION_DAYS', 14),

    /*
    | Batas potongan (chunk) per dokumen — lihat DocumentIndexer::MAX_CHUNKS.
    | Dokumen yang melewatinya tetap terindeks, ekornya dipangkas, dan
    | pemangkasannya dicatat di log.
    */
    'max_chunks' => (int) env('EVA_MAX_CHUNKS', 500),

    'brand' => [
        'title' => 'EVA Knowledge',
        'subtitle' => 'Admin Console',
    ],

    'nav' => [
        [
            'group' => null,
            'items' => [
                ['key' => 'coverage', 'label' => 'Coverage Dashboard', 'route' => 'eva.coverage'],
            ],
        ],
        [
            'group' => 'KNOWLEDGE BASE',
            'items' => [
                ['key' => 'articles', 'label' => 'Article Library', 'route' => 'eva.articles'],
                ['key' => 'faq', 'label' => 'Manage FAQ', 'route' => 'eva.faq'],
                ['key' => 'documents', 'label' => 'Documents', 'route' => 'eva.documents'],
                ['key' => 'taxonomy', 'label' => 'Category & Taxonomy', 'route' => 'eva.taxonomy'],
            ],
        ],
        [
            'group' => 'AI TRAINING',
            'items' => [
                ['key' => 'unanswered', 'label' => 'Unanswered Questions', 'route' => 'eva.unanswered'],
                ['key' => 'recommendation', 'label' => 'Ticket Recommendation', 'route' => 'eva.recommendation'],
                ['key' => 'training', 'label' => 'Training Overview', 'route' => 'eva.training'],
            ],
        ],
        [
            'group' => 'ASSISTANT',
            'items' => [
                ['key' => 'preview', 'label' => 'EVA Preview', 'route' => 'eva.preview'],
                ['key' => 'conversations', 'label' => 'Log Percakapan', 'route' => 'eva.conversations'],
            ],
        ],
        [
            'group' => 'INSIGHTS & QUALITY',
            'items' => [
                ['key' => 'analytics', 'label' => 'Analytics', 'route' => 'eva.analytics'],
                ['key' => 'ratings', 'label' => 'Rating & Feedback', 'route' => 'eva.ratings'],
            ],
        ],
        [
            'group' => 'CONFIGURATION',
            'items' => [
                ['key' => 'search-settings', 'label' => 'Search Settings', 'route' => 'eva.search-settings'],
                ['key' => 'apps', 'label' => 'Apps & Systems', 'route' => 'eva.apps'],
            ],
        ],
    ],
];

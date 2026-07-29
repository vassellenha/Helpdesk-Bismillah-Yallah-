<?php

namespace App\Services\Knowledge;

/**
 * Pencarian A — mencari JAWABAN. Sumbernya hanya kb_articles dan kb_faqs
 * (aturan #3: EVA tidak membaca tiket).
 *
 * Ini satu-satunya kontrak yang boleh dipanggil controller/komponen. Pindah ke
 * pgvector nanti = menukar satu binding di AppServiceProvider, tanpa menyentuh
 * pemanggilnya.
 *
 * Jangan menggabungkan antarmuka ini dengan pencarian nama masalah di
 * service_catalog_subjects (Pencarian B) — Service Catalog tidak berisi
 * jawaban, dan menggabungkan keduanya membuat EVA mengutip katalog seolah-olah
 * itu solusi.
 */
interface KnowledgeSearch
{
    /**
     * Ambang keyakinan. Di bawah ini EVA menyatakan belum menemukan jawaban
     * dan menawarkan draf tiket — EVA tidak pernah menebak.
     */
    public const MIN_CONFIDENCE = 55;

    /**
     * Keyakinan di bawah ini masih dijawab, tapi dengan bahasa yang menahan
     * diri ("kemungkinan besar…") — mengikuti perilaku `hedged` di mockup.
     */
    public const HEDGE_CONFIDENCE = 80;

    /**
     * @return SearchHit[] Terurut dari keyakinan tertinggi. Boleh kosong.
     */
    public function cari(string $pertanyaan, int $limit = 5): array;
}

# EVA — Audit Adversarial & Checklist Pengujian Ekstrim

**Peran:** Senior QA / Security Tester
**Tanggal:** 2026-07-29
**Cakupan:** 6 Aturan Mutlak, Pencarian A & B, Pipeline Dokumen/Queue, React Islands, SSO/Keamanan lintas aplikasi.

Dokumen ini **bukan** daftar dugaan. Setiap baris yang bertanda `[CELAH]` sudah **dibuktikan dengan tes yang dijalankan**, ada di `tests/Feature/Eva/AdversarialAuditTest.php` (26 tes, hijau — artinya perilakunya memang seperti yang ditulis, termasuk yang salah). Yang bertanda `[AMAN]` juga diuji dan memang sudah benar.

Jalankan bukti:

```bash
php artisan test --filter=AdversarialAuditTest
```

Konvensi label di test:
- `[AMAN]` — perilaku sudah benar; tes menahannya tetap benar.
- `[CELAH]` — perilaku **salah**; tes mengunci kesalahannya sebagai bukti. **Saat celah ditutup, tes ini harus gagal** → itu sinyal, bukan regresi. Balik assertion-nya lalu pindahkan label ke `[AMAN]`.

---

## STATUS PERBAIKAN (per 29 Juli 2026, sesi sore)

Delapan dari sepuluh celah sudah ditutup; tesnya sudah dibalik jadi `[AMAN]`
dengan nama metode baru. Rincian langkah dan sisa pekerjaan ada di
`docs/eva-qa-perbaikan-handoff.md`.

| # | Status | Penutupnya |
|---|--------|-----------|
| 1 | ✅ ditutup | middleware `eva.access` di grup route `eva` |
| 2 | ✅ ditutup | idem #1 |
| 3 | ✅ ditutup | kolom `kb_articles.updated_by` + diisi di `ArticleController::update()` |
| 4 | ⛔ **blocked** | butuh identitas nyata (SSO) — lihat catatan FASE 2 di handoff |
| 5 | ✅ ditutup | `throttle:20,1` di `eva/api/preview/ask` |
| 6 | ✅ ditutup | command terjadwal `eva:sweep-stuck-documents` (tiap 5 menit) |
| 7 | ⛔ **blocked** | sama dengan #4 |
| 8 | ✅ ditutup | `ScoredText` — kolom `tags` tidak lagi ikut dinilai |
| 9 | ✅ ditutup | `SubjectMatcher`: toleransi turun ke 1 huruf + dua huruf pertama wajib sama |
| 10 | ⏸️ **keputusan produk** | belum diubah — butuh keputusan pemilik, lihat handoff FASE 5 |

Tiga temuan LOW: ekstensi dari nama berkas ✅ ditutup (ditentukan dari isi),
batas potongan ✅ ditutup (`eva.max_chunks`), dan 500 di non-MySQL ✅ ditutup
(pesan RuntimeException yang menunjuk ke DB_CONNECTION).

Nama metode tes ikut berubah saat celahnya ditutup — kolom "Bukti" di tabel di
bawah memakai nama LAMA. Cari dengan `grep -n "function test_" tests/Feature/Eva/AdversarialAuditTest.php`.

---

## RINGKASAN EKSEKUTIF — 10 celah terurut severity

| # | Severity | Celah | Bukti (metode test) |
|---|----------|-------|---------------------|
| 1 | **CRITICAL** | Semua layar `/eva/*` terbuka tanpa login | `test_celah_semua_layar_eva_terbuka_tanpa_login` |
| 2 | **CRITICAL** | Semua endpoint tulis (hapus FAQ/artikel/dokumen, edit) terbuka tanpa login | `test_celah_endpoint_tulis_terbuka_tanpa_login` |
| 3 | **CRITICAL** | Aturan #1 tembus lewat `PUT /eva/api/articles/{id}` — ganti seluruh body artikel = artikel manual de-facto | `test_aturan_1_ditembus_lewat_update_artikel_tanpa_login` |
| 4 | **HIGH** | IDOR `ticket-draft`: ubah `outcome` log milik siapa pun → geser deflection rate + hilang dari Unanswered | `test_celah_idor_ticket_draft_mengubah_log_orang_lain` |
| 5 | **HIGH** | Tidak ada rate limit di `preview/ask` (pencarian terberat) | `test_celah_tidak_ada_rate_limit_di_preview_ask` |
| 6 | **HIGH** | Dokumen menggantung `processing` selamanya bila worker mati (tanpa watchdog) | `test_celah_dokumen_menggantung_processing_saat_worker_mati` |
| 7 | **MEDIUM** | IDOR percakapan & rating: sisip giliran / nilai ke `conversation`/`log` orang lain | `test_celah_idor_menyisipkan_giliran...`, `test_celah_rating_bisa_diberikan...` |
| 8 | **MEDIUM** | Tag-stuffing membajak skor keyakinan Pencarian A tanpa menyentuh isi | `test_celah_tag_stuffing_membajak_skor_keyakinan` |
| 9 | **MEDIUM** | Jarak edit mencocokkan kata beda-makna (printer↔pointer) sebagai typo | `test_celah_jarak_edit_mencocokkan_kata_yang_berbeda_makna` |
| 10 | **MEDIUM** | Seri antar-subject beda-nama → EVA menyerah diam-diam tanpa bertanya balik | `test_celah_seri_beda_nama_tidak_bertanya_balik` |
| — | **LOW** | Tanpa batas jumlah chunk per dokumen; ekstensi dipercaya dari nama file; `preview/ask` 500 (bukan pesan terbaca) di non-MySQL | `test_celah_tidak_ada_batas_jumlah_potongan`, `test_celah_ekstensi_dipercaya...` |

---

## §1 — ENAM ATURAN MUTLAK

### ✅ Yang sudah kokoh
- **#1 (no manual article):** tidak ada route `POST /eva/api/articles`; fallback `{key}` GET-only → `404`. `test_aturan_1_tidak_ada_endpoint_pembuat_artikel`
- **#2 (FAQ terbit langsung):** `kb_faqs` **tidak punya kolom status**; FAQ baru langsung `answerable()`. `test_aturan_2_faq_terbit_langsung`
- **#3 (search tak baca tickets):** `DB::listen` membuktikan Pencarian B tidak menyentuh `tickets`. `test_aturan_3_...`
- **#4 (berhenti di draf):** `ticket-draft` tidak menambah baris `tickets`. `test_aturan_4_...`
- **#5 (katalog read-only):** snapshot 3 tabel katalog identik sebelum/sesudah siklus penuh EVA. `test_aturan_5_...`
- **#6 (approval di luar EVA):** dijaga **statis** — scan seluruh `app/Services/Knowledge`, `app/Http/Controllers/Eva`, `app/Jobs/Knowledge`: satu-satunya sebutan `approval` adalah **membaca** flag `requires_approval` katalog. `test_aturan_6_kode_eva_tidak_menulis_approval`

> Catatan metode: #6 sengaja diuji secara statis, bukan lewat request. Tabel approval belum ada di repo; tes perilaku akan "hijau karena tabelnya tidak ada" dan tetap hijau di hari tabelnya lahir — hijau yang menipu. Scan kode menutup celah itu.

### 🔴 CELAH #3 (severity CRITICAL) — Aturan #1 ditembus lewat pintu belakang
`PUT /eva/api/articles/{id}` menerima `title`, `summary`, `body` bebas dan menimpanya (`ArticleController::update`). Aturan #1 melarang **kelahiran** artikel manual, tapi **isi** artikel — yang justru dibaca EVA — bisa diganti total oleh siapa pun (lihat celah #1/#2: tanpa login). Satu dokumen sah cukup jadi cangkang; setelah artikel lahir, body-nya bisa jadi apa saja (mis. `wa.me/...` phishing).

**Bukti:** `test_aturan_1_ditembus_lewat_update_artikel_tanpa_login` menulis body berisi `wa.me` dan lolos `assertOk()`.

**Rekomendasi:** (a) pasang auth+policy (lihat §5); (b) pertimbangkan audit-trail `updated_by` untuk artikel; (c) bila body artikel dianggap "milik admin setelah disunting", minimal catat siapa penyuntingnya.

---

## §2 — PENCARIAN A & B

### Pencarian B (SubjectMatcher) — diuji penuh di SQLite

| Skenario | Hasil | Test |
|----------|-------|------|
| Ambang ganda 50/30 di titik batas persis | AMAN | `test_ambang_ganda_50_dan_30` |
| Tie-guard nama kembar beda-layanan → auto-fill ditahan + `calonSeri` isi 2 | AMAN | `test_tie_guard_menahan_autofill...` |
| Stemming imbuhan (`membukanya`↔`dibuka`) | AMAN (via `QuestionTokenizer`, sudah ada `EvaResponderTest`) | — |
| Kata pendek wajib persis (`sap`≠`sup`, `vpn`≠`apn`) | AMAN | `test_celah_jarak_edit...` (assertion kedua) |

**🟠 CELAH #9 — jarak edit lintas-makna.** `allowedDistance()` memberi toleransi 2 huruf untuk kata ≥7 huruf. `levenshtein('printer','pointer')=1` → "pointer macet" mencocokkan subject "Printer Macet". Untuk kata teknis panjang (`forticlient`, `sharepoint`) satu-dua huruf beda bisa menyeret ke subject yang salah. **Bukti:** `test_celah_jarak_edit_mencocokkan_kata_yang_berbeda_makna`.
**Rekomendasi:** turunkan toleransi kata panjang ke 1, atau kunci huruf pertama (typo jarang di huruf awal), atau whitelist istilah teknis yang wajib persis.

**🟠 CELAH #10 — seri beda-nama menyerah diam-diam.** Bila dua subject **beda nama** sama-sama dalam `TIE_MARGIN` dan ≥ `MIN_CONFIDENCE`, `terbaik()` menahan auto-fill (benar) tapi `calonSeri()` sengaja mengembalikan `[]` (hanya seri **nama identik** yang ditanyakan). Akibat: `EvaResponder` langsung `noAnswer()` → draf dengan subject **kosong**, padahal dua kandidat kuat ada. Tidak terlihat di layar mana pun. **Bukti:** `test_celah_seri_beda_nama_tidak_bertanya_balik`.
**Rekomendasi:** keputusan produk — apakah "reset akun" (SAP vs SILO beda-nama) layak ditanyakan? Bila ya, longgarkan `calonSeri` untuk seri beda-nama dengan pembeda layanan/subcategory.

### Pencarian A (FulltextKnowledgeSearch) — **butuh MySQL, uji manual**

`whereFullText()` **tidak ada di SQLite** → setiap `preview/ask` melempar `RuntimeException` di lingkungan non-MySQL. Ini sendiri temuan (**LOW**): server dengan `DB_CONNECTION` salah setel akan 500, bukan pesan terbaca. Di suite audit kami stub `KnowledgeSearch` agar test lain jalan; jalur FULLTEXT-nya diuji terpisah di `FulltextKnowledgeSearchTest` (butuh DB `helpdesk_eva_test`).

**🟡 CELAH #8 — tag-stuffing.** `ConfidenceScorer::score()` menilai body = `summary + body + tags` (Article) / `answer + tags` (FAQ). Menaruh kata pertanyaan target di kolom `tags` menaikkan skor tanpa relevansi isi. Digabung celah #1/#2 (tulis tanpa login), siapa pun bisa membajak jawaban pertanyaan apa pun. **Bukti:** `test_celah_tag_stuffing_membajak_skor_keyakinan` (0 → ≥60).
**Rekomendasi:** keluarkan `tags` dari sinyal skor, atau beri bobot jauh lebih kecil daripada title/body.

**✅ Sinonim tak bisa menyelundupkan wildcard.** Term `%`, `_` dipecah tokenizer → tidak lolos ke `LIKE '%..%'` fallback. `test_sinonim_tidak_bisa_menyelundupkan_wildcard`.

### Prosedur manual Pencarian A (di MySQL/MariaDB)

```bash
# 1. Fallback LIKE saat token < innodb_ft_min_token_size (default 3)
#    "vpn mati" → FULLTEXT nol baris, fallback LIKE harus menemukan.
php artisan tinker
>>> app(\App\Services\Knowledge\KnowledgeSearch::class)->cari('vpn mati');

# 2. Stemming: pastikan "membukanya" dan artikel "cara dibuka" bertemu.
>>> app(\App\Services\Knowledge\KnowledgeSearch::class)->cari('cara membukanya');

# 3. Regresi diam: jalankan suite MySQL sungguhan.
DB_CONNECTION=mysql php artisan test tests/Feature/Knowledge/FulltextKnowledgeSearchTest.php
```

Checklist manual Pencarian A:
- [ ] `membukanya` vs `dibuka` → hit yang sama (stemming konsisten pertanyaan & isi).
- [ ] `vpn mati` (2 huruf token) → fallback LIKE menutup lubang `innodb_ft_min_token_size`.
- [ ] Query 1 kata (`sap`) kena `SHORT_QUERY_DAMPING` (×0.75) → tidak dijawab mantap.
- [ ] Sinonim di tahap recall: artikel yang hanya menulis "password" muncul untuk "sandi".
- [ ] Toggle FAQ off di Training → efek terasa di pencarian **berikutnya** (bukan perlu restart).

---

## §3 — PIPELINE DOKUMEN & ASYNC QUEUE

| Skenario | Hasil | Test |
|----------|-------|------|
| XLSX tanpa teks tempel ditolak seketika (422) | AMAN | `test_xlsx_ditolak_di_muka` |
| XLSX + teks tempel → 202 (jalur disengaja) | AMAN | `test_xlsx_boleh_masuk_dengan_teks_tempel` |
| DOCX tanpa `word/document.xml` → `failed` + `failure_reason` terbaca | AMAN | `test_docx_rusak_gagal_dengan_alasan_terbaca` |

**🔴 CELAH #6 (HIGH) — dokumen menggantung `processing` selamanya.** `Queue::fake()` (mensimulasikan worker mati): dokumen tetap `processing`, `failure_reason=null`, bahkan setelah `travel(7)->days()`. Tidak ada watchdog / batas umur / indikator "worker mati". Invarian "tidak boleh tertinggal processing" hanya dipegang **saat job benar-benar jalan** (`IndexDocument::failed()`), bukan saat job **tak pernah** dijalankan. **Bukti:** `test_celah_dokumen_menggantung_processing_saat_worker_mati`.
**Rekomendasi:** (a) command terjadwal `documents:sweep-stuck` menandai `processing` > N menit jadi `failed`; (b) `queue:monitor` + healthcheck; (c) tampilkan umur "processing" di UI agar macet terlihat.

**🟢 LOW — ekstensi dari nama file.** `store()` mengambil ekstensi dari `getClientOriginalExtension()`. File `payload.txt` berisi `<?php ...` masuk, terbaca sebagai TXT, jadi body artikel. Tersimpan di **disk privat** (bukan public) → bukan RCE, tapi melanggar janji "5 format" dan mengisi KB dengan sampah. **Bukti:** `test_celah_ekstensi_dipercaya_dari_nama_berkas`.

**🟢 LOW — tanpa batas jumlah chunk.** 20 MB ÷ ~900 char = puluhan-ribu `INSERT` dalam satu `DB::transaction`. **Bukti:** `test_celah_tidak_ada_batas_jumlah_potongan`.
**Rekomendasi:** batasi jumlah chunk / panjang teks terindeks; chunk sisanya "truncated".

### OCR / Tesseract — uji manual (butuh binari poppler+tesseract)
Kode `PopplerTesseractPdfReader` sudah menutup dua hal penting secara benar (dibaca, bukan diuji di CI):
- **Timeout 900s job + 120s/proses** → `ProcessTimedOutException` ditangkap, return `null`, worker tidak menggantung.
- **Gambar sementara SELALU dihapus** via `finally { @unlink($image); @unlink($prefix); }` — termasuk saat OCR gagal (mencegah bocornya halaman SOP di `/tmp`).

Checklist manual OCR:
- [ ] PDF rusak/terpotong → `pdfinfo` gagal → `pageCount=0` → dokumen `failed` beralasan, bukan crash.
- [ ] PDF pindai besar (>30 hal) → dibatasi `max_pages`, tidak habiskan disk.
- [ ] Bunuh proses `pdftoppm`/`tesseract` di tengah jalan → cek `/tmp` bersih dari `eva-ocr*.png`.
- [ ] Server tanpa binari OCR → `canRead('PDF')=false` → UI berhenti menjanjikan PDF, upload PDF diminta teks tempel.

---

## §4 — UI/UX & REACT ISLANDS

Diverifikasi lewat **pembacaan kode** (komponen React tak dieksekusi di PHPUnit) + tes XSS server-side.

- **✅ Dropdown "Semua" tidak mengosongkan tabel.** Pola konsisten di seluruh komponen: `<option value={ALL}>` memakai konstanta `ALL` (bukan `value=""`), dan filter membandingkan `=== ALL`. Kembali ke "Semua" mengembalikan semua baris. (`EvaArticleLibrary.jsx`, `EvaFaqManager.jsx`, `EvaDocuments.jsx`).
- **✅ Pagination reset ke hal.1 saat filter berubah.** `usePagination(items, size, resetKey)` — `useEffect([resetKey]) → setPage(1)`, dan `page` dijepit `Math.min(page, totalPages)`. Buka hal.5 lalu ketik query 3-baris → otomatis hal.1, tidak blank. Semua pemanggil mengoper `resetKey` gabungan filter (mis. `` `${query}|${service}|${visibility}|${tag}` ``). Satu-satunya tanpa resetKey: `EvaCoverageDashboard` & `EvaTicketRecommendation` — tabelnya tak punya filter live, jadi aman.
- **✅ Sidebar via localStorage tak merusak layout.** Kelas `eva-sidebar-collapsed` menempel di `<html>`, di-set skrip `<head>` sebelum body render (anti-flicker); `try/catch` di sekitar `localStorage` (mode privat tak mematikan tombol).
- **✅ Komponen tak terdaftar = silent-ish fail terkendali.** `app.jsx` `mountIslands()`: komponen absen dari `registry` → `console.warn`, node kosong, halaman lain tetap hidup. **Ini yang diminta** — tidak crash seluruh halaman.

**🟡 CELAH XSS — sudah AMAN, tapi diuji karena rawan.** Komentar rating dikirim ke React lewat atribut Blade `data-props="{{ json_encode(...) }}"`. `{{ }}` meng-escape `<`, `>`, `"` → payload `"><img src=x onerror=alert(document.domain)>` menjadi entitas, tidak bisa menutup atribut. **Bukti:** `test_komentar_rating_tidak_lolos_sebagai_html` (memastikan `<img src=x` dan `"><img` **tidak** muncul mentah, tapi `&lt;img` muncul).
⚠️ **Aturan pemeliharaan:** jangan pernah ganti `{{ }}` jadi `{!! !!}` untuk props. Satu-satunya `{!! !!}` di `views/eva/` adalah `_icon.blade.php` (path SVG statis dari kode, bukan input user) — aman.

Checklist manual browser (jalankan di `/eva/*` saat `npm run dev`):
- [ ] Article Library: filter Layanan→spesifik→"Semua layanan"; tabel kembali penuh.
- [ ] Documents: buka hal.5, ketik query 3-baris; langsung hal.1, tak blank.
- [ ] Toggle sidebar, pindah menu; state tersimpan; tabel di sebelah tak bergeser rusak.
- [ ] Rating: submit komentar `"><img src=x onerror=alert(1)>`; buka dashboard admin; **tidak** ada alert.
- [ ] DevTools Console: pastikan tak ada `is not registered` untuk komponen yang seharusnya tampil.

---

## §5 — SSO, AUTENTIKASI, IDOR, RATE LIMIT

Ini **kluster paling parah**. Grup route `Route::prefix('eva')` di `routes/web.php` **tanpa satu middleware pun** — tidak `auth`, tidak `throttle`, tidak policy.

**🔴 CELAH #1 (CRITICAL) — semua layar tanpa login.** 14 layar `/eva/*` semua `assertOk()` sebagai guest. **Bukti:** `test_celah_semua_layar_eva_terbuka_tanpa_login`.

**🔴 CELAH #2 (CRITICAL) — semua endpoint tulis tanpa login.** `DELETE /eva/api/faqs/{id}` sebagai guest → FAQ terhapus. Berlaku untuk semua CUD artikel/FAQ/dokumen/sinonim/tag. **Bukti:** `test_celah_endpoint_tulis_terbuka_tanpa_login`.

**🔴 CELAH #4 (HIGH) — IDOR ticket-draft.** `answer_log_id` hanya divalidasi `exists`, tak dicek kepemilikan. Membalik `outcome` log korban → `ticket_draft` menggeser **deflection rate** (Analytics) & mengeluarkannya dari **Unanswered**. **Bukti:** `test_celah_idor_ticket_draft_mengubah_log_orang_lain`.

**🟠 CELAH #7 (MEDIUM) — IDOR percakapan & rating.**
- `preview/ask` dengan `conversation_id` orang lain → giliran penyerang tersisip ke percakapan korban. `test_celah_idor_menyisipkan_giliran_ke_percakapan_orang_lain`.
- `preview/rate` ke `answer_log_id` siapa pun → rating tercatat (atas nama persona tetap `CurrentActor::requester()`). `test_celah_rating_bisa_diberikan_ke_log_milik_siapa_pun`.

**🔴 CELAH #5 (HIGH) — tanpa rate limit di `preview/ask`.** 40 request beruntun, semua 200, 40 `AnswerLog` — endpoint yang memicu pencarian FULLTEXT+skoring terberat tanpa `throttle`. Vektor DoS murah, dan mengeruhkan data (`kb_answer_logs` & coverage) dengan sampah. **Bukti:** `test_celah_tidak_ada_rate_limit_di_preview_ask`.

**⚠️ Ketergantungan `CurrentActor` (temuan desain).** Seluruh aksi dikaitkan ke dua persona hard-coded by-email (`marcell.laforteza@`, `andi.pratama@`). Di target SSO ADHI (multi-aplikasi), tanpa integrasi identitas nyata: (a) semua audit menunjuk satu orang; (b) setiap endpoint `firstOrFail()` → **500** bila baris persona tak ada di DB produksi.

### CORS & token lintas domain (rancangan uji manual — belum ada di kode)
EVA akan dipasang di portal SSO, beda origin dari Helpdesk. Belum ada konfigurasi CORS/SSO di repo. Uji setelah integrasi:
- [ ] `config/cors.php`: `allowed_origins` **whitelist eksplisit** origin portal SSO, **bukan** `*`. Dengan cookie/kredensial, `*` ditolak browser & berbahaya.
- [ ] Preflight `OPTIONS` untuk `POST /eva/api/*` mengembalikan header yang benar hanya untuk origin tepercaya.
- [ ] Token SSO (Bearer/cookie): validasi signature, exp, audience. Tolak token aplikasi-tetangga (audience beda) meski SSO sama.
- [ ] CSRF: bila pindah ke Bearer token stateless, endpoint tetap butuh proteksi replay; bila tetap cookie-session, pastikan `SameSite` + origin check.
- [ ] Uji token kadaluarsa/dicabut → `401`, bukan akses diam-diam.

### Perbaikan minimum yang disarankan (routes/web.php)
```php
Route::prefix('eva')->name('eva.')
    ->middleware(['auth', 'can:access-eva-console'])   // #1, #2, #3
    ->group(function () {
        // ... layar & api ...
        Route::post('/api/preview/ask', ...)
            ->middleware('throttle:20,1');             // #5
    });
```
Plus: policy kepemilikan pada `answer_log_id`/`conversation_id` (#4, #7), dan sweep `processing` macet (#6, §3).

---

## CARA MENJALANKAN & MEMELIHARA

```bash
# Semua bukti audit (SQLite, cepat):
php artisan test --filter=AdversarialAuditTest

# Pencarian A jalur FULLTEXT (butuh MySQL + DB helpdesk_eva_test):
DB_CONNECTION=mysql php artisan test tests/Feature/Knowledge/FulltextKnowledgeSearchTest.php

# Suite penuh (regresi):
php artisan test
```

**Kontrak label:** saat sebuah `[CELAH]` diperbaiki, tesnya akan **gagal** karena assertion masih merekam perilaku lama. Itu benar — balik assertion, ganti label jadi `[AMAN]`, dan celah berubah jadi penjaga regresi. Dengan begitu daftar ini tak pernah basi diam-diam.

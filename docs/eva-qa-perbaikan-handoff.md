# EVA — Handoff Perbaikan Celah (status: 8 dari 10 ditutup)

> Diperbarui 29 Juli 2026 (sesi sore). Bukti tiap celah tetap di
> `tests/Feature/Eva/AdversarialAuditTest.php`; rincian temuannya di
> `docs/eva-qa-adversarial.md`.

## Keadaan sekarang

```bash
php artisan test          # 276 tes, 276 lulus
```

Tidak ada lagi tes berlabel `[CELAH]` untuk hal yang sudah diperbaiki — nama
metodenya ikut berubah dari `test_celah_*` menjadi `test_aman_*`.

| Fase | Isi | Status |
|------|-----|--------|
| 1 | Auth & throttle (#1, #2, #5) | ✅ selesai |
| 2 | IDOR kepemilikan (#4, #7) | ⛔ blocked — menunggu SSO |
| 3 | Jejak penyunting artikel (#3) | ✅ selesai |
| 4 | Watchdog dokumen macet (#6) | ✅ selesai |
| 5 | Kualitas pencarian (#8, #9, 3 LOW) | ✅ selesai kecuali #10 |
| — | #10 seri beda-nama | ⏸️ menunggu keputusan pemilik |

---

## Yang berubah

### FASE 1 — Auth & throttle

`middleware('auth')` bawaan Laravel TIDAK dipakai, dan itu disengaja: repo ini
tidak punya route bernama `login`, jadi `auth` akan me-redirect tamu ke route
yang tidak ada — tamu menerima **500**, bukan penolakan.

- **Baru:** `app/Http/Middleware/EnsureEvaConsoleAccess.php`, dialiaskan
  `eva.access` di `bootstrap/app.php`, dipasang di grup `Route::prefix('eva')`.
  Tamu ditolak **401** dengan pesan terbaca.
- **Baru:** `throttle:20,1` khusus di `POST /eva/api/preview/ask`.
- **Jembatan sementara:** `config('eva.console.local_actor')` — default
  mengikuti `APP_ENV=local`. Saat menyala, request tanpa identitas dilekatkan
  ke `CurrentActor::admin()` supaya konsol tetap bisa dipakai mengisi KB di
  mesin pengembang. `testing` dan `production` **tidak** mendapat jembatan ini.
  **Ini yang pertama harus dicabut begitu SSO terpasang** (`EVA_LOCAL_ACTOR=false`
  cukup untuk mematikannya lebih awal).
- **Tes:** semua tes yang memanggil endpoint EVA kini memakai trait
  `tests/Concerns/ActsAsEvaAdmin` (`$this->actingAsEvaAdmin()` di `setUp`).
  Yang sengaja tetap tamu hanya dua tes penjaga di §3 audit.

### FASE 3 — Jejak penyunting artikel

Kolom `kb_articles.updated_by` (migration `2026_07_29_100000_*`), diisi di
`ArticleController::update()` dari `$request->user()`, ditampilkan di Article
Library sebagai "disunting <nama>". `author_id` tidak tersentuh — dua
pertanyaan berbeda, dua kolom berbeda.

Menyunting isi artikel tetap **diizinkan**; yang ditutup adalah perubahan tanpa
jejak.

### FASE 4 — Watchdog dokumen macet

`app/Console/Commands/SweepStuckDocuments.php` (`eva:sweep-stuck-documents`),
terjadwal tiap 5 menit di `routes/console.php`. Dokumen `processing` yang lebih
tua dari `config('eva.stuck_after_minutes')` (default 30) ditandai `failed`
dengan alasan yang menunjuk ke worker, bukan ke isi berkas. Ambangnya sengaja
di atas timeout job (900 detik) supaya OCR panjang tidak ikut divonis.

Butuh `php artisan schedule:run` hidup (cron) — lihat `docs/eva-deploy.md`.

### FASE 5 — Kualitas pencarian

| Celah | Perbaikan |
|-------|-----------|
| #8 tag-stuffing | `app/Services/Knowledge/ScoredText.php` — `tags` tidak lagi ikut dinilai ConfidenceScorer. Daya temu tidak berkurang: tahap recall memang tidak pernah memindai kolom `tags`. |
| #9 jarak edit | `SubjectMatcher`: toleransi kata panjang turun dari 2 → 1, DAN dua huruf pertama wajib sama persis. |
| LOW ekstensi | `DocumentController::extensionOf()` — ekstensi dari ISI berkas; isi yang tertebak di luar daftar izin ditolak 422. Format berbasis ZIP (DOCX/XLSX) jatuh ke nama berkas karena kerap terbaca "zip". |
| LOW batas chunk | `DocumentIndexer::MAX_CHUNKS` / `config('eva.max_chunks')` = 500. Pemangkasan dicatat di log, tidak diam-diam. |
| LOW non-MySQL | `FulltextKnowledgeSearch` melempar RuntimeException berisi nama driver + petunjuk `DB_CONNECTION`, bukan SQL error mentah. |

**Catatan penting soal #9:** saran asli di dokumen ini ("turunkan toleransi ke
1") TIDAK menutup contoh yang dibuktikan — `printer` dan `pointer` hanya
berjarak 1. Yang menutupnya adalah kunci dua huruf pertama. Harganya jujur:
typo yang mengenai awal kata (`ativasi` untuk `aktivasi`) tidak lagi dikenali
dan EVA akan bertanya balik. Arah kegagalan itu dipilih sadar — bertanya lebih
murah daripada menjawab subject yang salah.

---

## Sisa pekerjaan

### FASE 2 — IDOR kepemilikan (#4, #7) — BLOCKED

Cek kepemilikan (`$log->asked_by === $actor->id`, dst) **belum ditambahkan**,
dan ini keputusan sadar: selama identitas masih persona tunggal
`CurrentActor`, setiap cek kepemilikan bernilai benar secara tautologis. Ia
akan lulus tes tanpa melindungi apa pun, lalu memberi rasa aman palsu.

Kerjakan tepat setelah identitas SSO tersedia. Tesnya sudah menunggu, masih
berlabel `[CELAH]`:

- `test_celah_idor_ticket_draft_mengubah_log_orang_lain`
- `test_celah_idor_menyisipkan_giliran_ke_percakapan_orang_lain`
- `test_celah_rating_bisa_diberikan_ke_log_milik_siapa_pun`

Pola perbaikannya (dari audit): `abort_unless($log->asked_by === $actor->id, 403)`
di `PreviewController::ticketDraft()`, `ask()` (jalur `conversation_id`), dan
`RatingController`/`PreviewController::rate()`.

### #10 — Seri beda-nama: KEPUTUSAN PRODUK, belum diubah

Tes `test_celah_seri_beda_nama_tidak_bertanya_balik` masih `[CELAH]`.
Pertanyaannya: kalau "reset password" cocok ke dua subject dengan **nama
berbeda** di layanan berbeda (SAP vs SILO), haruskah EVA bertanya balik seperti
yang sudah dilakukannya untuk subject **bernama sama**? Jangan diubah sepihak.

### Di luar jangkauan SQLite — uji manual

```bash
# Pencarian A jalur FULLTEXT (butuh MySQL + DB helpdesk_eva_test):
DB_CONNECTION=mysql php artisan test tests/Feature/Knowledge/FulltextKnowledgeSearchTest.php
```

Checklist browser React (dropdown "Semua", pagination reset, sidebar, XSS) ada
di §4 `docs/eva-qa-adversarial.md` — jalankan saat `npm run dev`.

Satu hal yang layak dicek manual setelah perubahan ini: unggah DOCX/XLSX asli
lewat browser, pastikan `extensionOf()` tidak menolaknya (jalur "tertebak zip →
jatuh ke nama berkas").

---

## Ekor sesi 29 Juli — tiga bug yang muncul saat dicoba di browser

Perbaikan di atas hijau di tes tapi menyingkap tiga hal yang hanya terlihat
saat aplikasinya benar-benar dipakai. Semuanya sudah ditutup; rinciannya di
bagian "Jebakan yang baru ketahuan" pada `docs/eva-status.md`.

1. **Migration `updated_by` belum jalan di dev MySQL** → simpan artikel 500.
   Tes hijau karena SQLite selalu bermigrasi dari nol. `php artisan migrate`.
2. **`apiFetch` tidak men-serialize body** → dua pemanggilan di layar Unanswered
   mengirim `"[object Object]"`, dan Laravel melaporkannya sebagai "field wajib
   kosong". Diperbaiki di call site + jaring pengaman di `resources/js/lib/api.js`.
3. **Portal menunjuk ke mockup tim** (`dashboard.eva`) alih-alih konsol EVA.
   `config/helpdesk.php` kini mengarah ke `eva.coverage`.

Selain itu, atas permintaan pemilik: **kartu "Dihapus dari daftar kerja" di layar
Unanswered ditiadakan** — "Hapus" kini berarti barisnya langsung hilang.
`UnansweredController::restore()` dipertahankan sebagai satu-satunya jalan
membatalkan, dipanggil manual. Belum dijawab pemilik: apakah route itu dicabut
sekalian, dan apakah `dashboard.eva` dibuat me-redirect ke `eva.coverage`.

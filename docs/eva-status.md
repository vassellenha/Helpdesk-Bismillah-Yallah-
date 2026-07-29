# EVA — Status & Handoff

> Baca ini lebih dulu di sesi baru. Detail lengkap tiap iterasi ada di
> [`eva-development-plan.md`](./eva-development-plan.md). Dokumen ini ringkasan
> "di mana kita sekarang" per **29 Juli 2026**.

---

## MULAI DARI SINI (29 Juli 2026, sesi malam) — EVA punya permukaan

**Keadaan: `php artisan test` → 286 hijau (825 assertion).** Sesi ini memasang
**widget EVA di portal**. Untuk pertama kalinya EVA bisa disentuh orang yang
bukan admin.

### Yang dikerjakan sesi ini

1. **Widget mengambang di pojok kanan bawah portal** (`layouts/portal.blade.php`).
   Ikon bulat → klik → panel chat. Empat jalur balasan bekerja sama persis
   dengan EVA Preview: menjawab, bertanya balik, mengaku belum tahu, dan
   menyiapkan draf tiket.
2. **`EvaChat` — satu jalur percakapan untuk dua permukaan.** Seluruh urutan
   (buka percakapan → tanya → catat giliran → nilai → draf) dulu hidup di dalam
   `PreviewController`, sehingga permukaan kedua hanya bisa dibuat dengan
   menyalinnya. Sekarang `PreviewController` dan `AssistantController` sama-sama
   tipis di atas layanan yang sama.
3. **Kotak catatan penilaian akhirnya ada isinya** — utang yang sengaja ditunda
   28 Juli karena "EvaPreview adalah layar ADMIN". Widget inilah momen yang
   ditunggu itu. Terverifikasi di MySQL: baris ke-49 `kb_answer_ratings` adalah
   **yang pertama** punya `reason` dan `comment` terisi; 48 sebelumnya semuanya
   NULL. Panel "Tanggapan tertulis terbaru" di Rating & Feedback berhenti kosong.
4. **Celah IDOR percakapan DITUTUP.** Lihat di bawah.

### Endpoint baru — SENGAJA di luar `eva.access`

```
POST /assistant/api/ask          throttle 20/menit
POST /assistant/api/rate         throttle 30/menit
POST /assistant/api/rate/note    throttle 30/menit
POST /assistant/api/ticket-draft throttle 20/menit
```

Grup `eva/` adalah konsol admin; widget harus bisa dipakai siapa pun yang
membuka portal. Menaruhnya di dalam grup akan menolak setiap karyawan dengan
401 begitu SSO memberi identitas non-admin. **Ada tesnya** — kalau suatu saat
ada yang memindahkannya, `AssistantWidgetTest` gagal sebelum ada karyawan yang
menemukannya lewat layar 401.

Segmen `/api/` di tengah path bukan kebetulan: `bootstrap/app.php` merender
error sebagai JSON untuk pola bintang-slash-api. Tanpa segmen itu error validasi
dibalas HTML dan `apiFetch` gagal memparsenya.

**Ini endpoint EVA pertama yang terbuka tanpa penjaga identitas.** Yang
menahannya sekarang hanya throttle per route dan CSRF bawaan grup `web`.

### Kenapa `rate` dan `rate/note` DIPISAH

Mengirim bintang kedua kali dengan `reason`/`comment` terisi akan kena
`unique(answer_log_id, rated_by)` → 409, dan catatan karyawan hilang tepat
setelah ia menulisnya. Jadi bintangnya dikirim lebih dulu dan langsung
terkunci; catatan menyusul sebagai pelengkap baris yang sama lewat
`EvaChat::annotate()`. Sifat "sekali nilai per jawaban" tetap utuh — pintu
`note` tidak bisa mengubah bintang.

Kotak catatan hanya muncul untuk bintang ≤ 3. Bintang lima tidak menghasilkan
pekerjaan bagi siapa pun; meminta catatan pada semua nilai membuat orang
berhenti memberi nilai sama sekali.

### Celah IDOR percakapan — DITUTUP

`EvaChat::resolveConversation()` mencari percakapan **dalam milik penanya**,
bukan lewat id telanjang, sehingga id asing dibalas 404. Dikerjakan justru pada
momen ini karena widget adalah endpoint pertama yang terbuka — di konsol admin
celah itu masih tertahan `eva.access`. Tes di `AdversarialAuditTest` berubah
dari `test_celah_idor_menyisipkan_giliran...` menjadi
`test_ditutup_giliran_tidak_bisa_disisipkan...`. **Dua celah IDOR lain
(ticket-draft & rating) MASIH terbuka** dan masih menunggu SSO.

### Jebakan yang ditemukan sesi ini

- **`layouts/portal.blade.php` tidak punya `<meta name="csrf-token">`.**
  Halamannya dulu murni baca-saja. Tanpa baris itu setiap pertanyaan dibalas
  419, dan pesannya tidak menyebut CSRF sama sekali. Sudah ditambahkan; ada
  tesnya. **Layout mana pun yang memasang widget wajib punya baris ini.**
- **`*/api/*` di dalam blok komentar PHP menutup komentarnya sendiri.** `*/`
  adalah penutup komentar. Menulis pola itu apa adanya di komentar
  `routes/web.php` menghasilkan ParseError yang menunjuk ke baris berikutnya.

### Cara memasang widget di layout lain

Satu baris, dan `<meta name="csrf-token">` wajib ada di `<head>`:

```blade
@include('eva._assistant-widget')
```

Pada layout yang pojok kanan bawahnya sudah terpakai tombol "⇄ Switch Role"
milik tim (`layouts/app`, `admin`, `requester`), naikkan posisinya supaya tidak
bertumpuk — kode tim tidak disentuh sama sekali:

```blade
@include('eva._assistant-widget', ['evaWidgetOffset' => 96])
```

**Saat ini widget HANYA dipasang di `layouts/portal.blade.php`** atas keputusan
pemilik. Layout requester sengaja belum.

### Diverifikasi di browser, bukan hanya lewat tes

Portal dibuka di Chrome dengan MySQL dev: ikon mount → panel terbuka →
"cara reset password SAP" dijawab keyakinan **97** dari SOP Reset Password SAP →
2 bintang + catatan terkirim → "kursi kantor saya rusak rodanya" jatuh ke
`no_answer` → draf tiket dibuat **tanpa satu baris pun masuk tabel tiket** →
"tidak bisa login" memicu `clarify`. Satu percakapan dengan 6 giliran, tidak
beranak. Konsol browser bersih. `kb_answer_logs` mencatat keempat jalur.

### Berikutnya

Tidak berubah dari daftar di bawah, kecuali dua hal yang kini punya tempat:
kotak catatan sudah SELESAI, dan pertanyaan "bentuk pemasangan di portal SSO"
kini punya jawaban konkret untuk ditunjukkan — widget ini adalah bentuk yang
tinggal dipindahkan.

---

## MULAI DARI SINI (29 Juli 2026, sesi sore)

**Keadaan: `php artisan test` → 276 hijau.** Sesi ini mengeksekusi audit
adversarial lalu memperbaiki tiga bug yang muncul saat pemilik mencoba
hasilnya di browser.

### Yang dikerjakan sesi ini

1. **Perbaikan celah audit — 8 dari 10 ditutup.** Status lengkap + sisa
   pekerjaan ada di [`eva-qa-perbaikan-handoff.md`](./eva-qa-perbaikan-handoff.md).
   Ringkasnya: konsol EVA kini dijaga middleware `eva.access` (tamu ditolak
   401), `preview/ask` di-throttle 20/menit, artikel punya jejak penyunting
   (`kb_articles.updated_by`), dokumen macet disapu `eva:sweep-stuck-documents`
   tiap 5 menit, dan kualitas pencarian diperketat (tag tidak lagi menaikkan
   skor, toleransi typo turun + kunci dua huruf awal).
2. **Tiga bug pasca-perbaikan, semuanya sudah ditutup** — lihat "Jebakan yang
   baru ketahuan" di bawah.
3. **Dua perubahan perilaku atas permintaan pemilik** — lihat "Keputusan
   pemilik" di bawah.

### Nyalakan — TIGA hal (tidak berubah)

1. MySQL lewat XAMPP GUI → Manage Servers → MySQL Database → Start
2. `php artisan serve --port=8000` → portal di http://127.0.0.1:8000/
3. **`php artisan queue:work`** di terminal terpisah. Tanpa ini dokumen berhenti
   di `processing` — bedanya sekarang penyapu terjadwal akan menandainya
   `failed` setelah 30 menit dengan alasan yang menunjuk ke worker, BUKAN diam
   selamanya seperti dulu. Penyapu itu sendiri butuh `php artisan schedule:run`
   (cron) untuk benar-benar jalan.
4. `php artisan test` → pastikan 276 hijau sebelum menyentuh apa pun.

### Jebakan yang baru ketahuan (semuanya sudah ditutup, jangan diulang)

- **Migration baru WAJIB dijalankan manual di dev MySQL.** Tes memakai SQLite
  yang selalu bermigrasi dari nol, jadi tes tetap hijau sementara aplikasi di
  browser 500. Gejalanya: "tidak bisa menyimpan artikel". Selalu
  `php artisan migrate` setelah menambah migration, dan jangan menganggap tes
  hijau sebagai bukti aplikasi jalan.
- **`apiFetch` tidak melakukan JSON.stringify sendiri** (dulu). Objek biasa yang
  diteruskan ke `fetch` terkirim sebagai teks harfiah `"[object Object]"`, dan
  Laravel melaporkannya sebagai "field wajib kosong" untuk field yang terisi —
  kegagalan yang menunjuk ke arah yang salah. Dua pemanggilan di layar
  Unanswered kena; sekarang `resources/js/lib/api.js` punya jaring pengaman yang
  men-serialize objek biasa. Konvensinya tetap: pemanggil menulis
  `JSON.stringify` sendiri.
- **Portal menunjuk ke mockup, bukan konsol.** Peran "EVA Knowledge" di portal
  dulu mengarah ke route tim `dashboard.eva` (komponen `KnowledgeConsole` +
  `DummyData`) sehingga terlihat seperti konsol yang gagal memuat data. Kini
  `config/helpdesk.php` mengarah ke `eva.coverage`. Satu tempat itu menyetir dua
  pintu masuk sekaligus: kartu portal dan RoleSwitcher di semua layout.

### Keputusan pemilik yang sudah diambil (jangan dibongkar ulang)

- **"Hapus" di Unanswered = baris langsung hilang.** Kartu "Dihapus dari daftar
  kerja" beserta tombol Kembalikan-nya DITIADAKAN atas permintaan pemilik
  (29 Juli). Datanya tetap tercatat di `kb_dismissed_questions` — itu yang
  menahan pertanyaannya tidak muncul lagi — dan barisnya kembali sendiri kalau
  pertanyaan itu ditanyakan ulang. Konsekuensi yang disadari: tidak ada lagi
  cara membatalkan dari layar; `UnansweredController::restore()` dipertahankan
  sebagai satu-satunya jalan pulang, dipanggil manual.
- **`EVA_LOCAL_ACTOR`** — jembatan sementara supaya konsol tetap bisa dipakai di
  mesin pengembang tanpa SSO. Aktif hanya di `APP_ENV=local`. **Ini yang pertama
  harus dicabut saat SSO datang.**

### Pekerjaan berikutnya (belum diputuskan / belum dikerjakan)

1. **Pekerjaan ISI** — coverage masih rendah; daftar tugasnya sudah ada di layar
   Ticket Recommendation & Unanswered Questions.
2. **FASE 2 audit (IDOR) — blocked menunggu SSO.** Cek kepemilikan bernilai
   tautologis selama identitas masih persona tunggal `CurrentActor`. Tiga tes
   penandanya masih berlabel `[CELAH]` di `AdversarialAuditTest`.
3. **Celah #10 — keputusan produk belum diambil.** Kalau satu pertanyaan cocok
   ke dua subject BERBEDA NAMA di layanan berbeda (SAP vs SILO), haruskah EVA
   bertanya balik seperti yang sudah dilakukannya untuk subject bernama sama?
4. **Dua tawaran yang belum dijawab pemilik:** (a) cabut route `restore` +
   method + tesnya supaya benar-benar bersih; (b) buat route tim `dashboard.eva`
   me-redirect ke `eva.coverage` supaya layar mockup lama tidak bisa dijangkau —
   satu baris, tanpa menghapus berkas tim.
5. **Keputusan pgvector/PostgreSQL** sebelum kode embedding ditulis (tidak
   berubah, lihat bagian prasyarat model AI di bawah).

---

## MULAI DARI SINI (27 Juli 2026 — snapshot lama)

Sesi 27 Juli ditutup dalam keadaan **bersih, dan itu diperiksa satu per satu —
bukan diasumsikan**:

- **250 tes hijau (649 assertion)** — 146 dari sesi pagi, 46 dari iterasi 18
  (Recommendation + CRUD FAQ & Document), 8 dari iterasi 19 (penambang tiket),
  16 dari iterasi 20 (prefill FAQ + tombol Hapus), 12 dari iterasi 21
  (Rating & Feedback), 10 dari iterasi 22 (Log Percakapan), 2 dari iterasi 23 (cap Coverage). Dijalankan dengan
  server MATI, jadi tesnya memang tidak bergantung pada apa pun yang menyala
- 14/14 layar EVA membalas 200; Documents & Article Library dibuka di browser
  tanpa satu pun error/warning di konsol
- Jalur jawaban EVA dicoba dengan data nyata: "cara reset password SAP" →
  keyakinan 97 dari artikel "SOP Reset Password SAP"
- Semua komponen React terdaftar di `registry.js` (jebakan tersering repo ini)
- Tidak ada migrasi tertunda; tidak ada `dd()`/`console.log` tertinggal
- Antrean kosong, 0 job gagal, 0 dokumen tersangkut `processing`
- Server, worker, dan berkas uji sementara sudah dibersihkan/dihentikan

**Nyalakan dulu — TIGA hal, bukan dua:**

1. MySQL lewat XAMPP GUI → Manage Servers → MySQL Database → Start
2. `php artisan serve --port=8000` → konsol di http://127.0.0.1:8000/eva
3. **`php artisan queue:work`** di terminal terpisah — WAJIB sejak iterasi 14.
   Tanpa ini setiap dokumen yang diunggah berhenti di status `processing`
   selamanya, tanpa error apa pun di layar maupun di log. Ini gejala yang paling
   mudah disalahartikan sebagai bug.
4. `php artisan test` → pastikan tetap 250 hijau sebelum menyentuh apa pun.
   (Tes pencarian memakai database terpisah `helpdesk_eva_test`, dibuat otomatis
   kalau belum ada. Database dev TIDAK pernah disentuh tes.)

**Pekerjaan berikutnya.** Dua sisa pekerjaan kode sudah ditutup (iterasi 18 —
tes Recommendation/FAQ/Document; `docs/eva-deploy.md` — cron + supervisor).
**Yang tersisa bukan lagi pekerjaan kode:**

1. **Pekerjaan ISI** — coverage 12/140. Daftar tugasnya **sudah ada, tinggal
   dijalankan**: `php artisan eva:mine-ticket-subjects` mencetak 20 subject yang
   sudah terbukti ditanyakan lewat tiket tapi belum punya materi (iterasi 19).
   `kb_answer_logs` tidak dipakai untuk ini — isinya cuma 7 pertanyaan unik dan
   semuanya dari admin di EVA Preview.
2. **EVA belum menyentuh satu pengguna pun** — `resources/views/requester`,
   `portal`, dan `dashboard` NOL referensi ke EVA. Mesinnya matang
   (`PreviewController` sudah punya `ask`/`rate`/`ticket-draft`, aturan #4
   terkunci tes), yang belum ada permukaannya untuk pengguna + aktor sungguhan
   (`CurrentActor` masih persona tunggal) + rate limit di `eva/api/*`. Selama
   ini belum ada, deflection rate dan seluruh layar Insights melaporkan angka
   yang benar secara teknis tapi kosong secara bisnis.

   **DI MANA EVA DIPASANG — jangan salah lagi.** Bukan di portal requester
   Helpdesk ini. EVA dipasang di **portal SSO ADHI Karya**: satu portal yang
   menampilkan banyak aplikasi yang bisa diakses karyawan, dan Helpdesk hanyalah
   salah satunya. Dokumen ini sempat menulis "portal requester" — itu keliru dan
   sudah diperbaiki 28 Juli 2026 atas koreksi pemilik.

   Akibatnya bagi rencana kerja, dan ini bukan perbedaan kecil:
   - Permukaan EVA hidup **di luar aplikasi ini**, jadi `eva/api/*` berubah dari
     endpoint internal jadi **API lintas aplikasi** — CORS, autentikasi
     antar-aplikasi, dan rate limit jadi syarat, bukan pelengkap.
   - Identitas pemakai datang dari **SSO**, bukan dari tabel `users` Helpdesk.
     Ini yang akhirnya menggantikan `CurrentActor` persona tunggal.
   - Pertanyaan yang masuk **tidak semuanya soal Helpdesk**. Karyawan bertanya
     dari portal yang memuat banyak aplikasi, jadi EVA akan menerima pertanyaan
     di luar cakupan katalog layanan — perilaku "di luar jangkauan" perlu
     dirancang, bukan dibiarkan jatuh jadi draf tiket.

   **Belum ditanyakan ke pemilik:** bentuk pemasangannya (widget/iframe yang
   di-embed portal SSO, atau EVA sebagai aplikasi tersendiri di portal itu),
   dan siapa yang memiliki portal SSO tersebut.

   **Ikut dikerjakan bersama butir ini — kotak catatan penilaian (DITUNDA
   SENGAJA, 28 Juli 2026).** `kb_answer_ratings` punya kolom `comment` dan
   `reason`, dan `preview/rate` sudah memvalidasi keduanya — tapi
   `EvaPreview.jsx` hanya mengirim `stars`. **Tidak ada satu pun kotak teks di
   seluruh aplikasi yang bisa mengisi kolom itu**, jadi hari ini 48 penilaian
   tersimpan dengan 0 komentar dan 0 reason. Panel tanggapan di layar Rating &
   Feedback (iterasi 21) sudah jadi dan teruji, tapi **sumber isinya belum
   ada**.

   Ditunda BUKAN karena sulit — backend tidak perlu diubah sama sekali, yang
   kurang cuma kotak teks di EvaPreview. Ditunda karena EvaPreview adalah layar
   ADMIN: sampai EVA hidup di portal SSO, yang tertampung hanya catatan admin
   atas percobaannya sendiri, bukan suara karyawan. Membangunnya sekarang
   menghasilkan fitur yang terisi data yang salah orang.

   Kerjakan kotak catatan itu **berbarengan dengan permukaan EVA di portal
   SSO** — di situ barulah ia menangkap kalimat karyawan sungguhan.
3. **Keputusan pemilik soal model AI** — lihat "Prasyarat sebelum model AI
   ditanam" di bawah. Catatan penting: pertanyaan "pgvector atau tidak"
   kemungkinan besar SALAH DIRUMUSKAN. Skalanya ~2.000 potongan; cosine
   brute-force di PHP hitungan milidetik. Yang benar-benar prasyarat adalah
   **model embedding mana dan jalan di mana** (on-premise vs API — isi SOP
   internal boleh keluar atau tidak).

**Dua risiko yang belum tercatat di mana pun:** konsol `/eva` sama sekali tanpa
autentikasi (harus beres SEBELUM butir 2), dan `eva/api/*` tanpa rate limit
(`preview/ask` memanggil pencarian penuh tiap request).

Kalau tetap ingin pekerjaan kode: yang tersisa hanya perkara kecil di
"Sisa lain (prioritas lebih rendah)" — tak satu pun memblokir apa pun.

**Jangan lupa batasan repo:** dilarang push/pull/commit. Seluruh pekerjaan EVA
masih berupa perubahan lokal di atas commit tim `0554d22`.

---

## Keadaan sekarang

- **Konsol EVA lengkap: 14 dari 14 layar sidebar hidup.** Tidak ada lagi menu
  bertanda SOON.
- **250 tes hijau** (`php artisan test`), 649 assertion. Seluruh logika
  terpadat-bug sudah terkunci, termasuk otak percakapan. Tidak ada lagi
  controller EVA yang nol tes.
- **Tidak ada lagi angka fiktif di konsol.** Semua yang tampil dihitung dari
  data nyata atau direkam dari kejadian nyata.
- **Tidak ada lagi keputusan terbuka yang memblokir.** Cacat kebenaran terakhir
  (satu artikel = satu subject) sudah ditutup — lihat iterasi 9 di bawah.
- Aplikasi berjalan di atas repo tim (Laravel 13 + Blade + React islands).
  **Batasan mutlak: dilarang push/pull/commit** (repo hasil clone tim).

## Cara menjalankan (server sering perlu dinyalakan ulang)

MySQL harus hidup dulu (XAMPP GUI → Manage Servers → MySQL Database → Start),
lalu:

```bash
php artisan serve --port=8000
php artisan queue:work          # terminal terpisah — WAJIB, lihat iterasi 14
php -d mysqli.default_socket=/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock \
    -d display_errors=Off -S 127.0.0.1:8081 \
    -t /Applications/XAMPP/xamppfiles/phpmyadmin
```

- Konsol EVA: http://127.0.0.1:8000/eva
- phpMyAdmin: http://127.0.0.1:8081
- Ubah React sambil preview: `npm run dev` di terminal terpisah. Kalau tidak,
  `npm run build` setelah mengubah komponen.
- **Jebakan tersering repo ini:** komponen React yang lupa didaftarkan di
  `resources/js/components/registry.js` gagal mount TANPA error — hanya
  `console.warn`. Selalu cek registry + buka di browser.
- Untuk server sungguhan (supervisor `queue:work`, cron `schedule:run`, OCR,
  izin storage): [`eva-deploy.md`](./eva-deploy.md).

## Yang sudah dikerjakan sesi terakhir (iterasi 6–8)

- **Ticket Recommendation** (Pencarian B / `SubjectMatcher`) — mencocokkan
  pertanyaan bebas ke `service_catalog_subjects`. Dua ambang: SUGGEST_FLOOR 30
  (masuk daftar), MIN_CONFIDENCE 50 (isi otomatis draf tiket).
- **Jarak edit (Levenshtein)** tahan typo; kata pendek (≤4 huruf) wajib persis.
- **Colokan AI**: `interface SubjectSearch` di-bind di `AppServiceProvider` —
  ganti ke embedding nanti = satu baris. Kembar dengan `KnowledgeSearch`.
- **Penjaga seri (tie-guard)** + **klarifikasi seri** di EVA Preview ("reset
  password" → "SAP atau SILO?").
- **Training Overview** — sakelar sumber jawaban (artikel/FAQ) yang NYATA:
  `FulltextKnowledgeSearch` membacanya tiap pencarian. Tabel `kb_settings`.
- **Istilah**: semua "aplikasi" → "layanan" (SAP/SILO/dll disebut layanan).
- **Tes**: QuestionTokenizer, ConfidenceScorer, SubjectMatcher,
  AnswerSourceSettings, TagRegistry, TrainingController (HTTP).
- **Bug laten diperbaiki**: `bootstrap/app.php` kini merender error JSON untuk
  `*/api/*` (bukan hanya `api/*`) — endpoint EVA di `eva/api/*` dulu membalas
  HTML saat validasi gagal.

## Iterasi 9 — satu artikel → banyak subject (SELESAI)

Keputusan terbuka sesi lalu dipilih **opsi B**, dan sudah dikerjakan.

- Tabel baru **`kb_article_subject`** (pivot, unique per pasangan). Kolom
  `kb_articles.catalog_subject_id` SENGAJA tetap ada sebagai **subject utama** —
  itu yang dicatat ke `kb_answer_logs` saat artikel menjawab, dan yang jadi
  identitas artikel di daftar. Pivot hanya menambah jangkauan.
- **`Article::allSubjectIds()`** = subject utama ∪ tautan tambahan. Ini
  satu-satunya jawaban atas "artikel ini melayani subject apa saja".
  **`Article::answerableCountsBySubject()`** membungkusnya di balik gerbang
  `answerable()`, jadi artikel draf tetap tidak bisa terlihat menutup subject.
- `CoverageCalculator` membaca helper itu, sehingga **Coverage, Apps & Systems,
  Taxonomy, dan Ticket Recommendation ikut benar sekaligus** — tak satu pun
  perlu diubah sendiri-sendiri.
- Article Library: pemilih **"Subject tambahan"** di drawer sunting, tautan
  tampil di kolom SUBJECT KATALOG (`+ User Locked`), filter layanan kini
  memeriksa semua subject, dan "BELUM TERTAUT" berarti tak punya subject apa
  pun. Endpoint `PUT /eva/api/articles/{id}` menerima `subject_ids`.
- Indeks ulang dokumen melepas tautan tambahan yang baru saja menjadi subject
  utama, supaya satu subject tidak tercatat di dua tempat.
- Aturan #1 tidak tersentuh: artikel tetap lahir dari SATU dokumen. Yang jamak
  adalah subject yang DILAYANI, bukan asal-usulnya.
- Data nyata: "SOP Unlock Akun SAP" kini juga menutup "User Locked"
  (coverage 10/140 → 11/140).

**Sisa pekerjaan editorial:** artikel lain kemungkinan besar juga melayani lebih
dari satu subject. Menautkannya sekarang murni pekerjaan isi, tanpa kode —
buka Article Library → Sunting → Subject tambahan.

## Iterasi 10 — jaring pengaman otak percakapan (SELESAI)

`EvaResponder` dan `PreviewController` sebelumnya nol tes. Sekarang 32 tes baru.

- **`EvaResponderTest`** (18 tes) — ambang keyakinan diuji pada nilai PERSIS
  (`MIN_CONFIDENCE`, `MIN_CONFIDENCE - 1`, `HEDGE_CONFIDENCE`), pertanyaan kabur,
  clarify-seri berikut pembedanya, dan penandaan hasil percakapan.
  Invarian utama yang dikunci: **setiap jalur menulis tepat satu baris
  `kb_answer_logs`** — kebocoran di sini tidak memunculkan error apa pun, ia
  hanya membuat Unanswered/Analytics/Recommendation melaporkan angka terlalu
  kecil tanpa petunjuk.
- **`PreviewControllerTest`** (14 tes) — percakapan tidak beranak, ordinal
  giliran menyambung, sekali-nilai-per-jawaban, dan **aturan #4 diuji langsung**
  (`Ticket::count()` tidak berubah setelah draf dibuat).
- **Pencarian A dipalsukan lewat interface `KnowledgeSearch`** — seam
  portabilitas yang sama yang nanti dipakai menukar FULLTEXT dengan embedding.
  Itu yang membuat tes ini jalan di SQLite DAN membuat ambang bisa diuji pada
  nilai persis. **Pakai pola ini untuk tes berikutnya yang butuh Pencarian A.**
- Tes dibuktikan menggigit lewat dua mutasi sengaja (ambang digeser satu poin;
  clarify dianggap menutup percakapan) — keduanya tertangkap.

**Bug yang ketemu & diperbaiki:** `PreviewController::rate()` mendeteksi
duplikat lewat kode error **1062 milik MySQL saja**. Di driver lain penilaian
kedua meledak jadi 500, bukan 409 — dan baru ketahuan jauh setelah pindah
driver. Sekarang memakai `UniqueConstraintViolationException` (netral-driver);
sudah diverifikasi tetap benar di MySQL.

## Iterasi 11 — tren Coverage jadi angka nyata (SELESAI)

Angka fiktif terakhir di konsol sudah dicabut.

- Seeder **berhenti mengarang riwayat**: `coverage_history => [2,3,4,6,7]` dan
  `seedCoverageHistory()` dihapus. Lima baris karangan di database dev juga
  dihapus (persis cocok dengan array itu — terbukti fiktif).
- Perintah baru **`php artisan eva:snapshot-coverage`** — satu-satunya cara
  baris riwayat lahir. Idempoten per tanggal, jadi aman dijalankan berkali-kali.
- **Direkam HARIAN, ditampilkan BULANAN** (`routes/console.php`, 01:00).
  Pemisahan itu disengaja: hari yang terlewat tidak bisa direkam ulang karena
  angkanya sudah berubah, sedangkan kekasaran tampilan bisa diubah kapan saja.
  Jadi yang dijadwalkan adalah resolusi terhalus yang murah, bukan resolusi yang
  kebetulan sedang ditampilkan. Kalau nanti butuh mingguan, datanya sudah ada —
  cukup ubah pengelompokan di `CoverageCalculator::trend()`.
- Satu bulan diwakili rekaman TERAKHIR bulan itu. Bulan berjalan tidak diambil
  dari snapshot; ia sudah diwakili titik terakhir yang selalu dihitung ulang.
### BELUM DIPASANG — cron penjadwal (pekerjaan saat deploy)

`Schedule` di Laravel **tidak berjalan sendiri**. Ia butuh satu baris cron di
server yang memanggil `schedule:run` tiap menit:

```
* * * * * cd /path/ke/aplikasi && php artisan schedule:run >> /dev/null 2>&1
```

Tanpa baris itu, `eva:snapshot-coverage` tidak akan pernah jalan otomatis dan
grafik tren selamanya cuma satu titik. `php artisan schedule:run` yang
dijalankan manual di luar jam 01:00 akan menjawab "No scheduled commands are
ready to run" — itu benar, bukan error: ia pemeriksa jadwal, bukan pemicu.

Sengaja TIDAK dipasang di laptop dev: cron hanya jalan saat mesin hidup,
sehingga titiknya bolong-bolong dan tidak mewakili apa pun. Pasang di server
tim saat deploy. Sementara itu rekam manual dengan
`php artisan eva:snapshot-coverage` (aman diulang, satu baris per tanggal).
- `reset()` seeder **tidak lagi mengosongkan `kb_coverage_snapshots`** — isinya
  satu-satunya data di skema kb_* yang tidak bisa dihitung ulang dari apa pun.
  Sudah diverifikasi: snapshot bertahan setelah `db:seed` diulang.
- Coverage Dashboard menampilkan keadaan jujur saat riwayat kosong (satu titik
  bukan tren) berikut cara mulai merekamnya — bukan garis + "+0 poin".

## Iterasi 12 — unggah berkas sungguhan (SELESAI)

Sebelumnya layar Documents cuma form tempel-teks: dropdown PDF/DOCX hanya
LABEL, tak ada berkas yang benar-benar tersimpan. Kolom `original_filename`,
`storage_path`, `size_bytes` sudah ada di skema sejak awal tapi selalu kosong.

- **`DocumentTextExtractor`** — membaca isi berkas TANPA dependensi baru.
  TXT/MD apa adanya; **DOCX dibaca lewat ZipArchive** (format aslinya memang
  arsip ZIP berisi `word/document.xml`, dan ZipArchive sudah bagian dari PHP).
- **PDF/XLSX mengembalikan `null`, bukan string kosong.** Bedanya menentukan:
  string kosong akan lolos ke indexer dan melahirkan artikel tanpa isi yang
  tampak berhasil diunggah. Sekarang ditolak 422 dengan alasan yang jelas.
- **PDF tetap boleh diunggah asal teksnya ditempel** — berkasnya disimpan,
  sehingga saat ekstraksi PDF disetujui nanti dokumen lama bisa dibaca ulang
  tanpa mengunggah apa pun lagi.
- Berkas disimpan di **disk privat** (`storage/app/private/kb-documents`),
  dengan nama acak — nama kiriman klien tak pernah menentukan lokasi tulis
  (ada tesnya: `../../rahasia.txt`). Batas 20 MB, ekstensi dibatasi daftar.
- Daftar format terbaca dikirim server ke komponen (`readableExtensions`),
  bukan diketik ulang di JS — supaya layar tidak menjanjikan pembacaan otomatis
  yang tidak terjadi.
- 21 tes baru. Diverifikasi ujung-ke-ujung di browser + MySQL: DOCX asli
  diunggah → teks terekstrak → artikel lahir → status `indexed`.

**Catatan:** `store()` menamai berkas tersimpan dengan ekstensi hasil tebakan
MIME, jadi DOCX bisa tersimpan berakhiran `.zip`. Tidak menimbulkan masalah —
tipe sebenarnya ada di kolom `extension` dan nama asli di `original_filename`.

**OCR tetap TIDAK ada, dan itu bukan hal yang sama dengan ekstraksi PDF.**
`smalot/pdfparser` hanya membaca PDF yang punya lapisan teks. PDF hasil PINDAI
(di-scan, ditandatangani, distempel) butuh OCR — Tesseract di server atau
layanan awan, keputusan yang jauh lebih besar termasuk soal apakah isi SOP
internal boleh dikirim keluar.

## Iterasi 13 — OCR PDF hasil pindai (SELESAI)

**Tesseract on-premise**, sesuai keputusan pemilik: isi SOP tidak keluar
jaringan ADHI. Sebagian SOP memang hasil pindai, jadi ekstraksi lapisan teks
saja tidak cukup.

**Temuan yang mengubah lingkup: `smalot/pdfparser` TIDAK jadi dibutuhkan.**
`pdftotext` dan `pdfinfo` sudah ikut poppler — yang memang wajib dipasang demi
OCR. Jadi pembacaan PDF, baik lahir-digital maupun pindai, kini jalan **tanpa
satu pun paket composer baru**. `composer.json` tim tetap tidak tersentuh.

- **`PdfTextReader`** (interface) — seam ketiga, sejajar `KnowledgeSearch` dan
  `SubjectSearch`, di-bind di `AppServiceProvider`. Ganti mesin nanti = satu
  baris.
- **`PopplerTesseractPdfReader`** — halaman diperiksa SATU PER SATU: lapisan
  teks dulu (`pdftotext -f n -l n`), OCR hanya untuk halaman yang teksnya di
  bawah 40 karakter. SOP nyata sering campuran: sebagian ekspor Word, sisanya
  lembar tanda tangan yang dipindai. Meng-OCR halaman yang sudah punya teks
  justru MENURUNKAN kualitas.
- **`OcrBinaries`** — menemukan binari lewat config/env → PATH → direktori
  lazim. Ini menutup jebakan yang sudah terbukti: PHP tidak melihat
  `/opt/homebrew/bin`, jadi nama telanjang gagal walau terminal jalan normal.
- Bahasa `ind+eng` (SOP TI penuh istilah Inggris di kalimat Indonesia), 300 dpi,
  batas 30 halaman & 120 detik per proses supaya satu berkas rusak tidak
  menggantung antrean. Semua bisa diatur lewat env.
- Gambar sementara SELALU dihapus lewat `finally` — halaman SOP yang tertinggal
  di /tmp adalah kebocoran isi dokumen internal, bukan sekadar sampah berkas.
- **`canRead('PDF')` mengikuti ketersediaan binari.** Di server yang belum
  memasangnya, layar Documents otomatis berhenti menjanjikan pembacaan
  otomatis — daftar format terbaca diturunkan dari sumber yang sama.

**Terbukti, bukan diasumsikan.** Tes membangun DUA fixture PDF: lahir-digital
dan hasil pindai (halaman dirender jadi JPEG lalu ditanam sebagai gambar).
Tes pindai memverifikasi lapisan teksnya benar-benar kosong lebih dulu, jadi ia
tidak bisa lulus diam-diam lewat jalur `pdftotext`. Diverifikasi juga di
browser + MySQL: PDF pindai diunggah tanpa teks tempel → OCR → terindeks →
artikel lahir.

### Prasyarat lingkungan

Mesin dev sudah lengkap (tesseract 5.5.2, poppler 26.07.0, bahasa `ind`).
Di server tim: `tesseract-ocr`, `tesseract-ocr-ind`, `poppler-utils` —
**binari, bukan paket composer**. Kalau PATH milik PHP-FPM minim, setel
`EVA_TESSERACT_PATH`, `EVA_PDFTOTEXT_PATH`, `EVA_PDFTOPPM_PATH`,
`EVA_PDFINFO_PATH` di `.env`.

### Antrean — SELESAI di iterasi 14 (lihat di bawah)

## Iterasi 14 — indexing pindah ke antrean (SELESAI)

**Rencana sesi lalu salah sasaran, dan itu ketahuan begitu kodenya dibaca.**
Catatan sebelumnya menyuruh memindahkan `DocumentIndexer::index()` ke queued
job. Itu tidak akan menyelesaikan apa pun: bagian yang mahal — OCR — berjalan
di `DocumentController::store()` lewat `$this->extractor->extract(...)`,
SEBELUM `Document` bahkan dibuat. Yang dipindah adalah **ekstraksi berikut
indexing**.

- **`App\Jobs\Knowledge\IndexDocument`** — satu job, menerima `documentId`
  (bukan model: id tidak bisa basi). `tries = 1` karena kegagalan di sini
  hampir selalu soal isi berkas; mengulang OCR mahal yang pasti gagal lagi cuma
  menahan antrean. `timeout = 900` supaya longgar di atas batas per-proses OCR.
- **`store()` membalas 202**, bukan 201 — pekerjaannya DITERIMA, belum selesai.
  `reindex()` juga 202, dan mengembalikan dokumen ke `processing` lebih dulu
  supaya lencana tidak menampilkan hasil indexing yang LAMA selagi yang baru
  dikerjakan.
- **`DocumentTextExtractor::extract()` sekarang menerima PATH, bukan
  `UploadedFile`.** Saat job jalan, berkas unggahan sementara sudah lama
  dihapus; yang masih ada berkas di disk privat. (Sebelum diubah, tesnya
  kebetulan tetap lulus karena `SplFileInfo::__toString()` diam-diam
  mengembalikan path — persis jenis kebetulan yang meledak begitu
  `strict_types` dinyalakan.)
- **Kolom baru `kb_documents.failure_reason`.** Ini konsekuensi yang tidak
  boleh dilewat: selama sinkron, berkas tak terbaca dijawab 422 berikut kalimat
  yang menjelaskan langkah berikutnya. Setelah asinkron, request sudah selesai
  jauh sebelum mesinnya menjawab — tanpa kolom ini yang tersisa cuma lencana
  merah tanpa petunjuk. Alasannya tampil di sebelah lencana, bukan di balik klik.
- **Yang MASIH ditolak seketika: format yang mustahil terbaca** (XLSX, atau PDF
  di server yang binari OCR-nya belum terpasang). Itu sudah diketahui tanpa
  membuka berkasnya, jadi menerimanya lalu menggagalkannya diam-diam cuma
  menukar kalimat jelas dengan lencana merah.
- **Invarian yang dikunci tes: dokumen tidak pernah tertinggal `processing`.**
  Setiap jalan keluar job menetapkan `indexed` atau `failed` — termasuk saat
  mesin OCR melempar exception (ditangkap, dicatat penuh ke log server,
  diringkas ke layar) dan saat worker mati di tengah jalan (`failed()` hook).
  Baris yang macet di `processing` tidak memunculkan error apa pun; ia hanya
  diam sementara admin menunggu sesuatu yang tak akan pernah datang.
- **Layar Documents menanyakan status** ke `GET /eva/api/documents/{id}` tiap 3
  detik selama masih ada yang `processing`. Dependensi efeknya adalah daftar id
  yang tertunda, BUKAN daftar dokumen — kalau seluruh daftar dipakai, setiap
  jawaban polling akan memulai ulang penghitung waktunya.
- **Kartu statistik kini dihitung di layar, bukan dikirim server.** Status
  berubah sendiri selagi halaman terbuka; angka yang dibekukan saat halaman
  dimuat akan berdebat dengan tabel di bawahnya. Kartu **DIPROSES** ditambahkan.
- 11 tes baru (`DocumentQueueTest`). Tes lama yang menguji "PDF tak terbaca
  ditolak" kini memalsukan `PdfTextReader` agar hasilnya tidak bergantung apakah
  tesseract terpasang di mesin yang menjalankannya.

**Diverifikasi ujung ke ujung**, bukan hanya lewat tes: PDF hasil pindai
(lapisan teks benar-benar kosong, sudah diperiksa `pdftotext`) diunggah di
browser dengan `queue:work` sungguhan di atas MySQL → job jalan di worker
(695 ms) → OCR membaca isinya → status berubah sendiri jadi `indexed` lewat
polling → artikel lahir. Tidak ada error di konsol browser.

### Konsekuensi operasional yang mudah terlupa

`php artisan queue:work` **wajib hidup**. Tanpanya dokumen berhenti di
`processing` selamanya — dan gejalanya persis seperti aplikasi yang menggantung,
padahal tidak ada yang salah selain tidak ada yang mengambil pekerjaan. Di
server tim ini artinya supervisor/systemd, sebaris dengan cron
`schedule:run` yang juga belum dipasang (lihat iterasi 11).

## Iterasi 15 — tombol Terbitkan (SELESAI)

Ditemukan lewat pertanyaan pemilik: "kok tidak ada tombol publish?" Ternyata
memang tidak ada. Status hanya bisa diubah lewat **Sunting → gulir → dropdown
Status → Simpan** — empat langkah untuk tindakan yang paling sering dilakukan
setelah dokumen diunggah, sementara "Tampilkan di EVA" yang jauh lebih jarang
disentuh sudah bisa satu klik di baris.

- **`POST /eva/api/articles/{id}/publish`** — memantul antara `draft` dan
  `published`, sejajar dengan `toggle` yang sudah ada. Tombolnya berlabel
  "Terbitkan" (biru) untuk draf dan "Jadikan draf" (ghost) untuk yang terbit.
- **Sengaja BUKAN lewat `update()`.** Endpoint itu penyimpan seluruh isi
  artikel; memakainya untuk sekadar mengubah status berarti setiap penerbitan
  ikut menulis ulang judul, ringkasan, dan body dengan apa pun yang kebetulan
  ada di layar. Ada tesnya.
- **Dua gerbang tetap terpisah.** `status` dan `is_eva_visible` berdiri
  sendiri (lihat `Article::scopeAnswerable()`), jadi menerbitkan artikel yang
  sengaja disembunyikan tidak diam-diam memunculkannya. Ada tesnya juga.
- 5 tes baru (`ArticlePublishTest`). Yang paling penting mengunci EFEKNYA,
  bukan cuma kolomnya: menerbitkan membuat `Article::answerable()` bertambah —
  kalau tidak, tombolnya cuma mengganti warna lencana sementara artikelnya
  tetap tak terpakai EVA.

**Yang TIDAK diubah: artikel tetap lahir sebagai `draft`.** Pertanyaan kedua
pemilik — "bukannya setelah OCR langsung terbit?" — jawabannya tidak, dan itu
disengaja. OCR salah baca: pada PDF uji iterasi 14, "Helpdesk" terbaca "Hal
pasak". Menerbitkan otomatis berarti EVA menjawab pengguna dengan teks yang
tidak pernah dilihat manusia. Alurnya tetap **dokumen → OCR → artikel draf →
admin rapikan → Terbitkan**. Ini juga konsisten dengan aturan #2: FAQ terbit
langsung karena diketik manusia; artikel adalah hasil baca mesin.

## Iterasi 16 — mesin jawaban akhirnya punya tes (SELESAI)

`FulltextKnowledgeSearch` — yang menentukan SETIAP jawaban EVA — sebelumnya nol
tes, dengan alasan yang sah: `whereFullText()` tidak ada di SQLite, jadi tes
lain melempar exception sebelum menyentuh apa pun. Akibatnya terbalik: komponen
paling menentukan justru paling tak terjaga, dan regresinya tidak memunculkan
error — EVA cuma menjawab lebih buruk, diam-diam.

- **12 tes baru di MySQL sungguhan**, database TERPISAH `helpdesk_eva_test`.
  Kalau MySQL tak bisa dihubungi, seluruhnya DILEWATI — sudah dibuktikan dengan
  menjalankannya ke port salah (12 skipped, 0 failed). Pola yang sama seperti
  `PdfOcrTest` terhadap binari OCR.
- **`DatabaseTruncation`, BUKAN `RefreshDatabase`.** Indeks FULLTEXT InnoDB
  diperbarui saat COMMIT, jadi baris yang ditulis di dalam transaksi
  RefreshDatabase tidak terlihat MATCH…AGAINST. Tesnya akan tetap "hijau" lewat
  fallback LIKE sementara jalur FULLTEXT-nya tak pernah dijalankan. Ini
  ditemukan karena tes kata-hanya-di-body gagal — fallback artikel memang hanya
  memindai title+summary, jadi kegagalan itu menunjuk langsung ke sebabnya.
- **Penjaga database dev.** Nama database uji wajib berakhiran `_test`, kalau
  tidak tes menolak jalan. Perlu ditegaskan: sejak Laravel 11 `migrate` MEMBUAT
  database yang belum ada, jadi salah tunjuk tidak akan ketahuan lewat error —
  penjaga itulah satu-satunya yang mencegah `migrate:fresh` menghapus isi KB.
- Yang dikunci: jalur FULLTEXT (kata yang HANYA ada di body — tak mungkin lewat
  fallback), jalur fallback, gerbang `answerable()` untuk draf & disembunyikan,
  sakelar sumber jawaban dua arah, urutan hasil menurun, batas jumlah, dan
  pertanyaan tanpa kata bermakna yang berhenti SEBELUM menyentuh database.

**Koreksi dokumentasi kode:** komentar di `candidates()` menyebut fallback LIKE
ada demi token di bawah `innodb_ft_min_token_size` (3). Itu tidak pernah
terjadi — `QuestionTokenizer::significant()` sudah membuang kata di bawah 3
huruf lebih dulu, jadi token sependek itu tak pernah sampai ke database. Sebab
yang SEBENARNYA adalah **pelucutan imbuhan**: "mengaktifkan" → "aktif",
sementara FULLTEXT mencocokkan kata utuh dan "aktif" tidak ada di dokumen mana
pun. Fallback-nya tetap benar dan tetap perlu; hanya alasannya yang salah tulis.
Tesnya sekarang mengunci sebab yang benar.

## Sisa lain (prioritas lebih rendah)

- Isi tag sebagian masih mengulang judul (editorial, bukan kode).
- XLSX satu-satunya format yang isinya masih harus ditempel. Bisa saja dibongkar
  seperti DOCX, tapi deretan nilai sel menghasilkan artikel yang buruk —
  dibiarkan sampai ada kebutuhan nyata.
- **Coverage 11/140 (8%) adalah kekurangan ISI, bukan kode**, dan sejak iterasi
  13 tidak ada lagi yang memblokirnya: PDF (termasuk hasil pindai), DOCX, TXT,
  dan MD semuanya terbaca otomatis tanpa satu pun paket composer baru.

## Iterasi 27 — kartu Coverage: dicoba lalu DIBATALKAN

Berakhir dengan Coverage Dashboard hanya menyisakan **dua** kartu angka:
KESIAPAN EVA dan BELUM TERTUTUP. Total kembali **250 tes (649 assertion)**.

**Riwayatnya, supaya tidak diulang dari nol.** Empat kartu semula: kesiapan,
"ditutup artikel", "hanya dari FAQ", "belum tertutup". Pemilik menilai dua kartu
tengah tidak berguna — dan itu benar: rincian artikel-vs-FAQ tidak pernah
mengubah keputusan siapa pun. Penggantinya dicoba berupa daftar kerja menulis
dari tiket nyata ("perlu ditulis 20 subject / 21 permintaan" lengkap dengan
daftarnya di layar). Setelah jadi dan berjalan, pemilik memutuskan membatalkannya
dan membuang kedua kartu tanpa pengganti.

**Yang TETAP ADA sesudah pembatalan:**

- `TicketSubjectMiner` — logika pemetaan tiket → subject katalog. Diangkat
  keluar dari `MineTicketSubjects` saat fitur dibuat, dan tetap dipakai perintah
  itu. Bukan kode mati.
- `php artisan eva:mine-ticket-subjects` — tetap satu-satunya tempat membaca
  daftar tugas menulis. Perilakunya tidak berubah sedikit pun; 8 tesnya hijau.

**Yang DIBUANG bersama fitur:**

- Kartu "PERLU DITULIS" dan "PERMINTAAN MENUNGGU" beserta daftar di layar
- `CoverageController::writingBacklog()` dan prop `backlog`
- `TicketSubjectMiner::backlog()` — tidak ada lagi pemanggilnya
- **Cache di dalam miner.** Cache itu ada semata untuk layar. Perintah CLI
  justru dijalankan sesudah admin mengubah sesuatu, jadi selalu membuangnya
  duluan. Cache yang tidak dipakai siapa pun hanya menyisakan ranjau — dan
  ranjau itu sudah sekali meledak (lihat di bawah).
- `WritingBacklogTest.php`

**PELAJARAN YANG TETAP BERLAKU walau fiturnya dibatalkan.** Versi pertama
menyimpan `Collection` di dalam cache dan seluruh Coverage Dashboard membalas
**500**:

> The script tried to call a method on an incomplete object … "Illuminate\Support\Collection"

Sebabnya driver cache berbeda antar lingkungan: **dev/produksi** memakai
`database` yang men-*serialize*, sedangkan **tes** memakai `array` yang tidak
pernah men-serialize. Artinya tes perilaku sebanyak apa pun tidak akan
menangkapnya — 257 tes hijau, layar tetap 500.

**Aturan untuk ke depan: apa pun yang masuk `Cache::` harus berupa data biasa
(array/skalar), tidak pernah objek.** Dan sekali lagi terbukti: membuka halaman
di browser tidak boleh dilewat, karena HTTP 200 maupun tes hijau tidak
membuktikan layarnya hidup.

## Iterasi 26 — teks antarmuka ditulis ulang (SELESAI)

Keluhan pemilik: banyak kalimat di EVA terlalu panjang dan justru membingungkan
admin. Diminta bahasa yang lebih ramah dan formal.

**Yang salah dengan teks lama.** Sebagian besar menjelaskan ALASAN RANCANGAN
kepada admin yang tidak menanyakannya: *"FAQ ditulis admin dan langsung tayang —
tidak ada antrean review"*, *"Saran dihitung ulang setiap layar dibuka — tidak
pernah disimpan"*. Itu catatan untuk pengembang, bukan keterangan untuk
pemakai. Polanya juga seragam dan melelahkan: satu kalimat, tanda pisah, lalu
sisipan pembenaran.

**Aturan penulisan yang dipakai sekarang:**

1. Satu kalimat bila cukup. Sebutkan APA-nya, bukan KENAPA-nya.
2. Tidak ada tanda pisah untuk menyisipkan alasan. Pakai titik.
3. Formal tapi tidak kaku: "Silakan salin teksnya", bukan "salin teksnya ke sini".
4. Sebut menu dengan namanya: "pada menu Documents", bukan "di layar Documents".
5. Alasan rancangan tetap ditulis — di komentar kode, bukan di layar.

**Cakupan:** 56 penulisan ulang di 14 layar — seluruh subjudul halaman, pesan
kosong, keterangan bantuan, teks dialog, dan lencana. Contoh:

| Sebelum | Sesudah |
|---|---|
| Pertanyaan nyata dari karyawan yang belum bisa dijawab EVA. Isinya berubah sendiri mengikuti kondisi Knowledge Base. | Pertanyaan karyawan yang belum dapat dijawab EVA. |
| Belum ada riwayat kesiapan — kesiapan hari ini 9%. Angkanya direkam tiap hari oleh `eva:snapshot-coverage`, dan grafik menampilkan satu titik per bulan, jadi garisnya mulai terbentuk bulan depan. | Riwayat kesiapan belum tersedia. Kesiapan hari ini 9%. Grafik mulai terbentuk setelah data harian terkumpul. |
| Uji di layar ini tidak dicatat ke log jawaban — percobaan admin tidak boleh mengotori daftar celah materi. | Pengujian pada halaman ini tidak dicatat ke log jawaban. |

**Tanda pisah yang SENGAJA dibiarkan:** `—` sebagai penanda nilai kosong di
tabel, dan `— belum tertaut —` pada pilihan dropdown. Keduanya lambang, bukan
kalimat.

Diverifikasi: 14 layar dibuka di browser, seluruh island mount, subjudul baru
tampil benar. 250 tes tetap hijau (teks antarmuka tidak disentuh tes mana pun).

## Iterasi 25 — saringan "Semua" yang justru mengosongkan daftar (SELESAI)

Dilaporkan pemilik: di Documents, pilih tag "akun" → jalan; kembalikan ke
"Semua tag" → **tidak ada dokumen yang tampil sama sekali**.

**Sebabnya, dan ini bukan efek pagination.** Opsi "semua" ditulis
`<option>{ALL} tag</option>` tanpa atribut `value`. Nilai sebuah `<option>`
tanpa `value` jatuh ke TEKSNYA — jadi `"Semua tag"`, bukan `"Semua"`. Saringan
lalu mencari dokumen yang punya tag bernama persis "Semua tag", tidak menemukan
apa pun, dan mengosongkan tabel tepat saat admin mengira sedang MELEPAS
saringan.

**Kenapa baru ketahuan sekarang.** State-nya dimulai dari `ALL` yang benar,
jadi halaman selalu benar saat pertama dibuka. Cacatnya hanya muncul lewat satu
jalur: memilih satu nilai lalu kembali ke "Semua". Cacat ini sudah ada sejak
layarnya ditulis — pagination hanya kebetulan datang berbarengan.

**Terkena:** Article Library (layanan + tag), Manage FAQ (layanan + tag),
Documents (tag). Log Percakapan sudah benar sejak awal karena kebetulan ditulis
`<option value={ALL}>`.

**Diperbaiki** dengan menulis `value` eksplisit di SETIAP `<option>`, termasuk
yang sudah benar secara kebetulan (nilai = teks), supaya polanya tidak lagi
bergantung pada kebetulan. Diverifikasi otomatis di browser: 6 select di 3
layar, tiap select dipilih nilai spesifik lalu dikembalikan ke "Semua" — semua
pulih ke jumlah baris semula.

**Kode tim TIDAK terkena dan tidak disentuh.** `TicketWorkspace` dan
`TicketManagementConsole` memakai label penuh sebagai nilai sentinelnya
("Semua Status" dibandingkan dengan "Semua Status"), jadi teks dan nilainya
memang sengaja sama.

**Aturan untuk ke depan:** di repo ini, `<option>` tanpa `value` hanya boleh
dipakai kalau teksnya PERSIS nilai yang dibandingkan. Begitu teksnya diberi
imbuhan ("Semua" → "Semua tag"), `value` wajib ditulis.

## Iterasi 24 — sidebar bisa ditutup (SELESAI)

**Ditutup = menyempit jadi RAIL BERIKON 68px, bukan hilang.** Sidebar yang
lenyap total memaksa admin membukanya lagi tiap pindah menu; rail menyisakan
navigasi sambil mengembalikan 212px ke isi layar. Tiap menu diberi `title`
supaya rail tetap bisa dipakai — 13 ikon Lucide saja tidak cukup untuk
dibedakan.

**Isi ikut melebar, bukan cuma bergeser.** `PAGE.maxWidth` di `ui.jsx` kini
`var(--eva-page-max)` — 1240px saat terbuka, **1560px saat tertutup**. Tanpa itu
menutup sidebar hanya memindahkan 212px jadi ruang kosong di kanan dan tombolnya
terasa tidak melakukan apa-apa. Terukur di browser: main 1167 → **1379px**,
tabel Article Library 1105 → **1317px**.

**Dua keputusan yang gampang salah kalau dikerjakan ulang:**

1. **Keadaannya di localStorage, bukan memori.** EVA bukan SPA — tiap menu
   adalah pemuatan halaman penuh. Keadaan di memori akan kembali terbuka di
   setiap klik menu, dan tombolnya terbaca rusak padahal bekerja.
2. **Kelasnya dipasang skrip mentah di `<head>`, di `<html>` bukan `<body>`.**
   Apa pun yang di-bundel Vite pasti jalan setelah HTML terurai, jadi sidebar
   akan tampil lebar dulu lalu menyempit di SETIAP perpindahan menu. Kedipan itu
   menggeser seluruh isi layar tepat saat mata mulai membaca. `<body>` belum ada
   saat baris itu jalan, karena itu kelasnya di `<html>`.

**Gaya sidebar pindah dari inline ke kelas CSS** (`.eva-sidebar`, `.eva-nav-*`
di `eva.css`). Bukan soal kerapian: keadaan tertutup harus menimpa padding dan
display tiap menu, dan aturan CSS tidak bisa menimpa style inline tanpa
`!important` di mana-mana.

**Jebakan yang sempat lolos ke layar dan dilaporkan pemilik:** seluruh menu
berubah BIRU. Sebabnya `.eva-app a { color: var(--blue-500) }` berlaku untuk
semua tautan, dan spesifisitasnya (0,1,1) mengalahkan `.eva-nav-item` (0,1,0).
Versi inline dulu kebal karena style inline menang atas apa pun. Perbaikannya:
warna menu ditulis `.eva-app .eva-nav-item` (0,2,0), lengkap dengan `:hover`
yang disebut eksplisit — kalau tidak, menu tetap membiru saat disentuh kursor.
**Pelajaran umum: memindahkan style inline ke kelas di repo ini berarti
tiba-tiba tunduk pada aturan global `.eva-app a`.**

**Diverifikasi:** buka/tutup bolak-balik, keadaan bertahan setelah pindah menu,
tombol tetap terlihat saat tertutup (tidak ada keadaan "tertutup dan tidak bisa
dibuka"), `aria-expanded`/`aria-label` ikut berubah, panel master–detail tetap
bertinggi tetap, konsol bersih. Halaman tim (`/`, `/admin`) tidak tersentuh —
`initSidebarToggle()` keluar sendiri kalau tombolnya tidak ada.

Berkas: `eva.css`, `eva/_sidebar.blade.php`, `layouts/eva.blade.php`,
`js/lib/eva-sidebar.js` (baru), `app.jsx`, `ui.jsx`. 250 tes tetap hijau.

## Iterasi 23 — pagination di seluruh konsol (SELESAI)

Total kini **250 tes (649 assertion)**.

**Satu perilaku untuk seluruh konsol**, bukan pagination beda-beda tiap layar:
`usePagination()` + `<Pagination>` di `ui.jsx`. Dua jebakan ditangani di sana,
bukan diserahkan ke tiap layar:

1. **Menyaring saat berada di halaman 5.** Kalau hasilnya tinggal 3 baris,
   halaman 5 kosong dan layar terbaca rusak. `resetKey` mengembalikan ke halaman
   1 tiap saringan berubah. Diverifikasi di browser: halaman 3/5 (25–36 dari 60)
   → ketik "Andi" → halaman 1/3 (1–12 dari 30).
2. **Baris terakhir di halaman terakhir dihapus.** `page` selalu dijepit ke
   jumlah halaman yang benar-benar ada.

**Sembilan layar dapat pagination:** Coverage (sub category), Article Library,
Manage FAQ, Documents, Apps & Systems, Unanswered Questions, Ticket
Recommendation, Log Percakapan, Rating & Feedback. Panel master–detail memakai
mode `compact` (tanpa deretan nomor) karena panelnya sempit.

**TIDAK diberi pagination, dan itu disengaja:** Category & Taxonomy (pohon
bersimpul lipat — memenggal pohon merusak konteks induknya), Search Settings
(diperkirakan tetap ~5 kelompok, lihat pembahasan sinonim vs AI), Analytics
(top-N sudah terbatas di server), EVA Preview & Training Overview (tidak punya
daftar).

**CACAT YANG DITEMUKAN SAAT MENGERJAKAN — Coverage memotong di server.**
`CoverageCalculator::bySubcategory()` hanya mengirim 10 teratas dari 39, dan
**29 sisanya tidak terjangkau dari mana pun di konsol**. Karena urutannya
menurun dari kesiapan tertinggi, yang lenyap justru sub category dengan
kesiapan TERBURUK — persis alasan layar itu dibuka. Cap dibuang; pemenggalan
kini urusan layar, dan tiap halamannya bisa dibuka. Dikunci
`CoverageSubcategoryTest` (2 tes). Diverifikasi di browser: 4 halaman, 1–12,
13–24, 25–36, 37–39 dari 39.

**Log Percakapan: panah ↑ ↓ menyeberang halaman.** Panah menyisir seluruh hasil
saringan, bukan cuma halaman yang terbuka — kalau tidak, menekan panah di baris
terakhir tidak melakukan apa-apa dan terbaca seperti kerusakan. Halaman
MENGIKUTI pilihan. Diverifikasi: baris terakhir halaman 1 → ArrowDown →
halaman 2 dengan baris pertamanya terpilih.

**Ini pagination SISI KLIEN.** Datanya memang sudah dikirim utuh ke halaman.
Cukup untuk skala sekarang (paling besar 140 subject) dan jauh lebih sederhana
daripada mengubah 9 controller + route + tes. **Titik ganti ke sisi server:**
saat satu layar rutin melewati ~1.000 baris, atau saat Article Library mulai
mengirim isi artikel penuh — mana yang lebih dulu.

## Iterasi 22 — Log Percakapan lepas dari accordion (SELESAI)

Keluhan admin: menekan "Baca" membuka isi percakapan sebagai baris tambahan di
dalam tabel, dan percakapan panjang menimpa baris-baris lain. Total kini **248
tes (644 assertion)**.

**Kenapa accordion salah di sini.** Isi yang panjangnya tak tentu tidak boleh
menumpang di aliran yang sama dengan daftarnya. Akibatnya bukan cuma perlu
menggulir: admin kehilangan tempatnya berada, dan membandingkan dua percakapan
berarti menutup yang satu lalu mencari lagi yang lain dari awal.

**Bentuk barunya** sama dengan iterasi 21 — master–detail bertinggi tetap
(560px). Diukur di browser: **835px dengan 60 percakapan, tetap 835px** setelah
membuka percakapan terpanjang. Daftar kiri tetap diam dan tetap terlihat.

**Tambahan: navigasi panah ↑ ↓.** Layar ini dipakai MENYISIR puluhan percakapan
berturut-turut, dan mengangkat tangan ke mouse tiap kali memutus ritme itu.
Pendengarnya di `window` supaya tidak menuntut panel difokuskan dulu, dengan
penjagaan agar tidak membajak panah saat admin mengetik di kotak cari —
keduanya diverifikasi di browser.

**Temuan sampingan:** `ticket_reference` sudah lama dikirim controller tapi
TIDAK PERNAH ditampilkan layar mana pun. Padahal itu yang menyambungkan
percakapan gagal ke tiket yang lahir darinya. Kini tampil sebagai lencana di
kepala panel kanan.

**ConversationController yang tadinya nol tes kini punya 10.** Isinya bukan
sekadar meneruskan kolom: judul baris diturunkan dari giliran PENGGUNA pertama
(bukan giliran pertama apa pun), keyakinan diambil dari giliran TERTINGGI, dan
urutan giliran mengikuti `ordinal`. Diuji mutasi: mengganti
`firstWhere('role', ROLE_USER)` jadi `first()` menjatuhkan 2 tes — tanpa tes itu
seluruh daftar bisa berjudul kalimat sapaan robot tanpa satu pun error.

Berkas: `EvaConversationLog.jsx` (tulis ulang),
`tests/Feature/Eva/ConversationLogTest.php`. Controller tidak diubah.

## Iterasi 21 — Rating & Feedback dirombak jadi daftar kerja (SELESAI)

Keluhan admin: pada 100 artikel + FAQ, tabel "performa per materi" tumbuh tanpa
batas dan mendorong tanggapan tertulis karyawan jauh ke bawah lipatan layar.
Total kini **238 tes (621 assertion)**.

**Cacat yang sebenarnya, di luar keluhan gulir.** Tabel lama juga (a) jalan
buntu — memberi vonis "SOP Printer 2.1★" lalu menyuruh admin mencari materinya
sendiri, dan (b) memisahkan angka dari alasan: komentar hidup sebagai satu
daftar global di bagian bawah, jadi "materi mana yang buruk" dan "kenapa" tidak
pernah terbaca berdampingan.

**Bentuk barunya:** master–detail dengan **tinggi tetap 520px** untuk kedua
panel. Keduanya bergulir di dalam dirinya sendiri, jadi tinggi halaman sama saja
untuk 5 materi maupun 500 — diukur di browser: 925px dengan 10 materi, tetap
925px setelah memilih materi, sementara isi daftar 916px bergulir di jendela
368px. Kiri = daftar kerja (saringan "Perlu ditinjau"/"Semua" + cari judul),
kanan = detail materi terpilih: angkanya, tanggapan atas materi ITU, dan tombol
ke layar tempat materinya diperbaiki. Tanpa pilihan, panel kanan tetap
menampilkan tanggapan terbaru seperti dulu — tidak ada yang hilang.

**Jebakan yang dikunci tes.** Pengelompokan komentar memakai
`source_type|source_id`, bukan `source_id` saja: Artikel #3 dan FAQ #3 sama-sama
ada, dan menyatukannya menempelkan keluhan atas materi lain — admin lalu menulis
ulang materi yang tidak apa-apa. Diuji mutasi: mengganti kuncinya jadi
`source_id` saja menjatuhkan 4 tes.

**Tambahan kecil yang jujur:** materi dengan penilai < 3 diberi tanda "data
tipis". Karena daftar diurutkan terburuk-dulu, satu penilaian 1★ melompat ke
puncak dan terbaca seperti krisis; barisnya tetap ditampilkan (menyembunyikan
keluhan nyata lebih buruk) tapi tidak lagi dikira gawat.

**Sisa yang sengaja tidak dikerjakan:** tombol "Buka di Article Library" hanya
membuka layarnya, belum menyorot materinya. Deep-link butuh dukungan query
param di layar Article Library dan tesnya sendiri.

Berkas: `EvaRatingFeedback.jsx` (tulis ulang), `RatingController.php`,
`KnowledgeStats::commentsBySource()`, `tests/Feature/Eva/RatingScreenTest.php`
(12 tes).

## Iterasi 20 — merawat daftar Unanswered Questions (SELESAI)

Dua keluhan admin, dan ternyata hanya satu yang butuh kode. Total kini **216 tes
(542 assertion)**.

**Yang sudah ada dari dulu:** pertanyaan yang materinya sudah ditulis TIDAK
menumpuk — layar ini memeriksa ulang tiap pertanyaan tiap kali dibuka, jadi
barisnya pindah sendiri ke daftar "sudah tertutup". Tidak ada dan tidak perlu
tombol "tandai selesai".

**Yang benar-benar celah:** pertanyaan yang tidak akan pernah dijawab materi —
salah ketik, sapaan, permintaan pribadi. Itu menumpuk selamanya dan mendorong
pekerjaan nyata ke luar batas 40 baris.

- **Prefill FAQ** (6 tes) — tombol "Tulis FAQ" kini membawa pertanyaannya:
  `/eva/faq?question=…`, form terbuka dengan kolom pertanyaan terisi APA ADANYA.
  Mengetik ulang dari ingatan adalah cara termudah membuat FAQ yang menjawab
  kalimat sedikit berbeda — celahnya tidak tertutup, tapi admin merasa selesai.
  Dipotong di 500 (batas validasi) supaya form tidak terbuka dengan isi yang
  pasti ditolak.
- **Tombol Singkirkan** (10 tes) — tabel baru `kb_dismissed_questions`. Yang
  disingkirkan adalah **teks pertanyaan**, BUKAN baris `kb_answer_logs`:
  menghapus log berarti mengubah angka Analytics dan deflection rate bulan lalu.
  Konfirmasinya lewat dialog (`Modal` baru di `ui.jsx`), ada daftar
  "Disingkirkan" berikut tombol Kembalikan.
- **Tidak ada kolom `reason`, dan itu disengaja.** Rancangan pertama menyimpan
  alasan (`written`/`irrelevant`), lalu dialognya diputuskan tidak menanyakan
  alasan — jadi kolomnya ikut dicabut, bukan diisi nilai bawaan. Kolom yang
  diisi sendiri oleh kode selalu berakhir dibaca orang sebagai keputusan
  manusia. Dikunci di `assertArrayNotHasKey('reason', …)`.
- **Keputusannya kedaluwarsa sendiri.** Pertanyaan yang ditanyakan LAGI sesudah
  disingkirkan muncul kembali — itu bukti baru. Terkunci di
  `DismissedQuestion::hiddenQuestions()`, dan tes membuktikannya menggigit lewat
  mutasi (penjaga waktu dilepas → 2 tes jatuh).
- **Penyaringan di SQL, bukan sesudahnya** (`topUnansweredQuestions($limit,
  $exclude)`) — kalau tidak, baris yang disingkirkan tetap memakan jatah 40 dan
  daftar kerja mengecil sendiri tanpa alasan yang terlihat.
- **Batas yang disengaja:** layar Ticket Recommendation TIDAK ikut menyaring
  yang disingkirkan. Singkirkan adalah keputusan triase daftar kerja materi;
  Recommendation mengurus pengarahan tiket. Kalau nanti terasa perlu, tinggal
  memberinya `DismissedQuestion::hiddenQuestions()` yang sama.

## Iterasi 19 — daftar tugas menulis dari tiket nyata (SELESAI)

`php artisan eva:mine-ticket-subjects` — 8 tes, total kini **200 tes (486
assertion)**.

**Kenapa ada.** Rencana lama memakai `kb_answer_logs` untuk memilih subject mana
yang ditulis duluan. Itu buntu: 29 baris `no_answer` ternyata hanya **7
pertanyaan unik**, dan semuanya dari EVA Preview (admin) — terlalu tipis untuk
mengurutkan 140 subject. Sinyal yang jauh lebih tebal sudah ada di tabel
`tickets` tapi tak terbaca: `catalog_subject_id` semuanya NULL. Padahal
`subject_name` + `service_name` TERISI di 30/30 tiket.

**Hasil di data nyata:** 27 tiket non-draf → 22 subject tersentuh, **20 di
antaranya belum punya materi**, 4 tiket tak terpetakan, dan **0 tebakan** —
semuanya terpetakan persis lewat nama katalog. Caveat jujur: sebarannya masih
datar (hampir semua 1 tiket per subject), jadi ini memberi *himpunan awal*
bertuntutan nyata, bukan urutan prioritas yang kuat. Itu tetap jauh lebih baik
daripada menebak di antara 128 subject kosong.

- **Dua sumber DIBEDAKAN di keluaran** (`katalog` vs `tebakan`). Menyatukannya
  membuat tebakan lemah terbaca sama meyakinkannya dengan pemetaan persis.
- **Cacat yang ketahuan dari data nyata, bukan dari tes:** nama subject bisa
  kembar di SATU layanan ("Reset Password" di AKUN APLIKASI › SAP dan AKUN
  APLIKASI › SILO). Indeks pertama menimpanya diam-diam dan mengarahkan
  pekerjaan menulis ke cabang yang kebetulan terbaca belakangan. Sekarang kunci
  kembar dibuang dan tiketnya jatuh ke Pencarian B yang membaca deskripsi.
  Dikunci di `test_nama_subject_kembar_tidak_dipetakan_sembarangan`, dan tes itu
  terbukti menggigit saat penjaganya dilepas.
- **Tidak menulis apa pun** — termasuk tidak mengisi `tickets.catalog_subject_id`
  dari tebakan (aturan #5). Dihitung ulang tiap dijalankan, sama seperti layar
  Ticket Recommendation.
- Opsi: `--limit=20`, `--all` (ikut menampilkan subject yang sudah bermateri).

## Iterasi 18 — layar terakhir yang tanpa tes akhirnya punya tes (SELESAI)

Tiga controller yang tersisa tanpa satu pun tes — Recommendation, CRUD FAQ, dan
CRUD Document — kini tertutup 46 tes baru (total saat itu 192 tes, 463 assertion).

- **`RecommendationControllerTest`** (15 tes). Pencarian B dipalsukan lewat
  interface `SubjectSearch` — seam yang sama yang nanti dipakai menukar
  cocok-kata dengan embedding — sehingga ambang bisa diuji pada nilai PERSIS
  (`MIN_CONFIDENCE` masuk isi-otomatis, satu poin di bawahnya tidak). Dua
  invarian yang dikunci: **saran tidak pernah disimpan** (`catalog_subject_id`
  di `kb_answer_logs` tetap null setelah layar dibuka — kalau bocor, kolom itu
  berarti dua hal sekaligus) dan **`has_material` memakai gerbang
  `answerable()`** (materi yang masih draf tetap terhitung celah).
- **`FaqCrudTest`** (18 tes). Aturan #2 dikunci sebagai perilaku: FAQ yang baru
  disimpan LANGSUNG masuk `Faq::answerable()`, tanpa draf/review. Menghapus FAQ
  tidak menghapus `kb_answer_logs` miliknya — deflection rate bulan lalu tidak
  boleh berubah gara-gara materi hari ini dirapikan.
- **`DocumentCrudTest`** (13 tes). Yang paling mahal kalau bocor: **indeks ulang
  tidak menggandakan** (dijalankan dua kali → tetap satu artikel dengan id yang
  SAMA dan jumlah potongan yang sama), indeks ulang melepas `failure_reason`
  lama, dan menyunting keterangan tidak menyentuh `extracted_text` maupun
  status. Ada juga penjaga drift: daftar `readableExtensions` di layar wajib
  sama persis dengan `DocumentTextExtractor::canRead()`.
- **Dibuktikan menggigit lewat tiga mutasi sengaja**: ambang `>=` digeser jadi
  `>` → tes ambang gagal; `has_material` dipaksa selalu true → 4 tes gagal;
  artikel dihapus-lalu-dibuat-ulang saat indeks ulang → tes id artikel gagal.
  Ketiganya dikembalikan, hijau lagi.

Catatan yang berguna untuk tes berikutnya: **`matches()` dan beberapa nama lain
sudah dipakai `PHPUnit\Framework\Assert` sebagai method final** — helper bernama
sama membuat seluruh berkas fatal error, bukan gagal satu tes. Helper di sini
memakai nama Indonesia (`saranUntuk`, `calon`) yang otomatis bebas bentrok.

## Iterasi 17 — tes pencarian jadi KONTRAK (SELESAI)

Menutup prasyarat AI #1, dikerjakan sekarang justru karena mesin keduanya
BELUM ada: sesudah ia ada, godaan untuk "sesuaikan saja tesnya" jadi besar.

- **`KnowledgeSearchContract`** (abstrak, tidak dijalankan sendiri) memuat 12
  perilaku yang wajib benar apa pun mesinnya: menemukan artikel dari kata yang
  HANYA ada di body, menjembatani beda bentuk kata, gerbang `answerable()` untuk
  draf & disembunyikan, sakelar sumber jawaban dua arah, urutan menurun, batas
  jumlah, pertanyaan tanpa kata bermakna, dan subject katalog di `SearchHit`.
- **`FulltextKnowledgeSearchTest` tinggal jadi subkelas tipis**: menyediakan
  lingkungan MySQL + penjaga `_test`, lalu 2 tes yang KHAS mesin ini —
  bahwa FULLTEXT sendirian TIDAK menemukan kata hasil pelucutan (membuktikan
  yang menyelamatkan kontrak memang jalur fallback), dan bahwa pertanyaan tanpa
  kata bermakna tidak menembak query sama sekali.
- **Garis pemisahnya dijaga:** kontrak mengunci HASIL, bukan CARA. Mesin
  embedding menjembatani beda bentuk kata secara semantik, bukan lewat pelucutan
  + LIKE — jadi cara-nya milik subkelas, hasilnya milik kontrak.
- **Dibuktikan menggigit lewat mutasi sengaja**: urutan `usort` dibalik jadi
  menaik → tes urutan gagal, dan kegagalannya dilaporkan dari
  `KnowledgeSearchContract.php`, bukan dari berkas konkretnya. Dikembalikan,
  hijau lagi.
- Cacat kecil yang ikut diperbaiki: `test_hit_membawa_subject_katalog` dulu
  mencari hit lewat `sourceId` telanjang — id artikel dan id FAQ adalah dua
  urutan terpisah, jadi FAQ ber-id sama bisa lolos sebagai artikel. Sekarang
  dicocokkan berikut `sourceType`.

**Cara menambah mesin kedua nanti:** buat subkelas `KnowledgeSearchContract`,
sediakan lingkungannya di `setUp()`, kembalikan implementasinya dari
`searchUnderTest()`. Selesai — 12 perilaku itu langsung berlaku.

## Prasyarat sebelum model AI ditanam

Rencananya sudah jelas sejak awal (lihat `eva-development-plan.md` §6 dan bagian
"Embedding"): pencarian dikurung di balik interface supaya menukar mesin cukup
mengubah satu baris `bind()`. Tiga seam sudah berdiri —
`KnowledgeSearch` (Pencarian A), `SubjectSearch` (Pencarian B), `PdfTextReader` —
dan `kb_chunks` sudah ada dengan kolom vektor sengaja ditunda.

Yang BELUM beres, dan sebaiknya diberesi sebelum topiknya tiba:

**1. ~~Tes pencarian terikat kelas konkret~~ — SUDAH BERES di iterasi 17.**
`KnowledgeSearchContract` (abstrak) memuat 12 perilaku yang wajib dipenuhi mesin
mana pun. Mesin embedding nanti cukup menambah satu subkelas yang menyediakan
lingkungannya dan mengembalikan implementasinya lewat `searchUnderTest()` —
seluruh perilaku itu langsung berlaku untuknya, tanpa menyalin satu baris tes.

**2. Embedding tidak menambah materi, hanya memperbaiki pencarian atasnya.**
Dengan coverage 11/140, model secanggih apa pun tetap tidak punya apa-apa untuk
ditemukan pada ~92% subject. Urutannya: **isi dulu, baru mesin.** Dibalik,
hasilnya akan terbaca sebagai "AI-nya jelek" padahal yang kosong lemarinya —
dan kesimpulan salah itu mahal karena mengarahkan usaha ke tempat yang keliru.

**3. KEPUTUSAN PEMILIK YANG BELUM DIAMBIL: pgvector berarti PostgreSQL.**
Tim jalan di MySQL (XAMPP, `helpdeskbismillahyallah`). Rencana menyebut
`PgVectorKnowledgeSearch` seolah tinggal menukar binding, padahal di baliknya
ada perpindahan database — itu bukan satu baris. Alternatif yang belum pernah
dibahas di dokumen mana pun: embedding dilayani proses terpisah (sementara MySQL
tetap jadi sumber kebenaran), atau tabel vektor buatan sendiri di MySQL tanpa
tipe `VECTOR` bawaan. Ketiganya punya konsekuensi operasional yang jauh berbeda.
**Jangan mulai menulis kode embedding sebelum ini diputuskan.**

## Enam aturan yang tidak boleh dilanggar

1. Artikel lahir dari dokumen, tak pernah dibuat manual.
2. FAQ terbit langsung, tanpa review.
3. EVA hanya membaca artikel & FAQ, tak pernah tiket.
4. EVA hanya MEREKOMENDASIKAN tiket — berhenti di draf, tanpa tulis ke tabel
   tiket.
5. Service Catalog milik role Admin — EVA hanya membaca (tak ada tambah/ubah/
   hapus kategori di EVA).
6. BPO & approval dikelola di Admin.

## Peta arsitektur singkat

- Pencarian A (jawaban): `KnowledgeSearch` → `FulltextKnowledgeSearch`
  (kb_articles, kb_faqs).
- Pencarian B (subject): `SubjectSearch` → `SubjectMatcher`
  (service_catalog_subjects).
- Keduanya di-bind di `app/Providers/AppServiceProvider.php` — seam portabilitas
  ke embedding/AI nanti.
- Otak percakapan: `EvaResponder` (jawab / clarify / clarify-seri / no-answer,
  semua log ke kb_answer_logs).
- Subject yang dilayani artikel: `Article::allSubjectIds()` (utama ∪
  `kb_article_subject`). Cakupan selalu lewat
  `Article::answerableCountsBySubject()` → `CoverageCalculator`.
- Layar & route: `config/eva.php` (nav), `routes/web.php` (grup `eva.`),
  `resources/views/eva/`, `resources/js/components/eva/`.
- Tabel EVA semua ber-prefix `kb_`.

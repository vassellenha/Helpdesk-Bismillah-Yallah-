# Eskalasi Tiket "Lainnya" — Temuan & Perbaikan

Catatan kerja 18 Agustus 2026, ditulis setelah laporan *"pas eskalasi dari BPO ke
IT, tiketnya tidak sampai ke PIC IT"*.

Semuanya berpusat pada satu jenis tiket: **tiket "Lainnya"** — requester memilih
Layanan tapi tidak menemukan Subject yang cocok, sehingga `catalog_subject_id`
tiketnya kosong. Tiket seperti ini tidak punya satu PIC yang ditunjuk katalog,
jadi dia di-*broadcast*: dilempar ke semua PIC Layanan itu, dan siapa pun yang
pertama bertindak otomatis menjadi pemiliknya.

## Cara kerja yang dituju

```
Requester pilih Layanan + Subject "Lainnya"
        |
        v
[TAHAP BPO]  broadcast ke SEMUA PIC BPO Layanan itu
        |    - semua dapat notifikasi + tiket muncul di My Tickets
        |    - siapa pun yang bertindak jadi pemilik, yang lain dilepas
        v
BPO menekan "Eskalasi IT"
        |
        v
[TAHAP IT]   broadcast ke SEMUA PIC IT Layanan itu
             - assigned_agent_id kembali null, status kembali Open
             - semua PIC IT dapat notifikasi + tiket muncul di My Tickets
             - siapa pun yang bertindak jadi pemilik, yang lain dilepas
```

`escalated_at` adalah penanda tahap: kosong berarti masih giliran BPO, terisi
berarti sudah giliran IT. `assigned_agent_id` dipakai ulang untuk kedua tahap —
`null` selalu berarti "belum diklaim siapa pun **di tahap yang sedang berjalan**".

Sumber daftar PIC sengaja **bukan** tabel terpisah, melainkan katalog itu
sendiri: kolom `support_agent_id` (BPO) dan `it_agent_id` (IT) di Subject-Subject
aktif Layanan tersebut. Jadi begitu Admin mengisinya di halaman Service Catalog,
efeknya langsung terasa — tidak ada yang perlu disinkronkan.

Satu hal yang sering disalahpahami: **membuka tiket tidak mengklaimnya.** Klaim
baru terjadi saat seseorang benar-benar bertindak.

| Aksi | Mengklaim? |
|---|---|
| Membuka halaman tiket | tidak |
| Menekan "Nanti" di popup | tidak |
| Menekan "Kerjakan Sekarang" | ya |
| Mengirim balasan di diskusi | ya |
| Service Closed / Eskalasi IT / Returned | ya |

## Bug yang diperbaiki

### 1. Tiket tidak muncul di daftar PIC yang punya lebih dari satu baris agent

Ada dua cara sistem mengenali "siapa PIC tiket ini", dan keduanya tidak sepakat:

| Dipakai untuk | Dicocokkan lewat |
|---|---|
| Notifikasi & izin membuka (`eligiblePics`, `canAct`) | `user_id` — orangnya |
| Daftar My Tickets / dashboard (`itServiceIds`, `bpoServiceIds`) | `id` baris `support_agents` |

Sebagian orang punya lebih dari satu baris `support_agents` untuk satu akun —
dobel peran BPO & IT, atau baris lama yang tertinggal saat namanya diperbarui.
Kalau katalog menunjuk baris **A** sementara login mengembalikan baris **B**,
hasilnya: loncengnya bunyi, tapi tiketnya tidak pernah muncul di daftar.

Dampaknya nyata di data yang ada:

| PIC (baris BPO) | Layanan terlihat sebelum | sesudah |
|---|---|---|
| Agung Wijayanto | **0** | 6 |
| Febria Sahrina | **0** | 6 |
| Arief Kurniawan | 1 | 6 |

**Perbaikan** — `SupportAgent::serviceIdsFor()` mencocokkan ke semua baris milik
akun yang sama, bukan hanya baris yang dipakai saat login.

Yang sengaja **tidak** ikut dilebarkan: pencocokan `assigned_agent_id` di
`visibleTicketsQuery`. Di sana filter per-baris justru yang menjaga tiket yang
sudah dieskalasi ke baris IT seseorang tidak ikut muncul di portal BPO-nya.

### 2. Tiket hilang total di Layanan yang tidak punya PIC IT

Kalau semua Subject aktif sebuah Layanan tidak mengisi `it_agent_id` (Level 1,
BPO-only), eskalasi tetap berjalan dan membalas `200 OK` — tapi tiketnya berakhir
`assigned_agent_id = null` dengan `escalated_at` terisi dan **tidak ada satu pun
orang** yang bisa melihat atau membukanya. Tanpa error, tanpa peringatan.

**Perbaikan** — `escalate()` memastikan dulu ada PIC IT lewat
`TicketBroadcast::itPics()`. Kalau kosong, jatuh ke jalur tunggal yang sudah ada
supaya selalu ada yang menerima.

### 3. Tiket nyangkut di daftar BPO setelah dieskalasi

Cabang broadcast di `SupportBpoController::visibleTicketsQuery()` tidak menyaring
`escalated_at`. Karena tiket hasil eskalasi juga `assigned_agent_id = null`, dia
kembali muncul di daftar semua PIC BPO seolah masih giliran mereka — satu tiket
tampil di dua portal sekaligus.

**Perbaikan** — tambahkan `whereNull('escalated_at')` di cabang broadcast,
cerminan dari `whereNotNull('escalated_at')` di sisi IT.

### 4. Panel PIC menampilkan nama pembacanya sendiri

`presentTicket()` mengisi `people.pic` dari agent **yang sedang melihat**, bukan
pemilik tiket. Akibatnya siapa pun yang membuka tiket melihat namanya sendiri
tercantum sebagai PIC — termasuk untuk tiket broadcast yang belum diklaim siapa
pun, tepat di sebelah tulisan "belum ada PIC" dari `TicketFlow`. Satu layar
menyatakan dua hal yang bertentangan, dan PIC mengira tiket yang masih bebas
sudah menjadi miliknya.

**Perbaikan** — `people.pic` diambil dari `assignedAgent`, dan bernilai `null`
saat belum ada pemilik. UI menuliskannya apa adanya: "Belum ada PIC".

### 5. PIC IT tidak mendapat popup "Mulai kerjakan tiket ini?"

Popup itu hanya muncul untuk tiket berstatus `Open`, dan `start()` menolak status
selain `Open` dengan 422. Tiket hasil eskalasi membawa status warisan tahap BPO
(`In Progress` kalau BPO sempat menekan Kerjakan Sekarang), jadi PIC IT menerima
tiket yang tampak sedang dikerjakan padahal tidak ada pemiliknya — dan tidak
punya jalan untuk mengklaimnya lewat tombolnya sendiri.

**Perbaikan** — `escalateBroadcast()` mengembalikan status ke `Open`. Tahap IT
memang dimulai dari nol.

## Berkas yang berubah

| Berkas | Perubahan |
|---|---|
| `app/Models/SupportAgent.php` | `serviceIdsFor()` — cocokkan semua baris milik satu akun |
| `app/Support/TicketBroadcast.php` | `itPics()` jadi publik; status kembali `Open` saat eskalasi |
| `app/Http/Controllers/SupportBpoController.php` | saring `escalated_at`; cek PIC IT sebelum broadcast; `people.pic` dari pemilik |
| `app/Http/Controllers/SupportController.php` | `people.pic` dari pemilik |
| `resources/js/components/support/SupportTicketDetail.jsx` | tampilkan "Belum ada PIC" saat kosong |
| `lang/id/support.php`, `lang/en/support.php` | kunci `detail.no_pic` |
| `tests/Feature/TicketEscalateBroadcastTest.php` | 5 tes regresi baru |

Perubahan JSX butuh build ulang aset (`npm run dev` atau `npm run build`);
perubahan PHP berlaku langsung.

## Verifikasi

**Tes otomatis** — 9 tes di `TicketEscalateBroadcastTest` dan
`TicketEscalateDualRoleTest` lolos. Suite penuh 569 lolos; 15 sisanya gagal
karena ekstensi PHP `zip` belum aktif di mesin lokal (`Class "ZipArchive" not
found`) dan tidak berhubungan dengan perubahan ini.

**Uji aplikasi sungguhan** — dijalankan lewat HTTP sebagai lima orang berbeda di
Layanan PERUBAHAN AKSES APLIKASI (4 PIC BPO, 6 PIC IT, tidak ada nama yang muncul
di dua sisi):

```
Requester buat tiket "Lainnya"                        OK
2 PIC BPO dua-duanya melihat tiketnya                 OK
BPO eskalasi ke IT                                    OK
tiket hilang dari daftar SEMUA PIC BPO                OK
6 PIC IT dapat notifikasi + tiket di My Tickets       OK
status kembali Open, PIC kosong (popup muncul)        OK
IT klik Kerjakan Sekarang, jadi pemiliknya            OK
PIC IT lain otomatis dilepas (403)                    OK
```

**Uji jalur Admin** — PIC IT diisi lewat halaman Service Catalog pada Layanan CCM
yang tadinya kosong, lalu flow-nya langsung jalan tanpa langkah tambahan. Katalog
CCM dikembalikan seperti semula setelahnya.

## Yang tersisa — ini data, bukan kode

Perbaikan di atas tidak menutup lubang di katalog. Tiga hal berikut perlu
dikerjakan Admin; kalau tidak, gejalanya akan muncul lagi di Layanan-Layanan itu.

**1. Layanan yang kolom PIC IT-nya kosong.** Eskalasi di sini tidak punya PIC IT
untuk dituju, jadi jatuh ke jalur tunggal:

> ADELE, ARISE, CCM, CLOUDIA, MAILIA, NETWORK, PERANGKAT, SILO APPS

**2. Layanan yang tidak punya PIC BPO yang lolos.** Tiket "Lainnya" di sini tidak
diterima siapa pun sejak awal:

> DHIERA, ERISKA, SINTA, VPN, Lisensi, QHSE, SDM

**3. Orang di slot BPO tanpa role `Support BPO`.** Mereka masuk katalog tapi
tersaring, jadi tidak pernah menerima tiket BPO apa pun:

| Orang | Role yang dimiliki |
|---|---|
| Aditya, Naufal, Sarah, Kevin, Rian | hanya `Support IT` |
| Denny Firmansyah | tidak punya role sama sekali |

Form Admin memvalidasi **tipe baris** (`support_agent_id` harus bertipe `bpo`,
`it_agent_id` harus bertipe `it`), tapi **tidak** memeriksa role akunnya. Jadi
Admin bisa menaruh orang tanpa role yang sesuai, dan orang itu tersaring
diam-diam tanpa peringatan apa pun di layar. Data bermasalah di atas berasal dari
seeder, bukan dari form Admin.

## Catatan untuk deploy

Fitur broadcast eskalasi (`ef2d951`) **sudah ada di `main`** lewat merge
`52910e3`, dan sudah hidup di server — terlihat dari label "Broadcast PIC IT" di
Riwayat Status tiket. Yang belum sampai ke server adalah **lima perbaikan di
dokumen ini**.

Sebelum fitur itu terpasang, eskalasi tiket "Lainnya" jatuh ke satu orang tetap —
agent IT aktif dengan `id` terkecil — lewat baris fallback ini:

```php
$itAgent = $subjectItAgent
    ?? SupportAgent::where('type', 'it')->where('is_active', true)->orderBy('id')->first();
```

Itu sebabnya semua eskalasi di server berakhir di orang yang sama, dan PIC IT
Layanan yang bersangkutan tidak pernah masuk hitungan.

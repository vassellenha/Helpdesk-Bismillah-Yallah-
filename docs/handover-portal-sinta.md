# Handover — Helpdesk di balik portal SINTA

> Ditulis 28 Agustus 2026, sesudah commit `27237aa`.
> Baca ini **sebelum** menyentuh apa pun yang berkaitan dengan lampiran,
> pemanggilan API dari sisi klien, atau keluhan "fitur tidak berfungsi".

---

## 1. Yang berubah secara mendasar

Helpdesk **tidak lagi diakses langsung**. Ia sekarang disajikan lewat modul
`remote` milik portal SINTA:

```
Pengguna → https://sinta.adhi.co.id/index.php/remote/new_remote/72//<jalur>
                                    ↓ (proxy sisi server)
           http://192.168.11.56/<jalur>          ← server helpdesk
```

`192.168.11.56` masih hidup dan bisa dibuka langsung, tapi **tidak bisa dipakai
login**: jalur login pengembangan (`DevLoginController`) dimatikan di produksi,
dan tombol yang tersisa melempar ke SINTA. Untuk menguji, masuklah lewat portal.

Hampir semua bug yang dilaporkan sejak pemindahan ini **bukan bug logika
aplikasi**, melainkan akibat perilaku proxy portal. Lima perilaku itu sudah
diukur langsung di produksi, bukan diduga — bukti tiap poin ada di pesan
commit-nya.

---

## 2. Lima perilaku proxy yang wajib diketahui

| # | Perilaku proxy | Akibatnya kalau dilupakan |
|---|---|---|
| 1 | Menulis ulang **setiap alamat helpdesk di dalam HTML** (tautan, `<script>`, `<link>`, dan JSON `data-props`) | Alamat dari server aman. Alamat yang **disusun JavaScript** tidak — ia dibaca relatif terhadap `sinta.adhi.co.id` dan mendarat di aplikasi portal |
| 2 | **Tidak meneruskan header khusus** seperti `X-CSRF-TOKEN` | Token hilang → "CSRF token mismatch" |
| 3 | **Tidak meneruskan body JSON mentah** | Seluruh isi permintaan lenyap; validasi menuduh field kosong |
| 4 | **Hanya meneruskan GET dan POST** | `PUT`, `PATCH`, `DELETE` tidak pernah sampai; balasannya HTML portal |
| 5 | **Menormalkan status menjadi 200** dan kadang mengganti `Content-Type` jadi `text/html`, membuang `Content-Disposition`, serta **memangkas byte nol di awal balasan** | `res.ok` selalu benar; berkas biner tampil sebagai simbol; MP4 rusak 3 byte di kepala |

### Aturan praktis untuk kode baru

- **Jangan** pernah menulis `fetch('/jalur/...')` langsung. Pakai `apiFetch()`
  dari `resources/js/lib/api.js` — ia yang mengurus basis alamat, penyamaran
  metode, dan token.
- **Jangan** menaruh alamat absolut hasil `route()` ke dalam **balasan JSON**.
  Pakai `route(..., absolute: false)` lalu susun di klien dengan `resolveUrl()`.
  Alamat di dalam Blade (`data-props`, `asset()`) aman karena ditulis ulang portal.
- **Jangan** percaya `Content-Type` dari balasan berkas. Tentukan dari ekstensi
  nama berkas.
- **Jangan** percaya status HTTP sebagai tanda sukses. `apiFetch` sudah menolak
  balasan 2xx yang isinya bukan JSON.

---

## 3. Perubahan yang sudah masuk

Semua sudah di `main` dan sudah ter-deploy ke produksi.

| Commit | Isi |
|---|---|
| `3cdceb0` | Basis alamat diturunkan dari `<script>` Vite yang sudah ditulis ulang portal (`resolveUrl`). `apiFetch` menolak balasan 2xx yang bukan JSON. Penangkap galat di setiap pulau React. `file_url` & `submit_url` jadi relatif. |
| `6376c41` | Kiriman dikemas sebagai **form**, bukan JSON. Token ikut sebagai field `_token`. `Content-Type` diserahkan ke browser. Widget EVA menolak balasan tanpa `type`. |
| `0993f42` | `PUT`/`PATCH`/`DELETE` disamarkan menjadi `POST` + `_method` (konvensi Laravel). |
| `9f31de8` | Permintaan tanpa body tetap dibuatkan kiriman, supaya `_token` punya tempat. |
| `871ba2a` | Pratinjau lampiran mengambil berkas sebagai data mentah lalu menentukan tipe dari ekstensi. Forum diskusi menerima **semua ekstensi**, batas **30 MB**. `store()` yang gagal kini ditolak, tidak lagi menyimpan baris dengan path kosong. |
| `c683b35` | `CommentAttachmentChip` (forum diskusi) ikut memakai pratinjau yang sama — sebelumnya masih `<a target="_blank">` ke alamat mentah. |
| `e706aa1` | Byte nol di kepala MP4/MOV dikembalikan (`pulihkanVideo`). Pesan "gagal diambil" dan "gagal ditampilkan" dibedakan. |
| `27237aa` | Logo Adhi Karya di header Team Lead — satu-satunya header yang dirender React — memakai `resolveUrl()`. |

Berkas yang paling penting dipahami sebelum mengubah apa pun:

- `resources/js/lib/api.js` — **pusat semua akal-akalan proxy**. Baca komentarnya.
- `resources/js/components/AttachmentPreview.jsx` — pratinjau lampiran bersama.
- `resources/js/app.jsx` — penangkap galat pulau React.

---

## 4. Yang BELUM selesai

### 4.1 Deploy terakhir gagal di tengah — periksa dulu

Build terakhir (`./deploy.sh` untuk `27237aa`) berhenti di plugin font:

```
[plugin laravel:fonts] TypeError: fetch failed
Error: connect ENETUNREACH 2602:ffe4:...:443
```

`laravel-vite-plugin` mengunduh font dari internet saat build, dan server
mencoba lewat **IPv6** yang tidak punya rute. Outbound IPv4 baik-baik saja
(`npm ci` di baris atasnya berhasil).

Jalan keluarnya:

```bash
NODE_OPTIONS=--dns-result-order=ipv4first npm run build
```

Sebaiknya dipasang permanen di `deploy.sh`. **Pastikan dulu produksi tidak
sedang tertinggal di mode maintenance** (`php artisan up`) — lihat 4.2.

### 4.2 `deploy.sh` meninggalkan situs offline saat gagal

Skrip memanggil `php artisan down` di baris awal dan `php artisan up` di baris
akhir. Karena `set -euo pipefail`, kegagalan di tengah membuat baris terakhir
tidak pernah tercapai dan **situs tetap offline**. Sudah terjadi sekali.

Perbaikannya satu baris, tepat setelah `php artisan down`:

```bash
trap 'php artisan up' EXIT
```

`deploy.sh` tidak ada di Git (berkas milik server), jadi harus disunting di sana.

Catatan lain: baris `sudo systemctl restart helpdesk-worker` sudah diganti
`php artisan queue:restart` karena user `helpdesk` diminta password untuk sudo,
dan skrip mati di situ. `queue:restart` juga lebih halus — worker menyelesaikan
job yang sedang jalan lalu keluar, systemd menyalakannya lagi (Restart=always).

### 4.3 Email notifikasi tidak pernah terkirim

Di `.env` produksi: `MAIL_MAILER=log`, `MAIL_HOST=127.0.0.1`, dan
`NOTIFY_EMAIL_ENABLED` tidak diset (defaultnya `true` — lihat
`config/notifications.php:55`).

Artinya aplikasi mengira notifikasi menyala, rajin menyusun email untuk setiap
tiket, lalu **membuangnya ke `storage/logs/laravel.log`**. Tidak ada error, dan
tidak satu pun email sampai ke pegawai sejak produksi hidup. Log juga membengkak
karena menampung HTML email.

Butuh kredensial SMTP dari pengelola. Sebelum itu ada, lebih jujur mematikannya
eksplisit: `NOTIFY_EMAIL_ENABLED=false`.

### 4.4 `composer.json` di server berbeda dari Git

Di server, `fakerphp/faker` dipindahkan dari `require-dev` ke `require` — supaya
`composer install --no-dev` tetap memasangnya, karena `DatabaseSeeder` memakai
factory. Perubahan itu **hanya ada di server, di luar Git**.

Siapa pun yang clone ulang atau menjalankan `git checkout composer.json` akan
kehilangannya diam-diam, dan seeding berhenti bekerja. Putuskan salah satu:
commit perubahan itu, atau perbaiki akarnya (produksi tidak menjalankan seeder
yang butuh Faker).

### 4.5 Izin folder penyimpanan

Lampiran tiket gagal disimpan selama beberapa hari karena
`storage/app/private/ticket-attachments` dibuat lewat CLI oleh user `helpdesk`
dengan mode `0700`, sedangkan yang menulis adalah PHP-FPM (`www-data`).
`store()` memulangkan `false`, dan barisnya tersimpan dengan `path=0`.

Sudah diperbaiki untuk folder itu (`chown www-data`, `chmod 2775`), tapi
**berlaku untuk folder penyimpanan baru mana pun** yang dibuat lewat artisan.
Amannya, sesudah menambah fitur yang menulis ke disk:

```bash
sudo chown -R helpdesk:www-data storage/app/private
sudo find storage/app/private -type d -exec chmod 2775 {} \;
```

Lima lampiran lama dengan `path=0` tidak bisa diselamatkan — berkasnya memang
tidak pernah tertulis.

### 4.6 Akun uji

`dummysinta@adhi.co.id` (user #3843) dibuat untuk pengujian, memegang ketujuh
role, dan **`nip`-nya sengaja NULL**. Itu bukan kelalaian: `EmployeeSync`
mencocokkan lewat NIP dan menonaktifkan baris ber-NIP yang tidak ada di data HR,
tapi seluruh kueri-nya memakai `whereNotNull('nip')`. NIP kosong membuat akun ini
tidak terlihat oleh sinkronisasi. **Jangan isi kolom itu.**

---

## 5. Cara menguji dengan benar

1. Deploy: `cd /var/www/helpdesk && ./deploy.sh`
2. **Buka dari menu SINTA di tab BARU.** Jangan me-reload tab lama: nama bundel
   JS berganti tiap build, dan tab lama masih memegang yang lama — perbaikan
   akan terlihat "tidak berhasil" padahal belum termuat.
3. Pastikan bundelnya benar: F12 → Network → berkas `app-*.js` harus bernama
   sama dengan yang ada di `public/build/manifest.json` server.

Kalau ada yang gagal, gejalanya sekarang jauh lebih terbaca daripada sebelumnya:

| Yang terlihat | Artinya |
|---|---|
| Kotak merah "Bagian ini gagal ditampilkan" | Komponen React crash — pesannya menyebut sebabnya, jejak lengkap di console |
| "Server tidak membalas dengan data (status …, text/html…)" | Permintaan tidak sampai ke helpdesk — periksa alamatnya di tab Network |
| Balasan berisi `<title>Page Expired</title>` | Token CSRF tidak sampai — permintaan dikirim tanpa body |
| Berkas tampil sebagai deretan simbol | Ada komponen yang membuka alamat mentah, bukan lewat `useAttachmentPreview` |

Alat yang paling cepat menjawab: **tab Network**. Lihat alamat permintaannya —
kalau tidak mengandung `remote/new_remote/72`, ia nyasar ke portal.

---

## 6. Cara menyelidiki, kalau menemukan gejala baru

Pola yang berulang sepanjang perbaikan ini: **jangan menebak, ukur di produksi.**
Semua penyebab di atas ditemukan dengan cara yang sama — menjalankan potongan
JavaScript di halaman yang sedang bermasalah, lalu membaca status, tipe konten,
dan byte pembuka balasannya. Contoh yang membuka kasus MP4:

```js
const r = await fetch(alamatLampiran);
const b = new Uint8Array(await r.arrayBuffer());
console.log(r.status, r.headers.get('content-type'),
            [...b.slice(0, 8)].map(x => x.toString(16)));
```

Byte pembukanya yang membuktikan berkasnya utuh dan proxy-lah yang memotong —
bukan sebaliknya.

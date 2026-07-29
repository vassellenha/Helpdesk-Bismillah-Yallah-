# EVA — Prasyarat Deploy

> Yang WAJIB hidup di server selain PHP-FPM/nginx. Dua-duanya adalah proses
> latar; keduanya gagal dengan diam, bukan dengan error di layar — itulah alasan
> dokumen ini ada.

---

## Ringkas

| Proses | Perintah | Kalau tidak hidup |
|---|---|---|
| Pekerja antrean | `php artisan queue:work` | Dokumen yang diunggah berhenti di `processing` **selamanya**, tanpa error di layar maupun di log |
| Penjadwal | `php artisan schedule:run` (tiap menit) | Grafik tren Coverage berhenti bertambah titik; hari yang terlewat **tidak bisa** direkam ulang |

Keduanya sama-sama tidak menimbulkan gejala yang menunjuk dirinya sendiri.
Yang pertama tampak seperti bug unggah; yang kedua baru ketahuan berminggu-
minggu kemudian saat grafiknya diperhatikan.

---

## 1. Pekerja antrean (supervisor)

Indexing dokumen — termasuk OCR PDF yang bisa memakan menit — dikerjakan
`App\Jobs\Knowledge\IndexDocument` di antrean. Driver antreannya `database`
(`QUEUE_CONNECTION=database`), jadi tidak ada Redis yang perlu disiapkan; tabel
`jobs` & `failed_jobs` sudah ada di migrasi tim.

`/etc/supervisor/conf.d/eva-worker.conf`:

```ini
[program:eva-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/helpdesk/artisan queue:work --queue=default --tries=1 --timeout=900 --sleep=3 --max-time=3600
directory=/var/www/helpdesk
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/helpdesk/storage/logs/worker.log
stopwaitsecs=960
```

```bash
sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl start eva-worker:*
```

Angka yang TIDAK boleh diubah asal:

- **`--tries=1`** — sama dengan `IndexDocument::$tries`. Kegagalan di job ini
  hampir selalu soal isi berkas (pindaian buram, PDF rusak, binari OCR belum
  terpasang); mengulang OCR mahal yang pasti gagal lagi hanya menahan antrean
  untuk dokumen berikutnya.
- **`--timeout=900`** dan **`stopwaitsecs=960`** — sama dengan
  `IndexDocument::$timeout`, dan `stopwaitsecs` harus LEBIH BESAR daripadanya.
  Kalau lebih kecil, supervisor membunuh pekerja di tengah OCR saat restart dan
  dokumennya tertinggal `processing`.
- **`numprocs=1`** — OCR memakai CPU penuh per halaman. Menaikkannya baru masuk
  akal setelah terbukti antreannya yang jadi hambatan, bukan CPU-nya.

Setelah deploy kode baru, pekerja WAJIB dimuat ulang — proses lama masih memakai
kode lama di memori:

```bash
php artisan queue:restart          # dijalankan sebagai bagian dari skrip deploy
```

## 2. Penjadwal (cron)

Satu baris crontab, seperti Laravel pada umumnya:

```cron
* * * * * cd /var/www/helpdesk && php artisan schedule:run >> /dev/null 2>&1
```

Yang dijadwalkan EVA saat ini hanya satu (`routes/console.php`):

- `eva:snapshot-coverage` — harian pukul 01:00, `withoutOverlapping()`. Merekam
  satu titik riwayat Coverage per tanggal. Aman dijalankan berkali-kali
  (`updateOrCreate` per tanggal), tetapi **hari yang terlewat hilang permanen**:
  angkanya sudah berubah saat hari itu lewat.

## 3. OCR (opsional, tapi menentukan)

Tanpa poppler + Tesseract, PDF pindai **ditolak saat diunggah** dengan pesan
yang menyuruh admin menempelkan teksnya — bukan gagal diam-diam. Jadi ini bukan
syarat agar aplikasi jalan, melainkan syarat agar PDF bisa dibaca sendiri.

```bash
sudo apt install poppler-utils tesseract-ocr tesseract-ocr-ind tesseract-ocr-eng
```

`tesseract-ocr-ind` wajib: tanpa paket bahasa Indonesia, Tesseract memakai
Inggris dan hasilnya pada teks Indonesia berantakan.

PHP-FPM kerap berjalan dengan PATH minimal sehingga binari itu tidak terlihat
walau sudah terpasang. Kalau begitu, sebutkan path-nya di `.env`:

```dotenv
EVA_PDFINFO_PATH=/usr/bin/pdfinfo
EVA_PDFTOTEXT_PATH=/usr/bin/pdftotext
EVA_PDFTOPPM_PATH=/usr/bin/pdftoppm
EVA_TESSERACT_PATH=/usr/bin/tesseract
EVA_OCR_LANGUAGES=ind+eng
```

Seluruh pengaturan lain (dpi, batas halaman, timeout) ada di `config/eva.php`
berikut alasan angkanya.

## 4. Penyimpanan berkas

Berkas asli disimpan di disk **privat** (`storage/app/private/kb-documents`),
bukan `public` — isi SOP internal tidak boleh terjangkau lewat web. Yang perlu
dipastikan hanya izin tulis `storage/` untuk user PHP-FPM; jangan membuat
symlink apa pun ke folder itu.

---

## Memastikan setelah deploy

```bash
php artisan about                          # QUEUE_CONNECTION harus database
php artisan queue:failed                   # harus kosong
php artisan schedule:list                  # eva:snapshot-coverage harus terdaftar
sudo supervisorctl status eva-worker:*     # RUNNING
```

Lalu uji jalur yang paling mudah salah: unggah satu TXT di layar Documents dan
pastikan statusnya berpindah `processing` → `indexed` sendiri dalam beberapa
detik. Kalau bertahan di `processing`, pekerja antreannya tidak hidup — bukan
unggahnya yang rusak.

Dokumen yang terlanjur tersangkut tidak perlu diunggah ulang: nyalakan
pekerjanya, lalu tekan **Indeks ulang** di barisnya.

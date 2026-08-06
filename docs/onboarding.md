# Onboarding — Setup & Deploy untuk Anggota Tim Baru

Panduan untuk orang yang baru bergabung dan perlu bisa mengembangkan sekaligus
men-deploy Helpdesk 2.0.

Untuk memasang server dari nol, lihat [deployment.md](deployment.md). Berkas ini
mengasumsikan servernya **sudah berjalan**.

---

## Bagian 1 — Akses yang harus diminta (sekali saja)

| Yang dibutuhkan | Minta ke |
|---|---|
| VPN `adhikarya` | Tim IT |
| Akses repo GitHub | Pemilik repo — minta diundang sebagai collaborator |
| Akses SSH ke server | Pemilik server |

Server ada di jaringan internal (`192.168.11.56`). **VPN wajib menyala** sebelum
bisa SSH; tanpa itu koneksi akan menggantung lalu timeout, dan itu bukan tanda
servernya bermasalah.

### Akses SSH: pakai kunci, jangan password bersama

Password yang dipakai beramai-ramai tidak bisa dicabut per orang, dan `last` di
server tidak bisa menunjukkan siapa yang masuk. Pakai kunci SSH.

**Yang baru bergabung menjalankan di laptopnya:**

```bash
ssh-keygen -t ed25519 -C "nama@adhi"
cat ~/.ssh/id_ed25519.pub
```

Kirimkan barisnya (dimulai dengan `ssh-ed25519 …`) ke pemilik server.

**Pemilik server menempelkannya:**

```bash
ssh helpdesk@192.168.11.56
echo "ssh-ed25519 AAAA... nama@adhi" >> ~/.ssh/authorized_keys
```

Mencabut akses nanti = menghapus satu baris itu.

### Supaya tidak perlu menghafal alamatnya

Di laptop, tambahkan ke `~/.ssh/config`:

```
Host helpdesk
    HostName 192.168.11.56
    User helpdesk
```

Setelah itu cukup `ssh helpdesk`.

---

## Bagian 2 — Siapkan laptop (sekali saja)

### 1. Prasyarat

macOS:

```bash
brew install php@8.3 composer node mysql
brew services start mysql
```

Ubuntu: lihat daftar paket di [deployment.md §2](deployment.md) — sama persis,
minus Nginx.

### 2. Ambil kode

```bash
git clone https://github.com/vassellenha/Helpdesk-Bismillah-Yallah- helpdesk
cd helpdesk
```

### 3. Buat database lokal

```bash
mysql -u root -e "CREATE DATABASE helpdesk CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

**Wajib MySQL, bukan PostgreSQL.** Pencarian EVA memakai indeks FULLTEXT MySQL;
di PostgreSQL indeksnya dilewati tanpa error dan EVA diam-diam jadi payah.
Alasan lengkapnya di [deployment.md §0](deployment.md).

### 4. Atur `.env`

```bash
cp .env.example .env
nano .env
```

```dotenv
DB_CONNECTION=mysql
DB_DATABASE=helpdesk
DB_USERNAME=root
DB_PASSWORD=
```

### 5. Pasang semuanya sekaligus

```bash
composer setup
```

Satu perintah ini menjalankan `composer install`, `key:generate`, `migrate`,
`npm install`, dan `npm run build`.

### 6. Isi data contoh

```bash
php artisan db:seed
```

### 7. Jalankan

```bash
composer dev
```

Menyalakan empat proses sekaligus: server PHP, queue worker, log viewer, dan
Vite. Buka `http://localhost:8000`.

**Queue worker wajib hidup.** Indexing dokumen EVA (termasuk OCR) berjalan di
antrean — tanpa worker, dokumen yang diunggah berhenti di status `processing`
selamanya.

### 8. OCR (opsional, hanya kalau mengerjakan fitur dokumen EVA)

```bash
brew install poppler tesseract tesseract-lang     # macOS
```

Tanpa ini, PDF hasil pindaian menghasilkan 0 potongan.

---

## Bagian 3 — Alur kerja harian

```bash
git pull origin main          # selalu tarik dulu sebelum mulai
# ... kerjakan perubahan ...
php artisan test              # WAJIB hijau sebelum push
git add -A
git commit -m "feat: deskripsi perubahan"
git push origin main
```

Format pesan commit: `<type>: <deskripsi>` —
`feat`, `fix`, `refactor`, `docs`, `test`, `chore`.

**Pecah commit per satu ide.** Kalau nanti satu perubahan bermasalah di
produksi, `git revert <hash>` bisa membatalkan hanya bagian itu. Satu commit
gemuk berisi lima hal memaksa kelimanya dibatalkan sekaligus.

---

## Bagian 4 — Deploy

```bash
# 1. Nyalakan VPN adhikarya

# 2. Masuk server
ssh helpdesk

# 3. Deploy
cd /var/www/helpdesk
git status                    # harus "working tree clean"
./deploy.sh

# 4. Pastikan worker hidup
sudo systemctl is-active helpdesk-worker
```

Skripnya berakhir dengan `Deploy selesai: <hash>`. **Cocokkan hash itu** dengan
commit terakhir Anda.

### Lalu buka browser dan klik sendiri

**Hard refresh dulu** (`Cmd+Shift+R` / `Ctrl+Shift+R`), lalu klik fitur yang
baru diubah.

Ini bukan formalitas. `deploy.sh` pernah mencetak "Deploy selesai" padahal
`npm run build` gagal — akibatnya backend versi baru, tampilan versi lama.
Satu-satunya yang menangkap itu adalah mata Anda sendiri di browser.

---

## Bagian 5 — Aturan main bersama

**Jangan deploy bersamaan.** Dua orang menjalankan `deploy.sh` bersamaan akan
bertabrakan di tengah `git pull`. Cukup bilang di grup: *"deploy dulu ya"*.

**Jangan pernah mengedit berkas langsung di server.** Satu berkas yang diubah di
`/var/www/helpdesk` membuat `git pull` berikutnya konflik, skrip berhenti di
tengah, dan aplikasi tertinggal dalam keadaan mati. Semua perubahan lewat Git,
tanpa kecuali.

**Jangan pakai `sudo` untuk `npm install` atau `composer install`.** Berkasnya
jadi milik root dan deploy berikutnya gagal dengan `EACCES`. Kalau terasa butuh
`sudo`, yang salah biasanya kepemilikan foldernya, bukan perintahnya.

**Selalu `php artisan test` sebelum push.** Push ke `main` berarti langsung siap
masuk produksi — tidak ada staging yang menangkap kesalahan.

**`.env` tidak ada di Git.** Kalau perubahan Anda menambah variabel baru,
tambahkan manual di server lalu:

```bash
php artisan optimize:clear && php artisan optimize
```

Gejala kalau lupa: fiturnya diam saja tanpa error, memakai nilai bawaan.

**Migrasi tidak punya tombol batal.** `git revert` mengembalikan kode, bukan
struktur database. Migrasi yang menghapus kolom sebaiknya dipisah ke deploy
tersendiri.

---

## Bagian 6 — Kalau ada yang salah

| Gejala | Tindakan |
|---|---|
| `ssh` menggantung lalu timeout | VPN `adhikarya` belum menyala |
| `deploy.sh` berhenti di tengah, aplikasi mati | Baca errornya, perbaiki, jalankan ulang. Paksa hidup: `php artisan up` |
| Deploy sukses tapi tampilan tidak berubah | `npm run build` gagal. Cek log deploy-nya, lalu lihat [deployment.md §15](deployment.md) |
| `git pull` konflik di server | Ada yang mengedit langsung di server. `git checkout -- .` lalu ulangi |
| Dokumen macet `processing` | Worker mati: `sudo systemctl status helpdesk-worker` |
| Perlu mengembalikan versi lama | Lihat prosedur rollback di [deployment.md](deployment.md) |

Log aplikasi ada di `storage/logs/laravel.log`.

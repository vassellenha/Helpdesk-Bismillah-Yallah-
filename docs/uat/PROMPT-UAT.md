# Lanjutkan UAT Helpdesk 2.0

Saya sedang mengerjakan User Acceptance Testing untuk aplikasi Helpdesk 2.0 milik ADHI Karya.
Tolong lanjutkan dari **test case kosong berikutnya**.

---

## 0. LANGKAH PERTAMA — jangan lewati

Jangan menebak lokasi berkas apa pun. Jalankan ini dulu dari dalam folder proyek:

```bash
python3 docs/uat/uat-status.py
```

Skrip itu akan memberi tahu, dengan mencari sendiri di komputer ini:

- lokasi folder proyek, Form UAT (.xlsx), folder screenshot, dan nama database
- apakah Excel sedang dibuka (kalau iya skrip berhenti — **jangan menulis**)
- berapa test case yang sudah selesai dan **baris mana yang harus dikerjakan berikutnya**
- isi lengkap baris tersebut (kolom B–I)
- nomor screenshot berikutnya yang harus dipakai

Kalau `openpyxl` belum ada: `pip3 install openpyxl`.

**Kalau skrip gagal menemukan sesuatu**, jangan berimprovisasi — tanyakan ke saya. Lokasi berkas
berbeda-beda di tiap komputer, dan menulis ke berkas yang salah lebih buruk daripada bertanya.

Kalau harus diisi manual, ini tiga hal yang perlu Anda tanyakan ke saya:

| Apa | Isi |
|---|---|
| Folder proyek | _(biasanya folder repo ini sendiri)_ |
| Form UAT | `.../Form UAT Sistem Helpdesk 2.0 - Terisi.xlsx` |
| Folder screenshot | `.../UAT Helpdesk 2.0/` |

---

## 1. Menyiapkan aplikasi

Stack: Laravel 13 + Blade + React islands (Vite), MySQL.

```bash
php artisan serve          # http://127.0.0.1:8000
npm run dev                # kalau aset belum ter-build
php artisan queue:work     # sebagian fitur butuh queue hidup
```

Nama database dibaca dari `.env` (`DB_DATABASE`) — skrip di atas sudah menampilkannya.
Jangan mengasumsikan nama database tertentu.

### Akun uji

Login lokal: buka `/login`, **cukup email, tanpa password** (hanya aktif di environment
non-produksi). Isi database tiap orang bisa berbeda, jadi cari sendiri akunnya:

```bash
php artisan tinker --execute="
\$rows = DB::table('users as u')
  ->join('role_user as ru','ru.user_id','=','u.id')
  ->join('roles as r','r.id','=','ru.role_id')
  ->selectRaw('u.name, u.email, GROUP_CONCAT(r.name ORDER BY r.name SEPARATOR \", \") as roles, (SELECT COUNT(*) FROM tickets t WHERE t.requester_id = u.id) as tiket')
  ->groupBy('u.id','u.name','u.email')->orderByDesc('tiket')->limit(10)->get();
foreach(\$rows as \$r) echo \$r->name.' | '.\$r->email.' | '.\$r->tiket.' tiket | '.\$r->roles.PHP_EOL;
"
```

Pilih akun yang perannya sesuai kolom Aktor pada test case. Untuk uji Requester, pakai akun
dengan tiket paling banyak. Akun yang punya semua peran sekaligus biasanya ada tombol
**Switch Role** di pojok kanan bawah untuk berpindah peran dengan cepat.

---

## 2. Struktur form UAT

Sheet **"Test Case UAT"**, data mulai baris 8. Total 73 test case (baris 8–80).

| Kolom | Isi |
|---|---|
| B | No |
| C | Kode FR |
| D | Use Case (mis. "UC-04 Mengelola Detail Tiket") |
| E | Aktor |
| F | Skenario Pengujian |
| G | Langkah Pengujian |
| H | Data / Input Uji |
| I | Hasil yang Diharapkan (Expected) |
| **J** | **Hasil Aktual — diisi** |
| **K** | **Status — dropdown: Pass / Fail / Blocked / Not Tested** |
| **L** | **Catatan / Bukti — diisi** |
| M | Tanggal Uji |
| N–Q | Kolom CLIENT — **JANGAN DISENTUH**, itu untuk klien |

Sheet "Rekap & Sign-off" menghitung otomatis dari kolom K. **Jangan rusak formulanya.**

---

## 3. ATURAN WAJIB — ini yang saya minta dan harus dipatuhi

### 3.1 Jujur di atas segalanya
Jangan menandai Pass kalau ada butir Expected yang tidak terpenuhi. Kalau ragu, katakan ragu.
Jangan mengarang hasil. Kalau ada yang tidak bisa diuji, tulis apa adanya dan jelaskan kenapa.
Kalau salah, ralat dengan jelas — jangan diam-diam diperbaiki.

### 3.2 Satu test case per giliran
Kerjakan SATU test case, tulis hasilnya, laporkan, lalu **berhenti dan tunggu saya bilang
"lanjut"**. Jangan main hantam 10 test case sekaligus.

### 3.3 Screenshot seperlunya saja
Maksimal ~2 screenshot per test case. Cukup yang benar-benar membuktikan. Kalau sesuatu bisa
dibuktikan lewat teks (angka, pesan galat, status HTTP), **cukup tulis teksnya, jangan pakai
gambar**.

### 3.4 Penamaan screenshot
Nama berkas = nomor use case + urutan: `UC-04-01.png`, `UC-04-02.png`, dst. Angka di belakang
menandakan urutan screenshot dalam satu use case, dan **terus berlanjut** kalau satu use case
dipakai beberapa test case. Nomor lanjutan yang benar sudah dihitung oleh skrip di bagian 0 —
pakai angka itu, jangan menghitung sendiri.

### 3.5 Folder screenshot per aktor
Taruh di `<folder screenshot>/<Nama Aktor>/`, mis. `Requester/`, `Approver/`, `Support BPO/`,
`Support IT/`, `Team Lead/`, `Administrator/`. Buat folder baru saat masuk aktor lain.
Tanda `/` pada nama aktor diganti ` : ` karena macOS tidak menerimanya
(mis. `Requester : Administrator`).

### 3.6 Kolom Bukti (L) cukup nama berkas
Contoh isi: `UC-04-01 & UC-04-02`. Kalau ada temuan penting, tulis di bawahnya secara ringkas.
**Jangan menyisipkan gambar ke dalam Excel** — cukup nama berkasnya.

### 3.7 Teks jangan bertele-tele
Kolom J dan L harus padat. Patokan: kolom J sekitar 600–900 karakter, kolom L sekitar
300–900 karakter.

### 3.8 JANGAN menulis ke Excel saat berkasnya sedang dibuka
Skrip di bagian 0 sudah memeriksa ini dan akan berhenti sendiri kalau Excel terbuka.
Kalau berhenti karena itu: **jangan menulis**, beri tahu saya supaya saya tutup Excel dulu.
Menulis saat terbuka membuat dokumen jadi read-only dan tulisan bisa hilang. Ini sudah pernah
terjadi. **Selalu backup dulu sebelum menulis** (`cp` ke nama `...backup-HHMMSS.xlsx`).

### 3.9 Jangan commit tanpa saya minta
Kerjakan sampai siap di-commit, lalu berhenti dan tunggu persetujuan saya.

### 3.10 Verifikasi di browser, bukan cuma tes otomatis
Tes hijau bukan bukti aplikasi jalan. Klik sendiri di browser sebelum melapor selesai.

### 3.11 Kalau menemukan bug, jangan langsung memperbaiki
Laporkan dulu, biar saya yang putuskan.

---

## 4. Jebakan teknis yang sudah ditemukan — hemat waktu Anda

### 4.1 Isian textarea React tidak tertangkap oleh tool otomasi
Tool `fill` dari chrome-devtools MCP **tidak memicu onChange React**. DOM-nya terisi tapi state
React tetap kosong, jadi datanya terkirim kosong ke server tanpa error apa pun. Ini sempat
membuat 3 tiket uji tersimpan dengan deskripsi NULL dan hampir dilaporkan sebagai bug.

Pakai cara ini untuk textarea/input React:

```javascript
const ta = document.querySelector('textarea');
const setter = Object.getOwnPropertyDescriptor(window.HTMLTextAreaElement.prototype,'value').set;
setter.call(ta, 'teks yang diinginkan');
ta.dispatchEvent(new Event('input', { bubbles: true }));
```

Untuk `<input>`, ganti `HTMLTextAreaElement` jadi `HTMLInputElement`.
**Selalu verifikasi ke DATABASE setelah submit**, jangan percaya DOM saja.

### 4.2 Dropdown di aplikasi ini bukan `<select>` biasa
Semuanya komponen React custom. Cara yang berhasil: `take_snapshot` → cari uid tombolnya →
`click` → `take_snapshot` lagi untuk melihat pilihan yang muncul → `click` pilihan.
Memanggil `.click()` lewat evaluate_script sering gagal karena butuh jeda render.

### 4.3 Screenshot tidak bisa langsung disimpan ke luar folder proyek
Tool chrome-devtools hanya boleh menulis ke dalam folder proyek. Simpan dulu ke
`storage/app/uat-tmp/`, lalu `mv` ke folder screenshot. Bersihkan `uat-tmp` setelahnya supaya
proyek tidak kotor.

### 4.4 Perilaku yang BUKAN bug (jangan dilaporkan sebagai temuan)
- Donut "Distribusi SLA" di dashboard ikut rentang Minggu/Bulan/Tahun, bukan seluruh tiket aktif.
- Tiket berstatus Draft dan Waiting for Approval punya `sla_kind = 'none'` — SLA memang belum
  berjalan. Target SLA-nya dihitung ulang dari waktu submit, bukan waktu draft dibuat.
- Kartu ringkasan tidak langsung berubah setelah aksi, baru benar setelah halaman di-refresh.
- Prefiks nomor tiket ada TIGA: INC (Incident), SR (Service Request), AR (Access Request),
  mengikuti Kategori Masalah yang diturunkan dari Subjek katalog.

### 4.5 Data uji di kolom H kadang tidak ada di katalog asli
Contoh: pernah ada test case menyebut sub-kategori yang tidak ada di layanan tersebut.
Kalau ketemu begini: pakai padanan terdekat yang benar-benar ada, lalu **catat di kolom L**
bahwa data uji di kolom H perlu disesuaikan.

### 4.6 Bersihkan kembali data yang diubah
Kalau test case mengubah data (menonaktifkan user, mengubah SLA, dsb), **kembalikan ke kondisi
semula setelah selesai** dan catat di kolom L bahwa sudah dikembalikan.

Tiket uji yang dibuat selama UAT boleh dibiarkan supaya bukti bisa ditelusuri ulang —
sebutkan nomor tiketnya di kolom L.

---

## 5. Riwayat temuan

Beberapa bug sudah ditemukan lewat UAT ini dan sudah diperbaiki. Untuk melihat yang mana:

```bash
git log --oneline -15
```

Kalau menemukan bug baru, ikuti aturan 3.11 — laporkan dulu, jangan langsung perbaiki.

---

## 6. Yang saya minta sekarang

Kerjakan **satu test case** — yaitu baris kosong berikutnya yang ditunjukkan skrip di bagian 0:

1. Jalankan `python3 docs/uat/uat-status.py` untuk tahu baris mana dan apa yang diuji
2. Uji betulan di browser di `http://127.0.0.1:8000` — jangan cuma baca kode
3. Verifikasi tiap butir Expected satu per satu, cek ke database bila perlu
4. Ambil maksimal 2 screenshot, simpan dengan penomoran yang ditunjukkan skrip
5. Backup Excel, lalu tulis kolom J (Hasil Aktual), K (Status), L (Bukti), M (Tanggal hari ini)
6. Laporkan secara ringkas, lalu **berhenti** dan tunggu saya bilang "lanjut"

Kalau skrip melaporkan ada baris yang terlewat lebih awal, **jangan diisi sendiri** — beberapa
sengaja dilewati (mis. Login SSO yang belum terpasang di lingkungan lokal). Tanyakan dulu.

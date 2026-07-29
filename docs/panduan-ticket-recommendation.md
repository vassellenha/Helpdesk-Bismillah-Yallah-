# Panduan Layar **Ticket Recommendation**

> Untuk admin yang baru pertama kali membuka konsol EVA.
> Tidak perlu tahu apa pun soal kode. Cukup 10 menit.
>
> Alamat layar: `http://127.0.0.1:8000/eva/recommendation`
> (sidebar → **AI TRAINING → Ticket Recommendation**)

---

## 1. Sebelum apa pun: layar ini BUKAN tentang jawaban

Ini sumber kebingungan nomor satu, jadi diselesaikan lebih dulu.

Bayangkan EVA seperti petugas di meja depan klinik. Ia melakukan dua hal yang
sangat berbeda:

| Peran | Pertanyaannya | Layar yang mengurus |
|---|---|---|
| **Menjawab** | "Saya tahu jawabannya, ini caranya." | EVA Preview, Article Library, Manage FAQ |
| **Mengarahkan** | "Saya tidak tahu — tapi ini urusan bagian mana." | **Ticket Recommendation** ← Anda di sini |

Layar ini hanya mengurus peran kedua. Ia menjawab satu pertanyaan saja:

> **Kalau EVA menyerah dan karyawan terpaksa membuat tiket, tiket itu akan
> diarahkan ke masalah yang mana di katalog layanan?**

Katalog layanan (Service Catalog) itu **daftar nama masalah**, bukan daftar
solusi. Isinya seperti "Reset Password", "Tidak bisa cetak ke printer jaringan",
"Interface Error". Jadi layar ini tidak sedang menilai bagus-tidaknya jawaban
EVA — ia menilai **ketepatan pengarahan**.

Sekali lagi, karena ini penting: **kalau ada yang terlihat salah di layar ini,
belum tentu materinya yang kurang. Bisa jadi kosakatanya yang tidak dikenali.**
Cara membedakannya ada di bagian 6.

---

## 2. Peta layar

Dari atas ke bawah ada empat bagian:

```
┌──────────────────────────────────────────────────────────────┐
│  ①  Empat kartu angka                                        │
│     PERTANYAAN GAGAL · SARAN KUAT · SARAN LEMAH · TANPA SARAN│
├──────────────────────────────────────────────────────────────┤
│  ②  Uji pengarahan                                           │
│     kotak ketik + tombol "Uji"                               │
├──────────────────────────────────────────────────────────────┤
│  ③  Subject tujuan yang belum punya materi     ← PALING PENTING│
│     daftar tugas menulis Anda                                │
├──────────────────────────────────────────────────────────────┤
│  ④  Tabel pengarahan                                         │
│     satu baris per pertanyaan yang gagal dijawab             │
└──────────────────────────────────────────────────────────────┘
```

Kalau Anda cuma punya waktu sebentar: **lihat bagian ③ saja.** Itu daftar
pekerjaan yang bisa langsung dikerjakan. Sisanya adalah penjelasan mengapa
daftar itu berisi apa yang berisi.

---

## 3. Dari mana angkanya datang

Setiap kali Anda membuka layar ini, sistem melakukan ini — **dari nol, saat itu
juga**:

1. Mengambil hingga **40 pertanyaan yang gagal dijawab EVA** (dicatat otomatis
   setiap kali seseorang bertanya dan EVA tidak menemukan jawaban).
2. Tiap pertanyaan itu **dicocokkan ulang** ke katalog layanan.
3. Hasilnya ditampilkan.

Konsekuensi yang menguntungkan Anda: **tidak ada yang disimpan.** Begitu Anda
menerbitkan satu artikel atau menambah satu sinonim, **seluruh riwayat langsung
ikut membaik** — tidak ada tombol "proses ulang", tidak ada yang perlu ditunggu.
Cukup muat ulang halaman.

---

## 4. Kamus: arti tiap angka dan lencana

### Empat kartu di atas

| Kartu | Bacanya | Kalau angkanya besar, artinya |
|---|---|---|
| **PERTANYAAN GAGAL** | Berapa pertanyaan gagal yang sedang diperiksa | EVA sering menyerah — normal di awal, saat materi masih sedikit |
| **SARAN KUAT** 🟢 | Katalognya ketemu dengan yakin; subject akan **diisikan otomatis** ke draf tiket | Bagus. Pengarahan sudah tepat, tinggal materinya yang perlu ditulis |
| **SARAN LEMAH** ⚪ | Ada calon, tapi belum cukup yakin; karyawan yang memilih sendiri | Kosakata katalog dan kosakata karyawan belum ketemu — tambah sinonim |
| **TANPA SARAN** 🔴 | Tak satu pun subject mendekati | **Sinyal paling serius.** Lihat bagian 6, kasus B |

### Lencana di daftar

| Lencana | Arti | Tindakan Anda |
|---|---|---|
| 🟢 **terisi otomatis** | Subject ini akan langsung terisi di draf tiket, tanpa karyawan memilih | Tidak ada — ini sudah benar |
| ⚪ **perlu dipilih manual** | Ditawarkan sebagai calon, tapi karyawan yang memutuskan | Kalau berulang untuk kata yang sama, tambah sinonimnya |
| 🔴 **belum ada materi** | Subject tujuannya **belum punya artikel/FAQ yang terbit** | **Tulis artikelnya.** Ini pekerjaan utama Anda |
| 🟠 **perlu approval** | Subject katalog ini butuh persetujuan atasan | Sekadar informasi dari katalog tim; tidak ada yang perlu diapa-apakan |
| ⚪ **3× ditanya** | Pertanyaan itu muncul 3 kali | Makin besar, makin mendesak |
| 🔴 **3× jadi tujuan** | Subject itu jadi tujuan 3 pertanyaan **berbeda** | Prioritas menulis paling tinggi |

> **Catatan penting:** tidak ada "status" seperti Open / In Progress / Closed di
> layar ini. Yang terlihat seperti status sebenarnya **lencana keyakinan** dan
> **lencana ketersediaan materi**. Layar ini tidak mengurus siklus hidup tiket
> sama sekali.

---

## 5. Dua garis keyakinan (30 dan 50)

Tiap calon subject diberi nilai keyakinan 0–100. Ada dua garis:

```
  0 ─────────────── 30 ─────────────── 50 ─────────────── 100
       tidak            ditawarkan          diisikan
     ditampilkan      sebagai calon        otomatis
    (TANPA SARAN)   ("perlu dipilih      ("terisi otomatis")
                        manual")
```

**Kenapa dua garis, bukan satu?** Karena dua kesalahan itu tidak sama beratnya:

- Mengisi otomatis dengan tebakan **salah** itu mahal — kolom yang sudah terisi
  cenderung tidak diperiksa lagi, dan tiket mendarat di tim yang keliru.
- Menyembunyikan calon yang **benar** juga mahal — karyawan jadi harus menebak
  sendiri dari ratusan pilihan.

Jadi: berani mengisi hanya di atas 50; berani menawarkan mulai dari 30.

---

## 6. Cara memakainya: tiga skenario nyata

### Kasus A — "Banyak lencana 🔴 belum ada materi"

**Artinya:** pengarahannya sudah benar, tapi EVA tidak punya bahan untuk
menjawab. Ini keadaan paling sehat — pekerjaannya jelas.

**Yang dilakukan:**

1. Lihat kartu **Subject tujuan yang belum punya materi** (bagian ③).
2. Ambil yang paling atas (lencana merah `N× jadi tujuan` paling besar).
3. Klik **Tulis artikel →** di pojok kanan kartu.
4. Tulis artikelnya, pilih subject katalog yang sesuai, lalu **Terbitkan**.
5. Kembali ke layar ini dan muat ulang — subject itu hilang sendiri dari daftar.

> Artikel yang masih **draf tidak dihitung**. Selama belum ditekan Terbitkan,
> subject itu tetap muncul sebagai celah — dan memang seharusnya begitu, karena
> EVA belum bisa memakainya untuk menjawab.

### Kasus B — "Kartu TANPA SARAN tinggi"

**Artinya:** masalahnya **bukan materi**, tapi **kosakata**. Karyawan memakai
istilah yang tidak dikenali katalog — misalnya mereka mengetik "mailbox penuh"
sementara katalog menamainya "Kapasitas Email".

Menulis artikel **tidak akan menolong** di sini. Artikelnya bisa saja sudah ada;
yang gagal adalah pengenalan istilahnya.

**Yang dilakukan:**

1. Lihat di tabel pengarahan (bagian ④) pertanyaan mana yang tidak punya calon.
2. Buka **CONFIGURATION → Search Settings**.
3. Tambahkan sinonim: istilah karyawan → istilah katalog.
4. Kembali ke sini, muat ulang, dan lihat apakah pertanyaannya sudah mendarat.

### Kasus C — "Sarannya masuk akal tapi cabangnya salah"

Misalnya "reset password SAP" diarahkan ke `AKUN APLIKASI › SILO (OTHER APPS) ›
Reset Password`, padahal maksudnya cabang SAP.

**Artinya:** ada dua subject bernama sama di cabang berbeda, dan pertanyaannya
belum memuat kata pembeda.

**Yang dilakukan:** pastikan artikel Anda menyebut nama layanannya secara
eksplisit di judul dan isi ("SOP Reset Password **SAP**"), dan tambahkan sinonim
yang mengikat istilah karyawan ke layanan yang benar.

---

## 7. Kotak "Uji pengarahan"

Kotak di bagian ② untuk mencoba kalimat apa pun **tanpa efek samping**:

- Ketik pertanyaan seperti yang akan diketik karyawan — bahasa sehari-hari,
  boleh salah ketik. Contoh: *"mailbox saya penuh"*, *"laptop lemot"*,
  *"gabisa login sap"*.
- Tekan **Uji** (atau Enter).
- Muncul hingga 5 calon subject beserta keyakinannya dan lencananya.

**Aman dipakai berkali-kali.** Mengetik di sini **tidak** tercatat sebagai
pertanyaan ke EVA, tidak memengaruhi angka di kartu mana pun, dan tidak menyimpan
apa pun ke database.

Gunanya: menguji hasil perbaikan Anda. Setelah menambah sinonim, ketik ulang
pertanyaan yang tadi gagal di sini — kalau sekarang mendarat, perbaikannya
berhasil.

---

## 8. Yang TIDAK dilakukan layar ini

Supaya tidak salah harap:

| Bukan tugas layar ini | Di mana sebenarnya |
|---|---|
| Mengirim tiket | EVA berhenti di **draf**. Penomoran, SLA, dan penugasan milik sistem Helpdesk |
| Menilai kualitas jawaban EVA | Rating & Feedback, Analytics |
| Menambah / mengubah subject katalog | Service Catalog (menu Admin) — EVA hanya membaca, tidak pernah menulis ke sana |
| Menyimpan saran | Tidak ada yang disimpan. Semua dihitung ulang tiap layar dibuka |

---

## 9. Tanya jawab singkat

**Angkanya tidak berubah padahal saya sudah menulis artikel.**
Artikelnya sudah **Terbitkan**? Draf tidak dihitung. Lalu muat ulang halaman.

**Kenapa pertanyaan di tabel cuma sedikit?**
Karena yang tercatat baru pertanyaan yang pernah diketik ke EVA — dan saat ini
EVA baru bisa diakses admin lewat layar EVA Preview. Begitu EVA dipasang di
portal SSO ADHI Karya, daftar ini akan terisi sendiri dengan cepat.

**Ada cara lain melihat masalah apa yang paling sering terjadi?**
Ada, dari tiket nyata. Jalankan di terminal:
```bash
php artisan eva:mine-ticket-subjects
```
Perintah itu membaca tiket yang benar-benar masuk dan mencetak subject mana yang
paling sering muncul tapi belum punya materi. Pelengkap layar ini, bukan
pengganti — sumbernya beda (tiket vs pertanyaan ke EVA).

**Saya salah menerbitkan artikel, apakah bisa ditarik?**
Bisa. Di Article Library tekan tombol yang sama untuk mengembalikannya ke draf.
Subject-nya akan muncul lagi di daftar celah materi.

**Apakah saya bisa merusak sesuatu dari layar ini?**
Tidak. Seluruh layar ini hanya membaca. Satu-satunya tombol yang mengubah
sesuatu adalah tautan **Tulis artikel →**, dan itu membawa Anda ke layar lain.

---

## 10. Ringkasan satu paragraf

Ticket Recommendation memperlihatkan ke mana tiket akan diarahkan setiap kali
EVA gagal menjawab. Bacalah dari kartu **"Subject tujuan yang belum punya
materi"** — itu daftar tugas menulis Anda, terurut dari yang paling sering
dibutuhkan. Kalau kartu **TANPA SARAN** tinggi, masalahnya kosakata, bukan
materi: perbaikannya di Search Settings. Gunakan kotak **Uji pengarahan** untuk
memastikan perbaikan Anda benar-benar bekerja. Tidak ada yang bisa rusak dari
sini, dan tidak ada yang perlu disimpan — semuanya dihitung ulang setiap kali
halaman dibuka.

<?php

/*
| Data awal Knowledge Base EVA.
|
| `subject` di bawah dicocokkan ke service_catalog_subjects.name milik tim —
| EVA tidak pernah membuat subject sendiri (aturan #5). Kalau sebuah nama tidak
| ditemukan, KnowledgeBaseSeeder berhenti dan menyebut namanya, bukan diam-diam
| menyimpan null; tautan subject yang hilang berarti materinya tidak terhitung
| dalam coverage.
|
| `text` adalah isi dokumen yang sesungguhnya. Dari sinilah artikel dan potongan
| (chunks) dilahirkan lewat DocumentIndexer — bukan artikel jadi yang ditempel
| langsung.
*/

return [
    'documents' => [
        [
            'name' => 'SOP Reset Password SAP',
            'extension' => 'PDF',
            'subject' => 'Password Expired',
            'tags' => 'sap, password, reset',
            'text' => <<<'TEXT'
Password SAP kedaluwarsa setiap 90 hari. Sistem menampilkan pesan "Password has expired" pada layar login dan Anda tidak dapat melanjutkan sebelum menggantinya.

Reset mandiri lewat Portal SSO. Buka Portal SSO, pilih menu Akun Saya, lalu klik Ubah Password SAP. Masukkan password lama, lalu password baru dua kali. Password baru minimal 8 karakter, mengandung huruf besar, huruf kecil, dan angka, serta tidak boleh sama dengan lima password terakhir.

Bila password lama sudah tidak diingat. Gunakan tautan Lupa Password di Portal SSO. Tautan verifikasi dikirim ke email korporat Anda dan berlaku 15 menit. Setelah verifikasi, Anda diminta menetapkan password baru.

Akun terkunci setelah gagal login. SAP mengunci akun setelah lima kali percobaan gagal. Reset mandiri tidak berlaku untuk akun terkunci — akun harus dibuka lebih dulu oleh IT Support. Prosedurnya ada di SOP Unlock Akun SAP.

Waktu proses. Perubahan password berlaku langsung. Bila setelah 5 menit Anda masih ditolak, tutup seluruh sesi SAP GUI dan login ulang agar cache kredensial ikut diperbarui.
TEXT,
        ],
        [
            'name' => 'Panduan Instalasi FortiClient VPN',
            'extension' => 'DOCX',
            'subject' => 'Instalasi FortiClient',
            'tags' => 'vpn, forticlient, remote',
            'text' => <<<'TEXT'
FortiClient adalah aplikasi VPN resmi untuk mengakses jaringan korporat dari luar kantor. Instalasinya tidak memerlukan hak administrator pada laptop dinas.

Unduh dan pasang. Ambil installer dari Portal SSO, menu Unduhan, pilih FortiClient VPN sesuai sistem operasi. Jalankan installer dan pilih hanya komponen VPN. Komponen antivirus tidak dipakai di lingkungan ADHI karena sudah ditangani endpoint protection korporat.

Konfigurasi koneksi. Buka FortiClient, pilih Remote Access, lalu New Connection. Isi Connection Name dengan ADHI VPN, Remote Gateway dengan alamat yang tertera di Portal SSO, dan centang Customize Port sesuai petunjuk. Simpan konfigurasi.

Login dan MFA. Masuk dengan akun korporat Anda. Pada login pertama Anda diminta melakukan verifikasi MFA melalui aplikasi authenticator. Kode berlaku 30 detik; bila selalu ditolak, pastikan jam perangkat Anda sinkron otomatis.

VPN sering terputus. Penyebab paling umum adalah jaringan rumah yang tidak stabil atau berpindah antar access point. Sambungkan ulang dari jaringan yang stabil. Bila masih terputus, hapus kredensial tersimpan di FortiClient lalu autentikasi ulang.
TEXT,
        ],
        [
            'name' => 'Troubleshooting Email MAILIA',
            'extension' => 'PDF',
            'subject' => 'Tidak Bisa Terima Email',
            'tags' => 'mailia, outlook, email',
            'text' => <<<'TEXT'
Outlook MAILIA yang tidak menerima email masuk umumnya disebabkan salah satu dari tiga hal: sinkronisasi tertahan, mailbox penuh, atau pesan tertahan filter spam.

Periksa status sinkronisasi. Lihat bilah status di bagian bawah Outlook. Bila tertulis Disconnected atau Working Offline, pilih tab Send/Receive lalu matikan Work Offline. Klik Send/Receive All Folders untuk memaksa sinkronisasi.

Mailbox penuh. Kuota mailbox standar adalah 5 GB. Bila kuota terlampaui, email masuk ditolak di sisi server dan pengirim menerima pesan gagal. Periksa pemakaian lewat File, Info, Mailbox Settings. Kosongkan folder Deleted Items dan arsipkan email lama untuk memulihkan kapasitas.

Pesan tertahan filter spam. Buka Junk Email dan Quarantine di webmail MAILIA. Pesan dari pengirim eksternal yang belum pernah berkorespondensi kadang tertahan otomatis. Tandai Not Junk untuk melepaskannya dan menambahkan pengirim ke daftar aman.

Bila ketiganya sudah diperiksa dan email tetap tidak masuk, kemungkinan masalah ada di sisi routing server dan perlu ditangani tim Email Service.
TEXT,
        ],
        [
            'name' => 'SOP Unlock Akun SAP',
            'extension' => 'PDF',
            'subject' => 'Aktivasi/ Unlock akun',
            'tags' => 'sap, unlock, terkunci',
            'text' => <<<'TEXT'
Akun SAP terkunci otomatis setelah lima kali percobaan login gagal berturut-turut. Penguncian ini adalah kontrol keamanan dan tidak bisa dibatalkan sendiri oleh pengguna.

Cara mengajukan unlock. Ajukan permintaan lewat EVA atau formulir tiket dengan kategori Access Request, subject Aktivasi/Unlock akun. Sertakan user ID SAP Anda. Verifikasi identitas dilakukan lewat data kepegawaian, sehingga permintaan atas nama orang lain akan ditolak.

Waktu proses. Permintaan unlock diproses IT Support dalam 30 menit pada jam kerja, yaitu 07.00 sampai 17.00 WIB. Permintaan di luar jam tersebut diproses pada hari kerja berikutnya.

Setelah akun dibuka. Anda tetap harus mengganti password bila penguncian terjadi karena password kedaluwarsa. Ikuti SOP Reset Password SAP.

Akun yang berulang kali terkunci. Bila akun Anda terkunci lebih dari dua kali dalam seminggu, biasanya ada aplikasi atau perangkat lain yang masih menyimpan password lama dan mencoba login otomatis di latar belakang. Laporkan hal ini agar sesi tersebut ditelusuri.
TEXT,
        ],
        [
            'name' => 'Panduan Printer Jaringan',
            'extension' => 'PDF',
            'subject' => 'Printer offline',
            'tags' => 'printer, perangkat, driver',
            'text' => <<<'TEXT'
Printer jaringan yang berstatus offline di Windows biasanya masih hidup, tetapi komputer kehilangan jalur ke alamatnya.

Langkah pertama. Pastikan printer menyala dan lampu jaringannya hidup. Pastikan komputer Anda terhubung ke jaringan kantor, bukan ke jaringan tamu. Printer korporat tidak dapat dijangkau dari jaringan tamu.

Hapus dan tambahkan kembali. Buka Settings, Bluetooth and devices, Printers and scanners. Pilih printer yang bermasalah lalu Remove. Klik Add device dan tunggu pemindaian. Bila printer tidak muncul, pilih Add manually lalu masukkan alamat IP printer yang tertera pada stiker di badan perangkat.

Driver di Windows 11. Sebagian driver lama tidak tersedia otomatis di Windows 11. Unduh driver dari Portal SSO menu Unduhan, pilih model printer yang sesuai, lalu pasang sebelum menambahkan perangkat.

Antrean cetak tersangkut. Bila status berubah menjadi offline setiap kali mencetak dokumen tertentu, kosongkan antrean lewat klik kanan printer, Open print queue, Cancel all documents, lalu coba cetak satu halaman uji.
TEXT,
        ],
        [
            'name' => 'Prosedur Permintaan Akun SAP Baru',
            'extension' => 'DOCX',
            'subject' => 'Pembuatan akun baru',
            'tags' => 'sap, akun, hc, bpo',
            'text' => <<<'TEXT'
Permintaan akun SAP baru diajukan untuk karyawan yang belum pernah memiliki user ID SAP, misalnya karyawan baru atau karyawan yang berpindah ke unit kerja yang memerlukan akses modul tertentu.

Prasyarat. Data kepegawaian yang bersangkutan sudah aktif di SINTA. Permintaan untuk karyawan yang datanya belum aktif akan ditolak karena user ID dibuat berdasarkan nomor induk karyawan.

Kolom yang wajib diisi. Nama lengkap, nomor induk karyawan, unit kerja, atasan langsung, modul SAP yang dibutuhkan, dan alasan kebutuhan akses. Modul harus disebut spesifik, misalnya MM untuk pengadaan atau FICO untuk keuangan.

Alur persetujuan. Permintaan disetujui berurutan oleh Human Capital untuk memastikan status kepegawaian, lalu oleh BPO modul terkait untuk memastikan kewenangan aksesnya sesuai. Kedua persetujuan wajib; tidak ada jalur percepatan.

Waktu proses. Setelah kedua persetujuan lengkap, akun dibuat dalam 2 hari kerja. Kredensial awal dikirim ke email korporat dan wajib diganti saat login pertama.
TEXT,
        ],
    ],

    'faqs' => [
        [
            'question' => 'Berapa lama proses unlock akun SAP?',
            'answer' => 'Setelah Anda mengirim permintaan unlock lewat EVA, IT Support memprosesnya dalam 30 menit pada jam kerja (07.00–17.00 WIB). Di luar jam tersebut diproses pada hari kerja berikutnya.',
            'subject' => 'Aktivasi/ Unlock akun',
            'tags' => 'sap, unlock, akun',
            'is_eva_visible' => true,
            'tests' => ['berapa lama unlock akun sap', 'proses unlock sap berapa lama'],
        ],
        [
            'question' => 'Bisakah akses email MAILIA dari ponsel pribadi?',
            'answer' => 'Bisa. Instal Outlook Mobile dan masuk dengan akun korporat. MFA wajib saat login pertama. Jika perangkat belum terdaftar, EVA bisa membuatkan draf permintaan akses.',
            'subject' => 'Email / Outlook Bermasalah',
            'tags' => 'mailia, mobile, outlook',
            'is_eva_visible' => true,
            'tests' => ['akses email mailia dari ponsel pribadi'],
        ],
        [
            'question' => 'Apa yang harus dilakukan jika VPN FortiClient sering terputus?',
            'answer' => 'Pindah ke jaringan yang stabil lalu sambungkan ulang. Jika masih terjadi, bersihkan cache kredensial FortiClient dan autentikasi ulang. Bila tetap gagal, EVA akan menyiapkan draf tiket Network untuk Anda kirim.',
            'subject' => 'VPN terputus',
            'tags' => 'vpn, forticlient',
            'is_eva_visible' => true,
            'tests' => ['vpn forticlient sering putus'],
        ],
        [
            'question' => 'Siapa yang menyetujui permintaan instalasi software baru?',
            'answer' => 'Instalasi software disetujui oleh BPO (Business Process Owner) setelah pengecekan lisensi. Waktu proses standar 1–2 hari kerja.',
            'subject' => 'Instalasi / reinstall aplikasi',
            'tags' => 'software, approval, bpo',
            'is_eva_visible' => true,
            'tests' => ['siapa yang approve instalasi software'],
        ],
        [
            'question' => 'Bagaimana menjadwal ulang tender di ELISA?',
            'answer' => 'Buka tender di ELISA, pilih Actions lalu Reschedule, kemudian ajukan persetujuan SCM. Penjadwalan ulang dalam 24 jam sebelum deadline membutuhkan persetujuan manajer.',
            'subject' => 'Reschedule jadwal tender',
            'tags' => 'elisa, tender',
            // Sengaja dimatikan: menunjukkan bahwa toggle ini benar-benar
            // memutus FAQ dari jawaban EVA, bukan sekadar hiasan di tabel.
            'is_eva_visible' => false,
            'tests' => ['reschedule tender elisa'],
        ],
        [
            'question' => 'Sinyal WiFi kantor lemah di lantai tertentu, apa yang bisa saya lakukan?',
            'answer' => 'Coba pindah mendekat ke access point terdekat dan pilih band 5 GHz bila tersedia. Periksa juga driver WiFi laptop Anda sudah versi terbaru. Bila seluruh area terdampak, laporkan agar penempatan access point ditinjau.',
            'subject' => 'Sinyal WiFi lemah',
            'tags' => 'wifi, network',
            'is_eva_visible' => true,
            'tests' => ['wifi lemah di lantai 3'],
        ],
    ],

    /*
    | Pertanyaan yang diputar ulang lewat EvaResponder saat seeding.
    |
    | Log-nya TIDAK ditulis tangan: pertanyaan ini benar-benar dijalankan
    | melalui pencarian yang sama dengan yang dipakai EVA di produksi. Jadi
    | angka Coverage Dashboard, Top Questions, dan Unanswered Questions berasal
    | dari jalur nyata — bukan daftar beku seperti di mockup.
    |
    | `repeat` menirukan pertanyaan yang memang sering ditanyakan.
    */
    'replayed_questions' => [
        ['q' => 'cara reset password SAP', 'repeat' => 9, 'stars' => 5],
        ['q' => 'saya lupa sandi SAP bagaimana', 'repeat' => 5, 'stars' => 4],
        ['q' => 'vpn forticlient tidak bisa connect', 'repeat' => 7, 'stars' => 5],
        ['q' => 'cara pakai vpn untuk wfh', 'repeat' => 3, 'stars' => 4],
        ['q' => 'outlook mailia tidak menerima email masuk', 'repeat' => 6, 'stars' => 4],
        ['q' => 'akun SAP saya terkunci bagaimana membukanya', 'repeat' => 5, 'stars' => 5],
        ['q' => 'printer jaringan saya offline tidak bisa cetak', 'repeat' => 4, 'stars' => 3],
        ['q' => 'sinyal wifi kantor lemah', 'repeat' => 3, 'stars' => 4],
        ['q' => 'cara mengajukan akun SAP baru untuk karyawan baru', 'repeat' => 2, 'stars' => 5],
        ['q' => 'berapa lama proses unlock akun sap', 'repeat' => 4, 'stars' => 5],
        // Sengaja tidak terjawab — inilah bahan Unanswered Questions.
        ['q' => 'bagaimana memperpanjang timeout sesi SAP', 'repeat' => 8, 'stars' => null],
        ['q' => 'bisakah klaim reimbursement internet rumah untuk WFH', 'repeat' => 6, 'stars' => null],
        ['q' => 'cara memindahkan shared mailbox ke pemilik lain', 'repeat' => 5, 'stars' => null],
        ['q' => 'bagaimana reset MFA di ponsel baru', 'repeat' => 4, 'stars' => null],
        ['q' => 'cara meminta monitor kedua', 'repeat' => 2, 'stars' => null],
        // Sengaja ambigu — EVA harus bertanya balik, bukan menebak.
        ['q' => 'tidak bisa login', 'repeat' => 5, 'stars' => null],
        ['q' => 'error terus dari tadi', 'repeat' => 3, 'stars' => null],
    ],

    /*
    | Riwayat coverage bulan-bulan sebelumnya, HANYA untuk garis tren.
    | Titik terakhir grafik tidak diambil dari sini — selalu dihitung ulang
    | oleh CoverageCalculator dari kondisi data sebenarnya.
    */
];

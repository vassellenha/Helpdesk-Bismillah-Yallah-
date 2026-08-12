import { useEffect, useState } from 'react';

/**
 * Sakelar terang/gelap di topbar.
 *
 * Tema disimpan sebagai kelas `.dark` di <html> — bukan di state React ini.
 * Alasannya: yang mewarnai halaman adalah CSS, dan CSS harus tahu temanya
 * sebelum React sempat dimuat. Skrip di partials/theme-boot.blade.php (di-include
 * SEMUA layout) yang memasang kelas itu lebih dulu; komponen ini hanya membalik
 * dan mencatat pilihannya. Kalau urutannya dibalik, halaman akan berkedip putih
 * dulu setiap kali dimuat.
 *
 * Pilihannya dicatat di cookie, bukan localStorage — lihat alasannya di partial
 * tersebut. Yang penting di sini: nama dan format cookie-nya harus tetap sama
 * dengan yang dibaca partial itu, karena keduanya adalah dua sisi dari satu
 * kesepakatan.
 *
 * Tiga keadaan, bukan dua: gelap, terang, dan BELUM MEMILIH. Yang ketiga itu
 * yang mengikuti setelan OS. Begitu tombol ini ditekan sekali, pilihan pengguna
 * menang selamanya di peramban itu — termasuk saat OS-nya berubah.
 */
const COOKIE = 'helpdesk_theme';

// Setahun. Tema bukan sesuatu yang pantas hilang karena seseorang menutup
// peramannya — sekali dipilih, ia berlaku sampai diubah lagi.
const MAX_AGE = 60 * 60 * 24 * 365;

function bacaCookie() {
    const cocok = document.cookie.match(/(?:^|;\s*)helpdesk_theme=(dark|light)/);

    return cocok ? cocok[1] : null;
}

export default function ThemeToggle() {
    const [dark, setDark] = useState(() => document.documentElement.classList.contains('dark'));

    // Mengikuti OS hanya selama pengguna belum pernah memilih sendiri. Tanpa
    // penjaga ini, seseorang yang memilih mode terang akan dipaksa kembali ke
    // gelap begitu laptopnya masuk jadwal malam.
    useEffect(() => {
        const media = window.matchMedia('(prefers-color-scheme: dark)');

        function follow(e) {
            if (bacaCookie()) return;
            document.documentElement.classList.toggle('dark', e.matches);
            setDark(e.matches);
        }

        media.addEventListener('change', follow);

        return () => media.removeEventListener('change', follow);
    }, []);

    function toggle() {
        const next = ! dark;
        document.documentElement.classList.toggle('dark', next);
        // `path=/` menentukan: tanpa itu cookie hanya berlaku di direktori
        // halaman tempat tombol ditekan, sehingga tema kembali terang begitu
        // pindah dari /requester ke /support. SameSite=Lax supaya tetap terbawa
        // saat berpindah halaman biasa.
        document.cookie = `${COOKIE}=${next ? 'dark' : 'light'}; path=/; max-age=${MAX_AGE}; SameSite=Lax`;
        setDark(next);
    }

    return (
        <button
            type="button"
            onClick={toggle}
            title={dark ? 'Ganti ke mode terang' : 'Ganti ke mode gelap'}
            aria-label={dark ? 'Ganti ke mode terang' : 'Ganti ke mode gelap'}
            aria-pressed={dark}
            className="relative flex h-9 w-9 items-center justify-center rounded-full text-gray-500 dark:text-ink-2 hover:bg-gray-100 dark:hover:bg-panel-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-400"
        >
            {/*
              Kedua ikon selalu dirender lalu disilangkan transisinya — menukar
              elemen akan membuat ikonnya melompat masuk tanpa animasi.

              Siluet PADAT, bukan garis seperti ikon lain di app ini. Pada 18px,
              palu bergaris hanya menyisakan kotak berongga, dan sabit bergaris
              kehilangan ujung runcingnya — justru dua hal yang membuat keduanya
              dikenali. Bentuknya juga disederhanakan sampai batas masih terbaca:
              ukiran Mjolnir dan tulisan di bilah sabit hilang seluruhnya, karena
              di ukuran ini keduanya jadi bercak abu-abu.
            */}

            {/* SIANG — matahari. Cakram pejal + delapan sinar. */}
            <svg
                width="18" height="18" viewBox="0 0 24 24"
                className={`absolute transition-all duration-200 ${dark ? 'scale-0 -rotate-90 opacity-0' : 'scale-100 rotate-0 opacity-100'}`}
            >
                <circle cx="12" cy="12" r="5" fill="currentColor" />
                {/* Sinarnya bergaris, bukan pejal: pada 18px, sinar pejal
                    setebal apa pun akan menyatu dengan cakramnya dan hasilnya
                    cuma lingkaran bergerigi. */}
                <path
                    fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"
                    d="M12 1.6v2.6 M12 19.8v2.6 M22.4 12h-2.6 M4.2 12H1.6 M19.35 4.65l-1.85 1.85 M6.5 17.5l-1.85 1.85 M19.35 19.35l-1.85-1.85 M6.5 6.5 4.65 4.65"
                />
            </svg>

            {/* MALAM — sabit Moon Knight. Simetris, kedua tanduk meruncing. */}
            <svg
                width="18" height="18" viewBox="0 0 24 24" fill="currentColor"
                className={`absolute transition-all duration-200 ${dark ? 'scale-100 rotate-0 opacity-100' : 'scale-0 rotate-90 opacity-0'}`}
            >
                {/*
                  Dua busur lingkaran: luar r9.5 berpusat (12,10.5), dalam r8.5
                  berpusat (12,6) — yang dalam letaknya lebih TINGGI, jadi ia
                  memotong bagian atas dan menyisakan sabit bertanduk ke atas.
                  Kalau pusatnya dibalik (dalam lebih rendah), sabitnya
                  terbalik jadi "∩" dan terbaca seperti mangkuk, bukan bulan.
                  Titik temunya (3.5, 6.25) dan (20.5, 6.25) — ujung runcingnya.
                */}
                <path d="M3.5 6.25A9.5 9.5 0 1 0 20.5 6.25A8.5 8.5 0 0 1 3.5 6.25Z" />
            </svg>
        </button>
    );
}

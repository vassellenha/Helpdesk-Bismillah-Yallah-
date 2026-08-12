import { useEffect, useState } from 'react';

/**
 * Sakelar terang/gelap di topbar.
 *
 * Tema disimpan sebagai kelas `.dark` di <html> — bukan di state React ini.
 * Alasannya: yang mewarnai halaman adalah CSS, dan CSS harus tahu temanya
 * sebelum React sempat dimuat. Skrip kecil di <head> (lihat layouts/app.blade.php)
 * yang memasang kelas itu lebih dulu; komponen ini hanya membalik dan mencatat
 * pilihannya. Kalau urutannya dibalik, halaman akan berkedip putih dulu setiap
 * kali dimuat.
 *
 * Tiga keadaan, bukan dua: gelap, terang, dan BELUM MEMILIH. Yang ketiga itu
 * yang mengikuti setelan OS. Begitu tombol ini ditekan sekali, pilihan pengguna
 * menang selamanya di peramban itu — termasuk saat OS-nya berubah.
 */
const STORAGE_KEY = 'helpdesk-theme';

export default function ThemeToggle() {
    const [dark, setDark] = useState(() => document.documentElement.classList.contains('dark'));

    // Mengikuti OS hanya selama pengguna belum pernah memilih sendiri. Tanpa
    // penjaga ini, seseorang yang memilih mode terang akan dipaksa kembali ke
    // gelap begitu laptopnya masuk jadwal malam.
    useEffect(() => {
        const media = window.matchMedia('(prefers-color-scheme: dark)');

        function follow(e) {
            if (localStorage.getItem(STORAGE_KEY)) return;
            document.documentElement.classList.toggle('dark', e.matches);
            setDark(e.matches);
        }

        media.addEventListener('change', follow);

        return () => media.removeEventListener('change', follow);
    }, []);

    function toggle() {
        const next = ! dark;
        document.documentElement.classList.toggle('dark', next);
        localStorage.setItem(STORAGE_KEY, next ? 'dark' : 'light');
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

            {/* SIANG — Mjolnir. Kepala balok + gagang pendek + pangkal. */}
            <svg
                width="18" height="18" viewBox="0 0 24 24" fill="currentColor"
                className={`absolute transition-all duration-200 ${dark ? 'scale-0 -rotate-90 opacity-0' : 'scale-100 rotate-0 opacity-100'}`}
            >
                <rect x="2.5" y="4" width="19" height="8.5" rx="1.6" />
                <rect x="10.6" y="12.5" width="2.8" height="6.2" />
                <rect x="8.6" y="18.4" width="6.8" height="2.6" rx="1.1" />
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

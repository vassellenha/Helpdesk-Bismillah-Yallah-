import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.jsx'],
            refresh: true,
            /*
             | TIDAK ada `fonts:` di sini, dan itu disengaja.
             |
             | Opsi itu membuat laravel-vite-plugin MENGUNDUH berkas font dari
             | internet setiap kali build berjalan tanpa cache — dan `npm ci` di
             | skrip deploy menghapus cache itu setiap kali. Di server produksi
             | yang DNS-nya kadang menjawab dengan alamat IPv6 sementara
             | jaringannya tidak punya rute ke sana, build gagal total:
             |
             |     [plugin laravel:fonts] TypeError: fetch failed
             |     Error: connect ENETUNREACH 2602:ffe4:...:443
             |
             | Deploy jadi bergantung pada jaringan luar untuk sesuatu yang
             | isinya tidak pernah berubah. Instrument Sans kini disimpan
             | sendiri di public/fonts/instrument-sans/ dengan @font-face di
             | app.css — pola yang sudah dipakai Plus Jakarta Sans dan SF Pro
             | Rounded di proyek ini. Build tidak lagi menyentuh internet.
            */
        }),
        tailwindcss(),
        react(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});

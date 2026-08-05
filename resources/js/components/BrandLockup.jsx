/**
 * Lockup brand Helpdesk: ikon tiket dalam kotak + wordmark.
 *
 * Kembaran React dari resources/views/partials/brand-lockup.blade.php — header
 * Team Lead dirender React, bukan Blade, jadi partial itu tidak bisa dipakai
 * dari sana. Kalau salah satunya diubah, ubah keduanya.
 *
 * Kotaknya yang biru, tiketnya siluet putih di dalam.
 *
 * Konsekuensi yang menentukan bentuk glyph-nya: di dalam kotak, tiket hanya
 * kebagian ~68% lebar kanvas — di navbar 36px itu cuma ~24px. Karena itu dua
 * garis perforasi dan kotak judul dari versi sebelumnya dibuang; ruasnya jatuh
 * di bawah 1px dan lenyap ditelan anti-aliasing. Yang disisakan hanya takik di
 * kedua sisi dan dua baris isi yang ditebalkan.
 *
 * Takik dan baris isi dibuat sebagai LUBANG lewat mask, bukan bentuk biru yang
 * ditimpa, supaya gradien dan pita kilau kotaknya benar-benar menembus.
 *
 * Definisi kelas .brand-lockup* ada di resources/css/app.css.
 */
export default function BrandLockup() {
    return (
        <span className="brand-lockup">
            <svg className="brand-lockup__icon" viewBox="0 0 100 100" aria-hidden="true" focusable="false">
                <defs>
                    {/*
                     * Skala biru yang sama dengan tombol primer aplikasi
                     * (Tailwind blue-400 → blue-500 → blue-700), supaya logo dan
                     * CTA terbaca dari satu keluarga warna.
                     */}
                    <linearGradient id="hd-fill" x1="0" y1="0" x2="1" y2="1">
                        <stop offset="0" stopColor="#60a5fa" />
                        <stop offset="0.55" stopColor="#3b82f6" />
                        <stop offset="1" stopColor="#1d4ed8" />
                    </linearGradient>

                    {/* rx 26 dari sisi 96 (~27%) — proporsi squircle ikon aplikasi. */}
                    <clipPath id="hd-box">
                        <rect x="2" y="2" width="96" height="96" rx="26" />
                    </clipPath>

                    <mask id="hd-ticket">
                        <rect width="100" height="100" fill="#000" />
                        <rect x="16" y="31" width="68" height="38" rx="9" fill="#fff" />
                        {/* Takik kiri-kanan */}
                        <circle cx="16" cy="50" r="6.5" fill="#000" />
                        <circle cx="84" cy="50" r="6.5" fill="#000" />
                        {/*
                         * Dua baris isi, sengaja tebal supaya bertahan di 36px.
                         * Posisinya dihitung agar blok dua baris berpusat tepat
                         * di y=50 — rata dari atas membuat sisa ruang menumpuk
                         * di bawah dan tiketnya terlihat berat sebelah.
                         */}
                        <rect x="27" y="42" width="30" height="5.5" rx="2.75" fill="#000" />
                        <rect x="27" y="52.5" width="20" height="5.5" rx="2.75" fill="#000" />
                    </mask>
                </defs>

                <g clipPath="url(#hd-box)">
                    <rect width="100" height="100" fill="url(#hd-fill)" />
                    <path d="M-10 100 L36 -4 L54 -4 L8 100 Z" fill="#fff" opacity="0.13" />
                </g>

                <rect width="100" height="100" fill="#fff" mask="url(#hd-ticket)" />
            </svg>
            <span className="brand-lockup__word">Helpdesk</span>
        </span>
    );
}

import { useId } from 'react';

/**
 * Mark EVA — orb asisten di dalam squircle biru.
 *
 * Sengaja memakai resep yang sama persis dengan logo Helpdesk
 * (resources/views/partials/brand-lockup.blade.php): squircle rx 26 dari sisi
 * 96, gradien blue-400 → blue-500 → blue-700, dan pita kilau diagonal. Yang
 * berbeda hanya glyph-nya. EVA adalah bagian dari Helpdesk, bukan produk lain
 * — kalau squircle-nya beda radius atau gradiennya beda arah, keduanya langsung
 * terbaca sebagai dua brand yang kebetulan sama-sama biru.
 *
 * Glyph-nya cincin konsentris dengan inti padat. Dipilih karena bentuk
 * konsentris tidak punya detail yang bisa hilang duluan saat diperkecil: di
 * 16px ia masih cincin dengan titik, bukan gumpalan.
 *
 * ID gradien dan mask dibuat lewat useId, bukan ditulis tetap. Dua mark bisa
 * tampil bersamaan di satu halaman (avatar header dan tombol peluncur), dan id
 * kembar membuat yang kedua memungut definisi milik yang pertama.
 */
export default function EvaMark({ size = 32, className = '' }) {
    const uid = useId().replace(/:/g, '');

    return (
        <svg
            width={size}
            height={size}
            viewBox="0 0 100 100"
            className={className}
            aria-hidden="true"
            focusable="false"
        >
            <defs>
                <linearGradient id={`eva-fill-${uid}`} x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0" stopColor="#60a5fa" />
                    <stop offset="0.55" stopColor="#3b82f6" />
                    <stop offset="1" stopColor="#1d4ed8" />
                </linearGradient>
                <clipPath id={`eva-box-${uid}`}>
                    <rect x="2" y="2" width="96" height="96" rx="26" />
                </clipPath>
                <mask id={`eva-orb-${uid}`}>
                    <rect width="100" height="100" fill="#000" />
                    <circle cx="50" cy="50" r="30" fill="#fff" />
                    <circle cx="50" cy="50" r="20" fill="#000" />
                    <circle cx="50" cy="50" r="9" fill="#fff" />
                </mask>
            </defs>

            <g clipPath={`url(#eva-box-${uid})`}>
                <rect width="100" height="100" fill={`url(#eva-fill-${uid})`} />
                <path d="M-10 100 L36 -4 L54 -4 L8 100 Z" fill="#fff" opacity="0.13" />
            </g>

            <rect width="100" height="100" fill="#fff" mask={`url(#eva-orb-${uid})`} />
        </svg>
    );
}

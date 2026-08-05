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
 * Glyph-nya kepala membulat dengan antena dan sepasang mata.
 *
 * Sempat dicoba cincin konsentris (orb asisten suara) dan gagal: bentuk itu
 * memang tenang, tapi tanpa isyarat kehadiran ia terbaca sebagai target atau
 * lensa kamera — bukan sebagai sesuatu yang bisa diajak bicara. Sepasang mata
 * adalah isyarat termurah yang membalik pembacaan itu sepenuhnya.
 *
 * Matanya batang membulat, bukan lingkaran, dan itu yang menjaganya tetap
 * bertahan saat diperkecil: batang setinggi 12 unit masih terbaca sebagai mata
 * di 16px, sedangkan lingkaran kecil melebur jadi dua titik samar.
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
                <mask id={`eva-head-${uid}`}>
                    <rect width="100" height="100" fill="#000" />
                    {/* Kepala */}
                    <rect x="22" y="32" width="56" height="46" rx="18" fill="#fff" />
                    {/* Antena: batang lalu bulatan, digambar terpisah supaya
                        sambungannya tetap tegas di ukuran kecil. */}
                    <rect x="47" y="20" width="6" height="12" rx="3" fill="#fff" />
                    <circle cx="50" cy="17" r="6" fill="#fff" />
                    {/* Mata dilubangkan, bukan digambar biru di atas kepala —
                        dengan begitu gradien dan pita kilau kotaknya yang
                        terlihat menembus, bukan warna solid yang menirunya. */}
                    <rect x="37" y="49" width="8" height="12" rx="4" fill="#000" />
                    <rect x="55" y="49" width="8" height="12" rx="4" fill="#000" />
                </mask>
            </defs>

            <g clipPath={`url(#eva-box-${uid})`}>
                <rect width="100" height="100" fill={`url(#eva-fill-${uid})`} />
                <path d="M-10 100 L36 -4 L54 -4 L8 100 Z" fill="#fff" opacity="0.13" />
            </g>

            <rect width="100" height="100" fill="#fff" mask={`url(#eva-head-${uid})`} />
        </svg>
    );
}

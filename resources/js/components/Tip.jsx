/**
 * Popup penjelasan kecil saat kursor menyentuh sesuatu.
 *
 * Muncul saat hover DAN saat fokus keyboard, karena baris yang dijelaskan sering
 * bisa dijangkau dengan Tab.
 *
 * Pemakaian: bungkus pemicunya dengan sebuah elemen ber-class "group relative",
 * lalu letakkan <Tip> sebagai anak terakhirnya.
 */
export default function Tip({ children, align = 'center', width = 'w-60' }) {
    const position = {
        center: 'left-1/2 -translate-x-1/2',
        left: 'left-0',
        right: 'right-0',
    }[align];

    return (
        <span
            role="tooltip"
            className={`pointer-events-none absolute bottom-full z-30 mb-2 ${width} ${position} rounded-2xl border border-white/15 bg-gray-900/70 px-3 py-2.5 text-left text-[11.5px] font-normal leading-relaxed text-white opacity-0 shadow-xl shadow-black/20 backdrop-blur-xl backdrop-saturate-150 transition-opacity duration-150 group-hover:opacity-100 group-focus-within:opacity-100`}
        >
            {children}
        </span>
    );
}

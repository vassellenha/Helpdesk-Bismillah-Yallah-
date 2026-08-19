import useLockBodyScroll from '../../lib/useLockBodyScroll';
import { t as trans } from '../../lib/i18n';

/**
 * Defaultnya `dense`, varian kaca yang nyaris solid (lihat `.liquid-glass-dense`
 * di app.css) — dan sekarang itu yang dipakai SEMUA popup, bukan hanya form
 * panjang.
 *
 * Varian `light` (`.liquid-glass`) sengaja tidak dipakai lagi. Di mode gelap
 * opasitasnya hanya 10%, sehingga teks halaman di baliknya ikut terbaca
 * menembus panel — terlihat seperti popup yang gagal dirender, bukan seperti
 * kaca. Dipertahankan di sini supaya kelasnya tidak hilang dari sistem
 * desain, tapi jangan dipakai untuk popup tanpa menaikkan dulu opasitas
 * gelapnya di app.css.
 */
export default function Modal({ children, onClose, maxWidth = 'max-w-lg', variant = 'dense' }) {
    useLockBodyScroll();
    const glassClass = variant === 'light' ? 'liquid-glass' : 'liquid-glass-dense';

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 p-4 backdrop-blur-sm" onClick={onClose}>
            <div
                className={`${glassClass} flex max-h-[90vh] w-full ${maxWidth} flex-col overflow-hidden rounded-2xl shadow-xl`}
                onClick={(e) => e.stopPropagation()}
            >
                {children}
            </div>
        </div>
    );
}

export function ModalHeader({ title, subtitle, onClose }) {
    return (
        <div className="flex items-start justify-between border-b border-gray-100 dark:border-edge px-6 py-4">
            <div>
                <h2 className="text-lg font-bold text-gray-900 dark:text-ink-1">{title}</h2>
                {subtitle && <p className="mt-0.5 text-sm text-gray-500 dark:text-ink-2">{subtitle}</p>}
            </div>
            <button onClick={onClose} className="rounded-full p-1.5 text-gray-400 dark:text-ink-3 hover:bg-gray-100 dark:hover:bg-panel-hover hover:text-gray-600" aria-label={trans('admin.common.close')}>
                ✕
            </button>
        </div>
    );
}

export function ModalFooter({ children }) {
    // Tanpa latar solid sendiri — sebelumnya `bg-gray-50 dark:bg-panel-3`
    // memutus kesan kaca panel di atasnya dengan balok rata di bawah. Cukup
    // garis pemisah; latar kaca panel induk tetap terlihat menerus.
    return <div className="flex justify-end gap-2 border-t border-gray-100 dark:border-edge px-6 py-4">{children}</div>;
}

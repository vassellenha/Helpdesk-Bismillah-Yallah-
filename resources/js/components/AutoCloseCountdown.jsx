import { useEffect, useState } from 'react';
import { t as trans } from '../lib/i18n';

/**
 * Hitung mundur menuju penutupan otomatis tiket yang sudah Resolved.
 *
 * Sisa waktunya dihitung ULANG di browser dari tenggat ISO yang dikirim server,
 * bukan memakai angka menit yang ikut di payload. Halaman tiket sering
 * dibiarkan terbuka; angka yang dirender sekali di server akan menunjukkan sisa
 * waktu yang sama berjam-jam kemudian — hitungan yang diam justru lebih
 * menyesatkan daripada tidak ada hitungan.
 *
 * Payload `minutesRemaining` tetap dipakai di sisi server untuk pengujian dan
 * sebagai nilai awal sebelum tik pertama, supaya tidak ada kedipan.
 */

const SECOND = 1000;

/** Sisa waktu dalam detik, boleh negatif bila tenggatnya sudah lewat. */
const secondsLeft = (iso) => Math.round((new Date(iso).getTime() - Date.now()) / 1000);

/**
 * "2 hari 5 jam" di atas satu hari, "5j 32m" di bawahnya, dan "4m 12d" pada
 * jam terakhir — makin dekat tenggat, makin halus satuannya. Menampilkan detik
 * sepanjang tiga hari hanya membuat angkanya gaduh tanpa menambah kejelasan.
 */
function formatRemaining(total) {
    const days = Math.floor(total / 86400);
    const hours = Math.floor((total % 86400) / 3600);
    const minutes = Math.floor((total % 3600) / 60);
    const seconds = total % 60;

    if (days > 0) return `${days}${trans('requester.auto_close.unit_day')} ${hours}${trans('requester.auto_close.unit_hour')}`;
    if (hours > 0) return `${hours}${trans('requester.auto_close.unit_hour')} ${minutes}${trans('requester.auto_close.unit_minute')}`;
    return `${minutes}${trans('requester.auto_close.unit_minute')} ${seconds}${trans('requester.auto_close.unit_second')}`;
}

/**
 * @param {object}  autoClose  Payload Ticket::autoClosePayload(), atau null.
 * @param {boolean} compact    Bentuk ringkas untuk baris tabel.
 */
export default function AutoCloseCountdown({ autoClose, compact = false }) {
    const [left, setLeft] = useState(() => (autoClose ? secondsLeft(autoClose.at) : 0));

    useEffect(() => {
        if (!autoClose) return undefined;

        setLeft(secondsLeft(autoClose.at));
        const id = setInterval(() => setLeft(secondsLeft(autoClose.at)), SECOND);

        return () => clearInterval(id);
    }, [autoClose?.at]);

    if (!autoClose) return null;

    // Penyapu berjalan tiap jam, jadi ada jeda antara hitungan menyentuh nol
    // dan status benar-benar berubah. Menampilkan "0 menit" selama jeda itu
    // terbaca seperti hitungan yang macet; katakan saja apa yang sedang terjadi.
    const expired = left <= 0;
    const urgent = !expired && left <= 86400;

    const tone = expired
        ? 'bg-gray-100 text-gray-600 dark:bg-panel-3 dark:text-ink-2'
        : urgent
            ? 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300'
            : 'bg-blue-50 text-blue-700 dark:bg-accent-soft dark:text-accent-text';

    const clock = (
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="shrink-0">
            <circle cx="12" cy="12" r="9" />
            <path d="M12 7v5l3 2" />
        </svg>
    );

    if (compact) {
        return (
            <span
                title={expired ? undefined : trans('requester.auto_close.tooltip', { at: autoClose.atLabel })}
                className={`inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-[11px] font-semibold ${tone}`}
            >
                {clock}
                {expired ? trans('requester.auto_close.closing') : formatRemaining(left)}
            </span>
        );
    }

    return (
        <div className={`flex flex-wrap items-center gap-2 rounded-xl px-3.5 py-2.5 text-[13px] font-semibold ${tone}`}>
            {clock}
            {expired ? (
                <span>{trans('requester.auto_close.closing_long')}</span>
            ) : (
                <>
                    <span>{trans('requester.auto_close.label', { time: formatRemaining(left) })}</span>
                    <span className="text-[11px] font-medium opacity-80">
                        {trans('requester.auto_close.tooltip', { at: autoClose.atLabel })}
                    </span>
                </>
            )}
        </div>
    );
}

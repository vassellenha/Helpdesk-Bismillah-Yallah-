import { t as trans } from '../lib/i18n';

/**
 * Tab "Minggu / Bulan / Tahun" di atas blok ringkasan dashboard.
 *
 * Markup-nya diangkat dari SupportDashboard supaya keempat dashboard yang
 * memakainya benar-benar identik. Sebelumnya tab ini hanya ada di Support dan
 * Support BPO sebagai JSX yang disalin; menyalinnya sekali lagi ke Requester
 * dan Approver berarti empat salinan yang harus diubah berbarengan setiap kali
 * gayanya disentuh — dan yang terlewat baru ketahuan saat ada yang
 * membandingkan dua layar berdampingan.
 *
 * Kunci periode ('week' | 'month' | 'year') sengaja dipakai apa adanya, bukan
 * labelnya: label ikut bahasa yang sedang aktif, kunci tidak.
 *
 * @param {string}   value      Kunci periode yang sedang aktif.
 * @param {Function} onChange   Dipanggil dengan kunci periode yang dipilih.
 * @param {string}   labelPath  Awalan kunci terjemahan, mis. 'requester.dashboard.periods'.
 */
const PERIOD_KEYS = ['week', 'month', 'year'];

export default function PeriodTabs({ value, onChange, labelPath }) {
    return (
        <div className="flex gap-1.5 rounded-xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 p-1.5 shadow-sm">
            {PERIOD_KEYS.map((p) => (
                <button
                    key={p}
                    type="button"
                    onClick={() => onChange(p)}
                    aria-pressed={value === p}
                    className={`rounded-lg px-3.5 py-2 text-[13px] font-semibold ${value === p
                        ? 'bg-blue-600 dark:bg-blue-500 text-white'
                        : 'text-gray-600 dark:text-ink-2 hover:bg-gray-50 dark:hover:bg-panel-hover dark:even:bg-white/[0.03]'}`}
                >
                    {trans(`${labelPath}.${p}`)}
                </button>
            ))}
        </div>
    );
}

export { PERIOD_KEYS };

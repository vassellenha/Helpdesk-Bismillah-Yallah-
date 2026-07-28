export default function AvgResolutionBar({ data = [] }) {
    const max = Math.max(0, ...data.map((d) => d.hours));
    const worst = data.reduce((a, b) => (b.hours > a.hours ? b : a), data[0]);

    return (
        <div>
            <ul className="space-y-3">
                {data.map((d) => (
                    <li key={d.label} className="flex items-center gap-3 text-sm">
                        <span className="w-40 shrink-0 text-gray-600 dark:text-ink-2">{d.label}</span>
                        <div className="h-3 flex-1 overflow-hidden rounded-full bg-gray-100 dark:bg-panel-3">
                            <div
                                className={`h-full rounded-full ${max > 0 && d.label === worst.label ? 'bg-amber-500' : 'bg-blue-600 dark:bg-blue-500'}`}
                                style={{ width: max > 0 ? `${(d.hours / max) * 100}%` : '0%' }}
                            />
                        </div>
                        <span className="w-16 shrink-0 text-right font-semibold text-gray-900 dark:text-ink-1">
                            {d.hours.toLocaleString('id-ID', { minimumFractionDigits: 1 })} Jam
                        </span>
                    </li>
                ))}
            </ul>
            <p className="mt-4 rounded-lg bg-amber-50 dark:bg-warn-soft p-3 text-xs text-amber-800">
                {max > 0 ? (
                    <><strong>{worst.label}</strong> memiliki rata-rata waktu penyelesaian tertinggi dan perlu ditinjau pada proses approval serta ketersediaan stok.</>
                ) : (
                    'Belum ada tiket yang selesai (Resolved/Closed), sehingga rata-rata waktu penyelesaian belum dapat dihitung.'
                )}
            </p>
        </div>
    );
}

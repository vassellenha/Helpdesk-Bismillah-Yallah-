import DonutChart from './DonutChart';

export default function TicketCategoryDonut({ data = [], total = 0 }) {
    return (
        <div className="flex flex-col items-center gap-6 sm:flex-row">
            <DonutChart
                data={data.map((d) => ({ ...d, key: d.label }))}
                size={188}
                thickness={25}
                emptyLabel="Belum ada tiket"
                // Nilai dari server sudah berupa persentase (SupportController::categoryDonut),
                // jadi jangan dihitung ulang terhadap jumlah slice.
                formatValue={(slice) => `${slice.value}% dari total tiket`}
                center={
                    <>
                        <span className="text-2xl font-bold text-gray-900 dark:text-ink-1">{total.toLocaleString('id-ID')}</span>
                        <span className="text-xs text-gray-400 dark:text-ink-3">TOTAL TIKET</span>
                    </>
                }
            />
            <ul className="w-full space-y-2">
                {data.map((d) => (
                    <li key={d.label} className="flex items-center justify-between text-sm">
                        <span className="flex items-center gap-2 text-gray-700 dark:text-ink-2">
                            <span className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: d.color }} />
                            {d.label}
                        </span>
                        <span className="font-semibold text-gray-900 dark:text-ink-1">{d.value}%</span>
                    </li>
                ))}
            </ul>
        </div>
    );
}

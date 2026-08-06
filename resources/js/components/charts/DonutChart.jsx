import { useId } from 'react';
import { Cell, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';

/**
 * Donat bersama untuk seluruh dashboard.
 *
 * Sebelumnya ada empat donat terpisah dengan blok Recharts yang nyaris identik
 * dan sama-sama tanpa tooltip: warna rata, tanpa kedalaman, dan tidak memberi
 * reaksi apa pun saat disentuh kursor. Menyatukannya di sini berarti perbaikan
 * tampilan cukup ditulis sekali dan tidak ada donat yang tertinggal.
 *
 * Yang membuatnya tidak lagi terlihat rata:
 * - gradien per segmen (warna penuh -> lebih redup ke bawah), memberi kesan
 *   cahaya dari atas tanpa perlu menghitung warna terang/gelap sendiri — penting
 *   karena palet grafik datang dari CSS custom property yang nilainya tidak bisa
 *   dibaca JavaScript;
 * - sudut segmen dibulatkan dan diberi jarak, jadi tiap potongan terbaca sebagai
 *   objek terpisah, bukan satu cakram yang dipotong-potong;
 * - bayangan halus di bawah cincin;
 * - segmen yang disentuh kursor membesar dan sisanya meredup (lihat aturan
 *   .donut-chart di app.css).
 *
 * Prop Recharts-nya sengaja dijaga sama dengan grafik lain yang sudah jalan di
 * aplikasi ini. API "activeShape"/"inactiveShape" sempat dicoba untuk efek
 * hover, tapi statusnya sudah deprecated di Recharts 3 dan tidak terpakai di
 * mana pun di repo ini; efek yang sama bisa didapat dari CSS biasa yang menempel
 * pada kelas .recharts-pie-sector, jadi tidak ada alasan memakainya.
 */
export default function DonutChart({
    data,
    size = 128,
    thickness = 18,
    center = null,
    emptyLabel = '—',
    formatValue = null,
}) {
    // useId() menyisipkan tanda baca yang tidak sah di dalam url(#...) SVG, dan
    // formatnya sudah berubah beberapa kali antar versi React (":r0:", "«r0»",
    // "_R_0_"). Buang semua yang bukan huruf/angka, bukan cuma titik dua.
    const uid = useId().replace(/[^a-zA-Z0-9_-]/g, '');

    const total = data.reduce((sum, d) => sum + (d.value ?? 0), 0);
    const isEmpty = total <= 0;
    const slices = isEmpty
        ? [{ key: '__empty', label: emptyLabel, color: 'var(--chart-empty)', value: 1 }]
        : data.filter((d) => (d.value ?? 0) > 0);

    // Menyisakan ruang untuk pembesaran saat hover dan bayangannya — tanpa ini
    // segmen yang membesar terpotong tepi SVG.
    const outerRadius = size / 2 - 8;
    const innerRadius = Math.max(outerRadius - thickness, 0);

    const describe = (slice) => {
        if (formatValue) return formatValue(slice, Math.round((slice.value / total) * 100));

        return `${slice.value} · ${Math.round((slice.value / total) * 100)}%`;
    };

    return (
        <div className="relative shrink-0" style={{ width: size, height: size }}>
            {/* Bayangannya dipasang lewat CSS pada .recharts-surface saja (lihat
                app.css). Sebelumnya filter drop-shadow ada di div ini, dan karena
                tooltip Recharts juga tinggal di dalamnya, tooltipnya ikut kena
                bayangan tebal dua lapis. */}
            <div className="donut-chart h-full w-full">
                <ResponsiveContainer width="100%" height="100%">
                    <PieChart>
                        <defs>
                            {slices.map((d, i) => (
                                <linearGradient key={d.key ?? d.label} id={`${uid}-${i}`} x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stopColor={d.color} stopOpacity={1} />
                                    <stop offset="100%" stopColor={d.color} stopOpacity={0.62} />
                                </linearGradient>
                            ))}
                        </defs>

                        {!isEmpty && (
                            <Tooltip
                                cursor={false}
                                /*
                                 | Kotak donatnya cuma selebar `size`. Tanpa ini
                                 | Recharts menjaga tooltip tetap di dalam kotak,
                                 | jadi ia dibalik ke arah dalam dan menimpa angka
                                 | di tengah donat — persis titik yang paling
                                 | ingin dibaca orang. Dibiarkan keluar kotak,
                                 | plus jarak dari kursor, tooltipnya mengambang
                                 | di luar cincin.
                                 */
                                allowEscapeViewBox={{ x: true, y: true }}
                                offset={16}
                                wrapperStyle={{ zIndex: 40, outline: 'none' }}
                                content={({ payload }) => {
                                    const slice = payload?.[0]?.payload;
                                    if (!slice) return null;

                                    return (
                                        <div
                                            className="rounded-lg border px-2.5 py-1.5 text-[12px] shadow-md"
                                            style={{
                                                backgroundColor: 'var(--chart-tooltip-bg)',
                                                borderColor: 'var(--chart-tooltip-border)',
                                                color: 'var(--chart-tooltip-text)',
                                            }}
                                        >
                                            <span className="flex items-center gap-1.5 font-semibold">
                                                <span className="h-2 w-2 shrink-0 rounded-full" style={{ backgroundColor: slice.color }} />
                                                {slice.label}
                                            </span>
                                            <span className="mt-0.5 block opacity-70">{describe(slice)}</span>
                                        </div>
                                    );
                                }}
                            />
                        )}

                        <Pie
                            data={slices}
                            dataKey="value"
                            nameKey="label"
                            innerRadius={innerRadius}
                            outerRadius={outerRadius}
                            paddingAngle={slices.length > 1 ? 3 : 0}
                            cornerRadius={slices.length > 1 ? 5 : 0}
                            strokeWidth={0}
                        >
                            {slices.map((d, i) => (
                                <Cell key={d.key ?? d.label} fill={`url(#${uid}-${i})`} />
                            ))}
                        </Pie>
                    </PieChart>
                </ResponsiveContainer>
            </div>

            {center && (
                <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">{center}</div>
            )}
        </div>
    );
}

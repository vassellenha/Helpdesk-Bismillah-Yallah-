import { useMemo, useState } from 'react';
import { StatusBadge } from '../StatusBadge';

const DECISION_STYLES = {
    approved: 'text-emerald-600',
    revision_requested: 'text-amber-600',
    rejected: 'text-red-600',
};

const CARDS = [
    { key: 'Total', label: 'Total Tiket', icon: 'M4 10h16 M6 10V7a4 4 0 0 1 8 0v3 M4 10h16v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-8Z', bg: 'bg-gray-100', color: 'text-gray-500' },
    { key: 'Open', label: 'Open', icon: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z M12 7v5l3 3', bg: 'bg-gray-100', color: 'text-gray-600' },
    { key: 'In Progress', label: 'In Progress', icon: 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Z M12 7v5l3 3', bg: 'bg-blue-50', color: 'text-blue-600' },
    { key: 'Resolved', label: 'Resolved', icon: 'M9 12l2 2 4-5 M21 12a9 9 0 1 1-9-9', bg: 'bg-emerald-50', color: 'text-emerald-600' },
    { key: 'Closed', label: 'Closed', icon: 'M4 10h16 M6 10V7a4 4 0 0 1 8 0v3 M4 10h16v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-8Z', bg: 'bg-gray-100', color: 'text-gray-500' },
];

const STATUS_PILLS = ['Semua', 'Open', 'In Progress', 'Resolved', 'Closed', 'Rejected'];

const STATUS_BUCKET = {
    Open: (s) => s === 'Open',
    'In Progress': (s) => ['Assigned', 'In Progress', 'Waiting for Response'].includes(s),
    Resolved: (s) => s === 'Resolved',
    Closed: (s) => ['Closed', 'Completed'].includes(s),
    Rejected: (s) => s === 'Rejected',
};

const RELATIVE_PERIODS = { 'Last 30 days': 30, 'Last 3 months': 90, 'Last 6 months': 183, 'This year': 366 };

const COLUMNS = [
    { key: 'id', label: 'Tiket' },
    { key: 'service', label: 'Layanan' },
    { key: 'status', label: 'Status' },
    { key: 'decisionLabel', label: 'Keputusan' },
    { key: 'note', label: 'Catatan' },
    { key: 'forwardedTo', label: 'Diteruskan Ke' },
    { key: 'createdAt', label: 'Waktu' },
];

function statusMatches(status, filterKey) {
    if (filterKey === 'Total' || filterKey === 'Semua') return true;
    const test = STATUS_BUCKET[filterKey];
    return test ? test(status) : true;
}

export default function ApprovalHistoryPage({ counts = {}, rows = [] }) {
    const [activeStatus, setActiveStatus] = useState('Total');
    const [search, setSearch] = useState('');
    const [layanan, setLayanan] = useState('All Layanan');
    const [periodDays, setPeriodDays] = useState(366);
    const [periodLabel, setPeriodLabel] = useState('This year');
    const [sortKey, setSortKey] = useState('createdAt');
    const [sortDir, setSortDir] = useState('desc');

    function toggleSort(key) {
        if (key === sortKey) {
            setSortDir((d) => (d === 'asc' ? 'desc' : 'asc'));
        } else {
            setSortKey(key);
            setSortDir('asc');
        }
    }

    function pickRelativePeriod(label) {
        setPeriodLabel(label);
        setPeriodDays(RELATIVE_PERIODS[label]);
    }

    const layananOptions = useMemo(() => ['All Layanan', ...new Set(rows.map((r) => r.layanan).filter((v) => v && v !== '—'))], [rows]);

    const filtered = useMemo(() => {
        const cutoff = Date.now() - periodDays * 24 * 60 * 60 * 1000;

        const list = rows.filter((r) => {
            if (!statusMatches(r.status, activeStatus)) return false;
            if (layanan !== 'All Layanan' && r.layanan !== layanan) return false;
            if (new Date(r.createdAt).getTime() < cutoff) return false;
            if (search.trim() !== '') {
                const q = search.trim().toLowerCase();
                const haystack = `${r.id} ${r.title} ${r.layanan}`.toLowerCase();
                if (!haystack.includes(q)) return false;
            }
            return true;
        });

        list.sort((a, b) => {
            const av = sortKey === 'createdAt' ? new Date(a.createdAt).getTime() : (a[sortKey] ?? '').toString().toLowerCase();
            const bv = sortKey === 'createdAt' ? new Date(b.createdAt).getTime() : (b[sortKey] ?? '').toString().toLowerCase();
            const cmp = av < bv ? -1 : av > bv ? 1 : 0;
            return sortDir === 'asc' ? cmp : -cmp;
        });

        return list;
    }, [rows, activeStatus, layanan, periodDays, search, sortKey, sortDir]);

    return (
        <div className="flex flex-col gap-7">
            <div>
                <h1 className="text-2xl font-extrabold tracking-tight text-gray-900">My Tickets</h1>
                <p className="mt-1 text-[13px] text-gray-400">{rows.length} tiket dipantau setahun terakhir — status penanganan diperbarui otomatis dari Support IT / BPO.</p>
            </div>

            <div className="grid grid-cols-2 gap-5 sm:grid-cols-3 lg:grid-cols-5">
                {CARDS.map((c) => (
                    <button
                        key={c.key}
                        onClick={() => setActiveStatus(c.key)}
                        className={`flex flex-col gap-2.5 rounded-2xl border bg-white p-4 text-left shadow-sm transition ${activeStatus === c.key ? 'border-blue-500 ring-2 ring-blue-100' : 'border-gray-200 hover:border-gray-300'}`}
                    >
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold text-gray-400">{c.label}</span>
                            <span className={`flex h-7 w-7 items-center justify-center rounded-lg ${c.bg} ${c.color}`}>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d={c.icon} /></svg>
                            </span>
                        </div>
                        <div className="text-[28px] font-extrabold leading-none text-gray-900">{counts[c.key] ?? 0}</div>
                    </button>
                ))}
            </div>

            <div className="flex items-center gap-2 rounded-[10px] border border-gray-200 bg-white px-4 py-3 shadow-sm">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" className="text-gray-400"><circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" /></svg>
                <input
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    type="text"
                    placeholder="Cari tiket, judul, atau layanan…"
                    className="flex-1 border-none bg-transparent text-[13px] text-gray-900 outline-none placeholder:text-gray-400"
                />
            </div>

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex flex-wrap gap-1.5 rounded-xl border border-gray-200 bg-white p-1.5 shadow-sm">
                    {STATUS_PILLS.map((p) => {
                        const key = p === 'Semua' ? 'Total' : p;
                        const active = activeStatus === key;
                        return (
                            <button
                                key={p}
                                onClick={() => setActiveStatus(key)}
                                className={`rounded-lg px-3.5 py-2 text-[13px] font-semibold ${active ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-50'}`}
                            >
                                {p}
                            </button>
                        );
                    })}
                </div>
                <div className="flex flex-wrap items-center gap-3">
                    <select value={layanan} onChange={(e) => setLayanan(e.target.value)} className="rounded-[10px] border border-gray-200 bg-white px-3 py-2.5 text-[13px] text-gray-700 outline-none">
                        {layananOptions.map((o) => <option key={o}>{o}</option>)}
                    </select>
                    <select value={periodLabel} onChange={(e) => pickRelativePeriod(e.target.value)} className="rounded-[10px] border border-gray-200 bg-white px-3 py-2.5 text-[13px] text-gray-700 outline-none">
                        {Object.keys(RELATIVE_PERIODS).map((p) => <option key={p}>{p}</option>)}
                    </select>
                </div>
            </div>

            <div className="rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div className="overflow-x-auto">
                    <table className="min-w-[920px] w-full text-sm">
                        <thead>
                            <tr className="border-b border-gray-100 bg-gray-50 text-[11px] font-bold uppercase tracking-wide text-gray-400">
                                {COLUMNS.map((col) => (
                                    <th key={col.key} className="px-4 py-3.5 text-left first:pl-6 last:pr-6">
                                        <button
                                            type="button"
                                            onClick={() => toggleSort(col.key)}
                                            className="flex items-center gap-1 uppercase tracking-wide text-gray-400 hover:text-gray-700"
                                        >
                                            {col.label}
                                            <span aria-hidden="true" className={sortKey === col.key ? 'text-gray-600' : 'text-gray-300'}>
                                                {sortKey === col.key ? (sortDir === 'asc' ? '↑' : '↓') : '↕'}
                                            </span>
                                        </button>
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {filtered.map((row) => (
                                <tr
                                    key={row.id}
                                    onClick={() => (window.location.href = row.href)}
                                    className="cursor-pointer border-b border-gray-50 last:border-0 hover:bg-blue-50/40"
                                >
                                    <td className="px-4 py-4 pl-6">
                                        <p className="font-bold text-blue-600">{row.id}</p>
                                        <p className="max-w-[200px] truncate text-xs text-gray-400">{row.title}</p>
                                    </td>
                                    <td className="px-4 py-4 text-gray-700">{row.service}</td>
                                    <td className="px-4 py-4"><StatusBadge status={row.status} /></td>
                                    <td className={`px-4 py-4 font-semibold ${DECISION_STYLES[row.decision] ?? 'text-gray-600'}`}>{row.decisionLabel}</td>
                                    <td className="px-4 py-4 text-gray-600">
                                        <p className="max-w-[220px] truncate">{row.note}</p>
                                    </td>
                                    <td className="px-4 py-4 text-gray-600">{row.forwardedTo}</td>
                                    <td className="px-4 py-4 pr-6 text-gray-400">{row.at}</td>
                                </tr>
                            ))}
                            {filtered.length === 0 && (
                                <tr>
                                    <td colSpan={7} className="px-5 py-12 text-center text-sm text-gray-400">Tidak ada tiket yang cocok dengan filter ini.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <div className="flex items-center justify-between px-5 py-3">
                    <span className="text-xs text-gray-400">Menampilkan {filtered.length} dari {rows.length} tiket</span>
                </div>
            </div>
        </div>
    );
}

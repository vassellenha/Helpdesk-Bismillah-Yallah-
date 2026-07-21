import { useMemo, useState } from 'react';

export default function KnowledgeConsole({ articles = [], unanswered = [] }) {
    const [search, setSearch] = useState('');

    const filtered = useMemo(
        () => articles.filter((a) => a.title.toLowerCase().includes(search.toLowerCase())),
        [articles, search]
    );

    return (
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div className="rounded-xl border border-gray-200 bg-white shadow-sm lg:col-span-2">
                <div className="flex items-center justify-between gap-3 border-b border-gray-100 p-4">
                    <h2 className="text-sm font-semibold text-gray-900">Basis Pengetahuan EVA</h2>
                    <div className="relative w-56">
                        <svg viewBox="0 0 24 24" fill="none" className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400">
                            <circle cx="11" cy="11" r="7" stroke="currentColor" strokeWidth="1.6" />
                            <path d="m20 20-3-3" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" />
                        </svg>
                        <input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Cari artikel..."
                            className="w-full rounded-lg border border-gray-200 py-2 pl-9 pr-3 text-sm focus:border-blue-400 focus:outline-none"
                        />
                    </div>
                </div>
                <table className="min-w-full divide-y divide-gray-100 text-sm">
                    <thead>
                        <tr className="text-left text-xs font-semibold uppercase tracking-wide text-gray-400">
                            <th className="px-4 py-3">Judul Artikel</th>
                            <th className="px-4 py-3">Dilihat</th>
                            <th className="px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-50">
                        {filtered.map((a) => (
                            <tr key={a.id} className="hover:bg-gray-50">
                                <td className="px-4 py-3 font-medium text-gray-900">{a.title}</td>
                                <td className="px-4 py-3 text-gray-600">{a.views}</td>
                                <td className="px-4 py-3">
                                    <span
                                        className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ${
                                            a.status === 'Published'
                                                ? 'bg-emerald-50 text-emerald-700'
                                                : 'bg-gray-100 text-gray-600'
                                        }`}
                                    >
                                        {a.status}
                                    </span>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <div className="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <h2 className="text-sm font-semibold text-gray-900">Pertanyaan Belum Terjawab</h2>
                <ul className="mt-3 space-y-3">
                    {unanswered.map((q, idx) => (
                        <li key={idx} className="rounded-lg bg-amber-50 p-3">
                            <p className="text-sm text-gray-800">{q.question}</p>
                            <p className="mt-1 text-xs text-amber-700">Ditanyakan {q.asked}x</p>
                        </li>
                    ))}
                </ul>
            </div>
        </div>
    );
}

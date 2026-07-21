import { useState } from 'react';

export default function AgentsPanel({ agents = [], editable = false }) {
    const [rows, setRows] = useState(agents);

    return (
        <div className="rounded-xl border border-gray-200 bg-white shadow-sm">
            <div className="border-b border-gray-100 p-4">
                <h2 className="text-sm font-semibold text-gray-900">Tim Support</h2>
            </div>
            <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-100 text-sm">
                    <thead>
                        <tr className="text-left text-xs font-semibold uppercase tracking-wide text-gray-400">
                            <th className="px-4 py-3">Agent</th>
                            <th className="px-4 py-3">Role</th>
                            <th className="px-4 py-3">Beban Tiket</th>
                            <th className="px-4 py-3">SLA On-Time</th>
                            {editable && <th className="px-4 py-3 text-right">Aksi</th>}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-50">
                        {rows.map((agent) => (
                            <tr key={agent.id} className="hover:bg-gray-50">
                                <td className="px-4 py-3 font-medium text-gray-900">{agent.name}</td>
                                <td className="px-4 py-3 text-gray-600">{agent.role}</td>
                                <td className="px-4 py-3 text-gray-600">{agent.load} tiket</td>
                                <td className="px-4 py-3">
                                    <div className="flex items-center gap-2">
                                        <div className="h-1.5 w-24 overflow-hidden rounded-full bg-gray-100">
                                            <div
                                                className={`h-full rounded-full ${agent.sla_ontime >= 90 ? 'bg-emerald-500' : agent.sla_ontime >= 80 ? 'bg-amber-500' : 'bg-red-500'}`}
                                                style={{ width: `${agent.sla_ontime}%` }}
                                            />
                                        </div>
                                        <span className="text-xs text-gray-500">{agent.sla_ontime}%</span>
                                    </div>
                                </td>
                                {editable && (
                                    <td className="px-4 py-3 text-right">
                                        <button
                                            onClick={() => setRows((prev) => prev.filter((a) => a.id !== agent.id))}
                                            className="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50"
                                        >
                                            Nonaktifkan
                                        </button>
                                    </td>
                                )}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

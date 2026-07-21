import { useEffect, useMemo, useRef, useState } from 'react';
import ServiceCatalogFormModal from './ServiceCatalogFormModal';
import ServiceCatalogDetailModal from './ServiceCatalogDetailModal';
import { apiFetch } from '../../lib/api';
import { LEVEL_LABELS } from '../../lib/formatters';

const ALL = 'Semua';

export default function ServiceCatalogConsole({ subjects: initialSubjects, issueCategories, services: initialServices, subcategories: initialSubcategories, supportAgents }) {
    const [subjects, setSubjects] = useState(initialSubjects);
    const [services, setServices] = useState(initialServices);
    const [subcategories, setSubcategories] = useState(initialSubcategories);

    const [search, setSearch] = useState('');
    const [layananFilter, setLayananFilter] = useState(ALL);
    const [subcategoryFilter, setSubcategoryFilter] = useState(ALL);
    const [issueFilter, setIssueFilter] = useState(ALL);
    const [approvalFilter, setApprovalFilter] = useState(ALL);
    const [statusFilter, setStatusFilter] = useState(ALL);

    const [modal, setModal] = useState(null); // 'add' | { type: 'edit'|'detail'|'duplicate', subject }
    const [menu, setMenu] = useState(null); // { subject, top, left }
    const [error, setError] = useState('');
    const menuRef = useRef(null);

    useEffect(() => {
        if (!menu) return;
        function onClickOutside(e) {
            if (menuRef.current && !menuRef.current.contains(e.target)) setMenu(null);
        }
        document.addEventListener('mousedown', onClickOutside);
        return () => document.removeEventListener('mousedown', onClickOutside);
    }, [menu]);

    const layananOptions = useMemo(() => Array.from(new Set(subjects.map((s) => s.layanan))).sort(), [subjects]);
    const subcategoryOptions = useMemo(() => Array.from(new Set(subjects.map((s) => s.subcategory))).sort(), [subjects]);

    const filtered = useMemo(() => {
        const q = search.toLowerCase();
        return subjects.filter((s) => {
            const matchesSearch = q === '' || s.layanan.toLowerCase().includes(q) || s.subcategory.toLowerCase().includes(q) || s.subject.toLowerCase().includes(q);
            const matchesLayanan = layananFilter === ALL || s.layanan === layananFilter;
            const matchesSubcategory = subcategoryFilter === ALL || s.subcategory === subcategoryFilter;
            const matchesIssue = issueFilter === ALL || s.issue_category === issueFilter;
            const matchesApproval = approvalFilter === ALL || (approvalFilter === 'Yes' ? s.requires_approval : !s.requires_approval);
            const matchesStatus = statusFilter === ALL || s.status === statusFilter;
            return matchesSearch && matchesLayanan && matchesSubcategory && matchesIssue && matchesApproval && matchesStatus;
        });
    }, [subjects, search, layananFilter, subcategoryFilter, issueFilter, approvalFilter, statusFilter]);

    function resetFilters() {
        setSearch('');
        setLayananFilter(ALL);
        setSubcategoryFilter(ALL);
        setIssueFilter(ALL);
        setApprovalFilter(ALL);
        setStatusFilter(ALL);
    }

    function upsert(saved) {
        setSubjects((prev) => (prev.some((s) => s.id === saved.id) ? prev.map((s) => (s.id === saved.id ? saved : s)) : [saved, ...prev]));
        if (!services.some((sv) => sv.name === saved.layanan)) {
            setServices((prev) => [...prev, { id: `tmp-${saved.layanan}`, name: saved.layanan }]);
        }
        if (!subcategories.some((sc) => sc.name === saved.subcategory)) {
            setSubcategories((prev) => [...prev, { id: `tmp-${saved.subcategory}`, name: saved.subcategory }]);
        }
        setModal(null);
    }

    function openMenu(e, subject) {
        if (menu?.subject.id === subject.id) {
            setMenu(null);
            return;
        }
        const rect = e.currentTarget.getBoundingClientRect();
        setMenu({ subject, top: rect.bottom + 4, left: rect.right - 176 });
    }

    async function toggleStatus(subject) {
        setMenu(null);
        setError('');
        try {
            const updated = await apiFetch(`/admin/service-catalog/subjects/${subject.id}/toggle`, { method: 'POST' });
            setSubjects((prev) => prev.map((s) => (s.id === updated.id ? updated : s)));
        } catch (e) {
            setError(e.message || 'Gagal memperbarui status.');
        }
    }

    async function remove(subject) {
        setMenu(null);
        if (!confirm(`Hapus subject "${subject.subject}"?`)) return;
        setError('');
        try {
            await apiFetch(`/admin/service-catalog/subjects/${subject.id}`, { method: 'DELETE' });
            setSubjects((prev) => prev.filter((s) => s.id !== subject.id));
        } catch (e) {
            setError(e.message || 'Gagal menghapus subject.');
        }
    }

    const activeCount = subjects.filter((s) => s.status === 'active').length;
    const approvalCount = subjects.filter((s) => s.requires_approval).length;

    return (
        <div>
            <div className="mb-6 flex items-start justify-between gap-3">
                <div>
                    <h1 className="text-3xl font-extrabold text-gray-900">Service Catalog Management</h1>
                    <p className="mt-1 text-sm text-gray-500">Master data Issue Category → Layanan → Sub Category → Subject — sumber tunggal untuk form Tiket Baru, dari data Excel Insiden &amp; Service List.</p>
                </div>
                <button onClick={() => setModal('add')} className="shrink-0 rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-800">
                    + Tambah Layanan
                </button>
            </div>

            {error && <p className="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">{error}</p>}

            <div className="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
                <Stat label="TOTAL SUBJECT" value={subjects.length} bg="bg-blue-50" color="text-blue-600" />
                <Stat label="AKTIF" value={activeCount} bg="bg-emerald-50" color="text-emerald-600" />
                <Stat label="MEMERLUKAN APPROVAL" value={approvalCount} bg="bg-amber-50" color="text-amber-600" />
                <Stat label="TOTAL LAYANAN" value={layananOptions.length} bg="bg-gray-100" color="text-gray-600" />
            </div>

            <div className="rounded-xl border border-gray-200 bg-white shadow-sm">
                <div className="flex flex-col gap-3 border-b border-gray-100 p-4 lg:flex-row lg:items-center lg:justify-between">
                    <input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Cari layanan, sub category, atau subject"
                        className="w-full max-w-sm rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none"
                    />
                    <div className="flex flex-wrap items-center gap-2">
                        <Select value={layananFilter} onChange={setLayananFilter} label="Semua Layanan" options={layananOptions} />
                        <Select value={subcategoryFilter} onChange={setSubcategoryFilter} label="Semua Sub Category" options={subcategoryOptions} />
                        <Select value={issueFilter} onChange={setIssueFilter} label="Semua Issue Category" options={issueCategories} />
                        <Select value={approvalFilter} onChange={setApprovalFilter} label="Semua Approval" options={['Yes', 'No']} />
                        <Select value={statusFilter} onChange={setStatusFilter} label="Semua Status" options={[['active', 'Aktif'], ['inactive', 'Nonaktif']]} />
                        <button onClick={resetFilters} className="text-sm font-medium text-blue-700 hover:text-blue-800">Reset Filter</button>
                    </div>
                </div>
                <p className="px-4 pt-3 text-sm text-gray-400">Menampilkan {filtered.length} dari {subjects.length} subject</p>

                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-100 text-sm">
                        <thead>
                            <tr className="text-left text-xs font-semibold uppercase tracking-wide text-gray-400">
                                <th className="px-4 py-3">Issue Category</th>
                                <th className="px-4 py-3">Layanan</th>
                                <th className="px-4 py-3">Sub Category</th>
                                <th className="px-4 py-3">Subject</th>
                                <th className="px-4 py-3">Support</th>
                                <th className="px-4 py-3">Level</th>
                                <th className="px-4 py-3">Approval</th>
                                <th className="px-4 py-3">Status</th>
                                <th className="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {filtered.map((s) => (
                                <tr key={s.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3">
                                        <span className="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600">{s.issue_category}</span>
                                    </td>
                                    <td className="px-4 py-3 text-gray-700">{s.layanan}</td>
                                    <td className="px-4 py-3 text-gray-600">{s.subcategory}</td>
                                    <td className="px-4 py-3 font-medium text-gray-900">{s.subject}</td>
                                    <td className="px-4 py-3 text-gray-700">
                                        {s.support_name ?? '—'}
                                        {s.support_type && (
                                            <span className="ml-1.5 rounded bg-blue-50 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-blue-600">{s.support_type}</span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-gray-600">{LEVEL_LABELS[s.support_level]}</td>
                                    <td className="px-4 py-3 text-gray-600">{s.requires_approval ? 'Yes' : 'No'}</td>
                                    <td className="px-4 py-3">
                                        <span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ${s.status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500'}`}>
                                            <span className={`h-1.5 w-1.5 rounded-full ${s.status === 'active' ? 'bg-emerald-500' : 'bg-gray-400'}`} />
                                            {s.status === 'active' ? 'Aktif' : 'Nonaktif'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <button onClick={(e) => openMenu(e, s)} className="rounded-full border border-gray-200 px-2.5 py-1 text-gray-500 hover:bg-gray-100">
                                            •••
                                        </button>
                                    </td>
                                </tr>
                            ))}
                            {filtered.length === 0 && (
                                <tr>
                                    <td colSpan={9} className="px-4 py-10 text-center text-sm text-gray-400">Tidak ada subject yang cocok dengan filter.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {menu && (
                <div ref={menuRef} style={{ top: menu.top, left: menu.left }} className="fixed z-50 w-44 overflow-hidden rounded-lg border border-gray-200 bg-white text-left shadow-lg">
                    <button onClick={() => { setModal({ type: 'detail', subject: menu.subject }); setMenu(null); }} className="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-gray-700 hover:bg-gray-50">
                        <SearchIcon /> Lihat Detail
                    </button>
                    <button onClick={() => { setModal({ type: 'edit', subject: menu.subject }); setMenu(null); }} className="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-gray-700 hover:bg-gray-50">
                        <EditIcon /> Edit
                    </button>
                    <button onClick={() => { setModal({ type: 'edit', subject: { ...menu.subject, id: undefined, __duplicate: true } }); setMenu(null); }} className="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-gray-700 hover:bg-gray-50">
                        <DuplicateIcon /> Duplicate
                    </button>
                    <button onClick={() => toggleStatus(menu.subject)} className="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-amber-600 hover:bg-amber-50">
                        <ToggleIcon /> {menu.subject.status === 'active' ? 'Deactivate' : 'Activate'}
                    </button>
                    <button onClick={() => remove(menu.subject)} className="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-red-600 hover:bg-red-50">
                        <DeleteIcon /> Delete
                    </button>
                </div>
            )}

            {(modal === 'add' || modal?.type === 'edit') && (
                <ServiceCatalogFormModal
                    subject={modal?.subject}
                    services={services}
                    subcategories={subcategories}
                    supportAgents={supportAgents}
                    onClose={() => setModal(null)}
                    onSaved={upsert}
                />
            )}
            {modal?.type === 'detail' && (
                <ServiceCatalogDetailModal subject={modal.subject} onClose={() => setModal(null)} />
            )}
        </div>
    );
}

function Select({ value, onChange, label, options }) {
    return (
        <select value={value} onChange={(e) => onChange(e.target.value)} className="rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700 focus:border-blue-400 focus:outline-none">
            <option value={ALL}>{label}</option>
            {options.map((opt) => {
                const [val, text] = Array.isArray(opt) ? opt : [opt, opt];
                return <option key={val} value={val}>{text}</option>;
            })}
        </select>
    );
}

function Stat({ label, value, bg, color }) {
    return (
        <div className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <span className={`flex h-9 w-9 items-center justify-center rounded-lg ${bg} ${color}`}>
                <span className="h-2.5 w-2.5 rounded-full bg-current" />
            </span>
            <p className="mt-3 text-2xl font-bold text-gray-900">{value}</p>
            <p className="text-xs font-medium text-gray-400">{label}</p>
        </div>
    );
}

function SearchIcon() {
    return (
        <svg viewBox="0 0 24 24" fill="none" className="h-4 w-4 text-gray-400">
            <circle cx="11" cy="11" r="7" stroke="currentColor" strokeWidth="1.6" />
            <path d="m20 20-3-3" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" />
        </svg>
    );
}

function EditIcon() {
    return (
        <svg viewBox="0 0 24 24" fill="none" className="h-4 w-4 text-gray-400">
            <path d="M12 20h9" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" />
            <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}

function DuplicateIcon() {
    return (
        <svg viewBox="0 0 24 24" fill="none" className="h-4 w-4 text-gray-400">
            <rect x="9" y="9" width="11" height="11" rx="2" stroke="currentColor" strokeWidth="1.6" />
            <path d="M5 15H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}

function ToggleIcon() {
    return (
        <svg viewBox="0 0 24 24" fill="none" className="h-4 w-4 text-amber-500">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" />
            <path d="M16 17l5-5-5-5M21 12H9" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}

function DeleteIcon() {
    return (
        <svg viewBox="0 0 24 24" fill="none" className="h-4 w-4 text-red-500">
            <path d="M3 6h18M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2m3 0-1 14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2L4 6" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}

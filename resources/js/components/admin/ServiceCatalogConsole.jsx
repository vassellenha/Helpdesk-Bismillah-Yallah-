import { useEffect, useMemo, useRef, useState } from 'react';
import ServiceCatalogFormModal from './ServiceCatalogFormModal';
import ServiceCatalogDetailModal from './ServiceCatalogDetailModal';
import SelectMenu from '../SelectMenu';
import AnchoredMenu from '../AnchoredMenu';
import { apiFetch } from '../../lib/api';
import { t as trans } from '../../lib/i18n';
import { LEVEL_LABELS } from '../../lib/formatters';

// Language-independent sentinel: this is compared against real catalog values.
const ALL = '__all';

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
    const [menu, setMenu] = useState(null); // { subject, anchorEl }
    const [error, setError] = useState('');
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
        setMenu({ subject, anchorEl: e.currentTarget });
    }

    async function toggleStatus(subject) {
        setMenu(null);
        setError('');
        try {
            const updated = await apiFetch(`/admin/service-catalog/subjects/${subject.id}/toggle`, { method: 'POST' });
            setSubjects((prev) => prev.map((s) => (s.id === updated.id ? updated : s)));
        } catch (e) {
            setError(e.message || trans('admin.catalog.status_failed'));
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
            setError(e.message || trans('admin.catalog.delete_failed'));
        }
    }

    const activeCount = subjects.filter((s) => s.status === 'active').length;
    const approvalCount = subjects.filter((s) => s.requires_approval).length;

    return (
        <div>
            <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 className="text-3xl font-extrabold text-gray-900 dark:text-ink-1">{trans('admin.catalog.title')}</h1>
                    <p className="mt-1 text-sm text-gray-500 dark:text-ink-2">{trans('admin.catalog.subtitle')}</p>
                </div>
                <button onClick={() => setModal('add')} className="shrink-0 rounded-lg bg-blue-700 dark:bg-blue-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-800 dark:hover:bg-blue-400">
                    {trans('admin.catalog.add_service')}
                </button>
            </div>

            {error && <p className="mb-4 rounded-lg bg-red-50 dark:bg-bad-soft p-3 text-sm text-red-700 dark:text-bad-text">{error}</p>}

            <div className="mb-6 grid grid-cols-1 sm:grid-cols-2 gap-4 lg:grid-cols-4">
                <Stat label={trans('admin.catalog.stat_total_subject')} value={subjects.length} bg="bg-blue-50 dark:bg-accent-soft" color="text-blue-600 dark:text-accent-text" />
                <Stat label={trans('admin.catalog.stat_active')} value={activeCount} bg="bg-emerald-50 dark:bg-ok-soft" color="text-emerald-600 dark:text-ok-text" />
                <Stat label={trans('admin.catalog.stat_needs_approval')} value={approvalCount} bg="bg-amber-50 dark:bg-warn-soft" color="text-amber-600 dark:text-warn-text" />
                <Stat label={trans('admin.catalog.stat_total_service')} value={layananOptions.length} bg="bg-gray-100 dark:bg-panel-3" color="text-gray-600 dark:text-ink-2" />
            </div>

            <div className="rounded-xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 shadow-sm">
                <div className="flex flex-col gap-3 border-b border-gray-100 dark:border-edge p-4 lg:flex-row lg:items-center lg:justify-between">
                    <input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder={trans('admin.catalog.search')}
                        className="w-full max-w-sm rounded-lg border border-gray-200 dark:border-edge-strong px-3 py-2 text-sm focus:border-blue-400 focus:outline-none"
                    />
                    <div className="flex flex-wrap items-center gap-2">
                        <SearchableSelect value={layananFilter} onChange={setLayananFilter} label={trans('admin.catalog.all_service')} options={layananOptions} searchPlaceholder={trans('admin.catalog.search_service')} />
                        <SearchableSelect value={subcategoryFilter} onChange={setSubcategoryFilter} label={trans('admin.catalog.all_subcategory')} options={subcategoryOptions} searchPlaceholder={trans('admin.catalog.search_subcategory')} />
                        <SearchableSelect value={issueFilter} onChange={setIssueFilter} label={trans('admin.catalog.all_issue')} options={issueCategories} searchPlaceholder={trans('admin.catalog.search_issue')} />
                        <Select value={approvalFilter} onChange={setApprovalFilter} label={trans('admin.catalog.all_approval')} options={[['Yes', trans('admin.integration.yes')], ['No', trans('admin.integration.no')]]} />
                        <Select value={statusFilter} onChange={setStatusFilter} label={trans('admin.catalog.all_status')} options={[['active', trans('admin.common.active')], ['inactive', trans('admin.common.inactive')]]} />
                        <button onClick={resetFilters} className="text-sm font-medium text-blue-700 dark:text-accent-text hover:text-blue-800 dark:hover:text-blue-300">{trans('admin.common.reset_filter')}</button>
                    </div>
                </div>
                <p className="px-4 pt-3 text-sm text-gray-400 dark:text-ink-3">{trans('admin.catalog.showing', { shown: filtered.length, total: subjects.length })}</p>

                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-100 dark:divide-transparent text-sm">
                        <thead>
                            <tr className="text-left text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-ink-3">
                                <th className="px-4 py-3">{trans('admin.catalog.col_issue')}</th>
                                <th className="px-4 py-3">{trans('admin.catalog.col_service')}</th>
                                <th className="px-4 py-3">{trans('admin.catalog.col_subcategory')}</th>
                                <th className="px-4 py-3">{trans('admin.catalog.col_subject')}</th>
                                <th className="px-4 py-3">{trans('admin.catalog.col_support')}</th>
                                <th className="px-4 py-3">{trans('admin.catalog.col_level')}</th>
                                <th className="px-4 py-3">{trans('admin.catalog.col_approval')}</th>
                                <th className="px-4 py-3">{trans('admin.common.status')}</th>
                                <th className="px-4 py-3 text-right">{trans('admin.common.action')}</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50 dark:divide-transparent">
                            {filtered.map((s) => (
                                <tr key={s.id} className="hover:bg-gray-50 dark:hover:bg-panel-hover dark:even:bg-white/[0.03]">
                                    <td className="px-4 py-3">
                                        <span className="rounded-full bg-gray-100 dark:bg-panel-3 px-2.5 py-1 text-xs font-medium text-gray-600 dark:text-ink-2">{s.issue_category}</span>
                                    </td>
                                    <td className="px-4 py-3 text-gray-700 dark:text-ink-2">{s.layanan}</td>
                                    <td className="px-4 py-3 text-gray-600 dark:text-ink-2">{s.subcategory}</td>
                                    <td className="px-4 py-3 font-medium text-gray-900 dark:text-ink-1">{s.subject}</td>
                                    <td className="px-4 py-3 text-gray-700 dark:text-ink-2">
                                        {!s.support_name && !s.it_name && '—'}
                                        {s.support_name && (
                                            <div>
                                                {s.support_name}
                                                <span className="ml-1.5 rounded bg-blue-50 dark:bg-accent-soft px-1.5 py-0.5 text-[10px] font-semibold uppercase text-blue-600 dark:text-accent-text">bpo</span>
                                            </div>
                                        )}
                                        {s.it_name && (
                                            <div>
                                                {s.it_name}
                                                <span className="ml-1.5 rounded bg-blue-50 dark:bg-accent-soft px-1.5 py-0.5 text-[10px] font-semibold uppercase text-blue-600 dark:text-accent-text">it</span>
                                            </div>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-gray-600 dark:text-ink-2">{LEVEL_LABELS[s.support_level]}</td>
                                    <td className="px-4 py-3 text-gray-600 dark:text-ink-2">{s.requires_approval ? trans('admin.integration.yes') : trans('admin.integration.no')}</td>
                                    <td className="px-4 py-3">
                                        <span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ${s.status === 'active' ? 'bg-emerald-50 dark:bg-ok-soft text-emerald-700 dark:text-ok-text' : 'bg-gray-100 dark:bg-panel-3 text-gray-500 dark:text-ink-2'}`}>
                                            <span className={`h-1.5 w-1.5 rounded-full ${s.status === 'active' ? 'bg-emerald-500' : 'bg-gray-400'}`} />
                                            {s.status === 'active' ? trans('admin.common.active') : trans('admin.common.inactive')}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <button onClick={(e) => openMenu(e, s)} className="rounded-full border border-gray-200 dark:border-edge-strong px-2.5 py-1 text-gray-500 dark:text-ink-2 hover:bg-gray-100 dark:hover:bg-panel-hover">
                                            •••
                                        </button>
                                    </td>
                                </tr>
                            ))}
                            {filtered.length === 0 && (
                                <tr>
                                    <td colSpan={9} className="px-4 py-10 text-center text-sm text-gray-400 dark:text-ink-3">{trans('admin.catalog.empty')}</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {menu && (
                <AnchoredMenu
                    anchorEl={menu.anchorEl}
                    onClose={() => setMenu(null)}
                    width={176}
                    className="z-50 overflow-hidden rounded-lg border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 text-left shadow-lg"
                >
                    <button onClick={() => { setModal({ type: 'detail', subject: menu.subject }); setMenu(null); }} className="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-gray-700 dark:text-ink-2 hover:bg-gray-50 dark:hover:bg-panel-hover dark:even:bg-white/[0.03]">
                        <SearchIcon /> Lihat Detail
                    </button>
                    <button onClick={() => { setModal({ type: 'edit', subject: menu.subject }); setMenu(null); }} className="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-gray-700 dark:text-ink-2 hover:bg-gray-50 dark:hover:bg-panel-hover dark:even:bg-white/[0.03]">
                        <EditIcon /> Edit
                    </button>
                    <button onClick={() => { setModal({ type: 'edit', subject: { ...menu.subject, id: undefined, __duplicate: true } }); setMenu(null); }} className="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-gray-700 dark:text-ink-2 hover:bg-gray-50 dark:hover:bg-panel-hover dark:even:bg-white/[0.03]">
                        <DuplicateIcon /> Duplicate
                    </button>
                    <button onClick={() => toggleStatus(menu.subject)} className="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-amber-600 dark:text-warn-text hover:bg-amber-50 dark:hover:bg-warn-soft">
                        <ToggleIcon /> {menu.subject.status === 'active' ? trans('admin.common.deactivate') : trans('admin.common.activate')}
                    </button>
                    <button onClick={() => remove(menu.subject)} className="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-red-600 dark:text-bad-text hover:bg-red-50 dark:hover:bg-bad-soft">
                        <DeleteIcon /> Delete
                    </button>
                </AnchoredMenu>
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
    const opts = useMemo(() => [
        { value: ALL, label },
        ...options.map((opt) => {
            const [val, text] = Array.isArray(opt) ? opt : [opt, opt];
            return { value: val, label: text };
        }),
    ], [label, options]);

    return <SelectMenu value={value} onChange={onChange} options={opts} />;
}

// Layanan/Sub Category/Issue Category can run into dozens of options —
// a plain <select> makes those unscannable, so this swaps in a search box
// over a styled, height-capped list instead (same pattern as the PIC
// filter in Admin Ticket Management).
function SearchableSelect({ value, onChange, label, options, searchPlaceholder = trans('admin.common.search') }) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const ref = useRef(null);

    useEffect(() => {
        function onClickOutside(e) {
            if (ref.current && !ref.current.contains(e.target)) {
                setOpen(false);
                setQuery('');
            }
        }
        document.addEventListener('mousedown', onClickOutside);
        return () => document.removeEventListener('mousedown', onClickOutside);
    }, []);

    const normalized = options.map((opt) => (Array.isArray(opt) ? opt : [opt, opt]));
    const filtered = normalized.filter(([, text]) => text.toLowerCase().includes(query.toLowerCase()));
    const selectedText = normalized.find(([val]) => val === value)?.[1];

    return (
        <div ref={ref} className="relative">
            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                className="flex min-w-[160px] items-center justify-between gap-2 rounded-lg border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 px-3 py-2 text-left text-sm text-gray-700 dark:text-ink-2 hover:border-gray-300 dark:hover:border-ink-3 focus:border-blue-400 focus:outline-none"
            >
                <span className="truncate">{value === ALL ? label : selectedText ?? label}</span>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="shrink-0 text-gray-400 dark:text-ink-3"><path d="m6 9 6 6 6-6" /></svg>
            </button>

            {open && (
                <div className="absolute left-0 top-[calc(100%+4px)] z-30 w-64 overflow-hidden rounded-lg border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 shadow-lg">
                    <div className="border-b border-gray-100 dark:border-edge p-2">
                        <input
                            autoFocus
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder={searchPlaceholder}
                            className="w-full rounded-md border border-gray-200 dark:border-edge-strong px-2.5 py-1.5 text-sm outline-none focus:border-blue-400"
                        />
                    </div>
                    <ul className="max-h-64 overflow-y-auto py-1">
                        <li>
                            <button
                                type="button"
                                onClick={() => { onChange(ALL); setOpen(false); setQuery(''); }}
                                className={`block w-full px-3 py-2 text-left text-sm hover:bg-blue-50 dark:hover:bg-panel-hover ${value === ALL ? 'bg-blue-50 dark:bg-accent-soft font-semibold text-blue-700 dark:text-accent-text' : 'text-gray-700 dark:text-ink-2'}`}
                            >
                                {label}
                            </button>
                        </li>
                        {filtered.map(([val, text]) => (
                            <li key={val}>
                                <button
                                    type="button"
                                    onClick={() => { onChange(val); setOpen(false); setQuery(''); }}
                                    className={`block w-full truncate px-3 py-2 text-left text-sm hover:bg-blue-50 dark:hover:bg-panel-hover ${val === value ? 'bg-blue-50 dark:bg-accent-soft font-semibold text-blue-700 dark:text-accent-text' : 'text-gray-700 dark:text-ink-2'}`}
                                >
                                    {text}
                                </button>
                            </li>
                        ))}
                        {filtered.length === 0 && <li className="px-3 py-4 text-center text-xs text-gray-400 dark:text-ink-3">{trans('admin.common.no_result')}</li>}
                    </ul>
                </div>
            )}
        </div>
    );
}

function Stat({ label, value, bg, color }) {
    return (
        <div className="rounded-xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 p-5 shadow-sm">
            <span className={`flex h-9 w-9 items-center justify-center rounded-lg ${bg} ${color}`}>
                <span className="h-2.5 w-2.5 rounded-full bg-current" />
            </span>
            <p className="mt-3 text-2xl font-bold text-gray-900 dark:text-ink-1">{value}</p>
            <p className="text-xs font-medium text-gray-400 dark:text-ink-3">{label}</p>
        </div>
    );
}

function SearchIcon() {
    return (
        <svg viewBox="0 0 24 24" fill="none" className="h-4 w-4 text-gray-400 dark:text-ink-3">
            <circle cx="11" cy="11" r="7" stroke="currentColor" strokeWidth="1.6" />
            <path d="m20 20-3-3" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" />
        </svg>
    );
}

function EditIcon() {
    return (
        <svg viewBox="0 0 24 24" fill="none" className="h-4 w-4 text-gray-400 dark:text-ink-3">
            <path d="M12 20h9" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" />
            <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}

function DuplicateIcon() {
    return (
        <svg viewBox="0 0 24 24" fill="none" className="h-4 w-4 text-gray-400 dark:text-ink-3">
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

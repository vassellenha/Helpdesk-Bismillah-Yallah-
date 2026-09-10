import { useMemo, useState } from 'react';
import SelectMenu from '../SelectMenu';
import { ALL, Select, SearchableSelect } from './CatalogFilterSelect';
import { apiFetch } from '../../lib/api';
import { t as trans } from '../../lib/i18n';

const UNSET = '__unset';
const ASSIGNED = '__assigned';

/**
 * Pembagian cakupan Team Lead BPO: satu baris per Subkategori, satu dropdown
 * per baris.
 *
 * Dropdown inline, bukan modal — 40 Subkategori harus ditugaskan dalam satu
 * sesi, dan modal berarti empat puluh kali (buka menu → Edit → pilih →
 * Simpan → tutup).
 *
 * Kolom "Subjek" bukan hiasan: ia memberi tahu Admin berapa banyak yang
 * benar-benar berpindah tangan sebelum ia menekan dropdown. Tanpa itu,
 * menugaskan Subkategori berisi 30 Subjek dan yang berisi 0 terasa sama.
 */
export default function ServiceSubcategoryTeamLeadTable({ subcategories: initial, teamLeadOptions }) {
    const [rows, setRows] = useState(initial);
    const [search, setSearch] = useState('');
    const [serviceFilter, setServiceFilter] = useState(ALL);
    const [leadFilter, setLeadFilter] = useState(ALL);
    const [assignFilter, setAssignFilter] = useState(ALL);
    const [savingId, setSavingId] = useState(null);
    const [error, setError] = useState('');

    const unassignedCount = rows.filter((r) => !r.team_lead_bpo_user_id).length;

    // Diambil dari baris yang ada, bukan dari daftar Layanan penuh: Layanan
    // yang belum punya Sub Kategori tidak akan pernah muncul di tabel ini,
    // dan menawarkannya sebagai filter hanya menghasilkan hasil kosong.
    const serviceOptions = useMemo(
        () => Array.from(new Set(rows.map((r) => r.service_name).filter(Boolean))).sort(),
        [rows],
    );

    const filtered = useMemo(() => {
        const q = search.toLowerCase();
        return rows.filter((r) => {
            const haystack = `${r.service_name ?? ''} ${r.name}`.toLowerCase();
            const matchesSearch = q === '' || haystack.includes(q);
            const matchesService = serviceFilter === ALL || r.service_name === serviceFilter;
            const matchesLead = leadFilter === ALL || String(r.team_lead_bpo_user_id ?? '') === leadFilter;
            const matchesAssign =
                assignFilter === ALL
                || (assignFilter === UNSET ? !r.team_lead_bpo_user_id : !!r.team_lead_bpo_user_id);
            return matchesSearch && matchesService && matchesLead && matchesAssign;
        });
    }, [rows, search, serviceFilter, leadFilter, assignFilter]);

    function resetFilters() {
        setSearch('');
        setServiceFilter(ALL);
        setLeadFilter(ALL);
        setAssignFilter(ALL);
    }

    async function assign(row, value) {
        const previous = rows;
        const userId = value === '' ? null : Number(value);
        const picked = teamLeadOptions.find((o) => o.id === userId);

        // Optimistis: dropdown 40 baris terasa berat kalau tiap pilihan
        // menunggu jaringan. Kalau gagal, keadaan lama dikembalikan utuh.
        setRows((all) => all.map((r) => (
            r.id === row.id
                ? { ...r, team_lead_bpo_user_id: userId, team_lead_bpo_name: picked?.name ?? null }
                : r
        )));
        setSavingId(row.id);
        setError('');

        try {
            const saved = await apiFetch(`/admin/service-catalog/subcategories/${row.id}/team-lead`, {
                method: 'PATCH',
                body: JSON.stringify({ team_lead_bpo_user_id: userId }),
            });
            setRows((all) => all.map((r) => (r.id === saved.id ? { ...r, ...saved } : r)));
        } catch (e) {
            setRows(previous);
            setError(e?.message || trans('admin.catalog.team_lead_save_failed'));
        } finally {
            setSavingId(null);
        }
    }

    return (
        <div className="rounded-xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 shadow-sm">
            <div className="flex flex-col gap-3 border-b border-gray-100 dark:border-edge p-4 lg:flex-row lg:items-center lg:justify-between">
                <input
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder={trans('admin.catalog.search_subcategory_name')}
                    className="w-full max-w-sm rounded-lg border border-gray-200 dark:border-edge-strong px-3 py-2 text-sm focus:border-blue-400 focus:outline-none"
                />
                <div className="flex flex-wrap items-center gap-2">
                    <SearchableSelect
                        value={serviceFilter}
                        onChange={setServiceFilter}
                        label={trans('admin.catalog.all_service')}
                        options={serviceOptions}
                        searchPlaceholder={trans('admin.catalog.search_service')}
                    />
                    <SearchableSelect
                        value={leadFilter}
                        onChange={setLeadFilter}
                        label={trans('admin.catalog.all_team_lead_person')}
                        options={teamLeadOptions.map((o) => [String(o.id), o.name])}
                        searchPlaceholder={trans('admin.catalog.search_team_lead')}
                    />
                    <Select
                        value={assignFilter}
                        onChange={setAssignFilter}
                        label={trans('admin.catalog.all_team_lead')}
                        options={[
                            [ASSIGNED, trans('admin.catalog.team_lead_assigned')],
                            [UNSET, trans('admin.catalog.team_lead_unassigned')],
                        ]}
                    />
                    <button onClick={resetFilters} className="text-sm font-medium text-blue-700 dark:text-accent-text hover:text-blue-800 dark:hover:text-blue-300">
                        {trans('admin.common.reset_filter')}
                    </button>
                </div>
            </div>

            {error && <p className="mx-4 mt-3 rounded-lg bg-red-50 dark:bg-bad-soft p-3 text-sm text-red-700 dark:text-bad-text">{error}</p>}

            <p className="px-4 pt-3 text-sm text-gray-400 dark:text-ink-3">
                {trans('admin.catalog.showing_subcategory', { shown: filtered.length, total: rows.length })}
                {' · '}
                {trans('admin.catalog.subcategory_unassigned_hint', { count: unassignedCount })}
            </p>

            <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-100 dark:divide-transparent text-sm">
                    <thead>
                        <tr className="text-left text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-ink-3">
                            <th className="px-4 py-3">{trans('admin.catalog.col_service')}</th>
                            <th className="px-4 py-3">{trans('admin.catalog.col_subcategory')}</th>
                            <th className="px-4 py-3 text-right">{trans('admin.catalog.col_subject_count')}</th>
                            <th className="px-4 py-3">{trans('admin.catalog.col_team_lead_bpo')}</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 dark:divide-edge">
                        {filtered.map((row) => (
                            <tr key={row.id} className="hover:bg-gray-50 dark:hover:bg-panel-3">
                                <td className="px-4 py-3 text-gray-700 dark:text-ink-2">{row.service_name ?? '—'}</td>
                                <td className="px-4 py-3 font-medium text-gray-900 dark:text-ink-1">
                                    {row.name}
                                    {/* Baris tanpa Team Lead bukan sekadar "belum lengkap": isinya
                                        tidak terbaca Team Lead manapun. Ditandai supaya bisa
                                        dipindai, bukan cuma dihitung di atas tabel. */}
                                    {!row.team_lead_bpo_user_id && (
                                        <span className="ml-2 rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide bg-amber-100 text-amber-700 dark:bg-warn-soft dark:text-warn-text">
                                            {trans('admin.catalog.team_lead_unset')}
                                        </span>
                                    )}
                                </td>
                                <td className="px-4 py-3 text-right text-gray-500 dark:text-ink-3">
                                    {row.active_subject_count}
                                    {row.subject_count !== row.active_subject_count && (
                                        <span className="text-gray-400 dark:text-ink-3"> / {row.subject_count}</span>
                                    )}
                                </td>
                                <td className="px-4 py-3">
                                    <div className={savingId === row.id ? 'opacity-50' : ''}>
                                        <SelectMenu
                                            searchable
                                            value={String(row.team_lead_bpo_user_id ?? '')}
                                            onChange={(v) => assign(row, v)}
                                            options={[
                                                { value: '', label: trans('admin.catalog.team_lead_unset') },
                                                ...teamLeadOptions.map((o) => ({ value: String(o.id), label: o.name })),
                                            ]}
                                        />
                                    </div>
                                </td>
                            </tr>
                        ))}
                        {filtered.length === 0 && (
                            <tr>
                                <td colSpan={4} className="px-4 py-10 text-center text-gray-400 dark:text-ink-3">
                                    {trans('admin.common.no_result')}
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

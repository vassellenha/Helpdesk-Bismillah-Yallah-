import { useEffect, useRef, useState } from 'react';
import SelectMenu from '../SelectMenu';
import { apiFetch } from '../../lib/api';
import { downloadFile } from '../../lib/download';
import { t as trans } from '../../lib/i18n';
import useLockBodyScroll from '../../lib/useLockBodyScroll';

const ALL_UNIT = '__all_unit';
const ALL_JABATAN = '__all_jabatan';
const ALL_ROLE = '__all_role';

/**
 * Popup Ekspor Pengguna — gaya visual sengaja beda dari modal lain di app ini
 * (lihat komentar `.liquid-glass` di app.css untuk alasannya). Filternya
 * (unit/jabatan/role) memakai query param YANG SAMA dengan yang dibaca
 * `UserRoleController::baseUsersQuery()`, jadi hitungan pratinjau di sini dan
 * isi berkas yang diunduh dijamin tidak pernah berbeda.
 */
export default function ExportUsersModal({ onClose, listUrl, exportUrl, roles, unitOrganisasi, jabatanOptions }) {
    useLockBodyScroll();

    const [format, setFormat] = useState('csv');
    const [unit, setUnit] = useState(ALL_UNIT);
    const [jabatan, setJabatan] = useState(ALL_JABATAN);
    const [role, setRole] = useState(ALL_ROLE);
    const [count, setCount] = useState(null);
    const [counting, setCounting] = useState(false);
    const [downloading, setDownloading] = useState(false);
    const [error, setError] = useState('');

    const unitOptions = [{ value: ALL_UNIT, label: trans('admin.users.all_unit') }, ...unitOrganisasi.map((u) => ({ value: u, label: u }))];
    const jabatanOpts = [{ value: ALL_JABATAN, label: trans('admin.users.export_all_jabatan') }, ...jabatanOptions.map((j) => ({ value: j, label: j }))];
    const roleOptions = [{ value: ALL_ROLE, label: trans('admin.users.all_role') }, ...roles.map((r) => ({ value: r.name, label: r.name }))];

    function filterParams() {
        const params = new URLSearchParams();
        if (unit !== ALL_UNIT) params.set('unit', unit);
        if (jabatan !== ALL_JABATAN) params.set('jabatan', jabatan);
        if (role !== ALL_ROLE) params.set('role', role);
        return params;
    }

    // Pratinjau jumlah baris yang AKAN diunduh, dihitung server lewat endpoint
    // listing yang sudah ada — bukan ditaksir di klien. Ditunda 300ms supaya
    // mengganti tiga dropdown berturut-turut tidak memicu tiga permintaan.
    const requestSeq = useRef(0);
    useEffect(() => {
        const seq = ++requestSeq.current;
        setCounting(true);
        const timer = setTimeout(async () => {
            try {
                const params = filterParams();
                params.set('page', '1');
                const res = await apiFetch(`${listUrl}?${params.toString()}`);
                if (seq === requestSeq.current) setCount(res.meta.total);
            } catch {
                if (seq === requestSeq.current) setCount(null);
            } finally {
                if (seq === requestSeq.current) setCounting(false);
            }
        }, 300);
        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [unit, jabatan, role]);

    useEffect(() => {
        function onKeyDown(e) {
            if (e.key === 'Escape') onClose();
        }
        document.addEventListener('keydown', onKeyDown);
        return () => document.removeEventListener('keydown', onKeyDown);
    }, [onClose]);

    async function handleExport() {
        setError('');
        setDownloading(true);
        try {
            const params = filterParams();
            params.set('format', format);
            await downloadFile(`${exportUrl}?${params.toString()}`);
            onClose();
        } catch (e) {
            setError(e.message || trans('admin.users.export_failed'));
        } finally {
            setDownloading(false);
        }
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4" onClick={onClose}>
            {/* Latar diredupkan + diburamkan, ditambah dua noda gradien merek yang
                sangat lembut — sumber "cahaya" yang dibiaskan panel kaca di
                depannya. Ini satu-satunya tempat gradien merek dipakai sepenuhnya
                dekoratif, jadi opasitasnya sengaja sangat rendah. */}
            <div className="absolute inset-0 bg-slate-950/40 backdrop-blur-sm" />
            <div
                className="pointer-events-none absolute -left-24 -top-24 h-72 w-72 rounded-full opacity-30 blur-3xl"
                style={{ background: 'linear-gradient(135deg, var(--color-brand-from), var(--color-brand-via))' }}
            />
            <div
                className="pointer-events-none absolute -bottom-24 -right-24 h-72 w-72 rounded-full opacity-30 blur-3xl"
                style={{ background: 'linear-gradient(135deg, var(--color-brand-via), var(--color-brand-to))' }}
            />

            <div
                className="liquid-glass relative flex max-h-[90vh] w-full max-w-md flex-col overflow-hidden rounded-3xl shadow-2xl"
                onClick={(e) => e.stopPropagation()}
            >
                <div className="flex items-start justify-between px-6 pb-1 pt-6">
                    <div>
                        <h2 className="text-lg font-bold text-gray-900 dark:text-ink-1">{trans('admin.users.export_title')}</h2>
                        <p className="mt-0.5 text-sm text-gray-600 dark:text-ink-2">{trans('admin.users.export_subtitle')}</p>
                    </div>
                    <button
                        onClick={onClose}
                        className="liquid-glass-well rounded-full p-1.5 text-gray-500 dark:text-ink-2 hover:text-gray-800 dark:hover:text-ink-1"
                        aria-label={trans('admin.common.close')}
                    >
                        ✕
                    </button>
                </div>

                <div className="space-y-4 overflow-y-auto px-6 py-5">
                    {error && <p className="rounded-xl bg-red-50/90 dark:bg-bad-soft p-3 text-sm text-red-700 dark:text-bad-text">{error}</p>}

                    <div>
                        <label className="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-ink-3">
                            {trans('admin.users.export_format')}
                        </label>
                        <div className="liquid-glass-well flex gap-1 rounded-xl p-1">
                            {[['csv', trans('admin.users.export_format_csv')], ['pdf', trans('admin.users.export_format_pdf')]].map(([value, label]) => (
                                <button
                                    key={value}
                                    type="button"
                                    onClick={() => setFormat(value)}
                                    className={`flex-1 rounded-lg px-3 py-2 text-sm font-semibold transition-colors ${
                                        format === value
                                            ? 'bg-blue-600 text-white shadow-sm'
                                            : 'text-gray-600 dark:text-ink-2 hover:bg-white/40 dark:hover:bg-white/5'
                                    }`}
                                >
                                    {label}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div>
                        <label className="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-ink-3">
                            {trans('admin.users.col_unit')}
                        </label>
                        <SelectMenu value={unit} onChange={setUnit} options={unitOptions} searchable />
                    </div>

                    <div>
                        <label className="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-ink-3">
                            {trans('admin.users.col_jabatan')}
                        </label>
                        <SelectMenu value={jabatan} onChange={setJabatan} options={jabatanOpts} searchable />
                    </div>

                    <div>
                        <label className="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-ink-3">
                            {trans('admin.users.col_role')}
                        </label>
                        <SelectMenu value={role} onChange={setRole} options={roleOptions} />
                    </div>

                    <p className="liquid-glass-well rounded-xl px-3 py-2 text-sm text-gray-700 dark:text-ink-2">
                        {counting
                            ? trans('admin.users.export_counting')
                            : count === null
                                ? trans('admin.users.export_count_unknown')
                                : trans('admin.users.export_count', { count })}
                    </p>
                </div>

                <div className="flex justify-end gap-2 px-6 pb-6 pt-1">
                    <button
                        onClick={onClose}
                        className="liquid-glass-well rounded-xl px-4 py-2 text-sm font-medium text-gray-700 dark:text-ink-2 hover:bg-white/50 dark:hover:bg-white/10"
                    >
                        {trans('admin.common.cancel')}
                    </button>
                    <button
                        onClick={handleExport}
                        disabled={downloading || count === 0}
                        className="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {downloading ? trans('admin.users.export_downloading') : trans('admin.users.export_action')}
                    </button>
                </div>
            </div>
        </div>
    );
}

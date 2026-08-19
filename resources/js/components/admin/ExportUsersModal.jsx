import { useEffect, useRef, useState } from 'react';
import Modal, { ModalHeader, ModalFooter } from './Modal';
import SelectMenu from '../SelectMenu';
import { apiFetch } from '../../lib/api';
import { downloadFile } from '../../lib/download';
import { t as trans } from '../../lib/i18n';

const ALL_UNIT = '__all_unit';
const ALL_JABATAN = '__all_jabatan';
const ALL_ROLE = '__all_role';

/**
 * Popup Ekspor Pengguna (CSV) — bentuknya sama dengan modal lain lewat
 * `Modal`/`ModalHeader`/`ModalFooter` bersama. Memakai kaca `-dense` seperti
 * seluruh popup lain: varian `light` terlalu tembus pandang di mode gelap
 * (10% opacity) sehingga teks halaman di baliknya ikut terbaca.
 *
 * Unit Kerja dan Jabatan SALING menyaring pilihan satu sama lain lewat
 * `filterOptionsUrl` — pilih satu, dan pilihan yang lain hanya menawarkan
 * nilai yang benar-benar berpasangan dengannya. Angka pratinjau memakai
 * query param yang SAMA dengan yang dibaca `UserRoleController::
 * baseUsersQuery()`, jadi hitungan di sini dan isi berkas yang diunduh
 * dijamin tidak pernah berbeda.
 */
export default function ExportUsersModal({ onClose, listUrl, exportUrl, filterOptionsUrl, roles, unitOrganisasi, jabatanOptions }) {
    const [unit, setUnit] = useState(ALL_UNIT);
    const [jabatan, setJabatan] = useState(ALL_JABATAN);
    const [role, setRole] = useState(ALL_ROLE);
    const [unitOpts, setUnitOpts] = useState(unitOrganisasi);
    const [jabatanOpts, setJabatanOpts] = useState(jabatanOptions);
    const [count, setCount] = useState(null);
    const [counting, setCounting] = useState(false);
    const [downloading, setDownloading] = useState(false);
    const [error, setError] = useState('');

    const unitOptions = [{ value: ALL_UNIT, label: trans('admin.users.all_unit') }, ...unitOpts.map((u) => ({ value: u, label: u }))];
    const jabatanSelectOptions = [{ value: ALL_JABATAN, label: trans('admin.users.export_all_jabatan') }, ...jabatanOpts.map((j) => ({ value: j, label: j }))];
    const roleOptions = [{ value: ALL_ROLE, label: trans('admin.users.all_role') }, ...roles.map((r) => ({ value: r.name, label: r.name }))];

    function filterParams() {
        const params = new URLSearchParams();
        if (unit !== ALL_UNIT) params.set('unit', unit);
        if (jabatan !== ALL_JABATAN) params.set('jabatan', jabatan);
        if (role !== ALL_ROLE) params.set('role', role);
        return params;
    }

    // Satu putaran, dua permintaan: jumlah pratinjau (bergantung ketiga
    // filter) dan pilihan Unit Kerja/Jabatan yang saling menyaring (hanya
    // bergantung dua di antaranya). Ditunda 300ms supaya mengubah tiga
    // dropdown berturut-turut tidak memicu enam permintaan. Hanya respons
    // dari putaran TERBARU yang boleh menulis ke state, sama seperti
    // UserManagementConsole — respons lambat dari filter lama yang datang
    // belakangan tidak boleh menimpa hasil filter yang sudah benar.
    const requestSeq = useRef(0);
    useEffect(() => {
        const seq = ++requestSeq.current;
        setCounting(true);
        const timer = setTimeout(async () => {
            const unitParam = unit === ALL_UNIT ? '' : unit;
            const jabatanParam = jabatan === ALL_JABATAN ? '' : jabatan;

            try {
                const countParams = filterParams();
                countParams.set('page', '1');

                const [listRes, optsRes] = await Promise.all([
                    apiFetch(`${listUrl}?${countParams.toString()}`),
                    apiFetch(`${filterOptionsUrl}?unit=${encodeURIComponent(unitParam)}&jabatan=${encodeURIComponent(jabatanParam)}`),
                ]);
                if (seq !== requestSeq.current) return;

                setCount(listRes.meta.total);
                setUnitOpts(optsRes.units);
                setJabatanOpts(optsRes.jabatans);
                // Kombinasi yang baru saja jadi mustahil (mis. unit dipilih lalu
                // jabatan yang sedang aktif ternyata tidak pernah ada di unit
                // itu) dikembalikan ke "Semua…" — dibiarkan tersangkut di
                // pilihan yang sudah tidak valid akan diam-diam menghasilkan
                // nol baris selamanya tanpa penjelasan.
                if (unit !== ALL_UNIT && !optsRes.units.includes(unit)) setUnit(ALL_UNIT);
                if (jabatan !== ALL_JABATAN && !optsRes.jabatans.includes(jabatan)) setJabatan(ALL_JABATAN);
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
            await downloadFile(`${exportUrl}?${filterParams().toString()}`);
            onClose();
        } catch (e) {
            setError(e.message || trans('admin.users.export_failed'));
        } finally {
            setDownloading(false);
        }
    }

    return (
        <Modal onClose={onClose} maxWidth="max-w-md">
            <ModalHeader title={trans('admin.users.export_title')} subtitle={trans('admin.users.export_subtitle')} onClose={onClose} />

            <div className="space-y-4 overflow-y-auto px-6 py-5">
                {error && <p className="rounded-xl bg-red-50/90 dark:bg-bad-soft p-3 text-sm text-red-700 dark:text-bad-text">{error}</p>}

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
                    <SelectMenu value={jabatan} onChange={setJabatan} options={jabatanSelectOptions} searchable />
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

            <ModalFooter>
                <button
                    onClick={onClose}
                    className="rounded-xl px-4 py-2 text-sm font-medium text-gray-700 dark:text-ink-2 hover:bg-gray-100 dark:hover:bg-panel-hover"
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
            </ModalFooter>
        </Modal>
    );
}

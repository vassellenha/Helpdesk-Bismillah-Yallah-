import { useEffect, useMemo, useRef, useState } from 'react';
import RoleManagerModal from './RoleManagerModal';
import AddRoleWizard from './AddRoleWizard';
import AddUserModal from './AddUserModal';
import ManageUserModal from './ManageUserModal';
import ExportUsersModal from './ExportUsersModal';
import RowActionMenu from '../RowActionMenu';
import SelectMenu from '../SelectMenu';
import { apiFetch, uploadFile } from '../../lib/api';
import { t as trans } from '../../lib/i18n';

const ICON_EDIT = 'M12 20h9 M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z';
const ICON_DEACTIVATE = 'M12 2v10 M18.4 5.6a8 8 0 1 1-12.8 0';
const ICON_ACTIVATE = 'M9 12l2 2 4-5 M21 12a9 9 0 1 1-9-9';
// Perisai, bukan roda gigi ⚙ (emoji mentah sebelumnya) — Kelola Role
// mengatur hak akses/izin, jadi ikon keamanan lebih tepat secara makna
// daripada ikon "pengaturan umum", dan gaya garisnya kini konsisten dengan
// ikon lain di toolbar ini (SVG stroke 2px, bukan glyph emoji).
const ICON_ROLES = 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z';
const ICON_EXPORT = 'M12 3v10 M7 9l5 5 5-5 M4 19h16';
// Kebalikan Export secara visual (panah naik, bukan turun) — bukan sekadar
// selera: "Import" berarti berkas masuk KE aplikasi, jadi arahnya harus
// terbaca berlawanan dengan "Export" (berkas keluar) di ikon yang sama-sama
// duduk berdampingan di toolbar ini.
const ICON_IMPORT = 'M12 13V3 M7 8l5-5 5 5 M4 19h16';

// Language-independent filter sentinels. A translated label here would be
// compared against real status/role/unit values and match nothing.
const ALL_STATUS = '__all_status';
const ALL_ROLE = '__all_role';
const ALL_UNIT = '__all_unit';

export default function UserManagementConsole({ users: initialUsers, usersMeta, userStats, listUrl, roles: initialRoles, permissionModules, permissionActions, unitOrganisasi, jabatanOptions, exportUrl, filterOptionsUrl, importUrl, lastSyncAt: initialLastSyncAt = null }) {
    const [users, setUsers] = useState(initialUsers);
    const [meta, setMeta] = useState(usersMeta);
    const [stats, setStats] = useState(userStats);
    const [lastSyncAt, setLastSyncAt] = useState(initialLastSyncAt);
    const [loading, setLoading] = useState(false);
    const [roles, setRoles] = useState(initialRoles);
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState(ALL_STATUS);
    const [roleFilter, setRoleFilter] = useState(ALL_ROLE);
    const [unitFilter, setUnitFilter] = useState(ALL_UNIT);
    const [page, setPage] = useState(1);
    const [menu, setMenu] = useState(null); // { user, top, left }
    const [modal, setModal] = useState(null); // 'role' | 'addRole' | 'addUser' | { type: 'manageUser', user }
    const [error, setError] = useState('');
    const [notice, setNotice] = useState('');
    const [syncing, setSyncing] = useState(false);
    const [syncResult, setSyncResult] = useState(null);
    // 'api' | 'csv' — which action produced syncResult, so the banner can
    // say "Sinkronisasi" or "Impor CSV" instead of always claiming the API.
    const [syncKind, setSyncKind] = useState('api');
    const [importing, setImporting] = useState(false);
    const importInputRef = useRef(null);

    function openMenu(e, user) {
        if (menu?.user.id === user.id) {
            setMenu(null);
            return;
        }
        setMenu({ user, anchorEl: e.currentTarget });
    }

    // Diambil dari daftar role, bukan diturunkan dari `users`. Sejak `users`
    // hanya berisi satu halaman, menurunkannya dari sana akan membuat pilihan
    // filter berubah-ubah tiap pindah halaman — dan role yang kebetulan tidak
    // ada di halaman 1 hilang dari daftar filter justru saat dibutuhkan.
    const roleNames = useMemo(() => roles.map((r) => r.name), [roles]);

    const statusOptions = useMemo(() => [[ALL_STATUS, trans('admin.users.all_status')], ['Aktif', trans('admin.common.active')], ['Nonaktif', trans('admin.common.inactive')]].map(([value, label]) => ({ value, label })), []);
    const roleOptions = useMemo(() => [{ value: ALL_ROLE, label: trans('admin.users.all_role') }, ...roleNames.map((r) => ({ value: r, label: r }))], [roleNames]);
    const unitOptions = useMemo(() => [{ value: ALL_UNIT, label: trans('admin.users.all_unit') }, ...unitOrganisasi.map((u) => ({ value: u, label: u }))], [unitOrganisasi]);

    function queryString(toPage) {
        const params = new URLSearchParams();
        if (search.trim() !== '') params.set('search', search.trim());
        if (statusFilter !== ALL_STATUS) params.set('status', statusFilter);
        if (roleFilter !== ALL_ROLE) params.set('role', roleFilter);
        if (unitFilter !== ALL_UNIT) params.set('unit', unitFilter);
        if (toPage > 1) params.set('page', String(toPage));
        return params.toString();
    }

    // Filter dan paginasi dikerjakan server. Daftar lengkapnya tidak pernah ada
    // di browser — dengan 3.847 pegawai, memuatnya sekali saja sudah beberapa
    // megabyte JSON tiap halaman dibuka.
    //
    // Dua hal yang dijaga di sini:
    //   - Ketikan di kotak cari ditunda 300 ms, supaya "budi" tidak jadi empat
    //     permintaan berturut-turut.
    //   - Setiap permintaan diberi nomor urut, dan hanya yang TERBARU yang boleh
    //     menulis ke state. Tanpa itu, respons lambat dari kata kunci lama bisa
    //     datang belakangan dan menimpa hasil pencarian yang sudah benar.
    const requestSeq = useRef(0);
    const firstRender = useRef(true);

    async function loadUsers(toPage) {
        const seq = ++requestSeq.current;
        setLoading(true);
        try {
            const qs = queryString(toPage);
            const res = await apiFetch(`${listUrl}${qs ? `?${qs}` : ''}`);
            if (seq !== requestSeq.current) return;
            setUsers(res.users);
            setMeta(res.meta);
            setStats(res.stats);
        } catch (e) {
            if (seq === requestSeq.current) setError(e.message || 'Gagal memuat daftar pengguna.');
        } finally {
            if (seq === requestSeq.current) setLoading(false);
        }
    }

    useEffect(() => {
        // Halaman pertama sudah dirender server; menariknya lagi saat mount
        // hanya menggandakan query yang sama.
        if (firstRender.current) {
            firstRender.current = false;
            return;
        }

        const timer = setTimeout(() => loadUsers(page), 300);

        return () => clearTimeout(timer);
    }, [search, statusFilter, roleFilter, unitFilter, page, listUrl]);

    // Mengubah filter harus mengembalikan ke halaman 1: bertahan di halaman 7
    // setelah hasilnya menyusut jadi 2 halaman memberi tabel kosong yang terbaca
    // seperti "tidak ada hasil", padahal hasilnya ada di halaman 1.
    function changeFilter(setter) {
        return (value) => {
            setter(value);
            setPage(1);
        };
    }

    async function toggleUserStatus(id) {
        setMenu(null);
        setError('');
        setNotice('');
        try {
            const updated = await apiFetch(`/admin/users/${id}/toggle`, { method: 'POST' });
            setUsers((prev) => prev.map((u) => (u.id === id ? updated : u)));

            // Enabling access on someone the company API reports as no longer
            // employed succeeds but changes nothing visible — say so, otherwise
            // the row just stays "Nonaktif" and the click looks broken.
            if (updated.helpdesk_access === 'enabled' && updated.status === 'Nonaktif') {
                setNotice(
                    `Akses helpdesk ${updated.name} sudah diaktifkan, tetapi akunnya tetap nonaktif karena ${updated.status_reason.toLowerCase()}. ` +
                    'Status kepegawaian hanya bisa berubah dari API perusahaan.'
                );
            }
        } catch (e) {
            setError(e.message || trans('admin.users.status_failed'));
        }
    }

    async function syncEmployees() {
        setError('');
        setSyncResult(null);
        setSyncing(true);
        try {
            const { summary, users: fresh, meta: freshMeta, stats: freshStats, lastSyncAt: freshSyncAt } = await apiFetch('/admin/users/sync', { method: 'POST' });
            setUsers(fresh);
            setMeta(freshMeta);
            setStats(freshStats);
            setSyncKind('api');
            setSyncResult(summary);
            setLastSyncAt(freshSyncAt);
        } catch (e) {
            setError(e.message || trans('admin.users.sync_failed'));
        } finally {
            setSyncing(false);
        }
    }

    function pickImportFile() {
        importInputRef.current?.click();
    }

    async function importCsv(e) {
        const file = e.target.files?.[0];
        // Cleared regardless of what happens next — otherwise picking the
        // exact same file twice in a row (e.g. after fixing it) never fires
        // onChange the second time, since the input's value never changed.
        e.target.value = '';
        if (!file) return;

        setError('');
        setSyncResult(null);
        setImporting(true);
        try {
            const { summary, users: fresh, meta: freshMeta, stats: freshStats, lastSyncAt: freshSyncAt } = await uploadFile(importUrl, file);
            setUsers(fresh);
            setMeta(freshMeta);
            setStats(freshStats);
            setSyncKind('csv');
            setSyncResult(summary);
            setLastSyncAt(freshSyncAt);
        } catch (err) {
            setError(err.message || trans('admin.users.import_failed'));
        } finally {
            setImporting(false);
        }
    }

    function saveUser(updated) {
        setUsers((prev) => prev.map((u) => (u.id === updated.id ? updated : u)));
        setModal(null);
    }

    function addUser() {
        setModal(null);
        // Tidak lagi menyisipkan baris baru ke awal daftar. Halaman ini terurut
        // menurut nama dan hanya memuat 25 baris, jadi menempelkannya di atas
        // menampilkan orang itu di posisi yang salah — dan mendorong satu orang
        // lain keluar dari halaman tanpa alasan. Muat ulang halaman 1 saja;
        // urutan yang benar datang dari server.
        refresh();
    }

    // Kembali ke halaman 1 dan muat ulang. Kalau sudah di halaman 1, setPage
    // tidak mengubah apa pun sehingga useEffect diam — karena itu pemuatannya
    // dipanggil langsung. Kalau tidak, useEffect ikut jalan dan permintaannya
    // menang lewat nomor urut; satu permintaan berlebih pada aksi sesekali
    // seperti ini lebih murah daripada state tambahan untuk mencegahnya.
    function refresh() {
        setPage(1);
        loadUsers(1);
    }

    function upsertRole(saved) {
        setRoles((prev) => (prev.some((r) => r.id === saved.id) ? prev.map((r) => (r.id === saved.id ? saved : r)) : [...prev, saved]));
    }

    return (
        <div>
            <div className="mb-4 flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                <div>
                    <h1 className="text-3xl font-extrabold text-gray-900 dark:text-ink-1">{trans('admin.users.title')}</h1>
                    <p className="mt-1 text-sm text-gray-500 dark:text-ink-2">{trans('admin.users.subtitle')}</p>
                </div>
                {/* Di bawah xl, toolbar turun jadi barisnya sendiri di bawah judul.
                    Layar sempit pakai grid dua kolom supaya tombolnya rata —
                    flex-wrap biasa membuat lima tombol berlebar-beda jatuh
                    berundak ("acak" di MacBook 13"). Teks "Terakhir sync" ditarik
                    ke barisnya sendiri di bawah tombol: sebagai item flex di
                    antara tombol, lebarnya yang berubah-ubah ikut menggeser titik
                    bungkus tombol lain. */}
                <div className="flex flex-col gap-1.5 xl:items-end">
                    <div className="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap xl:justify-end">
                        <ToolbarButton onClick={syncEmployees} disabled={syncing} title={trans('admin.users.sync_title')}>
                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                strokeWidth="2"
                                strokeLinecap="round"
                                className={`h-4 w-4 ${syncing ? 'animate-spin' : ''}`}
                                aria-hidden="true"
                            >
                                <path d="M21 12a9 9 0 1 1-2.64-6.36" />
                                <path d="M21 3v6h-6" />
                            </svg>
                            {syncing ? trans('admin.users.syncing') : trans('admin.users.sync')}
                        </ToolbarButton>
                        <ToolbarButton onClick={() => setModal('role')}>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4" aria-hidden="true">
                                <path d={ICON_ROLES} />
                            </svg>
                            Kelola Role
                        </ToolbarButton>
                        <ToolbarButton onClick={() => setModal('export')}>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4" aria-hidden="true">
                                <path d={ICON_EXPORT} />
                            </svg>
                            {trans('admin.users.export')}
                        </ToolbarButton>
                        {importUrl && (
                            <>
                                <input ref={importInputRef} type="file" accept=".csv,text/csv" className="hidden" onChange={importCsv} />
                                <ToolbarButton onClick={pickImportFile} disabled={importing} title={trans('admin.users.import_title')}>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4" aria-hidden="true">
                                        <path d={ICON_IMPORT} />
                                    </svg>
                                    {importing ? trans('admin.users.importing') : trans('admin.users.import')}
                                </ToolbarButton>
                            </>
                        )}
                        <button
                            onClick={() => setModal('addUser')}
                            className={`flex items-center justify-center rounded-lg bg-blue-700 dark:bg-blue-500 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 dark:hover:bg-blue-400 ${importUrl ? 'col-span-2 sm:col-span-1' : ''}`}
                        >
                            {trans('admin.users.add_user')}
                        </button>
                    </div>
                    {lastSyncAt && (
                        <span className="px-0.5 text-xs text-gray-400 dark:text-ink-3">
                            {trans('admin.users.last_sync', { at: lastSyncAt })}
                        </span>
                    )}
                </div>
            </div>

            {error && <p className="mb-4 rounded-lg bg-red-50 dark:bg-bad-soft p-3 text-sm text-red-700 dark:text-bad-text">{error}</p>}

            {notice && (
                <div className="mb-4 flex items-start justify-between gap-3 rounded-lg bg-amber-50 dark:bg-warn-soft p-3 text-sm text-amber-800 dark:text-warn-text">
                    <p>{notice}</p>
                    <button
                        onClick={() => setNotice('')}
                        className="shrink-0 rounded p-0.5 text-amber-600 dark:text-warn-text hover:bg-amber-100 dark:hover:bg-panel-hover"
                        aria-label={trans('admin.common.close')}
                    >
                        ✕
                    </button>
                </div>
            )}

            {syncResult && (
                <div className="mb-4 rounded-lg bg-emerald-50 dark:bg-ok-soft p-3 text-sm text-emerald-800 dark:text-ok-text">
                    <div className="flex items-start justify-between gap-3">
                        <p>
                            <span className="font-semibold">{trans(syncKind === 'csv' ? 'admin.users.import_done' : 'admin.users.sync_done')}</span>{' '}
                            {syncResult.fetched} data diterima — {syncResult.created} dibuat, {syncResult.updated} diperbarui,{' '}
                            {syncResult.unchanged} tidak berubah
                            {syncResult.deactivated > 0 && <>, {syncResult.deactivated} dinonaktifkan</>}
                            {syncResult.skipped.length > 0 && <>, {syncResult.skipped.length} dilewati</>}.
                        </p>
                        <button
                            onClick={() => setSyncResult(null)}
                            className="shrink-0 rounded p-0.5 text-emerald-600 dark:text-ok-text hover:bg-emerald-100 dark:hover:bg-panel-hover"
                            aria-label={trans('admin.common.close')}
                        >
                            ✕
                        </button>
                    </div>
                    {syncResult.changes.length > 0 && (
                        <ul className="mt-2 list-inside list-disc space-y-0.5 text-xs">
                            {syncResult.changes.map((c) => (
                                <li key={c.name}>
                                    <span className="font-medium">{c.name}</span> — {c.fields.join(', ')}
                                </li>
                            ))}
                        </ul>
                    )}
                    {syncResult.skipped.length > 0 && (
                        <ul className="mt-2 list-inside list-disc space-y-0.5 text-xs">
                            {syncResult.skipped.map((reason) => (
                                <li key={reason}>{reason}</li>
                            ))}
                        </ul>
                    )}
                    {syncResult.kept_empty > 0 && (
                        <p className="mt-2 text-xs opacity-80">
                            {trans('admin.users.kept_empty_note', { count: syncResult.kept_empty })}
                        </p>
                    )}
                    {syncResult.kept_admin_override > 0 && (
                        <p className="mt-2 text-xs opacity-80">
                            {syncResult.kept_admin_override} field dipertahankan karena pernah diedit manual lewat Edit
                            Pengguna — sync tidak akan menimpanya lagi selama field itu masih ditandai sebagai perubahan Admin.
                        </p>
                    )}
                    {(syncResult.not_in_source ?? []).length > 0 && (
                        <p className="mt-2 text-xs opacity-80">
                            Tidak ada di data API, jadi dibiarkan apa adanya:{' '}
                            <span className="font-medium">{syncResult.not_in_source.join(', ')}</span>.
                            Akun yang dibuat manual lewat Admin tidak ikut disinkronkan.
                        </p>
                    )}
                    {(syncResult.key_mismatch ?? []).length > 0 && (
                        <div className="mt-2 rounded-lg bg-amber-50 dark:bg-warn-soft p-2.5 text-xs text-amber-800 dark:text-warn-text">
                            <p className="font-semibold">
                                {syncResult.key_mismatch.length} pegawai NPP-nya berbeda antara API dan helpdesk.
                            </p>
                            <ul className="mt-1 list-inside list-disc space-y-0.5">
                                {syncResult.key_mismatch.map((m) => <li key={m}>{m}</li>)}
                            </ul>
                            <p className="mt-1.5">
                                Datanya tetap tersinkron lewat email, tapi NPP-nya sengaja tidak diubah — NPP adalah kunci
                                identitas, jadi perbaikannya harus diputuskan manusia.
                            </p>
                        </div>
                    )}
                    <p className="mt-2 text-xs opacity-80">Tercatat di Audit Trail — modul “Integrasi”.</p>
                </div>
            )}

            <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {/* Angka dari COUNT di database, bukan dari daftar di layar —
                    yang di layar cuma satu halaman berisi 25 baris. */}
                <Stat label={trans('admin.users.stat_total_user')} value={stats.total} color="text-blue-600 dark:text-accent-text" bg="bg-blue-50 dark:bg-accent-soft" />
                <Stat label={trans('admin.users.stat_active')} value={stats.active} color="text-emerald-600 dark:text-ok-text" bg="bg-emerald-50 dark:bg-ok-soft" />
                <Stat label={trans('admin.users.stat_inactive')} value={stats.inactive} color="text-gray-500 dark:text-ink-2" bg="bg-gray-100 dark:bg-panel-3" />
                <Stat label={trans('admin.users.stat_total_role')} value={roles.length} color="text-amber-600 dark:text-warn-text" bg="bg-amber-50 dark:bg-warn-soft" />
            </div>

            <div className="rounded-xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 shadow-sm">
                <div className="flex flex-col gap-3 border-b border-gray-100 dark:border-edge p-4 lg:flex-row lg:items-center lg:justify-between">
                    <input
                        value={search}
                        onChange={(e) => { setSearch(e.target.value); setPage(1); }}
                        placeholder={trans('admin.users.search')}
                        className="w-full max-w-sm rounded-lg border border-gray-200 dark:border-edge-strong px-3 py-2 text-sm focus:border-blue-400 focus:outline-none"
                    />
                    <div className="flex flex-wrap items-center gap-2">
                        <SelectMenu value={statusFilter} onChange={changeFilter(setStatusFilter)} options={statusOptions} />
                        <SelectMenu value={roleFilter} onChange={changeFilter(setRoleFilter)} options={roleOptions} />
                        <SelectMenu value={unitFilter} onChange={changeFilter(setUnitFilter)} options={unitOptions} />
                        <button
                            onClick={() => { setSearch(''); setStatusFilter(ALL_STATUS); setRoleFilter(ALL_ROLE); setUnitFilter(ALL_UNIT); setPage(1); }}
                            className="text-sm font-medium text-blue-700 dark:text-accent-text hover:text-blue-800 dark:hover:text-blue-300"
                        >
                            Reset Filter
                        </button>
                    </div>
                </div>
                <p className="px-4 pt-3 text-sm text-gray-400 dark:text-ink-3">
                    {meta.total === 0
                        ? trans('admin.users.showing', { shown: 0, total: 0 })
                        : `Menampilkan ${meta.from}–${meta.to} dari ${meta.total} pengguna`}
                    {loading && <span className="ml-2 text-gray-300 dark:text-ink-3">· memuat…</span>}
                </p>

                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-100 dark:divide-transparent text-sm">
                        <thead>
                            <tr className="text-left text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-ink-3">
                                <th className="px-4 py-3">{trans('admin.users.col_name')}</th>
                                <th className="px-4 py-3">{trans('admin.users.col_nip')}</th>
                                <th className="px-4 py-3">{trans('admin.users.col_contact')}</th>
                                <th className="px-4 py-3">{trans('admin.users.col_unit')}</th>
                                <th className="px-4 py-3">{trans('admin.users.col_jabatan')}</th>
                                <th className="px-4 py-3">{trans('admin.users.col_role')}</th>
                                <th className="px-4 py-3">{trans('admin.common.status')}</th>
                                <th className="px-4 py-3">{trans('admin.users.col_last_login')}</th>
                                <th className="px-4 py-3 text-right">{trans('admin.common.action')}</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50 dark:divide-transparent">
                            {users.map((u) => (
                                <tr key={u.id} className="hover:bg-gray-50 dark:hover:bg-panel-hover dark:even:bg-white/[0.03]">
                                    <td className="px-4 py-3 font-semibold text-gray-900 dark:text-ink-1">{u.name}</td>
                                    <td className="px-4 py-3 text-gray-600 dark:text-ink-2">
                                        <span>{u.nip || '—'}</span>
                                        {/* Asal-usul akun. Sesudah sinkronisasi pertama, 3.847 pegawai
                                            sungguhan berbaur dengan sisa akun seed, dan tanpa penanda ini
                                            tidak ada cara membedakannya saat bersih-bersih. Dibaca dari
                                            kolom synced_at, bukan ditebak dari bentuk NPP — satu NPP asli
                                            ternyata murni angka, persis seperti NIP hasil seed. */}
                                        {u.from_directory ? (
                                            <span
                                                title={`Dari direktori pegawai ADHI · disinkronkan ${u.synced_at}`}
                                                className="ml-2 rounded px-1.5 py-0.5 align-middle text-[10px] font-semibold uppercase tracking-wide text-emerald-700 ring-1 ring-emerald-200 dark:text-emerald-300 dark:ring-emerald-500/40"
                                            >
                                                Direktori
                                            </span>
                                        ) : (
                                            <span
                                                title="Tidak ada di direktori pegawai — akun lokal, data seed, atau dibuat manual oleh Admin"
                                                className="ml-2 rounded px-1.5 py-0.5 align-middle text-[10px] font-semibold uppercase tracking-wide text-amber-700 ring-1 ring-amber-200 dark:text-amber-300 dark:ring-amber-500/40"
                                            >
                                                Lokal
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <p className="text-gray-700 dark:text-ink-2">{u.email}</p>
                                        <p className="text-xs text-gray-400 dark:text-ink-3">{u.phone}</p>
                                    </td>
                                    <td className="px-4 py-3 text-gray-600 dark:text-ink-2">{u.unit}</td>
                                    <td className="px-4 py-3 text-gray-600 dark:text-ink-2">{u.jabatan}</td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-wrap gap-1">
                                            {u.roles.map((r) => (
                                                <span key={r} className="rounded-full bg-blue-50 dark:bg-accent-soft px-2 py-0.5 text-xs font-medium text-blue-700 dark:text-accent-text">{r}</span>
                                            ))}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ${u.status === 'Aktif' ? 'bg-emerald-50 dark:bg-ok-soft text-emerald-700 dark:text-ok-text' : 'bg-gray-100 dark:bg-panel-3 text-gray-500 dark:text-ink-2'}`}>
                                            <span className={`h-1.5 w-1.5 rounded-full ${u.status === 'Aktif' ? 'bg-emerald-500' : 'bg-gray-400'}`} />
                                            {u.status === 'Aktif' ? trans('admin.common.active') : trans('admin.common.inactive')}
                                        </span>
                                        {u.status_reason && (
                                            <p className="mt-1 text-xs text-gray-400 dark:text-ink-3">{u.status_reason}</p>
                                        )}
                                        {/* Akun bisa nonaktif SEMENTARA saklar milik Admin
                                            masih menyala — isActive() menuntut kedua kolom
                                            aktif. Tanpa baris ini, barisnya cuma bilang
                                            "Nonaktif" lalu menunya menawarkan "Nonaktifkan",
                                            dan itu terbaca seperti bug padahal keduanya
                                            menunjuk saklar yang berbeda. */}
                                        {u.status === 'Nonaktif' && u.helpdesk_access === 'enabled' && (
                                            <p className="mt-0.5 text-xs font-medium text-amber-600 dark:text-warn-text">
                                                {trans('admin.users.access_still_on')}
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-gray-500 dark:text-ink-2">{u.last_login}</td>
                                    <td className="px-4 py-3 text-right">
                                        <button
                                            onClick={(e) => openMenu(e, u)}
                                            className="rounded-full border border-gray-200 dark:border-edge-strong px-2.5 py-1 text-gray-500 dark:text-ink-2 hover:bg-gray-100 dark:hover:bg-panel-hover"
                                        >
                                            •••
                                        </button>
                                    </td>
                                </tr>
                            ))}
                            {users.length === 0 && !loading && (
                                <tr>
                                    <td colSpan={9} className="px-4 py-10 text-center text-sm text-gray-400 dark:text-ink-3">Tidak ada pengguna yang cocok dengan filter.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {meta.last_page > 1 && (
                    <div className="flex items-center justify-between gap-3 border-t border-gray-100 dark:border-edge px-4 py-3">
                        <p className="text-sm text-gray-500 dark:text-ink-3">
                            Halaman {meta.current_page} dari {meta.last_page}
                        </p>
                        <div className="flex items-center gap-2">
                            <PageButton
                                disabled={meta.current_page <= 1 || loading}
                                onClick={() => setPage(meta.current_page - 1)}
                                label="Sebelumnya"
                            />
                            <PageButton
                                disabled={meta.current_page >= meta.last_page || loading}
                                onClick={() => setPage(meta.current_page + 1)}
                                label="Berikutnya"
                            />
                        </div>
                    </div>
                )}
            </div>

            {menu && (
                <RowActionMenu
                    anchorEl={menu.anchorEl}
                    onClose={() => setMenu(null)}
                    items={[
                        { label: trans('admin.users.edit'), icon: ICON_EDIT, onClick: () => setModal({ type: 'manageUser', user: menu.user }) },
                        {
                            // Toggles helpdesk access only — employment status comes
                            // from the company API and cannot be changed from here.
                            label: menu.user.helpdesk_access === 'enabled' ? trans('admin.users.disable_access') : trans('admin.users.enable_access'),
                            icon: menu.user.helpdesk_access === 'enabled' ? ICON_DEACTIVATE : ICON_ACTIVATE,
                            tone: menu.user.helpdesk_access === 'enabled' ? 'danger' : 'success',
                            note: menu.user.employment_status === 'Nonaktif'
                                ? trans('admin.users.account_locked_note')
                                : null,
                            divider: true,
                            onClick: () => toggleUserStatus(menu.user.id),
                        },
                    ]}
                />
            )}

            {modal === 'role' && (
                <RoleManagerModal roles={roles} onClose={() => setModal(null)} onAddRole={() => setModal('addRole')} onRoleSaved={upsertRole} />
            )}
            {modal === 'addRole' && (
                <AddRoleWizard
                    unitOrganisasi={unitOrganisasi}
                    modules={permissionModules}
                    actions={permissionActions}
                    onClose={() => setModal(null)}
                    onSave={(role) => { upsertRole(role); setModal('role'); }}
                />
            )}
            {modal === 'addUser' && <AddUserModal roles={roles} onClose={() => setModal(null)} onSave={addUser} />}
            {modal === 'export' && (
                <ExportUsersModal
                    onClose={() => setModal(null)}
                    listUrl={listUrl}
                    exportUrl={exportUrl}
                    filterOptionsUrl={filterOptionsUrl}
                    roles={roles}
                    unitOrganisasi={unitOrganisasi}
                    jabatanOptions={jabatanOptions}
                />
            )}
            {modal?.type === 'manageUser' && (
                <ManageUserModal user={modal.user} roles={roles} onClose={() => setModal(null)} onSave={saveUser} />
            )}
        </div>
    );
}

// Tombol sekunder di toolbar. Di layar sempit ia jadi sel grid selebar
// setengah baris — isinya dibuat rata tengah dan ikon tidak boleh menyusut
// (`[&>svg]:shrink-0`) kalau labelnya membungkus dua baris. Dari sm ke atas
// kembali jadi tombol biasa yang rata kiri.
function ToolbarButton({ onClick, disabled = false, title, children }) {
    return (
        <button
            type="button"
            onClick={onClick}
            disabled={disabled}
            title={title}
            className="flex items-center justify-center gap-2 rounded-lg border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 px-4 py-2 text-center text-sm font-medium text-gray-700 dark:text-ink-2 hover:bg-gray-50 dark:hover:bg-panel-hover disabled:cursor-not-allowed disabled:opacity-60 sm:justify-start sm:text-left [&>svg]:shrink-0"
        >
            {children}
        </button>
    );
}

function PageButton({ disabled, onClick, label }) {
    return (
        <button
            type="button"
            onClick={onClick}
            disabled={disabled}
            className="rounded-lg border border-gray-200 dark:border-edge-strong px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-ink-2 hover:bg-gray-50 dark:hover:bg-panel-hover disabled:cursor-not-allowed disabled:opacity-40"
        >
            {label}
        </button>
    );
}

function Stat({ label, value, color, bg }) {
    return (
        <div className="rounded-xl border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 p-4 shadow-sm">
            <span className={`flex h-8 w-8 items-center justify-center rounded-lg ${bg} ${color}`}>
                <span className="h-2 w-2 rounded-full bg-current" />
            </span>
            <p className="mt-2 text-xl font-bold text-gray-900 dark:text-ink-1">{value}</p>
            <p className="text-xs font-medium text-gray-400 dark:text-ink-3">{label}</p>
        </div>
    );
}

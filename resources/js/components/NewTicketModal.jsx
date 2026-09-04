import { useEffect, useMemo, useRef, useState } from 'react';
import Modal, { ModalFooter, ModalHeader } from './admin/Modal';
import SelectMenu from './SelectMenu';
import { apiFetch, uploadFile } from '../lib/api';
import { attachmentFailureNotice } from '../lib/attachmentUpload';
import { t as trans } from '../lib/i18n';
import { priorityGlyph, priorityList } from '../lib/priority';

/*
 * Permukaan seragam untuk SEMUA kolom isian di formulir tiket.
 *
 * Sebelumnya tiap kolom memilih latarnya sendiri: dropdown memakai
 * `bg-gray-50`, kotak teks dan textarea tidak memakai latar sama sekali
 * (jadi tembus ke panel modal), dan kolom yang belum bisa diisi memakai
 * `bg-gray-100` + teks kelabu. Di atas panel modal yang nyaris putih,
 * ketiganya membaur jadi bidang kelabu tanpa tepi — kolom yang sebenarnya
 * aktif pun terbaca seperti sudah dinonaktifkan.
 *
 * Yang memisahkan kolom dari latarnya sekarang GARIS TEPI-nya (gray-300,
 * bukan gray-200 yang nyaris tak terlihat), bukan warna isinya. Isinya putih
 * penuh supaya terang dan seragam; kolom yang belum bisa diisi tetap putih
 * dan hanya dibedakan oleh kursor serta teks penjelas di dalamnya, karena
 * placeholder-nya sudah menyebutkan syaratnya ("Pilih Layanan terlebih
 * dahulu").
 *
 * Di mode gelap isinya `panel-3`, BUKAN `panel-2`: badan modal sendiri sudah
 * `panel-2` (lihat `.liquid-glass-dense` gelap di app.css), jadi kolom
 * ber-`panel-2` akan lenyap ke dalam panelnya — persis keluhan yang sama,
 * hanya terbalik warnanya.
 */
const FIELD_SURFACE = 'border border-gray-300 dark:border-edge-strong bg-white dark:bg-panel-3';
const FIELD_FOCUS = 'outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 dark:focus:ring-blue-500/20';

const OTHER = '__other__';
const MAX_ATTACHMENT_BYTES = 30 * 1024 * 1024;
const MAX_ATTACHMENTS = 5;
const ACCEPTED_ATTACHMENT_TYPES = ['image/png', 'image/jpeg', 'application/pdf', 'video/mp4', 'video/quicktime', 'video/webm'];

function AttachmentPreviewModal({ file, onClose }) {
    const url = useMemo(() => URL.createObjectURL(file), [file]);
    useEffect(() => () => URL.revokeObjectURL(url), [url]);
    const isImage = file.type.startsWith('image/');
    const isVideo = file.type.startsWith('video/');

    return (
        <div className="fixed inset-0 z-[60] flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-sm" onClick={onClose}>
            <div className="liquid-glass-dense flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl shadow-xl" onClick={(e) => e.stopPropagation()}>
                <div className="flex items-center justify-between border-b border-gray-100 dark:border-edge px-5 py-3.5">
                    <p className="truncate pr-4 text-sm font-bold text-gray-900 dark:text-ink-1">{file.name}</p>
                    <button type="button" onClick={onClose} className="shrink-0 rounded-full p-1.5 text-gray-400 dark:text-ink-3 hover:bg-gray-100 dark:hover:bg-panel-hover hover:text-gray-600">✕</button>
                </div>
                <div className="flex-1 overflow-auto bg-gray-50 dark:bg-panel-3 p-4">
                    {isImage ? (
                        <img src={url} alt={file.name} className="mx-auto max-h-[70vh] rounded-lg object-contain" />
                    ) : isVideo ? (
                        <video src={url} controls className="mx-auto max-h-[70vh] w-full rounded-lg" />
                    ) : (
                        <iframe src={url} title={file.name} className="h-[70vh] w-full rounded-lg border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2" />
                    )}
                </div>
            </div>
        </div>
    );
}

function formatBytes(bytes) {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function SearchableSelect({ value, placeholder, disabled, options, onChange, searchPlaceholder = 'Cari…' }) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const ref = useRef(null);

    useEffect(() => {
        function onClickOutside(e) {
            if (ref.current && !ref.current.contains(e.target)) setOpen(false);
        }
        document.addEventListener('mousedown', onClickOutside);
        return () => document.removeEventListener('mousedown', onClickOutside);
    }, []);

    const filtered = options.filter((o) => o.label.toLowerCase().includes(query.toLowerCase()));
    const selected = options.find((o) => o.value === value);

    return (
        <div ref={ref} className="relative">
            <button
                type="button"
                disabled={disabled}
                onClick={() => setOpen((v) => !v)}
                className={`flex w-full items-center justify-between rounded-[10px] ${FIELD_SURFACE} ${FIELD_FOCUS} px-3 py-2.5 text-left text-[13px] ${disabled ? 'cursor-not-allowed text-gray-400 dark:text-ink-3' : 'text-gray-700 dark:text-ink-2 hover:border-gray-400 dark:hover:border-ink-3'}`}
            >
                <span className={selected ? 'text-gray-900 dark:text-ink-1' : ''}>{selected ? selected.label : placeholder}</span>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="shrink-0 text-gray-400 dark:text-ink-3"><path d="m6 9 6 6 6-6"/></svg>
            </button>

            {open && !disabled && (
                <div className="absolute left-0 top-[calc(100%+4px)] z-30 w-full overflow-hidden rounded-[10px] border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 shadow-lg">
                    <div className="border-b border-gray-100 dark:border-edge p-2">
                        <input
                            autoFocus
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder={searchPlaceholder}
                            className={`w-full rounded-md ${FIELD_SURFACE} ${FIELD_FOCUS} px-2.5 py-1.5 text-[13px] text-gray-900 dark:text-ink-1`}
                        />
                    </div>
                    <ul className="max-h-56 overflow-y-auto py-1">
                        {filtered.map((o) => (
                            <li key={o.value}>
                                <button
                                    type="button"
                                    onClick={() => {
                                        onChange(o.value);
                                        setOpen(false);
                                        setQuery('');
                                    }}
                                    className={`block w-full px-3 py-2 text-left text-[13px] hover:bg-blue-50 dark:hover:bg-panel-hover ${o.value === value ? 'bg-blue-50 dark:bg-accent-soft font-semibold text-blue-700 dark:text-accent-text' : 'text-gray-700 dark:text-ink-2'}`}
                                >
                                    {o.label}
                                </button>
                            </li>
                        ))}
                        {filtered.length === 0 && <li className="px-3 py-4 text-center text-xs text-gray-400 dark:text-ink-3">No matches.</li>}
                    </ul>
                </div>
            )}
        </div>
    );
}

const emptyForm = {
    serviceId: '',
    subcategoryId: '',
    subjectId: '',
    subjectText: '',
    issueCategoryId: '',
    description: '',
    priority: 'Low',
    approverId: '',
    approverName: '',
};

/**
 * Perataan tooltip per kolom, bukan selalu di tengah tombol.
 *
 * Tooltipnya (14rem) jauh lebih lebar dari tombolnya, dan badan modal memakai
 * overflow-hidden — kalau semuanya dipusatkan, tooltip kolom terluar terpotong
 * tepi modal. Grid-nya 2 kolom di ponsel dan 4 kolom dari sm ke atas, jadi
 * kolom mana yang "terluar" ikut berubah dan varian sm-nya harus menyesuaikan.
 * Kelasnya ditulis utuh karena Tailwind memindai string, bukan hasil rangkaian.
 */
const TIP_ALIGN = [
    'left-0',
    'right-0 sm:left-1/2 sm:right-auto sm:-translate-x-1/2',
    'left-0 sm:left-1/2 sm:-translate-x-1/2',
    'right-0',
];

/**
 * Menit -> "4 jam" / "2 hari", mengikuti bahasa aktif.
 *
 * Kunci tunggal dan jamak dipisah karena target respons Critical persis 60
 * menit, sehingga "1 hours" bukan kasus teoretis — itu yang tampil di layar.
 */
function humanMinutes(minutes) {
    if (!minutes || minutes <= 0) return '—';

    const [count, unit] = minutes % 1440 === 0 ? [minutes / 1440, 'day'] : [Math.round(minutes / 60), 'hour'];

    return trans(`requester.priority_help.${unit}${count === 1 ? '' : 's'}`, { count });
}

/**
 * Panduan singkat saat requester ragu memilih prioritas.
 *
 * Angka SLA-nya dibaca dari policy yang sedang aktif, bukan ditulis ulang di
 * sini — kalau Admin mengubah target di Konfigurasi SLA, tooltip ini ikut
 * berubah. Menyalin angkanya akan membuat form menjanjikan sesuatu yang tidak
 * lagi berlaku.
 *
 * Muncul saat hover DAN saat fokus keyboard: tombol prioritas bisa dijangkau
 * dengan Tab, jadi panduannya tidak boleh hanya untuk pengguna tetikus.
 */
function PriorityTip({ priority, policy, disabled, index }) {
    return (
        <div
            role="tooltip"
            className={`pointer-events-none absolute bottom-full z-20 mb-2 w-56 rounded-xl bg-gray-900 px-3 py-2.5 text-left text-[11.5px] leading-relaxed text-white opacity-0 shadow-lg transition-opacity duration-150 group-hover:opacity-100 group-focus-within:opacity-100 ${TIP_ALIGN[index % TIP_ALIGN.length]}`}
        >
            {/*
                Prioritas buatan Admin tidak punya entri terjemahan, dan
                mencetak kunci mentahnya ("requester.priority_help.Impossible")
                di dalam tooltip jelas salah. Judulnya jatuh ke nama prioritas
                itu sendiri; target SLA di bawahnya tetap menjelaskan artinya.
            */}
            <p className="font-semibold">{trans(`requester.priority_help.${priority}`, {}, priority)}</p>
            {disabled ? (
                <p className="mt-1.5 text-[11px] text-amber-300">{trans('requester.priority_help.inactive')}</p>
            ) : policy ? (
                <p className="mt-1.5 text-[11px] text-gray-300">
                    {trans('requester.priority_help.sla', {
                        response: humanMinutes(policy.response_time_minutes),
                        resolution: humanMinutes(policy.resolution_time_minutes),
                    })}
                </p>
            ) : null}
        </div>
    );
}

export default function NewTicketModal({
    catalogUrl = '/api/catalog',
    approversUrl = '/api/approvers',
    submitUrl = '/api/tickets',
    editTicket = null,
    editUrl = null,
    triggerLabel = 'Tiket Baru',
    evaDraft = null,
}) {
    const isEdit = !!editTicket;
    // A draft that an approver sent back for revision can only be re-submitted,
    // not re-saved as a fresh draft — so the "Save as Draft" action is hidden
    // in that case, leaving just Cancel and Submit Ticket.
    const isRevision = editTicket?.approvalNote?.decision === 'revision_requested' || !!editTicket?.supportReturnNote;
    // Datang dari EVA berarti karyawan sudah menyatakan maksudnya di chat.
    // Menyuruh mereka mengeklik "New Ticket" sekali lagi hanya untuk melihat
    // form yang sudah terisi adalah langkah kosong, jadi form dibuka langsung.
    const [open, setOpen] = useState(!!evaDraft && !editTicket);
    const [catalog, setCatalog] = useState(null);
    const [policies, setPolicies] = useState(null);
    const [approvers, setApprovers] = useState(null);
    const [approverQuery, setApproverQuery] = useState('');
    const [approverOpen, setApproverOpen] = useState(false);
    const [form, setForm] = useState(emptyForm);
    const [attachments, setAttachments] = useState([]);
    const [existingAttachments, setExistingAttachments] = useState([]);
    const [attachmentError, setAttachmentError] = useState('');
    const [previewFile, setPreviewFile] = useState(null);
    const [dragOver, setDragOver] = useState(false);
    const [error, setError] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [created, setCreated] = useState(null);
    // Lampiran yang ditolak server SESUDAH tiket tersimpan. Dulu ditelan diam-diam.
    const [attachmentNotice, setAttachmentNotice] = useState('');
    const approverRef = useRef(null);
    // Draf EVA sekali pakai. Kalau karyawan menutup form lalu membukanya lagi,
    // yang mereka maksud adalah tiket BARU — bukan mengulang draf yang tadi
    // sudah mereka batalkan.
    const evaDraftUsed = useRef(false);

    useEffect(() => {
        if (!open) return;
        setCatalog(null);
        setPolicies(null);
        setApprovers(null);
        setForm(emptyForm);
        setAttachments([]);
        setExistingAttachments(editTicket?.attachments ?? []);
        setAttachmentError('');
        setError('');
        setCreated(null);
        apiFetch(catalogUrl).then(setCatalog).catch(() => setCatalog({ services: [], subcategories: [], subjects: [], issueCategories: [] }));
        apiFetch('/api/sla-policies/active').then(setPolicies).catch(() => setPolicies([]));
        apiFetch(approversUrl).then(setApprovers).catch(() => setApprovers([]));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, catalogUrl, approversUrl]);

    // Prefills the form from the existing draft once the catalog has
    // loaded — resolving by name since the ticket only stores names, not
    // catalog IDs (there's no persisted catalog_subject_id on the ticket).
    useEffect(() => {
        if (!open || !isEdit || !catalog) return;

        const service = catalog.services.find((s) => s.name === editTicket.serviceName);
        const isOther = editTicket.subcategoryName === 'Other' || !service;

        if (isOther) {
            const issueCategory = catalog.issueCategories.find((c) => c.name === editTicket.issueCategory);
            set({
                serviceId: service ? String(service.id) : '',
                subcategoryId: OTHER,
                subjectText: editTicket.subjectName ?? '',
                issueCategoryId: issueCategory ? String(issueCategory.id) : '',
                description: editTicket.description ?? '',
                priority: editTicket.priority ?? 'Low',
                approverId: editTicket.approverId ? String(editTicket.approverId) : '',
                approverName: editTicket.approverName ?? '',
            });
            return;
        }

        const subcategory = catalog.subcategories.find((sc) => String(sc.service_id) === String(service.id) && sc.name === editTicket.subcategoryName);
        const subject = subcategory && catalog.subjects.find((s) => String(s.subcategory_id) === String(subcategory.id) && s.name === editTicket.subjectName);

        set({
            serviceId: String(service.id),
            subcategoryId: subcategory ? String(subcategory.id) : '',
            subjectId: subject ? String(subject.id) : '',
            description: editTicket.description ?? '',
            priority: editTicket.priority ?? 'Low',
            approverId: editTicket.approverId ? String(editTicket.approverId) : '',
            approverName: editTicket.approverName ?? '',
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, isEdit, catalog]);

    // Mengisi form dari draf yang dititipkan EVA, setelah katalog termuat.
    //
    // Subject dicari lewat ID, bukan lewat nama seperti alur Edit Draft di
    // atas. EVA memang membawa subject_id katalog, dan nama subject tidak unik
    // — ada "Reset Password" di bawah SAP dan "Reset Password" lain di bawah
    // SILO. Mencocokkan lewat nama berarti tiketnya bisa mendarat di tim yang
    // salah tanpa ada yang menyadarinya.
    //
    // Prioritas dan approver SENGAJA tidak diisi: keduanya keputusan karyawan,
    // dan EVA tidak punya dasar untuk menebaknya.
    useEffect(() => {
        if (!open || isEdit || !catalog || !evaDraft || evaDraftUsed.current) return;
        evaDraftUsed.current = true;

        const subject = evaDraft.subject
            && catalog.subjects.find((s) => String(s.id) === String(evaDraft.subject.subject_id));
        const subcategory = subject
            && catalog.subcategories.find((sc) => String(sc.id) === String(subject.subcategory_id));

        // Jalur "Lainnya" — EVA tahu APLIKASINYA, tidak tahu masalahnya.
        //
        // Terjadi saat karyawan menyebut nama aplikasi tanpa menjelaskan
        // kendalanya ("bagaimana melaporkan kendala di ELISA"). Yang bisa
        // dipastikan cuma Layanannya, karena namanya diketik apa adanya; jenis
        // masalahnya belum tentu ada di katalog. Membuka form pada sub category
        // "Lainnya" milik Layanan itu menyerahkan persis sebanyak yang EVA tahu
        // — dan tiketnya tetap sampai ke tim yang benar lewat TicketBroadcast.
        const service = !subcategory && evaDraft.service
            && catalog.services.find((s) => String(s.id) === String(evaDraft.service.service_id));

        // Issue Category tidak punya Subject untuk diturunkan di jalur ini, jadi
        // hanya diisikan bila seluruh subject Layanan itu memang berada di bawah
        // satu Issue Category yang sama — server sudah menyaringnya di
        // ServiceMatch::soleIssueCategory(). Selain itu, karyawan memilih sendiri.
        const issueCategory = service && evaDraft.service.issue_category
            && catalog.issueCategories.find((c) => c.name === evaDraft.service.issue_category);

        // Tebakan subject boleh saja meleset atau kosong — EVA hanya menebak
        // saat cukup yakin. Kalau begitu, deskripsinya tetap terisi dan
        // karyawan memilih sendiri kategorinya.
        //
        // subjectText SENGAJA dibiarkan kosong pada jalur "Lainnya": teks itu
        // menjadi JUDUL tiket, dan kalimat mentah penanya ("bagaimana saya bisa
        // melaporkan…") adalah judul yang buruk. Validasi form sudah menuntut
        // karyawan menuliskannya sendiri.
        set({
            description: evaDraft.description ?? '',
            ...(subcategory ? {
                serviceId: String(subcategory.service_id),
                subcategoryId: String(subcategory.id),
                subjectId: String(subject.id),
            } : service ? {
                serviceId: String(service.id),
                subcategoryId: OTHER,
                issueCategoryId: issueCategory ? String(issueCategory.id) : '',
            } : {}),
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, isEdit, catalog, evaDraft]);

    // A priority whose SLA policy the admin has deactivated can't be picked —
    // if the currently selected one just went inactive (or the form opened
    // defaulting to one that isn't active), fall back to a priority that still
    // has a live policy.
    //
    // Yang dipilih adalah yang PALING LONGGAR, bukan yang pertama di daftar.
    // Daftarnya terurut dari yang paling mendesak, jadi mengambil yang pertama
    // berarti tiket yang dibuka pengguna sudah tercentang di prioritas paling
    // genting sebelum ia menyentuh apa pun — persis kebalikan dari default
    // "Low" yang dipakai selama ini.
    useEffect(() => {
        if (!open || !policies) return;
        const activePriorityList = policies.map((p) => p.priority);
        if (activePriorityList.length > 0 && !activePriorityList.includes(form.priority)) {
            set({ priority: activePriorityList[activePriorityList.length - 1] });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, policies, form.priority]);

    useEffect(() => {
        function onClickOutside(e) {
            if (approverRef.current && !approverRef.current.contains(e.target)) setApproverOpen(false);
        }
        document.addEventListener('mousedown', onClickOutside);
        return () => document.removeEventListener('mousedown', onClickOutside);
    }, []);

    function set(patch) {
        setForm((f) => ({ ...f, ...patch }));
    }

    const serviceOptions = useMemo(
        () => (catalog?.services ?? []).map((s) => ({ value: String(s.id), label: s.name })),
        [catalog]
    );

    const subcategoryOptions = useMemo(() => {
        if (!catalog || !form.serviceId) return [];
        const options = catalog.subcategories
            .filter((sc) => String(sc.service_id) === form.serviceId)
            .map((sc) => ({ value: String(sc.id), label: sc.name }));
        return [...options, { value: OTHER, label: 'Lainnya' }];
    }, [catalog, form.serviceId]);

    const isOtherSubcategory = form.subcategoryId === OTHER;

    const subjectOptions = useMemo(() => {
        if (!catalog || !form.subcategoryId || isOtherSubcategory) return [];
        return catalog.subjects
            .filter((s) => String(s.subcategory_id) === form.subcategoryId)
            .map((s) => ({ value: String(s.id), label: s.name }));
    }, [catalog, form.subcategoryId, isOtherSubcategory]);

    const selectedSubject = useMemo(
        () => catalog?.subjects.find((s) => String(s.id) === form.subjectId) ?? null,
        [catalog, form.subjectId]
    );

    const requiresApproval = isOtherSubcategory ? false : !!selectedSubject?.requires_approval;
    const issueCategoryName = isOtherSubcategory
        ? catalog?.issueCategories.find((c) => String(c.id) === form.issueCategoryId)?.name ?? ''
        : selectedSubject?.issue_category ?? '';

    // The ticket "title" is derived from the chosen Subject (or the free-text
    // item for "Other") — there's no separate title field in this form.
    const selectedService = catalog?.services.find((s) => String(s.id) === form.serviceId);
    const derivedTitle =
        (isOtherSubcategory ? form.subjectText.trim() : selectedSubject?.name) ||
        (selectedService ? `${selectedService.name} request` : '');

    function selectService(serviceId) {
        set({ serviceId, subcategoryId: '', subjectId: '', subjectText: '', issueCategoryId: '', approverId: '', approverName: '' });
    }

    function selectSubcategory(subcategoryId) {
        set({ subcategoryId, subjectId: '', subjectText: '', issueCategoryId: '', approverId: '', approverName: '' });
    }

    function selectSubject(subjectId) {
        set({ subjectId, approverId: '', approverName: '' });
        setApproverQuery('');
    }

    function onAttachmentFiles(fileList) {
        setAttachmentError('');
        const files = Array.from(fileList ?? []);
        if (files.length === 0) return;

        setAttachments((current) => {
            const next = [...current];
            for (const file of files) {
                if (existingAttachments.length + next.length >= MAX_ATTACHMENTS) {
                    setAttachmentError(trans('requester.attachment_error.too_many', { count: MAX_ATTACHMENTS }));
                    break;
                }
                if (!ACCEPTED_ATTACHMENT_TYPES.includes(file.type)) {
                    setAttachmentError(trans('requester.attachment_error.bad_type'));
                    continue;
                }
                if (file.size > MAX_ATTACHMENT_BYTES) {
                    setAttachmentError(trans('requester.attachment_error.too_large'));
                    continue;
                }
                next.push(file);
            }
            return next;
        });
    }

    function removeAttachment(index) {
        setAttachmentError('');
        setAttachments((current) => current.filter((_, i) => i !== index));
    }

    // Already-persisted attachments (from a previously saved draft) live on
    // the server, not in this form's local state — removing one has to call
    // the same destroy endpoint the ticket detail page uses, not just drop
    // it from an in-memory list.
    async function removeExistingAttachment(attachment) {
        setAttachmentError('');
        try {
            await apiFetch(`/requester/tickets/${editTicket.id}/attachment/${attachment.id}`, { method: 'DELETE' });
            setExistingAttachments((current) => current.filter((a) => a.id !== attachment.id));
        } catch (e) {
            setAttachmentError(e.message || 'Gagal menghapus lampiran.');
        }
    }

    const totalAttachmentCount = existingAttachments.length + attachments.length;
    const filteredApprovers = (approvers ?? []).filter((a) => a.name.toLowerCase().includes(approverQuery.toLowerCase()));
    const activePriorities = useMemo(() => new Set((policies ?? []).map((p) => p.priority)), [policies]);

    /**
     * Prioritas yang digambar di layar.
     *
     * Diambil dari SLA Policy aktif, bukan dari daftar nama di dalam berkas
     * ini. Daftar itulah yang membuat prioritas baru buatan Admin tidak pernah
     * muncul: server sudah mengirimkannya, tapi loop-nya hanya menggambar
     * empat nama yang sudah ditulis lebih dulu.
     *
     * Selagi `policies` masih diambil, dipakai daftar yang dititipkan layout —
     * isinya sama, dan pemilihnya jadi tidak berkedip dari empat tombol
     * bawaan ke daftar yang sebenarnya.
     */
    const priorityChoices = useMemo(() => {
        if (policies && policies.length > 0) {
            return policies.map((p) => p.priority);
        }

        return priorityList().map((p) => p.name);
    }, [policies]);

    /**
     * Kolom wajib yang masih kosong, dipetakan ke pesannya.
     *
     * Objek kosong berarti formulir sudah lengkap. Dipakai untuk dua hal
     * sekaligus: memutuskan boleh-tidaknya mengirim, dan menggambar pesan di
     * bawah isian yang bersangkutan.
     */
    const missing = useMemo(() => {
        const result = {};

        if (form.serviceId === '') result.service = 'Layanan wajib dipilih.';
        if (form.subcategoryId === '') result.subcategory = 'Sub Kategori wajib dipilih.';

        if (isOtherSubcategory) {
            if (form.subjectText.trim() === '') result.subject = 'Jelaskan kebutuhan Anda terlebih dahulu.';
            if (form.issueCategoryId === '') result.issueCategory = 'Kategori Masalah wajib dipilih.';
        } else if (form.subjectId === '') {
            result.subject = 'Subjek wajib dipilih.';
        }

        if (requiresApproval && form.approverId === '') result.approver = 'Pilih approver yang akan menyetujui tiket ini.';

        return result;
    }, [form, isOtherSubcategory, requiresApproval]);

    const canSubmit = Object.keys(missing).length === 0;

    /**
     * Pesan baru muncul setelah pengguna benar-benar mencoba mengirim.
     *
     * Formulir yang baru dibuka sudah pasti kosong; menyalakan semua pesan
     * merah sejak detik pertama membuatnya terlihat seolah pengguna berbuat
     * salah padahal belum menyentuh apa pun.
     */
    const [showErrors, setShowErrors] = useState(false);
    const errors = showErrors ? missing : {};

    async function submit(isDraft) {
        setError('');

        // Draf memang boleh setengah jadi, tapi Layanan tetap dituntut — dari
        // situlah nomor tiketnya diturunkan.
        if (isDraft) {
            if (!form.serviceId) {
                setShowErrors(true);
                setError('Silakan pilih Layanan terlebih dahulu.');
                return;
            }
        } else if (!canSubmit) {
            setShowErrors(true);
            setError('Lengkapi kolom yang ditandai di bawah ini sebelum mengirim.');
            return;
        }

        setShowErrors(false);

        setSubmitting(true);
        try {
            const priorityPolicy = (policies ?? []).find((p) => p.priority === form.priority) ?? policies?.[0];
            const service = selectedService;
            const subcategory = isOtherSubcategory ? 'Other' : catalog?.subcategories.find((s) => String(s.id) === form.subcategoryId)?.name;

            const payload = {
                title: derivedTitle,
                category: issueCategoryName || null,
                sla_policy_id: priorityPolicy?.id ?? null,
                service_name: service?.name ?? null,
                service_id: form.serviceId ? Number(form.serviceId) : null,
                subcategory_name: subcategory ?? null,
                subject_name: isOtherSubcategory ? form.subjectText : selectedSubject?.name ?? null,
                issue_category: issueCategoryName || null,
                description: form.description || null,
                catalog_subject_id: isOtherSubcategory || !form.subjectId ? null : Number(form.subjectId),
                approver_id: requiresApproval && form.approverId ? Number(form.approverId) : null,
                requires_approval: requiresApproval,
                is_draft: isDraft,
            };

            let ticket = isEdit
                ? await apiFetch(editUrl, { method: 'PUT', body: JSON.stringify(payload) })
                : await apiFetch(submitUrl, { method: 'POST', body: JSON.stringify(payload) });

            const gagalUnggah = [];

            if (attachments.length > 0 && ticket.ticket_no) {
                for (const file of attachments) {
                    try {
                        await uploadFile(`/requester/tickets/${ticket.ticket_no}/attachment`, file);
                    } catch (e) {
                        /*
                         | Tiketnya sudah sah tersimpan, jadi kegagalan di sini
                         | tidak boleh menahan konfirmasi — tapi harus disebut.
                         | Ditelan diam-diam, pengguna pergi mengira buktinya
                         | ikut terkirim padahal tiketnya kosong.
                         */
                        gagalUnggah.push({ name: file.name, reason: e?.message || '' });
                    }
                }
            }

            setAttachmentNotice(attachmentFailureNotice(gagalUnggah));
            setCreated(ticket);
        } catch (e) {
            setError(e.message || 'Gagal menyimpan tiket.');
        } finally {
            setSubmitting(false);
        }
    }

    function close() {
        setOpen(false);
        // Formulir yang dibuka lagi harus mulai bersih; tanda merah sisa
        // percobaan sebelumnya akan menyambut pengguna tanpa sebab.
        setShowErrors(false);
        setError('');
        setAttachmentNotice('');
    }

    return (
        <>
            <button
                onClick={() => setOpen(true)}
                className="flex items-center gap-2 rounded-xl bg-blue-600 dark:bg-blue-500 px-4 py-2.5 text-[13px] font-bold text-white shadow-sm hover:bg-blue-700 dark:hover:bg-blue-400"
            >
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round">
                    {isEdit ? (
                        <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z" strokeLinejoin="round" />
                    ) : (
                        <><path d="M12 5v14" /><path d="M5 12h14" /></>
                    )}
                </svg>
                {triggerLabel}
            </button>

            {open && (
                <Modal onClose={close} maxWidth="max-w-2xl">
                    <ModalHeader title="Informasi Tiket" subtitle={isEdit ? 'Perbarui detail draf permintaan Anda.' : 'Isi detail permintaan Anda.'} onClose={close} />

                    <div className="space-y-4 overflow-y-auto px-6 py-5">
                        {created ? (
                            <div className="rounded-xl bg-emerald-50 dark:bg-ok-soft p-4 text-sm text-emerald-800 dark:text-ok-text">
                                <p className="font-semibold">
                                    {created.is_draft ? `Draft ${created.ticket_no} saved.` : `Ticket ${created.ticket_no} submitted.`}
                                </p>
                                <p className="mt-1">Status: <strong>{created.status}</strong></p>
                            </div>
                        ) : null}
                        {/* Syaratnya `created` saja, BUKAN `created && attachmentNotice`.
                            Dengan syarat lama, formnya cuma hilang kalau lampirannya
                            bermasalah — padahal itu kasus yang jarang. Pengiriman yang
                            mulus (tanpa lampiran, atau lampirannya berhasil) membuat
                            attachmentNotice kosong, cabangnya jatuh ke `else`, dan
                            seluruh form tetap terpampang di bawah pesan "tiket sudah
                            dibuat" — seolah kiriman tadi gagal dan harus diisi ulang. */}
                        {created ? (
                            attachmentNotice ? (
                                <div className="rounded-xl bg-amber-50 dark:bg-warn-soft p-4 text-sm text-amber-800 dark:text-warn-text">
                                    {attachmentNotice}
                                </div>
                            ) : null
                        ) : (
                            <>
                                {error && <p className="rounded-lg bg-red-50 dark:bg-bad-soft p-3 text-sm text-red-700 dark:text-bad-text">{error}</p>}

                                <Field label="Layanan" required error={errors.service}>
                                    <SearchableSelect
                                        value={form.serviceId}
                                        placeholder={catalog ? 'Pilih Layanan' : 'Memuat…'}
                                        disabled={!catalog}
                                        options={serviceOptions}
                                        onChange={selectService}
                                        searchPlaceholder="Cari layanan…"
                                    />
                                </Field>

                                <Field label="Sub Kategori" required error={errors.subcategory}>
                                    <SearchableSelect
                                        value={form.subcategoryId}
                                        placeholder={form.serviceId ? 'Pilih Sub Kategori' : 'Pilih Layanan terlebih dahulu'}
                                        disabled={!form.serviceId}
                                        options={subcategoryOptions}
                                        onChange={selectSubcategory}
                                        searchPlaceholder="Cari sub kategori…"
                                    />
                                </Field>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <Field label="Subjek" required error={errors.subject}>
                                        {isOtherSubcategory ? (
                                            <input
                                                value={form.subjectText}
                                                onChange={(e) => set({ subjectText: e.target.value })}
                                                placeholder="Jelaskan kebutuhan Anda"
                                                className={`w-full rounded-[10px] ${FIELD_SURFACE} ${FIELD_FOCUS} px-3.5 py-2.5 text-[13px] text-gray-900 dark:text-ink-1`}
                                            />
                                        ) : (
                                            <SearchableSelect
                                                value={form.subjectId}
                                                placeholder={form.subcategoryId ? 'Pilih Subjek' : 'Pilih Sub Kategori terlebih dahulu'}
                                                disabled={!form.subcategoryId}
                                                options={subjectOptions}
                                                onChange={selectSubject}
                                                searchPlaceholder="Cari subjek…"
                                            />
                                        )}
                                    </Field>
                                    <Field label="Kategori Masalah" required={isOtherSubcategory} error={errors.issueCategory}>
                                        {isOtherSubcategory ? (
                                            <SelectMenu
                                                value={form.issueCategoryId}
                                                onChange={(v) => set({ issueCategoryId: v })}
                                                options={[
                                                    { value: '', label: 'Pilih Kategori Masalah' },
                                                    ...(catalog?.issueCategories ?? []).map((c) => ({ value: String(c.id), label: c.name })),
                                                ]}
                                            />
                                        ) : (
                                            <div className={`flex w-full items-center rounded-[10px] ${FIELD_SURFACE} px-3.5 py-2.5 text-[13px] text-gray-500 dark:text-ink-2`}>
                                                {issueCategoryName || 'Pilih Subjek terlebih dahulu'}
                                            </div>
                                        )}
                                    </Field>
                                </div>

                                {requiresApproval && (
                                    <div className="rounded-2xl border border-blue-100 dark:border-edge-strong bg-blue-50 dark:bg-accent-soft p-4">
                                        <div className="flex items-center gap-2.5">
                                            <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-100 dark:bg-panel-2 text-blue-700 dark:text-accent-text">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Z"/><path d="M8.5 12l2.5 2.5 4.5-5"/></svg>
                                            </span>
                                            <span className="text-[13px] font-bold text-gray-900 dark:text-ink-1">Memerlukan Approval</span>
                                        </div>

                                        <p className="mb-1.5 mt-3 text-[13px] font-semibold text-gray-700 dark:text-ink-2">
                                            Minta approval kepada :
                                            <span aria-hidden="true" className="ml-0.5 text-red-600 dark:text-bad-text">*</span>
                                            <span className="sr-only"> (wajib)</span>
                                        </p>
                                        <div ref={approverRef} className="relative">
                                            {form.approverId ? (
                                                <div className="flex items-center justify-between rounded-[10px] border border-blue-300 dark:border-edge-strong bg-white dark:bg-panel-3 px-3.5 py-2.5 text-[13px]">
                                                    <span className="font-semibold text-blue-900 dark:text-accent-text">{form.approverName}</span>
                                                    <button
                                                        type="button"
                                                        onClick={() => set({ approverId: '', approverName: '' })}
                                                        className="text-xs font-semibold text-blue-600 dark:text-accent-text hover:text-blue-800 dark:hover:text-blue-300"
                                                    >
                                                        Ganti
                                                    </button>
                                                </div>
                                            ) : (
                                                <div className="relative">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" className="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 dark:text-ink-3"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                                                    <input
                                                        value={approverQuery}
                                                        onChange={(e) => setApproverQuery(e.target.value)}
                                                        onFocus={() => setApproverOpen(true)}
                                                        placeholder="Cari nama approver"
                                                        className={`w-full rounded-[10px] ${FIELD_SURFACE} ${FIELD_FOCUS} py-2.5 pl-9 pr-3.5 text-[13px] text-gray-900 dark:text-ink-1`}
                                                    />
                                                </div>
                                            )}
                                            {approverOpen && !form.approverId && (
                                                <ul className="absolute left-0 top-[calc(100%+4px)] z-30 max-h-48 w-full overflow-y-auto rounded-[10px] border border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 shadow-lg">
                                                    {approvers === null && <li className="px-3 py-3 text-xs text-gray-400 dark:text-ink-3">Memuat approver…</li>}
                                                    {approvers !== null && filteredApprovers.length === 0 && (
                                                        <li className="px-3 py-3 text-xs text-gray-400 dark:text-ink-3">Tidak ada approver yang cocok.</li>
                                                    )}
                                                    {filteredApprovers.map((a) => (
                                                        <li key={a.id}>
                                                            <button
                                                                type="button"
                                                                onClick={() => {
                                                                    set({ approverId: String(a.id), approverName: a.name });
                                                                    setApproverOpen(false);
                                                                }}
                                                                className="block w-full px-3.5 py-2.5 text-left hover:bg-blue-50 dark:hover:bg-panel-hover"
                                                            >
                                                                <span className="block text-[13px] font-semibold text-gray-900 dark:text-ink-1">{a.name}</span>
                                                                <span className="block text-[11px] text-gray-400 dark:text-ink-3">{a.jabatan}</span>
                                                            </button>
                                                        </li>
                                                    ))}
                                                </ul>
                                            )}
                                        </div>
                                        {errors.approver ? (
                                            <p role="alert" className="mt-1.5 text-[12px] font-semibold text-red-600 dark:text-bad-text">{errors.approver}</p>
                                        ) : (
                                            <p className="mt-1.5 text-[11px] text-gray-400 dark:text-ink-3">Ketik nama manager dituju baru dipilih.</p>
                                        )}
                                    </div>
                                )}

                                <Field label="Deskripsi Detail">
                                    <textarea
                                        rows={4}
                                        value={form.description}
                                        onChange={(e) => set({ description: e.target.value })}
                                        placeholder="Jelaskan masalah yang Anda hadapi secara detail…"
                                        className={`min-h-[96px] w-full resize-y rounded-xl ${FIELD_SURFACE} ${FIELD_FOCUS} px-3.5 py-3 text-[13px] text-gray-900 dark:text-ink-1`}
                                    />
                                </Field>

                                <Field label="Prioritas">
                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 rounded-2xl border border-gray-100 dark:border-edge bg-gray-50 dark:bg-panel-3 p-2.5 sm:grid-cols-4">
                                        {priorityChoices.map((name, i) => {
                                            const active = form.priority === name;
                                            const isActivePriority = !policies || activePriorities.has(name);
                                            const policy = (policies ?? []).find((x) => x.priority === name);
                                            return (
                                                <div key={name} className="group relative">
                                                    <button
                                                        type="button"
                                                        disabled={!isActivePriority}
                                                        onClick={() => set({ priority: name })}
                                                        className={`flex w-full flex-col items-center gap-1 rounded-xl border px-2 py-3 text-xs font-bold ${
                                                            !isActivePriority
                                                                ? 'cursor-not-allowed border-gray-100 dark:border-edge bg-gray-50 dark:bg-panel-3 text-gray-300'
                                                                : active
                                                                  ? 'border-blue-600 bg-blue-600 dark:bg-blue-500 text-white shadow-sm'
                                                                  : 'border-gray-200 dark:border-edge-strong bg-white dark:bg-panel-2 text-gray-600 dark:text-ink-2 hover:border-gray-300'
                                                        }`}
                                                    >
                                                        <span className="text-base font-extrabold leading-none">{priorityGlyph(name)}</span>
                                                        {name}
                                                    </button>
                                                    <PriorityTip priority={name} policy={policy} disabled={!isActivePriority} index={i} />
                                                </div>
                                            );
                                        })}
                                    </div>
                                </Field>

                                <Field label={`Attachment (Optional) · ${totalAttachmentCount}/${MAX_ATTACHMENTS}`}>
                                    <label
                                        onDragOver={(e) => { e.preventDefault(); setDragOver(true); }}
                                        onDragLeave={() => setDragOver(false)}
                                        onDrop={(e) => {
                                            e.preventDefault();
                                            setDragOver(false);
                                            onAttachmentFiles(e.dataTransfer.files);
                                        }}
                                        className={`flex flex-col items-center gap-1.5 rounded-2xl border-2 border-dashed px-4 py-7 text-center ${
                                            totalAttachmentCount >= MAX_ATTACHMENTS
                                                ? 'cursor-not-allowed border-gray-200 dark:border-edge-strong bg-gray-50 dark:bg-panel-3'
                                                : `cursor-pointer ${dragOver ? 'border-blue-500 bg-blue-50 dark:bg-accent-soft' : 'border-blue-300 dark:border-blue-500/40 bg-blue-50/60 dark:bg-accent-soft/40 hover:bg-blue-50 dark:hover:bg-accent-soft'}`
                                        }`}
                                    >
                                        <input
                                            type="file"
                                            accept=".png,.jpg,.jpeg,.pdf,.mp4,.mov,.webm"
                                            multiple
                                            disabled={totalAttachmentCount >= MAX_ATTACHMENTS}
                                            className="hidden"
                                            onChange={(e) => { onAttachmentFiles(e.target.files); e.target.value = ''; }}
                                        />
                                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="text-gray-700 dark:text-ink-2"><path d="M4 16.2A4.5 4.5 0 0 1 6.5 8a6 6 0 0 1 11.4 1.6A4 4 0 0 1 17.5 17H7"/><path d="M12 12v6"/><path d="m9 14.5 3-2.5 3 2.5"/></svg>
                                        <span className="text-[13px] font-bold text-gray-800 dark:text-ink-1">
                                            {totalAttachmentCount >= MAX_ATTACHMENTS ? 'Jumlah berkas sudah maksimal' : 'Klik untuk unggah atau seret berkas ke sini'}
                                        </span>
                                        <span className="text-[11px] text-gray-400 dark:text-ink-3">PNG, JPG, PDF, MP4, MOV, WEBM (Max. 30MB) · Maksimal {MAX_ATTACHMENTS} file</span>
                                    </label>
                                    {existingAttachments.length > 0 && (
                                        <div className="mt-2 space-y-1.5">
                                            {existingAttachments.map((a) => (
                                                <div key={`existing-${a.id}`} className="flex items-center justify-between gap-2 rounded-lg bg-gray-50 dark:bg-panel-3 px-3 py-2 text-xs text-gray-700 dark:text-ink-2">
                                                    <a
                                                        href={a.url}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        className="min-w-0 flex-1 truncate text-left hover:text-blue-700 hover:underline"
                                                        title="Buka lampiran"
                                                    >
                                                        {a.name}
                                                    </a>
                                                    <button type="button" onClick={() => removeExistingAttachment(a)} className="shrink-0 font-semibold text-red-600 dark:text-bad-text hover:text-red-800 dark:hover:text-red-300">Remove</button>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                    {attachments.length > 0 && (
                                        <div className="mt-2 space-y-1.5">
                                            {attachments.map((file, i) => (
                                                <div key={`${file.name}-${i}`} className="flex items-center justify-between gap-2 rounded-lg bg-gray-50 dark:bg-panel-3 px-3 py-2 text-xs text-gray-700 dark:text-ink-2">
                                                    <button
                                                        type="button"
                                                        onClick={() => setPreviewFile(file)}
                                                        className="min-w-0 flex-1 truncate text-left hover:text-blue-700 hover:underline"
                                                        title="Klik untuk pratinjau"
                                                    >
                                                        {file.name} · {formatBytes(file.size)}
                                                    </button>
                                                    <button type="button" onClick={() => removeAttachment(i)} className="shrink-0 font-semibold text-red-600 dark:text-bad-text hover:text-red-800 dark:hover:text-red-300">Remove</button>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                    {attachmentError && <p className="mt-1.5 text-xs text-red-600 dark:text-bad-text">{attachmentError}</p>}
                                </Field>
                            </>
                        )}
                    </div>

                    <ModalFooter>
                        {created ? (
                            <button
                                onClick={() => (isEdit ? window.location.reload() : close())}
                                className="rounded-full bg-blue-600 dark:bg-blue-500 px-6 py-2.5 text-[13px] font-bold text-white hover:bg-blue-700 dark:hover:bg-blue-400"
                            >
                                Close
                            </button>
                        ) : (
                            <>
                                <button onClick={close} className="rounded-full border border-gray-200 dark:border-edge-strong px-5 py-2.5 text-[13px] font-bold text-blue-600 dark:text-accent-text hover:bg-gray-50 dark:hover:bg-panel-hover dark:even:bg-white/[0.03]">Cancel</button>
                                {!isRevision && (
                                    <button
                                        onClick={() => submit(true)}
                                        disabled={submitting || !form.serviceId}
                                        className="rounded-full border border-gray-200 dark:border-edge-strong px-5 py-2.5 text-[13px] font-bold text-gray-700 dark:text-ink-2 hover:bg-gray-50 dark:hover:bg-panel-hover dark:even:bg-white/[0.03] disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        Save as Draft
                                    </button>
                                )}
                                {/*
                                    Tombolnya sengaja TIDAK dimatikan saat formulir belum lengkap.
                                    Tombol mati menahan pengiriman tanpa pernah mengatakan apa yang
                                    kurang; dibiarkan hidup, sekali klik memunculkan tanda dan pesan
                                    pada setiap isian yang masih kosong. `aria-disabled` memberi
                                    tahu pembaca layar bahwa aksinya belum bisa dijalankan, tanpa
                                    ikut membuang kliknya seperti `disabled`.
                                */}
                                <button
                                    onClick={() => submit(false)}
                                    disabled={submitting}
                                    aria-disabled={!canSubmit}
                                    className={`flex items-center gap-2 rounded-full bg-blue-600 dark:bg-blue-500 px-5 py-2.5 text-[13px] font-bold text-white shadow-sm hover:bg-blue-700 dark:hover:bg-blue-400 disabled:cursor-not-allowed disabled:opacity-50 ${canSubmit ? '' : 'opacity-60'}`}
                                >
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="m5 12 14-7-4 14-4-5z"/><path d="m11 14 8-9"/></svg>
                                    {submitting ? 'Mengirim…' : 'Kirim Tiket'}
                                </button>
                            </>
                        )}
                    </ModalFooter>
                </Modal>
            )}

            {previewFile && <AttachmentPreviewModal file={previewFile} onClose={() => setPreviewFile(null)} />}
        </>
    );
}

/**
 * `required` menggambar tanda wajib di sebelah label, `error` menggambar
 * pesannya di bawah isian.
 *
 * Keduanya ditambahkan setelah UAT test case 7 (FR-R05): sebelumnya formulir
 * ini sama sekali tidak menandai isian wajib dan tidak pernah memberi pesan
 * per isian — pengiriman hanya dicegah dengan mematikan tombolnya, sehingga
 * pengguna melihat tombol mati tanpa tahu bagian mana yang masih kurang.
 *
 * Tanda bintang disertai teks "wajib" yang hanya terbaca pembaca layar;
 * bintang sendirian tidak berarti apa-apa bila tidak terlihat.
 */
function Field({ label, children, required = false, error = null, htmlFor = null }) {
    return (
        <div>
            <label htmlFor={htmlFor ?? undefined} className="mb-1.5 block text-[13px] font-bold text-gray-800 dark:text-ink-1">
                {label}
                {required && (
                    <>
                        <span aria-hidden="true" className="ml-0.5 text-red-600 dark:text-bad-text">*</span>
                        <span className="sr-only"> (wajib)</span>
                    </>
                )}
            </label>
            {children}
            {error && (
                <p role="alert" className="mt-1.5 flex items-start gap-1 text-[12px] font-semibold text-red-600 dark:text-bad-text">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" className="mt-px shrink-0" aria-hidden="true">
                        <circle cx="12" cy="12" r="9" /><path d="M12 7v6" /><path d="M12 16.5v.5" />
                    </svg>
                    {error}
                </p>
            )}
        </div>
    );
}

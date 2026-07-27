import { useEffect, useMemo, useRef, useState } from 'react';
import Modal, { ModalFooter, ModalHeader } from './admin/Modal';
import { apiFetch, uploadFile } from '../lib/api';

const OTHER = '__other__';
const PRIORITIES = [
    { label: 'Low', glyph: '≡' },
    { label: 'Medium', glyph: '=' },
    { label: 'High', glyph: '!' },
    { label: 'Critical', glyph: '⚠' },
];
const MAX_ATTACHMENT_BYTES = 5 * 1024 * 1024;
const MAX_ATTACHMENTS = 5;
const ACCEPTED_ATTACHMENT_TYPES = ['image/png', 'image/jpeg', 'application/pdf'];

function AttachmentPreviewModal({ file, onClose }) {
    const url = useMemo(() => URL.createObjectURL(file), [file]);
    useEffect(() => () => URL.revokeObjectURL(url), [url]);
    const isImage = file.type.startsWith('image/');

    return (
        <div className="fixed inset-0 z-[60] flex items-center justify-center bg-gray-900/60 p-4" onClick={onClose}>
            <div className="flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl bg-white shadow-xl" onClick={(e) => e.stopPropagation()}>
                <div className="flex items-center justify-between border-b border-gray-100 px-5 py-3.5">
                    <p className="truncate pr-4 text-sm font-bold text-gray-900">{file.name}</p>
                    <button type="button" onClick={onClose} className="shrink-0 rounded-full p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600">✕</button>
                </div>
                <div className="flex-1 overflow-auto bg-gray-50 p-4">
                    {isImage ? (
                        <img src={url} alt={file.name} className="mx-auto max-h-[70vh] rounded-lg object-contain" />
                    ) : (
                        <iframe src={url} title={file.name} className="h-[70vh] w-full rounded-lg border border-gray-200 bg-white" />
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

function SearchableSelect({ value, placeholder, disabled, options, onChange, searchPlaceholder = 'Search…' }) {
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
                className={`flex w-full items-center justify-between rounded-[10px] border border-gray-200 px-3 py-2.5 text-left text-[13px] outline-none ${disabled ? 'cursor-not-allowed bg-gray-100 text-gray-400' : 'bg-gray-50 text-gray-700 hover:border-gray-300'}`}
            >
                <span className={selected ? 'text-gray-900' : ''}>{selected ? selected.label : placeholder}</span>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="shrink-0 text-gray-400"><path d="m6 9 6 6 6-6"/></svg>
            </button>

            {open && !disabled && (
                <div className="absolute left-0 top-[calc(100%+4px)] z-30 w-full overflow-hidden rounded-[10px] border border-gray-200 bg-white shadow-lg">
                    <div className="border-b border-gray-100 p-2">
                        <input
                            autoFocus
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder={searchPlaceholder}
                            className="w-full rounded-md border border-gray-200 px-2.5 py-1.5 text-[13px] outline-none focus:border-blue-400"
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
                                    className={`block w-full px-3 py-2 text-left text-[13px] hover:bg-blue-50 ${o.value === value ? 'bg-blue-50 font-semibold text-blue-700' : 'text-gray-700'}`}
                                >
                                    {o.label}
                                </button>
                            </li>
                        ))}
                        {filtered.length === 0 && <li className="px-3 py-4 text-center text-xs text-gray-400">No matches.</li>}
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

export default function NewTicketModal({
    catalogUrl = '/api/catalog',
    approversUrl = '/api/approvers',
    submitUrl = '/api/tickets',
    editTicket = null,
    editUrl = null,
    triggerLabel = 'New Ticket',
}) {
    const isEdit = !!editTicket;
    // A draft that an approver sent back for revision can only be re-submitted,
    // not re-saved as a fresh draft — so the "Save as Draft" action is hidden
    // in that case, leaving just Cancel and Submit Ticket.
    const isRevision = editTicket?.approvalNote?.decision === 'revision_requested';
    const [open, setOpen] = useState(false);
    const [catalog, setCatalog] = useState(null);
    const [policies, setPolicies] = useState(null);
    const [approvers, setApprovers] = useState(null);
    const [approverQuery, setApproverQuery] = useState('');
    const [approverOpen, setApproverOpen] = useState(false);
    const [form, setForm] = useState(emptyForm);
    const [attachments, setAttachments] = useState([]);
    const [attachmentError, setAttachmentError] = useState('');
    const [previewFile, setPreviewFile] = useState(null);
    const [dragOver, setDragOver] = useState(false);
    const [error, setError] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [created, setCreated] = useState(null);
    const approverRef = useRef(null);

    useEffect(() => {
        if (!open) return;
        setCatalog(null);
        setPolicies(null);
        setApprovers(null);
        setForm(emptyForm);
        setAttachments([]);
        setAttachmentError('');
        setError('');
        setCreated(null);
        apiFetch(catalogUrl).then(setCatalog).catch(() => setCatalog({ services: [], subcategories: [], subjects: [], issueCategories: [] }));
        apiFetch('/api/sla-policies/active').then(setPolicies).catch(() => setPolicies([]));
        apiFetch(approversUrl).then(setApprovers).catch(() => setApprovers([]));
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

    // A priority whose SLA policy the admin has deactivated can't be picked —
    // if the currently selected one just went inactive (or the form opened
    // defaulting to one that isn't active), fall back to the first priority
    // that still has a live policy.
    useEffect(() => {
        if (!open || !policies) return;
        const activePriorityList = policies.map((p) => p.priority);
        if (activePriorityList.length > 0 && !activePriorityList.includes(form.priority)) {
            set({ priority: activePriorityList[0] });
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
        return [...options, { value: OTHER, label: 'Other' }];
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
                if (next.length >= MAX_ATTACHMENTS) {
                    setAttachmentError(`You can attach up to ${MAX_ATTACHMENTS} files.`);
                    break;
                }
                if (!ACCEPTED_ATTACHMENT_TYPES.includes(file.type)) {
                    setAttachmentError('Only PNG, JPG, or PDF files are supported.');
                    continue;
                }
                if (file.size > MAX_ATTACHMENT_BYTES) {
                    setAttachmentError('File is larger than 5MB.');
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

    const filteredApprovers = (approvers ?? []).filter((a) => a.name.toLowerCase().includes(approverQuery.toLowerCase()));
    const activePriorities = useMemo(() => new Set((policies ?? []).map((p) => p.priority)), [policies]);

    const canSubmit =
        form.serviceId !== '' &&
        form.subcategoryId !== '' &&
        (isOtherSubcategory ? form.subjectText.trim() !== '' && form.issueCategoryId !== '' : form.subjectId !== '') &&
        (!requiresApproval || form.approverId !== '');

    async function submit(isDraft) {
        setError('');
        if (!isDraft && !canSubmit) {
            setError('Please complete all required fields before submitting.');
            return;
        }
        if (!form.serviceId) {
            setError('Silakan pilih Layanan terlebih dahulu.');
            return;
        }

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

            if (attachments.length > 0 && ticket.ticket_no) {
                for (const file of attachments) {
                    try {
                        await uploadFile(`/requester/tickets/${ticket.ticket_no}/attachment`, file);
                    } catch {
                        // Ticket is already saved — a failed attachment upload shouldn't block the confirmation.
                    }
                }
            }

            setCreated(ticket);
        } catch (e) {
            setError(e.message || 'Failed to save the ticket.');
        } finally {
            setSubmitting(false);
        }
    }

    function close() {
        setOpen(false);
    }

    return (
        <>
            <button
                onClick={() => setOpen(true)}
                className="flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-[13px] font-bold text-white shadow-sm hover:bg-blue-700"
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
                    <ModalHeader title="Ticket Information" subtitle={isEdit ? 'Update the details of your draft request.' : 'Fill in the details of your request.'} onClose={close} />

                    <div className="space-y-4 overflow-y-auto px-6 py-5">
                        {created ? (
                            <div className="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800">
                                <p className="font-semibold">
                                    {created.is_draft ? `Draft ${created.ticket_no} saved.` : `Ticket ${created.ticket_no} submitted.`}
                                </p>
                                <p className="mt-1">Status: <strong>{created.status}</strong></p>
                            </div>
                        ) : (
                            <>
                                {error && <p className="rounded-lg bg-red-50 p-3 text-sm text-red-700">{error}</p>}

                                <Field label="Layanan">
                                    <SearchableSelect
                                        value={form.serviceId}
                                        placeholder={catalog ? 'Pilih Layanan' : 'Loading…'}
                                        disabled={!catalog}
                                        options={serviceOptions}
                                        onChange={selectService}
                                        searchPlaceholder="Cari layanan…"
                                    />
                                </Field>

                                <Field label="Sub Category">
                                    <SearchableSelect
                                        value={form.subcategoryId}
                                        placeholder={form.serviceId ? 'Select a sub category' : 'Pilih Layanan terlebih dahulu'}
                                        disabled={!form.serviceId}
                                        options={subcategoryOptions}
                                        onChange={selectSubcategory}
                                        searchPlaceholder="Search sub category…"
                                    />
                                </Field>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <Field label="Subject">
                                        {isOtherSubcategory ? (
                                            <input
                                                value={form.subjectText}
                                                onChange={(e) => set({ subjectText: e.target.value })}
                                                placeholder="Describe the item"
                                                className="w-full rounded-[10px] border border-gray-200 px-3.5 py-2.5 text-[13px] text-gray-900 outline-none focus:border-blue-400"
                                            />
                                        ) : (
                                            <SearchableSelect
                                                value={form.subjectId}
                                                placeholder={form.subcategoryId ? 'Select a subject' : 'Select a sub category first'}
                                                disabled={!form.subcategoryId}
                                                options={subjectOptions}
                                                onChange={selectSubject}
                                                searchPlaceholder="Search subject…"
                                            />
                                        )}
                                    </Field>
                                    <Field label="Issue Category">
                                        {isOtherSubcategory ? (
                                            <select
                                                value={form.issueCategoryId}
                                                onChange={(e) => set({ issueCategoryId: e.target.value })}
                                                className="w-full rounded-[10px] border border-gray-200 bg-gray-50 px-3 py-2.5 text-[13px] text-gray-700 outline-none focus:border-blue-400"
                                            >
                                                <option value="">Select a category</option>
                                                {(catalog?.issueCategories ?? []).map((c) => (
                                                    <option key={c.id} value={c.id}>{c.name}</option>
                                                ))}
                                            </select>
                                        ) : (
                                            <div className="flex w-full items-center rounded-[10px] border border-gray-200 bg-gray-100 px-3.5 py-2.5 text-[13px] text-gray-500">
                                                {issueCategoryName || 'Select a subject first'}
                                            </div>
                                        )}
                                    </Field>
                                </div>

                                {requiresApproval && (
                                    <div className="rounded-2xl border border-blue-100 bg-blue-50/50 p-4">
                                        <div className="flex items-center gap-2.5">
                                            <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-100 text-blue-700">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Z"/><path d="M8.5 12l2.5 2.5 4.5-5"/></svg>
                                            </span>
                                            <span className="text-[13px] font-bold text-gray-900">Memerlukan Approval</span>
                                        </div>

                                        <p className="mb-1.5 mt-3 text-[13px] font-semibold text-gray-700">Minta approval kepada :</p>
                                        <div ref={approverRef} className="relative">
                                            {form.approverId ? (
                                                <div className="flex items-center justify-between rounded-[10px] border border-blue-200 bg-white px-3.5 py-2.5 text-[13px]">
                                                    <span className="font-semibold text-blue-900">{form.approverName}</span>
                                                    <button
                                                        type="button"
                                                        onClick={() => set({ approverId: '', approverName: '' })}
                                                        className="text-xs font-semibold text-blue-600 hover:text-blue-800"
                                                    >
                                                        Ganti
                                                    </button>
                                                </div>
                                            ) : (
                                                <div className="relative">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" className="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                                                    <input
                                                        value={approverQuery}
                                                        onChange={(e) => setApproverQuery(e.target.value)}
                                                        onFocus={() => setApproverOpen(true)}
                                                        placeholder="Cari nama approver"
                                                        className="w-full rounded-[10px] border border-gray-200 bg-white py-2.5 pl-9 pr-3.5 text-[13px] text-gray-900 outline-none focus:border-blue-400"
                                                    />
                                                </div>
                                            )}
                                            {approverOpen && !form.approverId && (
                                                <ul className="absolute left-0 top-[calc(100%+4px)] z-30 max-h-48 w-full overflow-y-auto rounded-[10px] border border-gray-200 bg-white shadow-lg">
                                                    {approvers === null && <li className="px-3 py-3 text-xs text-gray-400">Memuat approver…</li>}
                                                    {approvers !== null && filteredApprovers.length === 0 && (
                                                        <li className="px-3 py-3 text-xs text-gray-400">Tidak ada approver yang cocok.</li>
                                                    )}
                                                    {filteredApprovers.map((a) => (
                                                        <li key={a.id}>
                                                            <button
                                                                type="button"
                                                                onClick={() => {
                                                                    set({ approverId: String(a.id), approverName: a.name });
                                                                    setApproverOpen(false);
                                                                }}
                                                                className="block w-full px-3.5 py-2.5 text-left hover:bg-blue-50"
                                                            >
                                                                <span className="block text-[13px] font-semibold text-gray-900">{a.name}</span>
                                                                <span className="block text-[11px] text-gray-400">{a.jabatan}</span>
                                                            </button>
                                                        </li>
                                                    ))}
                                                </ul>
                                            )}
                                        </div>
                                        <p className="mt-1.5 text-[11px] text-gray-400">Ketik nama manager dituju baru dipilih.</p>
                                    </div>
                                )}

                                <Field label="Detailed Description">
                                    <textarea
                                        rows={4}
                                        value={form.description}
                                        onChange={(e) => set({ description: e.target.value })}
                                        placeholder="Describe the issue you are facing in detail…"
                                        className="min-h-[96px] w-full resize-y rounded-xl border border-gray-200 px-3.5 py-3 text-[13px] text-gray-900 outline-none focus:border-blue-400"
                                    />
                                </Field>

                                <Field label="Priority">
                                    <div className="grid grid-cols-2 gap-3 rounded-2xl border border-gray-100 bg-gray-50 p-2.5 sm:grid-cols-4">
                                        {PRIORITIES.map((p) => {
                                            const active = form.priority === p.label;
                                            const isActivePriority = !policies || activePriorities.has(p.label);
                                            return (
                                                <button
                                                    key={p.label}
                                                    type="button"
                                                    disabled={!isActivePriority}
                                                    title={isActivePriority ? undefined : 'SLA policy untuk priority ini sedang nonaktif'}
                                                    onClick={() => set({ priority: p.label })}
                                                    className={`flex flex-col items-center gap-1 rounded-xl border px-2 py-3 text-xs font-bold ${
                                                        !isActivePriority
                                                            ? 'cursor-not-allowed border-gray-100 bg-gray-50 text-gray-300'
                                                            : active
                                                              ? 'border-blue-600 bg-blue-600 text-white shadow-sm'
                                                              : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300'
                                                    }`}
                                                >
                                                    <span className="text-base font-extrabold leading-none">{p.glyph}</span>
                                                    {p.label}
                                                </button>
                                            );
                                        })}
                                    </div>
                                </Field>

                                <Field label={`Attachment (Optional) · ${attachments.length}/${MAX_ATTACHMENTS}`}>
                                    <label
                                        onDragOver={(e) => { e.preventDefault(); setDragOver(true); }}
                                        onDragLeave={() => setDragOver(false)}
                                        onDrop={(e) => {
                                            e.preventDefault();
                                            setDragOver(false);
                                            onAttachmentFiles(e.dataTransfer.files);
                                        }}
                                        className={`flex flex-col items-center gap-1.5 rounded-2xl border-2 border-dashed px-4 py-7 text-center ${
                                            attachments.length >= MAX_ATTACHMENTS
                                                ? 'cursor-not-allowed border-gray-200 bg-gray-50'
                                                : `cursor-pointer ${dragOver ? 'border-blue-500 bg-blue-50' : 'border-blue-300 bg-blue-50/60 hover:bg-blue-50'}`
                                        }`}
                                    >
                                        <input
                                            type="file"
                                            accept=".png,.jpg,.jpeg,.pdf"
                                            multiple
                                            disabled={attachments.length >= MAX_ATTACHMENTS}
                                            className="hidden"
                                            onChange={(e) => { onAttachmentFiles(e.target.files); e.target.value = ''; }}
                                        />
                                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="text-gray-700"><path d="M4 16.2A4.5 4.5 0 0 1 6.5 8a6 6 0 0 1 11.4 1.6A4 4 0 0 1 17.5 17H7"/><path d="M12 12v6"/><path d="m9 14.5 3-2.5 3 2.5"/></svg>
                                        <span className="text-[13px] font-bold text-gray-800">
                                            {attachments.length >= MAX_ATTACHMENTS ? 'Maximum files reached' : 'Click to upload or drag files here'}
                                        </span>
                                        <span className="text-[11px] text-gray-400">PNG, JPG, PDF (Max. 5MB) · Maksimal {MAX_ATTACHMENTS} foto</span>
                                    </label>
                                    {attachments.length > 0 && (
                                        <div className="mt-2 space-y-1.5">
                                            {attachments.map((file, i) => (
                                                <div key={`${file.name}-${i}`} className="flex items-center justify-between gap-2 rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-700">
                                                    <button
                                                        type="button"
                                                        onClick={() => setPreviewFile(file)}
                                                        className="min-w-0 flex-1 truncate text-left hover:text-blue-700 hover:underline"
                                                        title="Klik untuk pratinjau"
                                                    >
                                                        {file.name} · {formatBytes(file.size)}
                                                    </button>
                                                    <button type="button" onClick={() => removeAttachment(i)} className="shrink-0 font-semibold text-red-600 hover:text-red-800">Remove</button>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                    {attachmentError && <p className="mt-1.5 text-xs text-red-600">{attachmentError}</p>}
                                </Field>
                            </>
                        )}
                    </div>

                    <ModalFooter>
                        {created ? (
                            <button
                                onClick={() => (isEdit ? window.location.reload() : close())}
                                className="rounded-full bg-blue-600 px-6 py-2.5 text-[13px] font-bold text-white hover:bg-blue-700"
                            >
                                Close
                            </button>
                        ) : (
                            <>
                                <button onClick={close} className="rounded-full border border-gray-200 px-5 py-2.5 text-[13px] font-bold text-blue-600 hover:bg-gray-50">Cancel</button>
                                {!isRevision && (
                                    <button
                                        onClick={() => submit(true)}
                                        disabled={submitting || !form.serviceId}
                                        className="rounded-full border border-gray-200 px-5 py-2.5 text-[13px] font-bold text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        Save as Draft
                                    </button>
                                )}
                                <button
                                    onClick={() => submit(false)}
                                    disabled={submitting || !canSubmit}
                                    className="flex items-center gap-2 rounded-full bg-blue-600 px-5 py-2.5 text-[13px] font-bold text-white shadow-sm hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="m5 12 14-7-4 14-4-5z"/><path d="m11 14 8-9"/></svg>
                                    {submitting ? 'Submitting…' : 'Submit Ticket'}
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

function Field({ label, children }) {
    return (
        <div>
            <label className="mb-1.5 block text-[13px] font-bold text-gray-800">{label}</label>
            {children}
        </div>
    );
}

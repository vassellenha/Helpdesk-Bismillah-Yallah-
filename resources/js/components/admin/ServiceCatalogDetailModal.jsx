import Modal, { ModalFooter, ModalHeader } from './Modal';

function levelLabel(subject) {
    if (Number(subject.support_level) === 2) return 'Level 2 — Support BPO & IT';
    return subject.it_name && !subject.support_name ? 'Level 1 — Support IT' : 'Level 1 — Support BPO';
}

function supportLabel(subject) {
    const names = [subject.support_name, subject.it_name].filter(Boolean);
    return names.length > 0 ? names.join(' & ') : '—';
}

export default function ServiceCatalogDetailModal({ subject, onClose }) {
    return (
        <Modal onClose={onClose} maxWidth="max-w-lg">
            <ModalHeader title="Detail Service Catalog" subtitle={`${subject.layanan} — ${subject.subject}`} onClose={onClose} />

            <div className="grid grid-cols-2 gap-4 px-6 py-5 text-sm">
                <Detail label="Issue Category" value={subject.issue_category} />
                <Detail label="Layanan" value={subject.layanan} />
                <Detail label="Sub Category" value={subject.subcategory} />
                <Detail label="Subject" value={subject.subject} />
                <Detail label="Requires Approval" value={subject.requires_approval ? 'Yes' : 'No'} />
                <Detail label="Status" value={subject.status === 'active' ? 'Aktif' : 'Nonaktif'} />
                <Detail label="Level" value={levelLabel(subject)} />
                <Detail label="Support" value={supportLabel(subject)} />
            </div>

            <ModalFooter>
                <button onClick={onClose} className="rounded-lg bg-blue-700 dark:bg-blue-500 px-5 py-2 text-sm font-medium text-white hover:bg-blue-800 dark:hover:bg-blue-400">Tutup</button>
            </ModalFooter>
        </Modal>
    );
}

function Detail({ label, value }) {
    return (
        <div className="rounded-lg bg-gray-50 dark:bg-panel-3 p-3">
            <p className="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-ink-3">{label}</p>
            <p className="mt-1 text-sm font-medium text-gray-900 dark:text-ink-1">{value}</p>
        </div>
    );
}

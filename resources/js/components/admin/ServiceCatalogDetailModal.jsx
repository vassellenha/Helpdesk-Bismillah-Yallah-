import Modal, { ModalFooter, ModalHeader } from './Modal';
import { LEVEL_DESCRIPTIONS } from '../../lib/formatters';

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
                <Detail label="Level" value={`Level ${subject.support_level} — ${LEVEL_DESCRIPTIONS[subject.support_level]}`} />
                <Detail label="Support" value={subject.support_name ?? '—'} />
            </div>

            <ModalFooter>
                <button onClick={onClose} className="rounded-lg bg-blue-700 px-5 py-2 text-sm font-medium text-white hover:bg-blue-800">Tutup</button>
            </ModalFooter>
        </Modal>
    );
}

function Detail({ label, value }) {
    return (
        <div className="rounded-lg bg-gray-50 p-3">
            <p className="text-xs font-semibold uppercase tracking-wide text-gray-400">{label}</p>
            <p className="mt-1 text-sm font-medium text-gray-900">{value}</p>
        </div>
    );
}

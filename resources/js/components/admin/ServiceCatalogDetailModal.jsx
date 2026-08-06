import Modal, { ModalFooter, ModalHeader } from './Modal';
import { t as trans } from '../../lib/i18n';

function levelLabel(subject) {
    if (Number(subject.support_level) === 2) return trans('admin.catalog.level2_both');
    return subject.it_name && !subject.support_name ? trans('admin.catalog.level1_it') : trans('admin.catalog.level1_bpo');
}

function supportLabel(subject) {
    const names = [subject.support_name, subject.it_name].filter(Boolean);
    return names.length > 0 ? names.join(' & ') : '—';
}

export default function ServiceCatalogDetailModal({ subject, onClose }) {
    return (
        <Modal onClose={onClose} maxWidth="max-w-lg">
            <ModalHeader title={trans('admin.catalog.detail_title')} subtitle={`${subject.layanan} — ${subject.subject}`} onClose={onClose} />

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 px-6 py-5 text-sm">
                <Detail label={trans('admin.catalog.col_issue')} value={subject.issue_category} />
                <Detail label={trans('admin.catalog.col_service')} value={subject.layanan} />
                <Detail label={trans('admin.catalog.col_subcategory')} value={subject.subcategory} />
                <Detail label={trans('admin.catalog.col_subject')} value={subject.subject} />
                <Detail label={trans('admin.catalog.requires_approval')} value={subject.requires_approval ? trans('admin.integration.yes') : trans('admin.integration.no')} />
                <Detail label={trans('admin.common.status')} value={subject.status === 'active' ? trans('admin.common.active') : trans('admin.common.inactive')} />
                <Detail label={trans('admin.catalog.col_level')} value={levelLabel(subject)} />
                <Detail label={trans('admin.catalog.col_support')} value={supportLabel(subject)} />
            </div>

            <ModalFooter>
                <button onClick={onClose} className="rounded-lg bg-blue-700 dark:bg-blue-500 px-5 py-2 text-sm font-medium text-white hover:bg-blue-800 dark:hover:bg-blue-400">{trans('admin.common.close')}</button>
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

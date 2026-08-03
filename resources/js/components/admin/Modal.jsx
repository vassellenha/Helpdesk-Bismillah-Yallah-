import useLockBodyScroll from '../../lib/useLockBodyScroll';
import { t as trans } from '../../lib/i18n';

export default function Modal({ children, onClose, maxWidth = 'max-w-lg' }) {
    useLockBodyScroll();
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/40 p-4" onClick={onClose}>
            <div
                className={`flex max-h-[90vh] w-full ${maxWidth} flex-col overflow-hidden rounded-2xl bg-white dark:bg-panel-2 shadow-xl`}
                onClick={(e) => e.stopPropagation()}
            >
                {children}
            </div>
        </div>
    );
}

export function ModalHeader({ title, subtitle, onClose }) {
    return (
        <div className="flex items-start justify-between border-b border-gray-100 dark:border-edge px-6 py-4">
            <div>
                <h2 className="text-lg font-bold text-gray-900 dark:text-ink-1">{title}</h2>
                {subtitle && <p className="mt-0.5 text-sm text-gray-500 dark:text-ink-2">{subtitle}</p>}
            </div>
            <button onClick={onClose} className="rounded-full p-1.5 text-gray-400 dark:text-ink-3 hover:bg-gray-100 dark:hover:bg-panel-hover hover:text-gray-600" aria-label={trans('admin.common.close')}>
                ✕
            </button>
        </div>
    );
}

export function ModalFooter({ children }) {
    return <div className="flex justify-end gap-2 border-t border-gray-100 dark:border-edge bg-gray-50 dark:bg-panel-3 px-6 py-4">{children}</div>;
}

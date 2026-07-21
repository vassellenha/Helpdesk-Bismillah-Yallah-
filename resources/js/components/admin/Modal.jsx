export default function Modal({ children, onClose, maxWidth = 'max-w-lg' }) {
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/40 p-4" onClick={onClose}>
            <div
                className={`flex max-h-[90vh] w-full ${maxWidth} flex-col overflow-hidden rounded-2xl bg-white shadow-xl`}
                onClick={(e) => e.stopPropagation()}
            >
                {children}
            </div>
        </div>
    );
}

export function ModalHeader({ title, subtitle, onClose }) {
    return (
        <div className="flex items-start justify-between border-b border-gray-100 px-6 py-4">
            <div>
                <h2 className="text-lg font-bold text-gray-900">{title}</h2>
                {subtitle && <p className="mt-0.5 text-sm text-gray-500">{subtitle}</p>}
            </div>
            <button onClick={onClose} className="rounded-full p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600" aria-label="Tutup">
                ✕
            </button>
        </div>
    );
}

export function ModalFooter({ children }) {
    return <div className="flex justify-end gap-2 border-t border-gray-100 bg-gray-50 px-6 py-4">{children}</div>;
}

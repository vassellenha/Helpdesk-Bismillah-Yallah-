import { createRoot } from 'react-dom/client';
import { registry } from './components/registry';
import { initSidebarToggle } from './lib/eva-sidebar';

function mountIslands() {
    document.querySelectorAll('[data-react]').forEach((el) => {
        const name = el.getAttribute('data-react');
        const Component = registry[name];

        if (!Component) {
            console.warn(`[helpdesk] React component "${name}" is not registered.`);
            return;
        }

        const props = el.getAttribute('data-props')
            ? JSON.parse(el.getAttribute('data-props'))
            : {};

        createRoot(el).render(<Component {...props} />);
    });
}

function boot() {
    mountIslands();
    // Aman dipanggil di halaman tim yang tidak punya sidebar EVA — keluar
    // sendiri kalau tombolnya tidak ada.
    initSidebarToggle();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}

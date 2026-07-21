import { createRoot } from 'react-dom/client';
import { registry } from './components/registry';

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

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mountIslands);
} else {
    mountIslands();
}

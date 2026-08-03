import { createPortal } from 'react-dom';

/**
 * Renders children into document.body instead of where the component sits in
 * the tree.
 *
 * Needed because our top bars use `backdrop-blur`. Any element with a
 * backdrop-filter becomes the containing block for its `position: fixed`
 * descendants, so a full-screen overlay opened from the top bar would size
 * itself to the 62px-tall header and centre itself on the header — pushing
 * most of the dialog above the top of the screen.
 *
 * Portalling to <body> sidesteps that: the overlay is no longer a descendant
 * of the blurred element, so `fixed inset-0` means the viewport again.
 */
export default function Portal({ children }) {
    if (typeof document === 'undefined') return null;

    return createPortal(children, document.body);
}

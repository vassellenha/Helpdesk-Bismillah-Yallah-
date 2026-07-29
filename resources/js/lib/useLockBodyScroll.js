import { useEffect } from 'react';

/**
 * Locks page scroll behind an open modal/slide-over — the backdrop should
 * stay put while only the dialog itself scrolls. Restores whatever
 * `overflow` value was there before, so nesting two of these (e.g. a
 * slide-over opened from within another modal) doesn't clobber the outer
 * one's cleanup.
 *
 * Pass `active=false` for overlays that live inside an always-mounted parent
 * and only render conditionally (e.g. `{open && <div className="fixed ...">}`)
 * — the lock then tracks that flag instead of the host component's lifetime.
 */
export default function useLockBodyScroll(active = true) {
    useEffect(() => {
        if (!active) return undefined;

        const previous = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        return () => {
            document.body.style.overflow = previous;
        };
    }, [active]);
}

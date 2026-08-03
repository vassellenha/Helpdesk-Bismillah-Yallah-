/**
 * Translation for React islands.
 *
 * Laravel's __() only exists server-side, so Blade serialises the active
 * locale's messages into `window.__LANG__` (see partials/translations.blade.php)
 * and this reads from there. One dictionary shipped per request — no bundling
 * every language into the JS, and no extra fetch on load.
 *
 * A missing key returns the key itself rather than an empty string: a screen
 * reading "requester.columns.status" is obviously broken, while a blank label
 * looks like a styling bug and can survive review unnoticed.
 */
function dictionary() {
    return (typeof window !== 'undefined' && window.__LANG__) || {};
}

export function locale() {
    return (typeof window !== 'undefined' && window.__LOCALE__) || 'id';
}

/**
 * t('requester.columns.status')
 * t('requester.showing', { shown: 5, total: 20 })
 *
 * `fallback` is for keys that legitimately may not exist — an admin-created
 * custom role has no admin.role_desc entry, and printing the raw key there
 * would be wrong rather than merely untranslated. Leave it out everywhere
 * else so a genuine typo still surfaces as the key itself.
 */
export function t(key, replacements = {}, fallback = null) {
    const value = key.split('.').reduce((carry, part) => (carry && typeof carry === 'object' ? carry[part] : undefined), dictionary());

    if (typeof value !== 'string') {
        return fallback ?? key;
    }

    return Object.entries(replacements).reduce(
        (text, [token, replacement]) => text.replaceAll(`:${token}`, String(replacement)),
        value,
    );
}

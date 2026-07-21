function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

export async function apiFetch(url, options = {}) {
    const res = await fetch(url, {
        ...options,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            ...options.headers,
        },
    });

    const body = await res.json().catch(() => null);

    if (!res.ok) {
        const message = body?.message || body?.errors ? JSON.stringify(body.errors ?? body.message) : `Request failed (${res.status})`;
        throw new Error(message);
    }

    return body;
}

export async function uploadFile(url, file) {
    const form = new FormData();
    form.append('file', file);

    const res = await fetch(url, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: form,
    });

    const body = await res.json().catch(() => null);

    if (!res.ok) {
        const message = body?.message || body?.errors ? JSON.stringify(body.errors ?? body.message) : `Upload failed (${res.status})`;
        throw new Error(message);
    }

    return body;
}

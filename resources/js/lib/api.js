function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

/**
 * Objek biasa yang lolos ke `fetch` tanpa JSON.stringify dikirim sebagai teks
 * harfiah "[object Object]" — dan Laravel melaporkannya sebagai "field wajib
 * kosong" untuk field yang sebenarnya terisi. Kegagalan yang menunjuk ke arah
 * yang salah, persis seperti jebakan Content-Type di uploadFile() di bawah.
 *
 * Konvensinya tetap: pemanggil menulis JSON.stringify sendiri. Ini jaring
 * pengaman kalau ada yang lupa, bukan izin untuk berhenti menulisnya.
 */
function serializeBody(body) {
    const isPlainObject = body !== null
        && typeof body === 'object'
        && !(body instanceof FormData)
        && !(body instanceof Blob)
        && !(body instanceof URLSearchParams)
        && !(body instanceof ArrayBuffer);

    return isPlainObject ? JSON.stringify(body) : body;
}

export async function apiFetch(url, options = {}) {
    const res = await fetch(url, {
        ...options,
        ...('body' in options ? { body: serializeBody(options.body) } : {}),
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            ...options.headers,
        },
    });

    const body = await res.json().catch(() => null);

    if (!res.ok) {
        throw new Error(errorMessage(body, res.status));
    }

    return body;
}

/**
 * Pesan yang layak dibaca orang.
 *
 * Versi sebelumnya menulis `a || b ? JSON.stringify(...) : fallback`, yang
 * dibaca JavaScript sebagai `(a || b) ? ... : ...` — jadi pesan biasa pun ikut
 * dibungkus tanda kutip JSON. Error validasi diratakan jadi kalimat, bukan
 * objek mentah.
 */
function errorMessage(body, status) {
    if (body?.errors && typeof body.errors === 'object') {
        const flat = Object.values(body.errors).flat().filter(Boolean);
        if (flat.length > 0) return flat.join(' ');
    }

    return body?.message || `Request failed (${status})`;
}

/**
 * Kiriman multipart: satu berkas plus field pendamping.
 *
 * Content-Type SENGAJA tidak diset — browser harus menuliskannya sendiri
 * lengkap dengan boundary FormData. Menyetelnya manual membuat PHP menerima
 * body yang tidak bisa diurai, dan gejalanya cuma "field wajib kosong".
 */
export async function uploadFile(url, file, fields = {}) {
    const form = new FormData();
    form.append('file', file);

    Object.entries(fields).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') {
            form.append(key, value);
        }
    });

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
        throw new Error(errorMessage(body, res.status) === `Request failed (${res.status})`
            ? `Upload failed (${res.status})`
            : errorMessage(body, res.status));
    }

    return body;
}

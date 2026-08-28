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

/*
 | Basis alamat aplikasi — DITURUNKAN dari halaman, bukan diasumsikan.
 |
 | Helpdesk tidak selalu berada di akar domain. Saat dibuka lewat portal SINTA,
 | ia disajikan di bawah jalur seperti:
 |
 |   https://sinta.adhi.co.id/index.php/remote/new_remote/72//
 |
 | Portal menulis ulang setiap alamat helpdesk yang ada DI DALAM HTML — tautan,
 | script, CSS, dan JSON props. Yang tidak tersentuh adalah alamat yang disusun
 | JavaScript saat berjalan: `fetch('/admin/users/3843')` dibaca browser
 | relatif terhadap AKAR DOMAIN, jadi mendarat di `sinta.adhi.co.id/admin/...`
 | — aplikasi portal, bukan helpdesk. Balasannya HTML, dan komponen yang
 | menunggu JSON berantakan.
 |
 | Basisnya diambil dari alamat <script> Vite justru KARENA portal sudah
 | menulis ulang alamat itu: kalau script-nya tidak termuat, halaman ini tidak
 | akan berjalan sama sekali. Jadi tidak ada yang perlu ditambahkan di sisi
 | server, dan hasilnya benar di kedua keadaan — di belakang portal maupun saat
 | dibuka langsung.
*/
let cachedBase = null;

function appBase() {
    if (cachedBase !== null) return cachedBase;

    const src = document.querySelector('script[type="module"][src*="/build/"]')?.src ?? '';
    const cut = src.indexOf('/build/');

    // Tanpa aset terbangun (server pengembangan Vite), akar domain sudah benar.
    cachedBase = cut === -1 ? `${window.location.origin}/` : src.slice(0, cut + 1);

    return cachedBase;
}

/**
 * Alamat relatif → alamat penuh yang benar di lingkungan mana pun.
 *
 * Garis miring di depan DIBUANG sebelum disambung: basisnya sudah berakhir
 * dengan garis miring, dan menyambung dua garis menghasilkan jalur yang ditolak
 * router Laravel ("The route ... could not be found") — sudah terbukti di
 * produksi, bukan kehati-hatian teoretis.
 *
 * Alamat yang sudah absolut dibiarkan apa adanya.
 */
export function resolveUrl(url) {
    if (typeof url !== 'string' || url === '') return url;
    if (/^[a-z][a-z0-9+.-]*:/i.test(url)) return url;

    return appBase() + url.replace(/^\/+/, '');
}

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
    const res = await fetch(resolveUrl(url), {
        ...options,
        ...('body' in options ? { body: serializeBody(options.body) } : {}),
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            ...options.headers,
        },
    });

    // Dibaca sebagai TEKS dulu, baru diurai. `res.json()` menelan dua keadaan
    // yang sangat berbeda menjadi satu nilai null: balasan yang memang kosong,
    // dan balasan yang berisi sesuatu tapi bukan JSON.
    const raw = await res.text();
    const body = parseJson(raw);

    if (!res.ok) {
        throw new Error(errorMessage(body, res.status));
    }

    /*
     | Status 2xx tapi isinya BUKAN JSON — dan ini pernah menjatuhkan seluruh
     | layar di produksi.
     |
     | Dulu keadaan ini memulangkan `null` tanpa suara. Pemanggilnya menganggap
     | itu objek hasil penyimpanan, membaca `.id` di dalamnya, dan React
     | membatalkan render satu pulau penuh: layar berubah putih tanpa satu pun
     | pesan, sementara satu-satunya petunjuk hanya ada di console browser.
     |
     | Penyebabnya nyaris tidak pernah bug di controller — semua endpoint di
     | sini bertipe JsonResponse. Yang terjadi adalah permintaannya TIDAK
     | SAMPAI: sesi berakhir sehingga dijawab halaman login, atau alamatnya
     | nyasar ke server lain yang menjawab dengan HTML miliknya sendiri.
     | Keduanya perlu dikatakan, bukan disembunyikan.
     |
     | Balasan yang benar-benar kosong (204, atau body nol byte) TETAP
     | memulangkan null: ada endpoint yang memang tidak menjawab apa-apa, dan
     | menolaknya di sini akan mematikan yang selama ini bekerja.
    */
    if (body === null && raw.trim() !== '') {
        throw new Error(
            'Server tidak membalas dengan data (status '.concat(res.status, ', ')
            + (res.headers.get('content-type') || 'tanpa tipe konten')
            + '). Sesi Anda mungkin sudah berakhir, atau permintaan ini tidak sampai ke helpdesk. '
            + 'Muat ulang halaman lalu coba lagi.',
        );
    }

    return body;
}

/** @return {any|null} null bila teksnya kosong ATAU bukan JSON yang sah. */
function parseJson(raw) {
    if (raw.trim() === '') return null;

    try {
        return JSON.parse(raw);
    } catch {
        return null;
    }
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

    const res = await fetch(resolveUrl(url), {
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

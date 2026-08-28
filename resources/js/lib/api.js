function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

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

/*
 | Kiriman dikemas sebagai FORM, bukan JSON — dan itu bukan selera.
 |
 | Portal SINTA menyajikan helpdesk lewat modul proxy-nya sendiri, dan proxy itu
 | TIDAK meneruskan body JSON mentah maupun header khusus seperti X-CSRF-TOKEN.
 | Akibatnya Laravel tidak menerima apa pun: tokennya hilang (ditolak dengan
 | "CSRF token mismatch") dan seluruh isi permintaan ikut lenyap. Terbukti
 | langsung di produksi, tiga cara ke endpoint yang sama:
 |
 |   JSON + header X-CSRF-TOKEN   -> CSRF token mismatch
 |   JSON berisi _token           -> CSRF token mismatch
 |   form-urlencoded + _token     -> BERHASIL, EVA menjawab
 |
 | Body form diteruskan utuh oleh proxy mana pun: itu bentuk kiriman HTML yang
 | paling tua dan paling umum. Laravel membacanya lewat jalur input yang sama
 | persis dengan JSON, jadi tidak ada satu pun controller yang perlu berubah —
 | semuanya memakai validate()/input(), tidak ada yang memanggil $request->json().
*/
function toFormBody(value) {
    const params = new URLSearchParams();

    const tulis = (kunci, isi) => {
        /*
         | null menjadi string kosong, lalu dikembalikan JADI null oleh
         | middleware ConvertEmptyStringsToNull bawaan Laravel. Tanpa langkah
         | itu, `catalog_subject_id: null` akan sampai sebagai "" dan aturan
         | `nullable|integer` menolaknya.
        */
        if (isi === null || isi === undefined) {
            params.append(kunci, '');
            return;
        }

        // Kurung siku: bentuk yang diurai PHP menjadi array/objek bersarang.
        if (Array.isArray(isi)) {
            isi.forEach((item) => tulis(`${kunci}[]`, item));
            return;
        }

        if (typeof isi === 'object' && !(isi instanceof Date)) {
            Object.entries(isi).forEach(([sub, nilai]) => tulis(`${kunci}[${sub}]`, nilai));
            return;
        }

        // "1"/"0", bukan "true"/"false": keduanya diterima aturan `boolean`,
        // tapi yang ini juga lolos `accepted` dan perbandingan longgar di PHP.
        if (typeof isi === 'boolean') {
            params.append(kunci, isi ? '1' : '0');
            return;
        }

        params.append(kunci, String(isi));
    };

    Object.entries(value).forEach(([kunci, isi]) => tulis(kunci, isi));

    return params;
}

/**
 * Menyiapkan isi kiriman.
 *
 * Bentuk yang sudah disiapkan pemanggil (FormData, Blob, URLSearchParams)
 * dibiarkan apa adanya. Sisanya dijadikan form — termasuk teks JSON, karena
 * pemanggil di repo ini menulis `JSON.stringify(...)` sendiri mengikuti
 * konvensi lama. Teks itu diurai kembali di sini lalu dikemas ulang: mengubah
 * 45 berkas pemanggil hanya demi itu jauh lebih berisiko daripada satu tempat
 * yang mengurusnya.
 */
function serializeBody(body) {
    if (body === null || body === undefined) return body;

    if (body instanceof FormData || body instanceof Blob
        || body instanceof URLSearchParams || body instanceof ArrayBuffer) {
        return body;
    }

    if (typeof body === 'string') {
        try {
            const diurai = JSON.parse(body);

            if (diurai !== null && typeof diurai === 'object' && !Array.isArray(diurai)) {
                return toFormBody(diurai);
            }
        } catch {
            // Bukan JSON — biarkan apa adanya.
        }

        return body;
    }

    if (typeof body === 'object' && !Array.isArray(body)) {
        return toFormBody(body);
    }

    return body;
}

/*
 | Metode yang DISAMARKAN menjadi POST.
 |
 | Proxy portal SINTA hanya meneruskan GET dan POST. PUT, PATCH, dan DELETE
 | tidak pernah sampai ke helpdesk — yang kembali halaman HTML milik portal,
 | dan seluruh fungsinya mati tanpa pesan apa pun. Terbukti di produksi pada
 | penetapan role:
 |
 |   PATCH /admin/users/3843/roles          -> balasan HTML
 |   POST  + _method=PATCH                  -> BERHASIL
 |
 | Penyamaran ini konvensi resmi Laravel, yang dipakai form HTML sejak sebelum
 | ada AJAX: field `_method` dibaca lebih dulu daripada metode HTTP-nya. Jadi
 | ini bukan akal-akalan, melainkan jalur yang memang disediakan untuk keadaan
 | persis seperti ini — dan tetap benar saat helpdesk dibuka langsung.
*/
const METODE_DISAMARKAN = ['PUT', 'PATCH', 'DELETE'];

/** Metode yang tidak mengubah apa pun — tidak diperiksa CSRF, tidak diberi isi. */
const METODE_TANPA_ISI = ['GET', 'HEAD'];

export async function apiFetch(url, options = {}) {
    let metode = (options.method ?? 'GET').toUpperCase();
    let kiriman = 'body' in options ? serializeBody(options.body) : undefined;

    /*
     | Permintaan yang MENGUBAH sesuatu selalu dibuatkan kiriman, walau
     | pemanggilnya tidak mengirim apa-apa.
     |
     | Banyak aksi tombol berupa `apiFetch(url, { method: 'POST' })` tanpa body:
     | mulai kerjakan tiket, hapus lampiran, tandai pertanyaan, dan seterusnya.
     | Dikirim apa adanya, permintaannya berisi nol byte — dan tidak ada tempat
     | menaruh `_token`, sehingga Laravel menjawab halaman "Page Expired" (419)
     | begitu header X-CSRF-TOKEN dibuang proxy portal. Terbukti di produksi
     | pada tombol Mulai Kerjakan.
     |
     | GET dan HEAD tidak disentuh: keduanya tidak diperiksa CSRF, dan memberi
     | isi pada GET justru membuat sebagian proxy dan cache berperilaku aneh.
    */
    if (!METODE_TANPA_ISI.includes(metode) && (kiriman === undefined || kiriman === null)) {
        kiriman = new URLSearchParams();
    }

    if (METODE_DISAMARKAN.includes(metode)
        && (kiriman instanceof URLSearchParams || kiriman instanceof FormData)) {
        kiriman.append('_method', metode);
        metode = 'POST';
    }

    // Token ikut DI DALAM kiriman, bukan hanya di header: header khusus tidak
    // selamat melewati proxy portal. Header-nya tetap dikirim untuk akses
    // langsung — Laravel menerima keduanya, mana pun yang lebih dulu ada.
    if ((kiriman instanceof URLSearchParams || kiriman instanceof FormData)
        && !kiriman.has('_token')) {
        kiriman.append('_token', csrfToken());
    }

    const res = await fetch(resolveUrl(url), {
        ...options,
        // SETELAH sebaran options, bukan sebelum: metode yang disamarkan harus
        // menang atas nilai asli yang dikirim pemanggil.
        method: metode,
        ...(kiriman === undefined ? {} : { body: kiriman }),
        headers: {
            Accept: 'application/json',
            // Content-Type SENGAJA tidak diset: browser menuliskannya sendiri
            // sesuai bentuk kiriman, lengkap dengan boundary multipart yang
            // tidak mungkin ditebak dengan tangan.
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
    // Alasannya sama dengan apiFetch: header X-CSRF-TOKEN tidak selamat lewat
    // proxy portal, jadi tokennya ikut sebagai field.
    form.append('_token', csrfToken());

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

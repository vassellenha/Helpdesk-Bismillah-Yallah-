/**
 * Satu-satunya tempat sisi React tahu prioritas apa saja yang ada.
 *
 * Sebelumnya tiap layar menyimpan daftarnya sendiri — `['Critical','High',
 * 'Medium','Low']`, peta warna per nama, peta peringkat per nama — tersebar di
 * belasan berkas. Selama nama prioritas dikunci empat itu, salinan tersebut
 * tidak terasa salah. Begitu Admin boleh membuat dan mengganti nama prioritas,
 * tiap salinan berubah jadi layar yang diam-diam berbohong:
 *
 *   - Prioritas baru "Impossible" tidak ada di daftar, jadi tidak pernah
 *     digambar. Server mengirimnya; layar tiket baru tetap menampilkan empat.
 *   - "Critical" yang diganti jadi "Kritikal" tidak lagi cocok dengan daftar,
 *     jadi tombolnya tampil nonaktif dan warnanya jatuh ke abu-abu — prioritas
 *     paling genting tampak sepucat yang paling santai.
 *
 * Daftarnya sekarang datang dari server lewat <script id="priority-registry">
 * yang dititipkan layout, dan sudah terurut dari yang paling mendesak.
 */

const FALLBACK = [
    { name: 'Critical', color: '#dc2626', glyph: '⚠' },
    { name: 'High', color: '#d97706', glyph: '!' },
    { name: 'Medium', color: '#2563eb', glyph: '=' },
    { name: 'Low', color: '#9ca3af', glyph: '≡' },
];

const NEUTRAL_COLOR = '#64748b';

let cache = null;

/**
 * Semua prioritas aktif, terurut dari yang paling mendesak.
 *
 * @returns {Array<{name: string, color: string, glyph: string}>}
 */
export function priorityList() {
    if (cache) return cache;

    const el = typeof document !== 'undefined' && document.getElementById('priority-registry');
    if (! el) return (cache = FALLBACK);

    try {
        const parsed = JSON.parse(el.textContent);
        // Halaman yang belum punya satu pun policy aktif mengirim array kosong;
        // memakainya apa adanya akan membuat pemilih prioritas hilang total.
        cache = Array.isArray(parsed) && parsed.length > 0 ? parsed : FALLBACK;
    } catch {
        cache = FALLBACK;
    }

    return cache;
}

/** Nama-nama prioritas saja, terurut dari yang paling mendesak. */
export function priorityNames() {
    return priorityList().map((p) => p.name);
}

/** Warna sebuah prioritas; netral kalau namanya sudah tidak dikenal. */
export function priorityColor(name) {
    return priorityList().find((p) => p.name === name)?.color ?? NEUTRAL_COLOR;
}

/** Lencana ringkas untuk tombol pemilih prioritas. */
export function priorityGlyph(name) {
    return priorityList().find((p) => p.name === name)?.glyph ?? '·';
}

/** Peta nama => warna, untuk grafik yang butuh seluruh daftar sekaligus. */
export function priorityColors() {
    return Object.fromEntries(priorityList().map((p) => [p.name, p.color]));
}

/**
 * Angka untuk mengurutkan: makin mendesak makin besar.
 *
 * Menggantikan peta `{ Critical: 4, High: 3, … }` yang memberi nilai 0 —
 * alias paling bawah — kepada setiap prioritas yang namanya tidak dikenal,
 * termasuk yang paling genting sekalipun.
 */
export function priorityRank(name) {
    const list = priorityList();
    const index = list.findIndex((p) => p.name === name);

    return index === -1 ? 0 : list.length - index;
}

/**
 * Gaya inline untuk lencana prioritas.
 *
 * Warna dikirim sebagai custom property, bukan kelas Tailwind: kelas harus
 * berupa teks yang bisa dibaca Tailwind saat build, jadi warna yang baru ada
 * ketika Admin membuat prioritas tidak mungkin diungkapkan dengan kelas.
 * Aturan terang/gelapnya ada di `.priority-badge` pada app.css.
 */
export function priorityBadgeStyle(name) {
    return { '--priority-color': priorityColor(name) };
}

/**
 * Membaca nilai mentah `old_value`/`new_value` dari Audit Trail menjadi teks
 * yang bisa dibaca orang.
 *
 * Isi kedua kolom itu JSON bebas — tiap modul menyimpan bentuknya sendiri,
 * mulai dari string biasa sampai daftar objek (ringkasan sinkronisasi pegawai).
 * Karena itu perubahannya harus dibaca secara rekursif: apa pun yang masuk,
 * yang keluar tetap kalimat, tidak pernah "[object Object]".
 */

const KOSONG = '—';

function isPlainObject(value) {
    return typeof value === 'object' && value !== null && ! Array.isArray(value);
}

export function humanizeKey(key) {
    return key.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

export function humanizeValue(value) {
    if (Array.isArray(value)) {
        if (value.length === 0) {
            return KOSONG;
        }

        /*
         | Daftar objek dipisah baris, bukan koma. Tiap entri sudah memuat koma
         | di dalamnya ("Fields: email, jabatan"), jadi menggabungkannya dengan
         | koma lagi membuat batas antar-entri hilang sama sekali.
         */
        const pemisah = value.some(isPlainObject) ? '\n' : ', ';

        return value.map(humanizeValue).join(pemisah);
    }

    if (isPlainObject(value)) {
        const isi = Object.entries(value)
            .map(([key, isi]) => `${humanizeKey(key)}: ${humanizeValue(isi)}`)
            .join(' · ');

        return isi === '' ? KOSONG : isi;
    }

    if (typeof value === 'boolean') {
        return value ? 'Yes' : 'No';
    }

    if (value === null || value === undefined || value === '') {
        return KOSONG;
    }

    return String(value);
}

/**
 * Unduh berkas lewat fetch, bukan `window.location.href`.
 *
 * Mengarahkan alamat halaman ke endpoint unduhan hanya bekerja saat semuanya
 * lancar. Begitu server menjawab 422 (rentang tanggal tidak masuk akal), 403,
 * atau 419 (sesi kedaluwarsa), browser menggantikan seluruh dashboard dengan
 * halaman error mentah: pekerjaan yang sedang dibuka hilang, dan penyebabnya
 * tidak pernah sampai ke layar aslinya.
 *
 * Di sini responsnya diperiksa dulu. Gagal → melempar Error berisi pesan
 * server supaya pemanggil bisa menampilkannya sebagai toast; berhasil → blob-nya
 * disimpan lewat anchor sementara dan halaman tidak berpindah ke mana pun.
 */
export async function downloadFile(url) {
    const res = await fetch(url, { headers: { Accept: 'application/json' } });

    if (!res.ok) {
        throw new Error(await errorMessage(res));
    }

    const blob = await res.blob();
    const objectUrl = URL.createObjectURL(blob);
    const anchor = document.createElement('a');

    anchor.href = objectUrl;
    anchor.download = filenameFrom(res.headers.get('Content-Disposition'));
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();

    // Revoke ditunda satu tick: Safari membatalkan unduhan kalau URL-nya
    // dicabut pada tick yang sama dengan klik-nya.
    setTimeout(() => URL.revokeObjectURL(objectUrl), 0);
}

async function errorMessage(res) {
    const body = await res.json().catch(() => null);

    if (body?.errors && typeof body.errors === 'object') {
        const flat = Object.values(body.errors).flat().filter(Boolean);
        if (flat.length > 0) return flat.join(' ');
    }

    return body?.message || `Download failed (${res.status})`;
}

function filenameFrom(header) {
    const match = header?.match(/filename\*?=(?:UTF-8'')?"?([^";]+)"?/i);

    return match ? decodeURIComponent(match[1]) : 'download';
}

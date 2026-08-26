/**
 * Kabar kegagalan unggah lampiran.
 *
 * Tiket dibuat lebih dulu, lampirannya menyusul satu per satu. Kegagalan di
 * tahap kedua dulu ditelan diam-diam supaya konfirmasi tiket tidak ikut
 * tertahan — dan memang tidak perlu tertahan, tiketnya sudah sah tersimpan.
 * Yang keliru adalah tidak memberi tahu: layar tetap berkata "Ticket …
 * submitted", lalu pengguna pergi mengira buktinya ikut terkirim padahal
 * tiketnya kosong.
 *
 * Pemicunya sempit tapi nyata — berkas yang di-rename, gambar korup, atau
 * berkas nol byte lolos pemeriksaan jenis di browser lalu ditolak server yang
 * membaca isinya. Justru karena sempit, tidak ada yang akan curiga.
 *
 * Nama berkasnya disebut, bukan hanya jumlahnya: pengguna perlu tahu MANA yang
 * harus dilampirkan ulang.
 */

/**
 * @param {{name: string, reason?: string}[]} failed
 * @returns {string} kosong bila tidak ada yang gagal
 */
export function attachmentFailureNotice(failed) {
    if (! Array.isArray(failed) || failed.length === 0) {
        return '';
    }

    const rincian = failed
        .map(({ name, reason }) => (reason ? `${name} — ${reason}` : name))
        .join('; ');

    const berkas = failed.length === 1 ? 'Satu lampiran' : `${failed.length} lampiran`;

    return `${berkas} tidak ikut terkirim: ${rincian} `
        + 'Tiketnya sudah tersimpan; silakan lampirkan ulang dari halaman tiket.';
}

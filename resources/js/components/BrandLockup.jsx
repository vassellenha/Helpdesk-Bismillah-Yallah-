import { resolveUrl } from '../lib/api';

/**
 * Lockup brand Helpdesk: logo Adhi Karya + wordmark.
 *
 * Kembaran React dari resources/views/partials/brand-lockup.blade.php — header
 * Team Lead dirender React, bukan Blade, jadi partial itu tidak bisa dipakai
 * dari sana. Kalau salah satunya diubah, ubah keduanya.
 *
 * Logonya file statis di public/images/adhi-karya-logo.svg — sudah berwarna
 * dan berdiri sendiri (bola merah + wordmark putih di dalamnya), jadi cukup
 * di-<img>, tidak perlu digambar ulang lewat gradien/mask seperti ikon tiket
 * versi sebelumnya.
 *
 * Definisi kelas .brand-lockup* ada di resources/css/app.css.
 *
 * Alamat logonya dilewatkan resolveUrl(), TIDAK ditulis "/images/..." begitu
 * saja. Versi Blade memakai asset() yang menghasilkan alamat absolut, dan
 * portal SINTA menulis ulang alamat semacam itu; alamat relatif di dalam
 * JavaScript tidak ikut tersapu, sehingga ia dibaca browser relatif terhadap
 * akar domain portal dan berakhir 404. Itulah kenapa logo sempat hilang HANYA
 * di header Team Lead — satu-satunya header yang dirender React.
 */
export default function BrandLockup() {
    return (
        <span className="brand-lockup">
            <img className="brand-lockup__icon" src={resolveUrl('/images/adhi-karya-logo.svg')} alt="Adhi Karya" />
            <span className="brand-lockup__word">Helpdesk</span>
        </span>
    );
}

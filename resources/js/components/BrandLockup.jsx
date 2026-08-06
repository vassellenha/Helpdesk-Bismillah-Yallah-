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
 */
export default function BrandLockup() {
    return (
        <span className="brand-lockup">
            <img className="brand-lockup__icon" src="/images/adhi-karya-logo.svg" alt="Adhi Karya" />
            <span className="brand-lockup__word">Helpdesk</span>
        </span>
    );
}

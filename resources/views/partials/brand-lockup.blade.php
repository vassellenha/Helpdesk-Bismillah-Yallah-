{{--
    Lockup brand Helpdesk: logo Adhi Karya + wordmark, untuk kiri atas tiap
    header role.

    Logonya file statis di public/images/adhi-karya-logo.svg — sudah berwarna
    dan berdiri sendiri (bola merah + wordmark putih di dalamnya), jadi cukup
    di-<img>, tidak perlu digambar ulang lewat gradien/mask seperti ikon tiket
    versi sebelumnya.

    Definisi kelas .brand-lockup* ada di resources/css/app.css.
    Kembarannya untuk React: resources/js/components/BrandLockup.jsx — ubah
    keduanya kalau salah satu berubah.
--}}
<span class="brand-lockup">
    <img class="brand-lockup__icon" src="{{ asset('images/adhi-karya-logo.svg') }}" alt="Adhi Karya">
    <span class="brand-lockup__word">Helpdesk</span>
</span>

/**
 * Keluar dari sesi — POST, bukan tautan.
 *
 * Menggantikan `<a href="/">Log out</a>` yang ada di tiga menu profil selagi
 * repo ini belum punya autentikasi: tautan itu hanya memuat ulang beranda dan
 * meninggalkan sesi tetap hidup.
 *
 * POST, karena logout mengubah keadaan di server. Sebagai GET ia bisa dipicu
 * apa pun yang memuat URL — sebuah <img> di halaman lain sudah cukup untuk
 * mengeluarkan orang dari sesinya.
 */
export default function LogoutButton({ className = '', label = 'Log out' }) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    return (
        <form method="POST" action="/logout">
            <input type="hidden" name="_token" value={csrf} />
            <button
                type="submit"
                className={
                    className ||
                    'flex w-full items-center gap-2.5 rounded-[9px] px-3 py-2.5 text-left text-[13px] font-semibold text-red-600 dark:text-bad-text hover:bg-red-50 dark:hover:bg-bad-soft'
                }
            >
                {label}
            </button>
        </form>
    );
}

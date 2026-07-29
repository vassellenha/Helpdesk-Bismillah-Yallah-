/*
 | Buka/tutup sidebar EVA.
 |
 | Keadaannya disimpan di localStorage, bukan di memori, karena EVA BUKAN SPA:
 | tiap menu adalah pemuatan halaman penuh. Keadaan yang hanya hidup di memori
 | akan kembali terbuka di setiap klik menu — tombolnya terasa rusak padahal
 | bekerja.
 |
 | Kelasnya menempel di <html>, bukan <body>, supaya bisa dipasang skrip di
 | <head> SEBELUM body dirender. Lihat komentar di layouts/eva.blade.php.
 */

export const SIDEBAR_STORAGE_KEY = 'eva.sidebar.collapsed';

const COLLAPSED_CLASS = 'eva-sidebar-collapsed';

/** Mode penyamaran/privat melempar saat localStorage disentuh — jangan sampai itu mematikan tombolnya. */
function remember(collapsed) {
    try {
        localStorage.setItem(SIDEBAR_STORAGE_KEY, collapsed ? '1' : '0');
    } catch {
        // Tanpa penyimpanan, sidebar tetap bisa dibuka-tutup — hanya tidak
        // diingat setelah pindah halaman. Itu jauh lebih baik daripada gagal.
    }
}

function describe(button, collapsed) {
    const label = collapsed ? 'Buka sidebar' : 'Tutup sidebar';

    button.setAttribute('aria-expanded', String(!collapsed));
    button.setAttribute('aria-label', label);
    button.setAttribute('title', label);
}

export function initSidebarToggle() {
    const button = document.querySelector('[data-eva-sidebar-toggle]');

    if (!button) return;

    describe(button, document.documentElement.classList.contains(COLLAPSED_CLASS));

    button.addEventListener('click', () => {
        const collapsed = document.documentElement.classList.toggle(COLLAPSED_CLASS);

        remember(collapsed);
        describe(button, collapsed);
    });
}

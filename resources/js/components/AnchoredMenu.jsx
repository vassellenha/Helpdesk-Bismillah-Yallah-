import { useEffect, useLayoutEffect, useRef, useState } from 'react';

/** Jarak minimal panel ke tepi layar, supaya tidak menempel mepet. */
const VIEWPORT_MARGIN = 8;

/** Jarak panel ke tombol pemicunya. */
const TRIGGER_GAP = 4;

/**
 * Menghitung posisi `fixed` sebuah panel yang digantung di bawah tombol,
 * dengan UKURAN PANEL YANG SUDAH TERUKUR — bukan angka tebakan.
 *
 * Tiga hal yang dijaga sekaligus:
 * (1) Kalau ruang di bawah tombol tidak cukup, panel dibalik ke ATAS tombol.
 *     Ini bug yang terlihat di baris terakhir tabel Service Catalog: menunya
 *     terbuka ke bawah, keluar dari layar, dan tidak bisa diklik sama sekali.
 * (2) Kalau kedua sisi sama-sama sempit, dipilih sisi yang lebih lega lalu
 *     posisinya dijepit masuk ke dalam layar — panel tetap terjangkau.
 * (3) Sisi kiri/kanan ikut dijepit, supaya panel tidak keluar lewat tepi
 *     samping pada layar sempit.
 */
export function clampMenuPosition(triggerRect, { width, height }) {
    const maxLeft = Math.max(VIEWPORT_MARGIN, window.innerWidth - width - VIEWPORT_MARGIN);
    const left = Math.min(Math.max(VIEWPORT_MARGIN, triggerRect.right - width), maxLeft);

    const spaceBelow = window.innerHeight - triggerRect.bottom - TRIGGER_GAP - VIEWPORT_MARGIN;
    const spaceAbove = triggerRect.top - TRIGGER_GAP - VIEWPORT_MARGIN;

    // Tetap di bawah selama muat. Kalau tidak, baru dibalik ke atas — dan
    // ketika dua-duanya tidak muat, menang yang ruangnya lebih besar.
    const openDownward = height <= spaceBelow || spaceBelow >= spaceAbove;
    const preferredTop = openDownward
        ? triggerRect.bottom + TRIGGER_GAP
        : triggerRect.top - height - TRIGGER_GAP;

    const maxTop = Math.max(VIEWPORT_MARGIN, window.innerHeight - height - VIEWPORT_MARGIN);
    const top = Math.min(Math.max(VIEWPORT_MARGIN, preferredTop), maxTop);

    // Batas tinggi hanya dipasang kalau panelnya memang lebih tinggi dari
    // ruang yang ada; isinya jadi bisa digulir alih-alih terpotong hilang.
    const available = Math.max(spaceBelow, spaceAbove);
    const maxHeight = height > available ? Math.max(120, available) : undefined;

    return { top, left, maxHeight };
}

/**
 * Panel mengambang yang digantung pada sebuah tombol pemicu.
 *
 * Komponen ini sengaja tidak mengatur tampilan isinya — `className` dioper
 * apa adanya oleh pemanggil — supaya bisa dipakai ulang oleh menu-menu yang
 * sudah punya gaya sendiri tanpa mengubah tampilannya sedikit pun. Yang
 * dipegang komponen ini cuma tiga hal yang selama ini disalin-tempel dan
 * salah di beberapa tempat: posisi yang tidak keluar layar, menutup saat
 * klik di luar / tekan Escape, dan mengikuti tombolnya saat halaman digulir.
 *
 * `anchorEl` berupa elemen DOM tombolnya, bukan hasil getBoundingClientRect().
 * Rect adalah potret sesaat: begitu halaman digulir, angkanya basi dan panel
 * mengambang di tempat yang salah. Dengan elemennya, posisi bisa diukur ulang
 * kapan pun dibutuhkan.
 */
export default function AnchoredMenu({ anchorEl, onClose, className = '', width = 176, children }) {
    const ref = useRef(null);
    const [position, setPosition] = useState(null);

    // useLayoutEffect, bukan useEffect: pengukuran dan koreksi posisi harus
    // selesai SEBELUM browser mengecat layar. Dengan useEffect, panel sempat
    // tergambar satu frame di posisi awal yang salah — terlihat sebagai kedip.
    useLayoutEffect(() => {
        if (! anchorEl) return;

        function reposition() {
            const panel = ref.current;
            if (! panel) return;

            setPosition(clampMenuPosition(anchorEl.getBoundingClientRect(), {
                width: panel.offsetWidth || width,
                height: panel.offsetHeight,
            }));
        }

        reposition();

        // `true` supaya guliran kontainer di dalam halaman ikut tertangkap,
        // bukan cuma guliran jendela.
        window.addEventListener('resize', reposition);
        window.addEventListener('scroll', reposition, true);
        return () => {
            window.removeEventListener('resize', reposition);
            window.removeEventListener('scroll', reposition, true);
        };
    }, [anchorEl, width]);

    useEffect(() => {
        function onClickOutside(e) {
            // Klik pada tombol pemicunya sendiri diabaikan di sini; tombol itu
            // yang menutup/membuka menu. Tanpa ini, klik tombol saat menu
            // terbuka akan menutup lalu langsung membuka lagi.
            if (anchorEl?.contains(e.target)) return;
            if (ref.current && ! ref.current.contains(e.target)) onClose();
        }
        function onEscape(e) {
            if (e.key === 'Escape') onClose();
        }
        document.addEventListener('mousedown', onClickOutside);
        document.addEventListener('keydown', onEscape);
        return () => {
            document.removeEventListener('mousedown', onClickOutside);
            document.removeEventListener('keydown', onEscape);
        };
    }, [anchorEl, onClose]);

    return (
        <div
            ref={ref}
            style={{
                position: 'fixed',
                width,
                top: position?.top ?? 0,
                left: position?.left ?? 0,
                maxHeight: position?.maxHeight,
                overflowY: position?.maxHeight ? 'auto' : undefined,
                // Sebelum terukur, panel sudah ada di DOM (supaya bisa diukur)
                // tapi belum boleh terlihat di posisi tebakan.
                visibility: position ? 'visible' : 'hidden',
            }}
            className={className}
        >
            {children}
        </div>
    );
}

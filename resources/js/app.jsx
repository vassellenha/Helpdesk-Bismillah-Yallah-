import { Component } from 'react';
import { createRoot } from 'react-dom/client';
import { registry } from './components/registry';
import { initSidebarToggle } from './lib/eva-sidebar';

/*
 | Penangkap galat untuk setiap pulau React.
 |
 | Tanpa ini, satu kesalahan saat render membuat React MELEPAS seluruh pulau:
 | area isinya berubah putih kosong, tanpa satu pun kalimat di layar. Yang
 | dilihat pengguna adalah "fiturnya hilang", dan satu-satunya petunjuk hanya
 | ada di console browser — tempat yang tidak pernah dibuka orang yang sedang
 | bekerja. Itu persis yang terjadi di produksi: satu balasan server yang bukan
 | JSON menjatuhkan layar User & Role Management sampai putih.
 |
 | Kerangka halaman (kop, navigasi, sidebar) dirender Blade dan tetap utuh,
 | jadi yang perlu diselamatkan hanya isi pulaunya.
 |
 | Ditulis sebagai class — React sampai hari ini tidak punya hook untuk
 | menangkap galat render, dan `componentDidCatch` hanya ada di class.
 */
class IslandErrorBoundary extends Component {
    state = { error: null };

    static getDerivedStateFromError(error) {
        return { error };
    }

    componentDidCatch(error, info) {
        // Tetap dicetak ke console: pesan di layar sengaja pendek supaya bisa
        // dibaca siapa pun, sedangkan jejak lengkapnya dibutuhkan yang menelusuri.
        console.error(`[helpdesk] Komponen "${this.props.name}" gagal dirender.`, error, info);
    }

    render() {
        if (!this.state.error) {
            return this.props.children;
        }

        return (
            <div style={{
                border: '1px solid rgb(186 26 24 / 0.25)',
                background: 'rgb(186 26 24 / 0.06)',
                borderRadius: '10px',
                padding: '16px 18px',
                fontSize: '13.5px',
                lineHeight: 1.65,
                color: 'inherit',
            }}>
                <div style={{ fontWeight: 700, marginBottom: '6px' }}>
                    Bagian ini gagal ditampilkan.
                </div>
                <div style={{ marginBottom: '10px' }}>
                    {this.state.error?.message || 'Terjadi kesalahan yang tidak terduga.'}
                </div>
                <button
                    type="button"
                    onClick={() => window.location.reload()}
                    style={{
                        border: 'none', borderRadius: '8px', padding: '7px 14px',
                        fontSize: '12.5px', fontWeight: 600, cursor: 'pointer',
                        background: 'rgb(0 102 255)', color: '#fff',
                    }}
                >
                    Muat ulang halaman
                </button>
            </div>
        );
    }
}

function mountIslands() {
    document.querySelectorAll('[data-react]').forEach((el) => {
        const name = el.getAttribute('data-react');
        const Component = registry[name];

        if (!Component) {
            console.warn(`[helpdesk] React component "${name}" is not registered.`);
            return;
        }

        const props = el.getAttribute('data-props')
            ? JSON.parse(el.getAttribute('data-props'))
            : {};

        createRoot(el).render(
            <IslandErrorBoundary name={name}>
                <Component {...props} />
            </IslandErrorBoundary>,
        );
    });
}

function boot() {
    mountIslands();
    // Aman dipanggil di halaman tim yang tidak punya sidebar EVA — keluar
    // sendiri kalau tombolnya tidak ada.
    initSidebarToggle();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}

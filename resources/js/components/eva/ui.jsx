/*
 | Primitif tampilan bersama konsol EVA.
 |
 | Memakai inline style dengan var(--token), bukan kelas Tailwind: token EVA
 | di-scope ke `.eva-app` di resources/css/eva.css, dan mencampur keduanya
 | membuat sumber warna jadi dua tempat. Satu konsep, satu sumber.
 */

import { useEffect, useState } from 'react';

/*
 | `maxWidth` dibaca dari custom property, bukan angka mati.
 |
 | Saat sidebar ditutup, batasnya melebar (lihat eva.css). Tanpa itu, menutup
 | sidebar hanya memindahkan 212px jadi ruang kosong di kanan — isinya sama
 | sekali tidak bertambah lega dan tombolnya terasa tidak melakukan apa-apa.
 */
export const PAGE = { padding: '26px 30px 44px', maxWidth: 'var(--eva-page-max, 1240px)' };

export function PageHeader({ title, subtitle, right }) {
    return (
        <div style={{ display: 'flex', alignItems: 'flex-end', gap: '16px', marginBottom: '20px' }}>
            <div style={{ flex: 1, minWidth: 0 }}>
                <h1 style={{ fontSize: '21px', fontWeight: 700, letterSpacing: '-.3px', margin: 0 }}>{title}</h1>
                {subtitle && (
                    <p style={{ fontSize: '13px', color: 'var(--slate-500)', margin: '5px 0 0' }}>{subtitle}</p>
                )}
            </div>
            {right}
        </div>
    );
}

export function Card({ children, style }) {
    return (
        <div
            style={{
                background: 'var(--white)',
                border: '1px solid var(--border)',
                borderRadius: 'var(--r-lg)',
                boxShadow: '0 1px 2px var(--shadow-05)',
                ...style,
            }}
        >
            {children}
        </div>
    );
}

export function CardTitle({ children, right }) {
    return (
        <div style={{ display: 'flex', alignItems: 'center', gap: '10px', padding: '15px 18px', borderBottom: '1px solid var(--border-soft)' }}>
            <div style={{ flex: 1, minWidth: 0, fontSize: '13.5px', fontWeight: 700 }}>{children}</div>
            {right}
        </div>
    );
}

export function StatTile({ label, value, hint, tone }) {
    return (
        <Card style={{ padding: '15px 17px' }}>
            <div style={{ fontSize: '11px', fontWeight: 600, letterSpacing: '.3px', color: 'var(--slate-500)' }}>{label}</div>
            <div style={{ fontSize: '25px', fontWeight: 700, letterSpacing: '-.6px', marginTop: '5px', color: tone || 'var(--ink-900)' }}>
                {value}
            </div>
            {hint && <div style={{ fontSize: '11.5px', color: 'var(--slate-500)', marginTop: '3px' }}>{hint}</div>}
        </Card>
    );
}

export function StatRow({ children, columns = 4 }) {
    return (
        <div style={{ display: 'grid', gridTemplateColumns: `repeat(${columns}, minmax(0, 1fr))`, gap: '12px', marginBottom: '18px' }}>
            {children}
        </div>
    );
}

export function Badge({ children, tone = 'neutral' }) {
    const tones = {
        neutral: { color: 'var(--slate-500)', background: 'var(--surface-tint)' },
        blue: { color: 'var(--blue-ink)', background: 'var(--blue-050)' },
        green: { color: 'var(--green-500)', background: 'var(--green-soft)' },
        amber: { color: 'var(--amber-ink)', background: 'var(--amber-soft)' },
        red: { color: 'var(--red-600)', background: 'var(--red-soft)' },
    };

    return (
        <span
            style={{
                fontSize: '11px',
                fontWeight: 700,
                padding: '3px 9px',
                borderRadius: '999px',
                whiteSpace: 'nowrap',
                ...tones[tone],
            }}
        >
            {children}
        </span>
    );
}

/** Saklar "Show in EVA" — gerbang yang menentukan materi ikut dijawab atau tidak. */
export function Toggle({ on, onChange, label }) {
    return (
        <button
            type="button"
            onClick={onChange}
            aria-pressed={on}
            aria-label={label}
            style={{
                width: '40px',
                height: '23px',
                borderRadius: '999px',
                border: 'none',
                padding: '2px',
                cursor: 'pointer',
                display: 'flex',
                justifyContent: on ? 'flex-end' : 'flex-start',
                background: on ? 'var(--green-solid)' : 'var(--border)',
                transition: 'background .18s ease',
            }}
        >
            <span style={{ width: '19px', height: '19px', borderRadius: '999px', background: 'var(--on-accent)', boxShadow: '0 1px 3px var(--shadow-25)' }} />
        </button>
    );
}

export const inputStyle = {
    width: '100%',
    padding: '9px 11px',
    fontSize: '13px',
    borderRadius: 'var(--r-md)',
    border: '1px solid var(--border)',
    background: 'var(--white)',
    color: 'var(--ink-900)',
    outline: 'none',
};

export const labelStyle = {
    display: 'block',
    fontSize: '11.5px',
    fontWeight: 600,
    color: 'var(--ink-700)',
    marginBottom: '5px',
};

export function Button({ children, onClick, variant = 'primary', type = 'button', disabled }) {
    const variants = {
        primary: { background: 'var(--blue-500)', color: 'var(--on-accent)', border: 'none' },
        ghost: { background: 'var(--white)', color: 'var(--ink-700)', border: '1px solid var(--border)' },
        danger: { background: 'var(--white)', color: 'var(--red-600)', border: '1px solid var(--border)' },
        // Merah penuh — untuk tindakan merusak yang harus terbaca sebagai
        // merusak sejak sebelum diklik.
        dangerPrimary: { background: 'var(--red-solid)', color: 'var(--on-accent)', border: 'none' },
    };

    return (
        <button
            type={type}
            onClick={onClick}
            disabled={disabled}
            style={{
                padding: '9px 15px',
                fontSize: '13px',
                fontWeight: 600,
                borderRadius: 'var(--r-md)',
                cursor: disabled ? 'not-allowed' : 'pointer',
                opacity: disabled ? 0.55 : 1,
                ...variants[variant],
            }}
        >
            {children}
        </button>
    );
}

export const thStyle = {
    textAlign: 'left',
    fontSize: '11px',
    fontWeight: 700,
    letterSpacing: '.4px',
    color: 'var(--slate-500)',
    padding: '10px 14px',
    borderBottom: '1px solid var(--border-soft)',
    whiteSpace: 'nowrap',
};

export const tdStyle = {
    fontSize: '13px',
    color: 'var(--ink-800)',
    padding: '12px 14px',
    borderBottom: '1px solid var(--border-soft)',
    verticalAlign: 'top',
};

/**
 * Pesan kosong yang menyebut SEBABNYA.
 *
 * "Belum ada data" tidak memberi tahu apa pun; yang berguna adalah tahu
 * apakah filternya terlalu sempit atau memang belum ada isinya.
 */
export function EmptyState({ children }) {
    return (
        <div style={{ padding: '34px 18px', textAlign: 'center', fontSize: '13px', color: 'var(--slate-500)' }}>
            {children}
        </div>
    );
}

/**
 * Dialog konfirmasi untuk tindakan yang tidak boleh terjadi karena salah klik.
 *
 * SENGAJA bukan window.confirm(): dialog bawaan browser tidak bisa
 * memperlihatkan APA yang sedang dihapus, dan tombolnya selalu berbunyi
 * "OK" — kata yang tidak memberi tahu apa pun tentang akibatnya.
 * Latar gelapnya bisa diklik untuk membatalkan, sama seperti menekan Batal.
 */
export function Modal({ title, children, onClose, width = '460px' }) {
    return (
        <div
            onClick={onClose}
            style={{
                position: 'fixed', inset: 0, zIndex: 60, display: 'flex',
                alignItems: 'center', justifyContent: 'center', padding: '20px',
                background: 'var(--overlay)',
            }}
        >
            <div
                onClick={(e) => e.stopPropagation()}
                role="dialog"
                aria-modal="true"
                style={{
                    width: '100%', maxWidth: width, background: 'var(--surface)',
                    borderRadius: '10px', boxShadow: '0 18px 48px var(--shadow-25)',
                    border: '1px solid var(--border-soft)', overflow: 'hidden',
                }}
            >
                <div style={{ padding: '16px 20px 0' }}>
                    <h3 style={{ margin: 0, fontSize: '14px', fontWeight: 700, color: 'var(--ink-900)' }}>{title}</h3>
                </div>
                {children}
            </div>
        </div>
    );
}

/** Banner galat yang tidak pernah menelan pesan dari server. */
export function ErrorBanner({ message, onDismiss }) {
    if (!message) return null;

    return (
        <div
            style={{
                display: 'flex',
                gap: '10px',
                alignItems: 'flex-start',
                background: 'var(--red-soft-weak)',
                border: '1px solid var(--red-border)',
                color: 'var(--red-600)',
                borderRadius: 'var(--r-md)',
                padding: '11px 14px',
                fontSize: '12.5px',
                marginBottom: '14px',
            }}
        >
            <span style={{ flex: 1 }}>{message}</span>
            <button
                type="button"
                onClick={onDismiss}
                style={{ border: 'none', background: 'none', color: 'inherit', cursor: 'pointer', fontWeight: 700 }}
            >
                ×
            </button>
        </div>
    );
}

export function coverageTone(percent) {
    if (percent >= 60) return 'var(--green-500)';
    if (percent >= 25) return 'var(--amber-500)';
    return 'var(--red-600)';
}

/*
 | ---------------------------------------------------------------------------
 | Pagination
 | ---------------------------------------------------------------------------
 |
 | Satu perilaku dipakai seluruh konsol, karena pagination yang berbeda-beda di
 | tiap layar membuat admin harus belajar ulang di setiap menu.
 |
 | Dua jebakan yang ditangani di sini, bukan diserahkan ke tiap layar:
 |
 |  1. **Menyaring saat berada di halaman 5.** Kalau hasil saringan tinggal 3
 |     baris, halaman 5 kosong — layar terlihat rusak padahal datanya ada.
 |     `resetKey` mengembalikan ke halaman 1 tiap saringan berubah.
 |  2. **Baris terakhir di halaman terakhir dihapus.** Jumlah halaman menyusut
 |     dan halaman yang sedang dibuka lenyap. `page` selalu dijepit ke jumlah
 |     halaman yang benar-benar ada, jadi tidak pernah menunjuk ke ruang kosong.
 */

/**
 * @param  {Array}  items  seluruh baris SETELAH disaring
 * @param  {number} pageSize
 * @param  {string} resetKey  gabungan nilai saringan; berubah = kembali ke hal. 1
 */
export function usePagination(items, pageSize, resetKey = '') {
    const [page, setPage] = useState(1);

    useEffect(() => {
        setPage(1);
    }, [resetKey]);

    const totalPages = Math.max(1, Math.ceil(items.length / pageSize));
    const current = Math.min(page, totalPages);
    const from = (current - 1) * pageSize;

    return {
        page: current,
        totalPages,
        setPage,
        total: items.length,
        from: items.length === 0 ? 0 : from + 1,
        to: Math.min(from + pageSize, items.length),
        slice: items.slice(from, from + pageSize),
    };
}

/**
 * Nomor halaman yang ditampilkan: selalu pertama, terakhir, dan sekitar yang
 * sedang dibuka. Sisanya diringkas jadi "…" supaya deretannya tidak melebar
 * tanpa batas saat halamannya puluhan.
 */
function pageWindow(page, totalPages) {
    const wanted = [1, totalPages, page, page - 1, page + 1]
        .filter((n) => n >= 1 && n <= totalPages)
        .sort((a, b) => a - b);

    const shown = [];

    for (const number of wanted) {
        const last = shown[shown.length - 1];

        if (last === number) continue;
        if (last !== undefined && number - last > 1) shown.push('…');

        shown.push(number);
    }

    return shown;
}

/**
 * @param  {boolean} compact  untuk panel sempit (master–detail): tanpa nomor
 */
export function Pagination({ page, totalPages, from, to, total, onPage, unit = 'baris', compact = false }) {
    if (total === 0) return null;

    return (
        <div
            style={{
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'space-between',
                gap: '10px',
                flexWrap: 'wrap',
                padding: compact ? '9px 12px' : '11px 16px',
                borderTop: '1px solid var(--border-soft)',
                background: 'var(--white)',
            }}
        >
            <span style={{ fontSize: '11.5px', color: 'var(--slate-500)' }}>
                {compact ? `${from}–${to} dari ${total}` : `Menampilkan ${from}–${to} dari ${total} ${unit}`}
            </span>

            {totalPages > 1 && (
                <div style={{ display: 'flex', alignItems: 'center', gap: '4px' }}>
                    <PageButton disabled={page === 1} onClick={() => onPage(page - 1)} label="Halaman sebelumnya">‹</PageButton>

                    {compact ? (
                        <span style={{ fontSize: '11.5px', color: 'var(--slate-500)', padding: '0 4px' }}>
                            {page} / {totalPages}
                        </span>
                    ) : (
                        pageWindow(page, totalPages).map((entry, index) =>
                            entry === '…' ? (
                                <span key={`gap-${index}`} style={{ fontSize: '12px', color: 'var(--slate-500)', padding: '0 2px' }}>…</span>
                            ) : (
                                <PageButton key={entry} active={entry === page} onClick={() => onPage(entry)}>
                                    {entry}
                                </PageButton>
                            ),
                        )
                    )}

                    <PageButton disabled={page === totalPages} onClick={() => onPage(page + 1)} label="Halaman berikutnya">›</PageButton>
                </div>
            )}
        </div>
    );
}

function PageButton({ children, onClick, disabled, active, label }) {
    return (
        <button
            type="button"
            onClick={onClick}
            disabled={disabled}
            aria-label={label}
            aria-current={active ? 'page' : undefined}
            style={{
                minWidth: '27px',
                padding: '4px 7px',
                fontSize: '12px',
                fontWeight: 600,
                lineHeight: 1.5,
                borderRadius: 'var(--r-md)',
                cursor: disabled ? 'default' : 'pointer',
                opacity: disabled ? 0.4 : 1,
                border: `1px solid ${active ? 'transparent' : 'var(--border)'}`,
                background: active ? 'var(--blue-500)' : 'var(--white)',
                color: active ? 'var(--on-accent)' : 'var(--ink-700)',
            }}
        >
            {children}
        </button>
    );
}

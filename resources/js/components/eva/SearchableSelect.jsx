import { useEffect, useMemo, useRef, useState } from 'react';
import { inputStyle } from './ui';

/**
 * Dropdown yang bisa dicari — pengganti <select> bawaan untuk daftar panjang.
 *
 * ALASAN: katalog layanan berisi 140+ subject. Pada <select> bawaan, satu-
 * satunya cara menemukan "Gagal sinkronisasi pada desktop" adalah menggulir
 * melewati puluhan subject SAP yang tidak ada hubungannya. Mengetik tiga huruf
 * jauh lebih cepat daripada menggulir, dan jumlah subject hanya akan bertambah.
 *
 * Ditulis mengikuti bahasa visual konsol EVA (gaya inline + variabel CSS),
 * BUKAN memakai ulang SearchableSelect milik NewTicketModal yang berbasis kelas
 * Tailwind — komponen itu akan tampak seperti benda asing di layar ini. Yang
 * dipakai ulang adalah perilakunya: klik untuk buka, ketik untuk menyaring,
 * klik di luar untuk menutup.
 */
export default function SearchableSelect({
    value,
    onChange,
    options,
    placeholder = '— belum tertaut —',
    searchPlaceholder = 'Ketik untuk mencari…',
    disabled = false,
    // Ditampilkan sebagai pilihan pertama untuk mengosongkan kembali. Diberi
    // null agar pemanggil yang memang tidak boleh kosong bisa mematikannya.
    clearLabel = '— belum tertaut —',
}) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const ref = useRef(null);

    useEffect(() => {
        function onClickOutside(e) {
            if (ref.current && !ref.current.contains(e.target)) setOpen(false);
        }
        document.addEventListener('mousedown', onClickOutside);
        return () => document.removeEventListener('mousedown', onClickOutside);
    }, []);

    // Perbandingan sengaja longgar (==): nilai dari <select> lama datang sebagai
    // string sedangkan id subject adalah angka, dan draft yang belum disunting
    // masih membawa bentuk lamanya.
    const selected = useMemo(
        () => options.find((o) => String(o.value) === String(value ?? '')),
        [options, value],
    );

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();

        return q === '' ? options : options.filter((o) => o.label.toLowerCase().includes(q));
    }, [options, query]);

    function pilih(v) {
        onChange(v);
        setOpen(false);
        setQuery('');
    }

    return (
        <div ref={ref} style={{ position: 'relative' }}>
            <button
                type="button"
                disabled={disabled}
                onClick={() => setOpen((v) => !v)}
                style={{
                    ...inputStyle,
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    gap: '8px',
                    textAlign: 'left',
                    cursor: disabled ? 'not-allowed' : 'pointer',
                    opacity: disabled ? 0.6 : 1,
                    color: selected ? 'var(--ink-900)' : 'var(--slate-500)',
                }}
            >
                <span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                    {selected ? selected.label : placeholder}
                </span>
                <span aria-hidden="true" style={{ flex: 'none', color: 'var(--slate-500)', fontSize: '11px' }}>▾</span>
            </button>

            {open && !disabled && (
                <div
                    style={{
                        position: 'absolute', left: 0, right: 0, top: 'calc(100% + 4px)', zIndex: 40,
                        background: 'var(--white)', border: '1px solid var(--border)',
                        borderRadius: 'var(--r-md)', boxShadow: '0 12px 32px -12px rgba(0,0,0,0.35)',
                        overflow: 'hidden',
                    }}
                >
                    <div style={{ padding: '8px', borderBottom: '1px solid var(--border)' }}>
                        <input
                            autoFocus
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder={searchPlaceholder}
                            style={{ ...inputStyle, padding: '7px 9px', fontSize: '12.5px' }}
                        />
                    </div>

                    <ul style={{ listStyle: 'none', margin: 0, padding: '4px 0', maxHeight: '240px', overflowY: 'auto' }}>
                        {clearLabel !== null && query.trim() === '' && (
                            <li>
                                <Pilihan label={clearLabel} aktif={!selected} muted onClick={() => pilih('')} />
                            </li>
                        )}

                        {filtered.map((o) => (
                            <li key={o.value}>
                                <Pilihan
                                    label={o.label}
                                    aktif={String(o.value) === String(value ?? '')}
                                    onClick={() => pilih(o.value)}
                                />
                            </li>
                        ))}

                        {filtered.length === 0 && (
                            <li style={{ padding: '14px 12px', textAlign: 'center', fontSize: '12px', color: 'var(--slate-500)' }}>
                                Tidak ada subject yang cocok dengan “{query}”.
                            </li>
                        )}
                    </ul>
                </div>
            )}
        </div>
    );
}

function Pilihan({ label, aktif, muted = false, onClick }) {
    return (
        <button
            type="button"
            onClick={onClick}
            style={{
                display: 'block', width: '100%', textAlign: 'left', border: 'none', cursor: 'pointer',
                padding: '8px 12px', fontSize: '12.5px',
                background: aktif ? 'var(--blue-050)' : 'transparent',
                color: aktif ? 'var(--blue-ink)' : muted ? 'var(--slate-500)' : 'var(--ink-900)',
                fontWeight: aktif ? 600 : 400,
            }}
        >
            {label}
        </button>
    );
}

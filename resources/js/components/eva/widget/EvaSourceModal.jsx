import { useEffect, useRef, useState } from 'react';
import { apiFetch } from '../../../lib/api';

/*
 | Popup isi materi yang dikutip sebuah jawaban EVA.
 |
 | Muncul saat judul sumber di bawah jawaban ditekan. Sebelum ini judul itu teks
 | mati: karyawan tahu panduannya bernama apa, tapi untuk membacanya ia harus
 | keluar dari percakapan dan mencarinya sendiri — dan sebagian besar tidak
 | melakukannya, sehingga jawaban ringkas EVA menjadi satu-satunya yang terbaca.
 |
 | Isinya diambil saat dibuka, BUKAN dititipkan lebih dulu di balasan /ask.
 | Satu jawaban bisa mengutip materi panjang, dan menyeret seluruh badan artikel
 | ke setiap balasan membuat percakapan biasa membayar ongkos sesuatu yang jarang
 | dibuka. Ini juga menjaga isinya tetap segar: artikel yang disunting admin
 | sepuluh menit lalu terbaca versi barunya.
 |
 | Dipakai widget portal MAUPUN EVA Preview. Keduanya menunjuk endpoint yang
 | sama persis — lihat alasannya di PreviewController.
 */

/**
 * Alamat materi disusun dari cetakan yang dikirim server ('…/__type__/__id__'),
 * bukan dirangkai sendiri di sini. Bentuk alamatnya milik daftar rute; klien
 * hanya mengisi lubangnya.
 */
function materialUrl(template, hit) {
    return template
        .replace('__type__', encodeURIComponent(hit.type))
        .replace('__id__', encodeURIComponent(String(hit.source_id)));
}

/**
 * Ringkasan artikel sering merupakan salinan persis paragraf pembuka badannya —
 * begitulah bentuknya ketika artikel lahir dari ekstraksi dokumen. Menampilkan
 * keduanya membuat kalimat yang sama muncul dua kali berturut-turut, dan itu
 * terbaca sebagai layar yang rusak, bukan sebagai ringkasan.
 *
 * Dibandingkan setelah spasi dipadatkan supaya beda satu enter saja tidak lolos
 * sebagai "berbeda".
 */
function isSummaryRedundant(summary, body) {
    if (!summary || !body) return false;

    const flat = (text) => text.replace(/\s+/g, ' ').trim().toLowerCase();

    return flat(body).startsWith(flat(summary));
}

export default function EvaSourceModal({ hit, endpoint, onClose }) {
    const [material, setMaterial] = useState(null);
    const [error, setError] = useState(null);
    const closeRef = useRef(null);

    useEffect(() => {
        // Dibatalkan saat popup ditutup sebelum jaringan sempat menjawab —
        // tanpa ini, setState menyusul ke komponen yang sudah tidak ada.
        let alive = true;

        setMaterial(null);
        setError(null);

        apiFetch(materialUrl(endpoint, hit))
            .then((data) => alive && setMaterial(data))
            .catch((e) => alive && setError(e.message || 'Materi tidak bisa dibuka.'));

        return () => {
            alive = false;
        };
    }, [endpoint, hit]);

    // Esc menutup popup. Widget sendiri juga menutup panelnya dengan Esc, jadi
    // penanganan di sini WAJIB menghentikan penyebaran: tanpa itu satu tekan Esc
    // menutup popup sekaligus seluruh percakapan di belakangnya.
    useEffect(() => {
        function onKeyDown(e) {
            if (e.key !== 'Escape') return;
            e.stopPropagation();
            onClose();
        }
        document.addEventListener('keydown', onKeyDown, true);
        return () => document.removeEventListener('keydown', onKeyDown, true);
    }, [onClose]);

    useEffect(() => {
        closeRef.current?.focus();
    }, []);

    return (
        <div className="eva-src-overlay" onClick={onClose}>
            <div
                className="eva-src-modal"
                role="dialog"
                aria-modal="true"
                aria-label={`Materi rujukan: ${hit.title}`}
                onClick={(e) => e.stopPropagation()}
            >
                <div className="eva-src-head">
                    <div>
                        <div className="eva-src-kind">
                            {hit.type === 'faq' ? 'FAQ' : 'Artikel'}
                        </div>
                        <h2 className="eva-src-title">{material?.title ?? hit.title}</h2>
                    </div>
                    <button
                        ref={closeRef}
                        type="button"
                        className="eva-src-close"
                        aria-label="Tutup materi"
                        onClick={onClose}
                    >
                        ×
                    </button>
                </div>

                <div className="eva-src-body">
                    {error && <div className="eva-src-error">{error}</div>}

                    {!material && !error && <div className="eva-src-muted">Memuat materi…</div>}

                    {material && (
                        <>
                            {material.subject && (
                                <div className="eva-src-subject">
                                    {material.subject.service} · {material.subject.subject}
                                </div>
                            )}

                            {/*
                              | Ringkasan ditampilkan terpisah di atas badan tulisan, dan hanya
                              | milik artikel — FAQ tidak punya. Sengaja tidak dipaksa ada:
                              | menampilkan blok ringkasan kosong membuat FAQ terlihat seperti
                              | artikel yang datanya gagal termuat.
                            */}
                            {material.summary && !isSummaryRedundant(material.summary, material.body) && (
                                <p className="eva-src-summary">{material.summary}</p>
                            )}

                            {/*
                              | Teks polos, bukan HTML. Isi materi ditulis admin dan sebagian
                              | lahir dari ekstraksi dokumen; merendernya sebagai markup berarti
                              | satu dokumen yang salah bisa menjalankan skrip di layar setiap
                              | karyawan. Baris barunya dijaga lewat CSS (white-space: pre-wrap).
                            */}
                            <div className="eva-src-text">{material.body}</div>
                        </>
                    )}
                </div>
            </div>
        </div>
    );
}

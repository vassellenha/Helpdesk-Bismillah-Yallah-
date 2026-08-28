import { useEffect, useRef, useState } from 'react';
import { apiFetch } from '../../../lib/api';
import { DocumentBody, describeDocument, fileMeta } from '../documentView';

/*
 | Popup DOKUMEN yang dikutip sebuah jawaban EVA.
 |
 | Muncul saat judul sumber di bawah jawaban ditekan. Sebelum ini judul itu teks
 | mati: karyawan tahu panduannya bernama apa, tapi untuk membacanya ia harus
 | keluar dari percakapan dan mencarinya sendiri — dan sebagian besar tidak
 | melakukannya, sehingga jawaban ringkas EVA menjadi satu-satunya yang terbaca.
 |
 | Yang ditampilkan adalah BERKAS ASLINYA, bukan artikel hasil ekstraksi.
 | Bedanya bukan kosmetik: artikel adalah salinan teks yang boleh disunting
 | admin, sedangkan yang dipercaya karyawan adalah SOP atau surat edaran
 | lengkap dengan kop, tabel, dan tanda tangannya. Artikel hanya dipakai
 | sebagai cadangan terakhir, dan saat itu terjadi popup mengatakannya.
 |
 | Isinya diambil saat dibuka, BUKAN dititipkan lebih dulu di balasan /ask.
 | Satu jawaban bisa mengutip materi panjang, dan menyeret seluruh badannya ke
 | setiap balasan membuat percakapan biasa membayar ongkos sesuatu yang jarang
 | dibuka. Ini juga menjaga isinya tetap segar: dokumen yang diindeks ulang
 | sepuluh menit lalu terbaca versi barunya.
 |
 | Dipakai widget portal MAUPUN EVA Preview. Keduanya menunjuk endpoint yang
 | sama persis — lihat alasannya di PreviewController.
 */

/**
 * Alamat materi disusun dari cetakan yang dikirim server ('…/__type__/__id__'),
 * bukan dirangkai sendiri di sini. Bentuk alamatnya milik daftar rute; klien
 * hanya mengisi lubangnya.
 *
 * Alamat BERKAS tidak disusun begini — ia datang jadi (document.file_url) dari
 * server, karena hanya server yang tahu dokumen mana yang berkasnya benar-benar
 * ada di disk.
 */
function materialUrl(template, hit) {
    return template
        .replace('__type__', encodeURIComponent(hit.type))
        .replace('__id__', encodeURIComponent(String(hit.source_id)));
}

/**
 * Ringkasan artikel sering merupakan salinan persis paragraf pembuka isinya —
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

/**
 * Label di atas judul menyebut ASAL isinya, bukan bentuk yang kebetulan sedang
 * dirender. Selama materinya lahir dari sebuah dokumen, yang dibuka karyawan
 * adalah dokumen itu — walau berkasnya tidak tersimpan dan yang terbaca
 * tinggal teksnya. Keterangan kuning di bawahnya yang menjelaskan bedanya;
 * menukar labelnya jadi "Artikel" justru membuat judul dan baris keterangan
 * berkas di sebelahnya saling berdebat.
 */
function kindLabel(source, hit) {
    if (source.document) return 'Dokumen';

    return hit.type === 'faq' ? 'FAQ' : 'Artikel';
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
            .catch((e) => alive && setError(e.message || 'Dokumen tidak bisa dibuka.'));

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

    /*
     | Badan artikel dititipkan sebagai CADANGAN TERAKHIR, dipakai hanya bila
     | dokumennya sendiri tidak punya teks. Konsol admin tidak mengirim apa pun
     | di posisi ini — di sana tidak ada artikel yang bisa dipinjam.
    */
    const source = material ? describeDocument(material.document ?? null, material.body) : null;
    const document_ = source?.document ?? null;

    return (
        <div className="eva-src-overlay" onClick={onClose}>
            <div
                className="eva-src-modal"
                /*
                 | Pratinjau berkas butuh ruang jauh lebih besar daripada teks:
                 | halaman A4 yang diperas ke lebar 560px membuat hurufnya tidak
                 | terbaca, dan orang menutup popup lalu mengunduh — persis yang
                 | ingin dihindari fitur ini.
                 */
                data-wide={source?.mode === 'preview' ? 'yes' : 'no'}
                role="dialog"
                aria-modal="true"
                aria-label={`Dokumen rujukan: ${hit.title}`}
                onClick={(e) => e.stopPropagation()}
            >
                <div className="eva-src-head">
                    <div className="eva-src-heading">
                        <div className="eva-src-kind">{source ? kindLabel(source, hit) : 'Rujukan'}</div>
                        <h2 className="eva-src-title">{document_?.name ?? material?.title ?? hit.title}</h2>
                        {document_ && fileMeta(document_, source.mode === 'preview') && (
                            <div className="eva-src-meta">
                                {fileMeta(document_, source.mode === 'preview')}
                            </div>
                        )}
                    </div>
                    <button
                        ref={closeRef}
                        type="button"
                        className="eva-src-close"
                        aria-label="Tutup dokumen"
                        onClick={onClose}
                    >
                        ×
                    </button>
                </div>

                <div className="eva-src-body">
                    {error && <div className="eva-src-error">{error}</div>}

                    {!material && !error && <div className="eva-src-muted">Memuat dokumen…</div>}

                    {material && source && (
                        <>
                            {material.subject && (
                                <div className="eva-src-subject">
                                    {material.subject.service} · {material.subject.subject}
                                </div>
                            )}

                            {/*
                              | Berkas, keterangan, lalu teks — dirender penampil yang
                              | SAMA dengan pratinjau di konsol admin, supaya apa yang
                              | dilihat karyawan di sini tidak pernah berbeda dari yang
                              | dilihat admin saat memeriksanya.
                            */}
                            {/*
                              | Materi yang memang tidak punya dokumen — FAQ, atau artikel
                              | yang berkas sumbernya sudah dihapus — TIDAK diberi
                              | keterangan apa pun. Itu urusan pembukuan Knowledge Base,
                              | dan karyawan yang sedang mencari cara reset password tidak
                              | bisa menindaklanjutinya; yang tersisa hanya kesan bahwa
                              | ada sesuatu yang rusak. Keterangan yang tetap ada adalah
                              | yang bisa ditindaklanjuti: berkas asli yang tidak
                              | tersimpan, atau format yang harus diunduh dulu.
                            */}
                            <DocumentBody source={source}>
                                {/*
                                  | Ringkasan hanya milik ARTIKEL, dan hanya tampil saat
                                  | tidak ada dokumen di belakangnya.
                                  |
                                  | Ia dibuat dengan memotong 240 karakter pertama isi
                                  | dokumen, jadi di layar yang menampilkan dokumennya ia
                                  | selalu jadi paragraf pembuka yang diulang — kali ini
                                  | dengan titik-titik di ujungnya, sehingga bahkan tidak
                                  | tertangkap sebagai pengulangan.
                                */}
                                {source.text && !source.document && material.summary
                                    && !isSummaryRedundant(material.summary, source.text) && (
                                    <p className="eva-src-summary">{material.summary}</p>
                                )}
                            </DocumentBody>
                        </>
                    )}
                </div>
            </div>
        </div>
    );
}

/*
 | Cara sebuah DOKUMEN ditampilkan — dipakai bersama oleh dua layar.
 |
 | Popup rujukan yang dibuka karyawan dari jawaban EVA, dan pratinjau yang
 | dibuka admin di layar Documents, menampilkan benda yang sama. Aturannya juga
 | harus sama: PDF dibingkai, gambar dipasang apa adanya, format lain ditawarkan
 | diunduh, dan yang berkasnya tidak ada jatuh ke teks disertai keterangan.
 |
 | Ditulis dua kali, keduanya akan melenceng — dan yang paling mungkin melenceng
 | justru keterangannya, sehingga admin melihat dokumen "lengkap" di konsolnya
 | sementara karyawan melihat kalimat bahwa berkasnya tidak tersimpan.
 */

/**
 * Popup ini sedang menampilkan APA.
 *
 * Empat keadaan, dan semuanya nyata di data yang ada — karena itu tidak boleh
 * disamakan:
 *   preview  → berkas ada dan bisa dirender browser (PDF, gambar)
 *   download → berkas ada tapi browser tidak bisa merendernya (DOCX)
 *   text     → dokumennya ada tapi berkasnya tidak pernah tersimpan
 *   text     → materinya memang bukan turunan dokumen (FAQ)
 *
 * Keterangan di tiap keadaan ditulis eksplisit. Menampilkan teks hasil bacaan
 * tanpa mengatakan itu bukan berkas aslinya adalah cara tercepat membuat orang
 * mengira dokumen resminya memang setipis itu.
 *
 * @param fallbackText  Dipakai HANYA bila dokumennya sendiri tidak punya teks —
 *                      badan artikel di popup rujukan, dan null di konsol admin
 *                      (di sana tidak ada artikel yang bisa dipinjam).
 */
export function describeDocument(document, fallbackText = null) {
    const documentText = document?.text?.trim() ? document.text : null;

    if (document?.is_previewable) {
        return { mode: 'preview', document, notice: null, text: null };
    }

    // Teks dokumen didahulukan atas badan artikel di SEMUA cadangan: ia isi
    // dokumennya apa adanya, sedangkan artikel sudah boleh disunting admin.
    const text = documentText ?? fallbackText;

    if (document?.has_file) {
        return {
            mode: 'download',
            document,
            notice: `Berkas ${document.extension} tidak bisa ditampilkan langsung di browser. `
                + (documentText
                    ? 'Unduh untuk membuka dokumen aslinya — di bawah ini isi dokumen yang terbaca sistem.'
                    : 'Unduh untuk membuka dokumen aslinya.'),
            text,
        };
    }

    if (document) {
        return {
            mode: 'text',
            document,
            notice: documentText
                ? 'Berkas asli dokumen ini tidak tersimpan di server. Yang ditampilkan isi dokumen yang terbaca sistem.'
                : 'Berkas asli dokumen ini tidak tersimpan di server, dan isinya belum terbaca.',
            text,
        };
    }

    return { mode: 'text', document: null, notice: null, text: fallbackText };
}

/**
 * "PDF · 1.251 KB · 5 halaman" — baris keterangan BERKAS.
 *
 * Kosong bila berkasnya memang tidak ada, dan itu bukan penghematan tempat:
 * seluruh baris ini menggambarkan sebuah berkas. Dokumen yang isinya diketik
 * admin tetap membawa label format ('PDF') dan `size_bytes` yang sebenarnya
 * panjang teksnya, jadi menampilkannya menjanjikan sebuah PDF 1 KB tepat di
 * atas kalimat yang berkata berkasnya tidak tersimpan — dan yang dipercaya
 * pembaca adalah baris yang di atas.
 *
 * Jumlah halaman juga disembunyikan saat berkasnya dipratinjau: `page_count`
 * adalah TAKSIRAN dari panjang teks (lihat DocumentIndexer), sedangkan penampil
 * PDF di bawahnya menyebut jumlah halaman yang sebenarnya. Dua angka berbeda
 * yang bersebelahan di layar yang sama membuat keduanya tidak dipercaya.
 */
export function fileMeta(document, isPreviewing) {
    if (!document?.has_file) return null;

    const pageCount = !isPreviewing && document.preview_as !== 'image' && document.page_count;

    return [
        document.extension,
        document.size_kb > 0 ? `${document.size_kb.toLocaleString('id-ID')} KB` : null,
        pageCount ? `${document.page_count} halaman` : null,
    ].filter(Boolean).join(' · ');
}

/**
 * Badan dokumennya: berkas, keterangan, lalu teks.
 *
 * Sengaja TANPA kerangka popup (judul, tombol tutup, latar) — kedua layar
 * membungkusnya dengan kerangkanya masing-masing, dan yang wajib sama hanyalah
 * isinya.
 */
export function DocumentBody({ source, children = null }) {
    const { document, mode, notice, text } = source;

    return (
        <>
            {mode === 'preview' && (
                <>
                    {/*
                      | Berkasnya dirender browser sendiri, di alamat kita sendiri —
                      | bukan lewat penampil pihak ketiga. SOP internal tidak boleh
                      | singgah di server orang lain hanya supaya bisa dilihat.
                      |
                      | Gambar dipasang sebagai <img>, bukan dijejalkan ke dalam
                      | bingkai yang sama dengan PDF: di dalam iframe ia jadi halaman
                      | abu-abu berisi satu gambar mentah, tidak ikut menyesuaikan
                      | lebar popup, dan tidak punya teks alternatif untuk pembaca
                      | layar.
                    */}
                    {document.preview_as === 'image' ? (
                        <img
                            className="eva-src-image"
                            src={document.file_url}
                            alt={`Dokumen ${document.name}`}
                        />
                    ) : (
                        <iframe
                            className="eva-src-frame"
                            src={document.file_url}
                            title={`Dokumen ${document.name}`}
                        />
                    )}
                    <div className="eva-src-actions">
                        <a className="eva-src-action" href={document.file_url} target="_blank" rel="noreferrer">
                            Buka di tab baru
                        </a>
                        <span className="eva-src-muted">{document.filename}</span>
                    </div>
                </>
            )}

            {mode === 'download' && (
                <div className="eva-src-file">
                    <div className="eva-src-file-name">{document.filename}</div>
                    <a className="eva-src-action" href={document.file_url} target="_blank" rel="noreferrer">
                        Unduh dokumen asli
                    </a>
                </div>
            )}

            {notice && <div className="eva-src-notice">{notice}</div>}

            {children}

            {/*
              | Teks polos, bukan HTML. Isinya lahir dari ekstraksi dokumen;
              | merendernya sebagai markup berarti satu dokumen yang salah bisa
              | menjalankan skrip di layar setiap karyawan. Baris barunya dijaga
              | lewat CSS (white-space: pre-wrap).
            */}
            {text && <div className="eva-src-text">{text}</div>}
        </>
    );
}

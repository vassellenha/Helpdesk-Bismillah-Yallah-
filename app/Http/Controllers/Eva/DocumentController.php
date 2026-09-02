<?php

namespace App\Http\Controllers\Eva;

use App\Http\Controllers\Controller;
use App\Jobs\Knowledge\IndexDocument;
use App\Models\Knowledge\Document;
use App\Services\Knowledge\DocumentTextExtractor;
use App\Services\Knowledge\TagRegistry;
use App\Support\CurrentActor;
use App\Support\Eva\CatalogOptions;
use App\Support\Eva\SourceDocument;
use App\Support\KnowledgeAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Documents — hulu Knowledge Base.
 *
 * Mengunggah dokumen di sini otomatis melahirkan satu artikel draf lewat
 * DocumentIndexer. Indeks ulang memperbarui artikel yang sama, tidak
 * menggandakannya.
 *
 * Berkas yang diunggah disimpan utuh di disk privat. Pembacaan isinya —
 * termasuk OCR PDF yang bisa memakan menit — dikerjakan IndexDocument di
 * ANTREAN, jadi store() membalas 202 dengan dokumen berstatus `processing`.
 * Format yang mustahil dibaca sendiri (XLSX) tetap ditolak seketika: itu sudah
 * diketahui tanpa membuka berkasnya.
 */
class DocumentController extends Controller
{
    /**
     * Ekstensi yang boleh dicatat. Dibatasi supaya label tipe tidak liar.
     *
     * Gambar raster ikut karena foto surat edaran adalah bentuk "dokumen" yang
     * paling banyak beredar di grup kerja, dan Tesseract yang sudah dipakai
     * membaca PDF pindaian membacanya dengan jalur yang sama persis.
     *
     * SVG SENGAJA tidak ada di sini, dan jangan ditambahkan: ia markup yang
     * bisa memuat skrip, sedangkan berkas rujukan disajikan inline ke layar
     * setiap karyawan lewat endpoint dokumen EVA.
     */
    private const ALLOWED_EXTENSIONS = ['PDF', 'DOCX', 'XLSX', 'TXT', 'MD', 'PNG', 'JPG', 'JPEG'];

    /** Batas ukuran unggahan, dalam kilobyte. */
    private const MAX_UPLOAD_KB = 20480;

    /** Folder di disk privat. Bukan public — isi SOP internal tidak boleh terjangkau web. */
    private const STORAGE_FOLDER = 'kb-documents';

    public function __construct(
        private readonly TagRegistry $tags,
        private readonly DocumentTextExtractor $extractor,
    ) {}

    public function index(): View
    {
        $documents = Document::with(['catalogSubject:id,name', 'uploader:id,name', 'article:id,source_document_id,title'])
            ->withCount('chunks')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Document $document) => $this->present($document));

        return view('eva.documents', [
            'documents' => $documents,
            'subjects' => CatalogOptions::all(),
            'extensions' => self::ALLOWED_EXTENSIONS,
            // Daftar format yang isinya terbaca sendiri datang dari
            // DocumentTextExtractor, bukan diketik ulang di komponen — kalau
            // ditulis dua kali, layar akan menjanjikan pembacaan otomatis yang
            // ternyata tidak terjadi.
            'readableExtensions' => array_values(array_filter(
                self::ALLOWED_EXTENSIONS,
                fn (string $ext) => $this->extractor->canRead($ext),
            )),
            'tags' => $this->tags->tagsFor(Document::class),
            // Daftar format gambar diturunkan dari ekstraktor, bukan diketik
            // ulang: layar memakainya untuk mengingatkan bahwa hasil bacaan
            // FOTO bergantung ketajaman gambarnya — peringatan yang tidak
            // berlaku untuk PDF digital maupun DOCX.
            'imageExtensions' => DocumentTextExtractor::IMAGE_EXTENSIONS,
            // Tag aktif datang dari query string supaya tautan dari layar
            // Category & Taxonomy langsung membuka daftar yang sudah tersaring.
            'activeTag' => request()->query('tag'),
            // Kartu statistik sengaja TIDAK dikirim dari sini: status berubah
            // sendiri selagi halaman terbuka (indexing jalan di antrean), jadi
            // angka yang dibekukan saat halaman dimuat akan berdebat dengan
            // tabel di bawahnya. Komponen menghitungnya dari daftar yang tampil.
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => [
                'nullable', 'file', 'max:'.self::MAX_UPLOAD_KB,
                'extensions:'.mb_strtolower(implode(',', self::ALLOWED_EXTENSIONS)),
            ],
            // Wajib hanya kalau tidak ada berkas — nama diambil dari nama berkas.
            'name' => 'required_without:file|nullable|string|max:255',
            'extension' => ['required_without:file', 'nullable', Rule::in(self::ALLOWED_EXTENSIONS)],
            'extracted_text' => 'required_without:file|nullable|string',
            'catalog_subject_id' => ['nullable', 'integer', Rule::exists('service_catalog_subjects', 'id')],
            'tags' => 'nullable|string|max:255',
        ]);

        $file = $request->file('file');
        $extension = $file !== null
            ? $this->extensionOf($file)
            : $data['extension'];

        $text = $data['extracted_text'] ?? null;

        // Format yang MUSTAHIL dibaca sendiri (XLSX, atau PDF di server yang
        // binari OCR-nya belum terpasang) ditolak sekarang juga. Ini sudah
        // diketahui tanpa membuka berkasnya, jadi menerimanya lalu
        // menggagalkannya diam-diam beberapa detik kemudian cuma menukar
        // kalimat yang menjelaskan langkah berikutnya dengan lencana merah.
        if (blank($text) && $file !== null && ! $this->extractor->canRead($extension)) {
            throw ValidationException::withMessages([
                'extracted_text' => sprintf(
                    'Isi berkas %s belum bisa dibaca otomatis. Salin teksnya ke kolom isi dokumen — '
                    .'berkasnya tetap tersimpan dan bisa dibaca ulang bila ekstraksi %s diaktifkan nanti.',
                    $extension,
                    $extension,
                ),
            ]);
        }

        // store() memakai nama acak, BUKAN nama kiriman klien — nama seperti
        // "../../.env" tidak boleh menentukan lokasi tulis.
        $storagePath = $file?->store(self::STORAGE_FOLDER);

        $document = Document::create([
            'name' => $data['name'] ?? $this->nameFromFile($file),
            'extension' => $extension,
            // Sengaja belum diisi kalau berkasnya yang jadi sumber: pembacaan
            // (termasuk OCR) milik antrean, bukan request ini.
            'extracted_text' => $text,
            'original_filename' => $file?->getClientOriginalName(),
            'storage_path' => $storagePath,
            'size_bytes' => $file?->getSize() ?? mb_strlen((string) $text),
            'catalog_subject_id' => $data['catalog_subject_id'] ?? null,
            'tags' => $data['tags'] ?? null,
            'status' => Document::STATUS_PROCESSING,
            'uploaded_by' => CurrentActor::admin()->id,
        ]);

        KnowledgeAudit::record('create', 'document', $document->id, $document->name,
            "mengunggah dokumen \"{$document->name}\".",
            ['berkas' => $document->original_filename, 'ukuran_byte' => $document->size_bytes]);

        return $this->queueIndexing($document);
    }

    /**
     * 202, bukan 201/200: pekerjaannya DITERIMA, belum selesai. Layar Documents
     * menanyakan hasilnya lewat show() sampai statusnya berhenti `processing`.
     */
    private function queueIndexing(Document $document): JsonResponse
    {
        IndexDocument::dispatch($document->id);

        return response()->json($this->presentFresh($document), 202);
    }

    /** Satu dokumen, untuk polling status dari layar Documents. */
    public function show(Document $document): JsonResponse
    {
        return response()->json($this->presentFresh($document));
    }

    private function nameFromFile(UploadedFile $file): string
    {
        return mb_substr(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME), 0, 255);
    }

    /**
     * Ekstensi ditentukan dari ISI berkas, bukan dari nama kiriman klien.
     *
     * Nama berkas dipilih penggunanya sendiri, jadi memakainya sebagai penentu
     * cara membaca berarti pengunggah yang memutuskan parser mana yang jalan.
     * Ganti nama satu berkas dan aturan `extensions:` di atas ikut tertipu —
     * ia juga hanya membaca nama.
     *
     * Tiga kemungkinan, tiga sikap:
     *   - Tertebak dan diizinkan → itu yang dipakai, apa pun kata namanya.
     *   - Tertebak tapi TIDAK diizinkan → ditolak; ini kasus berkas yang
     *     menyamar (mis. biner berlabel .txt).
     *   - Tidak tertebak (DOCX/XLSX kerap terbaca sekadar "zip") → jatuh ke
     *     nama berkas, karena menolak Office yang sah jauh lebih merugikan
     *     daripada menerimanya.
     */
    private function extensionOf(UploadedFile $file): string
    {
        $diklaim = mb_strtoupper($file->getClientOriginalExtension());
        $tertebak = mb_strtoupper((string) $file->guessExtension());

        if ($tertebak === '' || $tertebak === 'ZIP') {
            return $diklaim;
        }

        if (! in_array($tertebak, self::ALLOWED_EXTENSIONS, true)) {
            throw ValidationException::withMessages([
                'file' => sprintf(
                    'Isi berkas terbaca sebagai %s, bukan %s seperti nama berkasnya. '
                    .'Unggah berkas dengan format yang sesuai isinya.',
                    $tertebak,
                    $diklaim !== '' ? $diklaim : 'format yang diizinkan',
                ),
            ]);
        }

        return $tertebak;
    }

    /**
     * Indeks ulang. Aman dijalankan berkali-kali: potongan lama dihapus dulu
     * dan artikelnya dicari lewat source_document_id, bukan dibuat baru.
     *
     * Dokumen dikembalikan ke `processing` lebih dulu supaya lencana di layar
     * tidak tetap menunjukkan hasil indexing yang LAMA selagi yang baru
     * dikerjakan — termasuk `failed` yang sebenarnya sedang dicoba ulang.
     */
    public function reindex(Document $document): JsonResponse
    {
        $document->update([
            'status' => Document::STATUS_PROCESSING,
            'failure_reason' => null,
        ]);

        KnowledgeAudit::record('reindex', 'document', $document->id, $document->name,
            "meminta indeks ulang dokumen \"{$document->name}\".");

        return $this->queueIndexing($document);
    }

    /**
     * Isi dokumen dan berkas aslinya — dibaca layar Documents saat admin
     * menekan Pratinjau atau Sunting.
     *
     * Terpisah dari show(), yang dipanggil BERULANG selama masih ada dokumen
     * `processing`. Menitipkan isi dokumen di sana berarti setiap denyut polling
     * menyeret seluruh teks SOP melewati jaringan setiap tiga detik, untuk
     * sesuatu yang hanya dibaca saat sebuah tombol ditekan.
     *
     * Bentuknya sama persis dengan yang dibaca popup rujukan EVA — satu
     * penampil di layar melayani keduanya, jadi apa yang dilihat admin di sini
     * tidak pernah berbeda dari yang dilihat karyawan di sana.
     */
    public function content(Document $document): JsonResponse
    {
        return response()->json([
            'document' => SourceDocument::present($document, SourceDocument::ROUTE_CONSOLE),
        ]);
    }

    /**
     * Berkas asli, untuk dibaca admin di konsol.
     *
     * Gerbangnya SENGAJA cuma `role:eva` dari grup rutenya — tidak ada syarat
     * artikelnya harus siap-jawab seperti di endpoint kembarannya untuk
     * karyawan. Dokumen yang artikelnya masih draf, atau yang indexing-nya
     * gagal, justru yang paling perlu dibuka: admin membukanya untuk mencari
     * tahu kenapa gagal.
     */
    public function file(Document $document): StreamedResponse
    {
        abort_unless($document->hasFile(), 404, 'Dokumen ini tidak punya berkas asli.');

        abort_unless(
            Storage::disk('local')->exists((string) $document->storage_path),
            404,
            'Berkas dokumen tidak ada di penyimpanan.',
        );

        $filename = SourceDocument::filename($document);

        return Storage::disk('local')->response((string) $document->storage_path, $filename, [
            'Content-Disposition' => 'inline; filename="'.addslashes($filename).'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * Menyunting keterangan dokumen — dan, bila diminta, ISINYA.
     *
     * Kolom isi itulah yang selama ini hilang. Ketika OCR gagal membaca sebuah
     * berkas, dokumennya ditandai `failed` dengan kalimat "salin teksnya ke
     * kolom isi, lalu indeks ulang" — kalimat yang menunjuk ke kolom yang tidak
     * pernah ada. Satu-satunya jalan keluar adalah menghapus dokumennya lalu
     * mengunggah ulang, dan berkas aslinya ikut terbuang bersamanya.
     *
     * Mengubah isi berarti MENGINDEKS ULANG: potongan pencarian lahir dari teks
     * ini, dan membiarkannya berbeda dari isi yang baru berarti EVA mencari di
     * kalimat yang sudah tidak ada lagi di dokumennya.
     *
     * Badan ARTIKEL tidak ikut tertimpa — itu aturan DocumentIndexer yang sudah
     * lama berlaku dan sengaja dipertahankan: artikel jadi milik admin begitu ia
     * menyuntingnya. Yang paling diuntungkan justru dokumen GAGAL, yang belum
     * punya artikel sama sekali: isi yang diketik di sinilah yang melahirkannya,
     * dan itulah yang membuat foto tak terbaca tetap bisa jadi rujukan.
     *
     * MENGOSONGKAN kolom isi bukan berarti "dokumen tanpa isi", melainkan
     * "baca ulang berkasnya" — IndexDocument mendahulukan teks tersimpan dan
     * baru turun ke ekstraksi ketika teksnya kosong. Itu jalan kembali bagi
     * admin yang terlanjur menempel teks yang salah.
     */
    public function update(Request $request, Document $document): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'catalog_subject_id' => ['nullable', 'integer', Rule::exists('service_catalog_subjects', 'id')],
            'is_eva_visible' => 'required|boolean',
            'tags' => 'nullable|string|max:255',
            // Tidak dikirim sama sekali = tidak disentuh. Dikirim kosong =
            // permintaan membaca ulang berkasnya. Keduanya harus terbedakan,
            // jadi jangan menggantinya dengan `?? ''`.
            'extracted_text' => 'sometimes|nullable|string',
        ]);

        $isiBerubah = $request->has('extracted_text')
            && (string) $data['extracted_text'] !== (string) $document->extracted_text;

        $document->update(array_merge(
            Arr::except($data, ['extracted_text']),
            $isiBerubah ? ['extracted_text' => $data['extracted_text']] : [],
        ));

        KnowledgeAudit::record('update', 'document', $document->id, $document->name,
            "mengubah dokumen \"{$document->name}\".", ['isi_berubah' => $isiBerubah]);

        if (! $isiBerubah) {
            return response()->json($this->presentFresh($document));
        }

        $document->update([
            'status' => Document::STATUS_PROCESSING,
            'failure_reason' => null,
        ]);

        return $this->queueIndexing($document);
    }

    /**
     * Menghapus dokumen berikut berkas aslinya di disk.
     *
     * Potongan ikut terhapus lewat cascade. Artikel turunannya SENGAJA tidak:
     * `source_document_id` dibuat nullOnDelete, karena isi artikel sudah jadi
     * milik admin begitu ia menyuntingnya — menghapus berkas sumber tidak boleh
     * diam-diam membuang pekerjaan itu. Artikelnya tetap hidup dan tetap
     * menjawab, hanya kehilangan jejak asal-usulnya.
     *
     * Berkas di disk dihapus di sini karena tidak ada yang lain yang akan
     * melakukannya: berkas yatim tidak memunculkan error apa pun, ia hanya
     * menumpuk sampai disk penuh.
     */
    public function destroy(Document $document): JsonResponse
    {
        KnowledgeAudit::record('delete', 'document', $document->id, $document->name,
            "menghapus dokumen \"{$document->name}\".", ['status' => $document->status]);

        if ($document->storage_path !== null) {
            Storage::delete($document->storage_path);
        }

        $document->delete();

        return response()->json(['deleted' => true]);
    }

    private function presentFresh(Document $document): array
    {
        return $this->present(
            Document::with(['catalogSubject:id,name', 'uploader:id,name', 'article:id,source_document_id,title'])
                ->withCount('chunks')
                ->findOrFail($document->id)
        );
    }

    private function present(Document $document): array
    {
        return [
            'id' => $document->id,
            'name' => $document->name,
            'extension' => $document->extension,
            'status' => $document->status,
            'failure_reason' => $document->failure_reason,
            'size_kb' => (int) round($document->size_bytes / 1024),
            'page_count' => $document->page_count,
            'chunk_count' => $document->chunks_count ?? 0,
            'is_eva_visible' => $document->is_eva_visible,
            'tags' => $document->tags,
            'catalog_subject_id' => $document->catalog_subject_id,
            'subject_name' => $document->catalogSubject?->name,
            'uploaded_by_name' => $document->uploader?->name,
            'article_id' => $document->article?->id,
            'article_title' => $document->article?->title,
            'updated_at' => $document->updated_at?->diffForHumans(),
        ];
    }
}

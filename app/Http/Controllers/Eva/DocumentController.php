<?php

namespace App\Http\Controllers\Eva;

use App\Http\Controllers\Controller;
use App\Jobs\Knowledge\IndexDocument;
use App\Models\Knowledge\Document;
use App\Services\Knowledge\DocumentTextExtractor;
use App\Services\Knowledge\TagRegistry;
use App\Support\CurrentActor;
use App\Support\Eva\CatalogOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

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
    /** Ekstensi yang boleh dicatat. Dibatasi supaya label tipe tidak liar. */
    private const ALLOWED_EXTENSIONS = ['PDF', 'DOCX', 'XLSX', 'TXT', 'MD'];

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

        return $this->queueIndexing($document);
    }

    public function update(Request $request, Document $document): JsonResponse
    {
        $document->update($request->validate([
            'name' => 'required|string|max:255',
            'catalog_subject_id' => ['nullable', 'integer', Rule::exists('service_catalog_subjects', 'id')],
            'is_eva_visible' => 'required|boolean',
            'tags' => 'nullable|string|max:255',
        ]));

        return response()->json($this->presentFresh($document));
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

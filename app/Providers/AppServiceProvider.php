<?php

namespace App\Providers;

use App\Services\Knowledge\DocumentTextExtractor;
use App\Services\Knowledge\FulltextKnowledgeSearch;
use App\Services\Knowledge\KnowledgeSearch;
use App\Services\Knowledge\OcrBinaries;
use App\Services\Knowledge\PdfTextReader;
use App\Services\Knowledge\PopplerTesseractPdfReader;
use App\Services\Knowledge\SubjectMatcher;
use App\Services\Knowledge\SubjectSearch;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Satu-satunya tempat implementasi pencarian EVA ditentukan.
        //
        // Pencarian A — mencari JAWABAN (kb_articles, kb_faqs).
        // Pindah ke PostgreSQL/pgvector nanti = ganti baris ini saja.
        $this->app->bind(KnowledgeSearch::class, FulltextKnowledgeSearch::class);

        // Pencarian B — mencocokkan pertanyaan ke SUBJECT katalog.
        // Saat lisensi model AI tersedia, ganti SubjectMatcher (cocok-kata +
        // jarak edit) ke implementasi berbasis embedding di baris ini saja;
        // RecommendationController dan PreviewController tidak berubah.
        $this->app->bind(SubjectSearch::class, SubjectMatcher::class);

        // Pembacaan PDF: seam ketiga, sejajar dengan dua di atas. Mesinnya
        // poppler + Tesseract on-premise — isi SOP tidak boleh keluar jaringan.
        $this->app->singleton(PdfTextReader::class, function () {
            $config = (array) config('eva.ocr', []);

            return new PopplerTesseractPdfReader(new OcrBinaries($config), $config);
        });

        $this->app->singleton(
            DocumentTextExtractor::class,
            fn ($app) => new DocumentTextExtractor($app->make(PdfTextReader::class)),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}

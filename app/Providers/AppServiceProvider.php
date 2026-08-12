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
use App\Support\CurrentActor;
use App\Support\RoleRegistry;
use Illuminate\Support\Facades\View;
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
        /*
        | Daftar role untuk tombol switch role, dibagikan ke SEMUA layout.
        |
        | MENGGANTIKAN tiga komposer "bertindak sebagai" yang dulu ada di sini
        | (agentSwitcher untuk support & support-bpo, approverSwitcher untuk
        | approver). Ketiganya membiarkan satu sesi menjadi ORANG LAIN, dan itu
        | tidak bisa hidup berdampingan dengan pengujian hak akses: selama siapa
        | pun bisa berpindah menjadi agent mana pun, "user ini tidak boleh
        | melihat itu" tidak pernah benar-benar terbukti.
        |
        | Yang menggantikannya berpindah antar ROLE MILIK SENDIRI, bukan antar
        | orang. Isinya dihitung dari user yang sedang masuk, jadi orang dengan
        | satu role menerima daftar berisi satu — dan partial-nya tidak merender
        | apa pun (lihat partials/role-switcher.blade.php).
        |
        | Satu komposer untuk delapan layout, bukan blok @php yang disalin di
        | masing-masing seperti sebelumnya. Salinan kedelapan itu yang paling
        | mungkin ketinggalan saat aturannya berubah.
        */
        View::composer([
            'layouts.admin',
            'layouts.app',
            'layouts.approver',
            'layouts.eva',
            'layouts.requester',
            'layouts.support',
            'layouts.support-bpo',
            'layouts.team-lead',
        ], function ($view) {
            $user = CurrentActor::user();

            $view->with(
                'roleSwitcherEntries',
                $user ? RoleRegistry::switcherEntriesFor($user) : collect(),
            );
        });
    }
}

<?php

namespace App\Providers;

use App\Models\SupportAgent;
use App\Models\User;
use App\Services\Knowledge\DocumentTextExtractor;
use App\Services\Knowledge\FulltextKnowledgeSearch;
use App\Services\Knowledge\KnowledgeSearch;
use App\Services\Knowledge\OcrBinaries;
use App\Services\Knowledge\PdfTextReader;
use App\Services\Knowledge\PopplerTesseractPdfReader;
use App\Services\Knowledge\SubjectMatcher;
use App\Services\Knowledge\SubjectSearch;
use App\Support\CurrentActor;
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
        View::composer('layouts.support', fn ($view) => $view->with(
            'agentSwitcher',
            $this->buildAgentSwitcher('it', CurrentActor::support(), 'support.switch-agent')
        ));

        View::composer('layouts.support-bpo', fn ($view) => $view->with(
            'agentSwitcher',
            $this->buildAgentSwitcher('bpo', CurrentActor::supportBpo(), 'support-bpo.switch-agent')
        ));

        View::composer('layouts.approver', fn ($view) => $view->with(
            'approverSwitcher',
            $this->buildApproverSwitcher(CurrentActor::approver())
        ));
    }

    /**
     * Every active user holding the Approver role is a valid person to "act
     * as" — see CurrentActor::approver()'s doc comment for why this
     * switching exists at all.
     */
    private function buildApproverSwitcher(User $currentUser): array
    {
        $options = \App\Support\ActingSwitcher::approverOptions();

        return [
            'currentApproverId' => $options->first(fn (User $u) => $u->id === $currentUser->id)?->id,
            'options' => $options,
            'switchUrl' => route('approver.switch-approver'),
        ];
    }

    /**
     * Every active agent of the given type who has a linked user account is
     * a valid person to "act as" — see CurrentActor's support()/supportBpo()
     * doc comment for why this switching exists at all.
     */
    private function buildAgentSwitcher(string $type, \App\Models\User $currentUser, string $routeName): array
    {
        $options = \App\Support\ActingSwitcher::agentOptions($type);

        return [
            'currentAgentId' => $options->first(fn (SupportAgent $a) => $a->user_id === $currentUser->id)?->id,
            'options' => $options,
            'switchUrl' => route($routeName),
        ];
    }
}

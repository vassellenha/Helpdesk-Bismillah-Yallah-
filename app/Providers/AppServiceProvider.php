<?php

namespace App\Providers;

use App\Services\Knowledge\AnswerParaphraser;
use App\Services\Knowledge\DocumentTextExtractor;
use App\Services\Knowledge\FulltextKnowledgeSearch;
use App\Services\Knowledge\KnowledgeSearch;
use App\Services\Knowledge\KnowledgeSynthesizer;
use App\Services\Knowledge\NoSynthesizer;
use App\Services\Knowledge\OcrBinaries;
use App\Services\Knowledge\OpenAiParaphraser;
use App\Services\Knowledge\OpenAiSynthesizer;
use App\Services\Knowledge\PassthroughParaphraser;
use App\Services\Knowledge\PdfTextReader;
use App\Services\Knowledge\PopplerTesseractPdfReader;
use App\Services\Knowledge\SubjectMatcher;
use App\Services\Knowledge\SubjectSearch;
use App\Support\CurrentActor;
use App\Support\ProfilePresenter;
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

        // Seam keempat: cara jawaban KB ditulis ulang sebelum sampai ke
        // karyawan. Bawaannya TIDAK mengubah apa pun.
        //
        // Kunci kosong ikut mematikan fitur ini. Tanpa penjagaan itu, flag yang
        // menyala di server yang belum diisi kredensialnya membuat setiap
        // pertanyaan menempuh satu panggilan HTTP yang pasti ditolak 401 —
        // lambat untuk semua orang, dan gejalanya cuma "EVA terasa berat".
        $this->app->bind(AnswerParaphraser::class, function () {
            $config = (array) config('services.openai', []);

            return ($config['paraphrase_enabled'] ?? false) && ! empty($config['key'])
                ? new OpenAiParaphraser($config)
                : new PassthroughParaphraser;
        });

        // Seam kelima: apakah EVA boleh merangkai jawaban dari beberapa sumber
        // sekaligus. Mati = perilaku aslinya, menjawab dari satu sumber
        // terbaik. Penjagaan kuncinya sama alasannya dengan di atas.
        $this->app->bind(KnowledgeSynthesizer::class, function () {
            $config = (array) config('services.openai', []);

            return ($config['synthesis_enabled'] ?? false) && ! empty($config['key'])
                ? new OpenAiSynthesizer($config)
                : new NoSynthesizer;
        });
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

        /*
        | Identitas pemakai untuk bilah atas konsol EVA.
        |
        | Di layout, bukan di tiap controller: konsol ini 13 layar dan tiap
        | layar punya controllernya sendiri, jadi prop yang ditempel satu per
        | satu pasti ada yang terlewat — dan layar yang terlewat akan kehilangan
        | menu profilnya tanpa error apa pun.
        */
        View::composer('layouts.eva', function ($view) {
            $user = CurrentActor::user();

            $view->with('evaUser', $user ? [
                'name' => $user->name,
                'title' => trim(($user->jabatan ?: 'Knowledge Administrator').' · '.($user->unit ?: ''), " ·\t\n"),
                'initials' => ProfilePresenter::initials($user->name),
            ] : null);
        });
    }
}

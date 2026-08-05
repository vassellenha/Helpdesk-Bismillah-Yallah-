<?php

use App\Http\Controllers\Admin\IntegrationController;
use App\Http\Controllers\Admin\TicketManagementController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AuditTrailController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Eva\AnalyticsController as EvaAnalyticsController;
use App\Http\Controllers\Eva\AppsController as EvaAppsController;
use App\Http\Controllers\Eva\AssistantController as EvaAssistantController;
use App\Http\Controllers\Eva\ArticleController as EvaArticleController;
use App\Http\Controllers\Eva\ConversationController as EvaConversationController;
use App\Http\Controllers\Eva\CoverageController as EvaCoverageController;
use App\Http\Controllers\Eva\DocumentController as EvaDocumentController;
use App\Http\Controllers\Eva\FaqController as EvaFaqController;
use App\Http\Controllers\Eva\PlaceholderController as EvaPlaceholderController;
use App\Http\Controllers\Eva\PreviewController as EvaPreviewController;
use App\Http\Controllers\Eva\RatingController as EvaRatingController;
use App\Http\Controllers\Eva\RecommendationController as EvaRecommendationController;
use App\Http\Controllers\Eva\SearchSettingsController as EvaSearchSettingsController;
use App\Http\Controllers\Eva\TaxonomyController as EvaTaxonomyController;
use App\Http\Controllers\Eva\TrainingController as EvaTrainingController;
use App\Http\Controllers\Eva\UnansweredController as EvaUnansweredController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ServiceCatalogController;
use App\Http\Controllers\SlaPolicyController;
use App\Http\Controllers\SsoController;
use App\Http\Controllers\SupportBpoController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\TeamLeadController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TicketDetailController;
use App\Http\Controllers\UserRoleController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PortalController::class, 'index'])->name('portal.index');

Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');

// SINTA portal SSO. The helpdesk still works without signing in (see
// CurrentActor's fixed personas); logging in narrows it to the real person.
Route::prefix('auth/sso')->name('sso.')->group(function () {
    Route::get('/login', [SsoController::class, 'login'])->name('login');
    Route::get('/redirect', [SsoController::class, 'redirect'])->name('redirect');
    Route::get('/callback', [SsoController::class, 'callback'])->name('callback');
    // Portal-initiated: SINTA sends the employee straight here with a signed
    // identity. Off (404) unless SSO_ENTRY_DRIVER is configured.
    Route::get('/entry', [SsoController::class, 'entry'])->name('entry');
    Route::post('/logout', [SsoController::class, 'logout'])->name('logout');
});

Route::prefix('dashboard')->name('dashboard.')->group(function () {
    Route::get('/requester', [DashboardController::class, 'requester'])->name('requester');
    Route::get('/approver', [ApprovalController::class, 'inbox'])->name('approver');
    Route::get('/support', [SupportController::class, 'dashboard'])->name('support');
    Route::get('/support-bpo', [SupportBpoController::class, 'dashboard'])->name('support-bpo');
    Route::get('/team-lead', [TeamLeadController::class, 'dashboard'])->name('team-lead');
    Route::get('/eva', [DashboardController::class, 'eva'])->name('eva');
});

Route::get('/eva/profile', [ProfileController::class, 'eva'])->name('eva.profile');

Route::prefix('approver')->name('approver.')->group(function () {
    Route::get('/profile', [ProfileController::class, 'approver'])->name('profile');
    Route::get('/tickets', [ApprovalController::class, 'history'])->name('tickets');
    Route::get('/tickets/{ticket}', [ApprovalController::class, 'show'])->name('tickets.show');
    Route::get('/tickets/{ticket}/data', [ApprovalController::class, 'data'])->name('tickets.data');
    Route::post('/tickets/{ticket}/comments', [ApprovalController::class, 'addComment'])->name('tickets.comments.store');
    Route::post('/tickets/{ticket}/decide', [ApprovalController::class, 'decide'])->name('tickets.decide');
    Route::post('/notifications/{notification}/read', [ApprovalController::class, 'markNotificationRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [ApprovalController::class, 'markAllNotificationsRead'])->name('notifications.read-all');
    Route::post('/switch-approver', [ApprovalController::class, 'switchApprover'])->name('switch-approver');
});

Route::prefix('support')->name('support.')->group(function () {
    Route::get('/profile', [ProfileController::class, 'support'])->name('profile');
    Route::get('/tickets', [SupportController::class, 'myTickets'])->name('tickets');
    Route::get('/tickets/{ticket}', [SupportController::class, 'show'])->name('tickets.show');
    Route::get('/tickets/{ticket}/data', [SupportController::class, 'data'])->name('tickets.data');
    Route::post('/tickets/{ticket}/comments', [SupportController::class, 'addComment'])->name('tickets.comments.store');
    Route::post('/tickets/{ticket}/resolve', [SupportController::class, 'resolve'])->name('tickets.resolve');
    Route::post('/tickets/{ticket}/return', [SupportController::class, 'returnTicket'])->name('tickets.return');
    Route::post('/notifications/{notification}/read', [SupportController::class, 'markNotificationRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [SupportController::class, 'markAllNotificationsRead'])->name('notifications.read-all');
    Route::post('/switch-agent', [SupportController::class, 'switchAgent'])->name('switch-agent');
});

Route::prefix('support-bpo')->name('support-bpo.')->group(function () {
    Route::get('/profile', [ProfileController::class, 'supportBpo'])->name('profile');
    Route::get('/tickets', [SupportBpoController::class, 'myTickets'])->name('tickets');
    Route::get('/tickets/{ticket}', [SupportBpoController::class, 'show'])->name('tickets.show');
    Route::get('/tickets/{ticket}/data', [SupportBpoController::class, 'data'])->name('tickets.data');
    Route::post('/tickets/{ticket}/comments', [SupportBpoController::class, 'addComment'])->name('tickets.comments.store');
    Route::post('/tickets/{ticket}/resolve', [SupportBpoController::class, 'resolve'])->name('tickets.resolve');
    Route::post('/tickets/{ticket}/escalate', [SupportBpoController::class, 'escalate'])->name('tickets.escalate');
    Route::post('/tickets/{ticket}/return', [SupportBpoController::class, 'returnTicket'])->name('tickets.return');
    Route::post('/notifications/{notification}/read', [SupportBpoController::class, 'markNotificationRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [SupportBpoController::class, 'markAllNotificationsRead'])->name('notifications.read-all');
    Route::post('/switch-agent', [SupportBpoController::class, 'switchAgent'])->name('switch-agent');
});

Route::prefix('team-lead')->name('team-lead.')->group(function () {
    Route::get('/profile', [ProfileController::class, 'teamLead'])->name('profile');
    Route::get('/data', [TeamLeadController::class, 'dataFeed'])->name('data-feed');
    Route::get('/tickets/{ticket}', [TeamLeadController::class, 'showTicket'])->name('tickets.show');
    Route::get('/tickets/{ticket}/data', [TeamLeadController::class, 'ticketData'])->name('tickets.data');
    Route::post('/tickets/{ticket}/note', [TeamLeadController::class, 'addNote'])->name('tickets.note');
    Route::post('/tickets/{ticket}/remind', [TeamLeadController::class, 'remind'])->name('tickets.remind');
    Route::post('/tickets/{ticket}/reassign', [TeamLeadController::class, 'reassign'])->name('tickets.reassign');
    Route::post('/tickets/{ticket}/raise-priority', [TeamLeadController::class, 'raisePriority'])->name('tickets.raise-priority');
    Route::post('/agents/{agent}/remind-rating', [TeamLeadController::class, 'remindRating'])->name('agents.remind-rating');
    Route::post('/escalation/raise', [TeamLeadController::class, 'escalateGroup'])->name('escalation.raise');
    Route::get('/reports/export', [TeamLeadController::class, 'exportReport'])->name('reports.export');
    Route::post('/notifications/{notification}/read', [TeamLeadController::class, 'markNotificationRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [TeamLeadController::class, 'markAllNotificationsRead'])->name('notifications.read-all');
});

Route::prefix('requester')->name('requester.')->group(function () {
    Route::get('/profile', [ProfileController::class, 'requester'])->name('profile');
    Route::get('/tickets', [DashboardController::class, 'myTickets'])->name('tickets');
    Route::get('/tickets/{ticket}', [TicketDetailController::class, 'show'])->name('tickets.show');
    Route::get('/tickets/{ticket}/data', [TicketDetailController::class, 'data'])->name('tickets.data');
    Route::put('/tickets/{ticket}', [TicketController::class, 'update'])->name('tickets.update');
    Route::delete('/tickets/{ticket}', [TicketController::class, 'destroy'])->name('tickets.destroy');
    Route::post('/tickets/{ticket}/comments', [TicketDetailController::class, 'addComment'])->name('tickets.comments.store');
    Route::post('/tickets/{ticket}/reopen', [TicketDetailController::class, 'reopen'])->name('tickets.reopen');
    Route::post('/tickets/{ticket}/close', [TicketDetailController::class, 'close'])->name('tickets.close');
    Route::post('/tickets/{ticket}/attachment', [TicketDetailController::class, 'uploadAttachment'])->name('tickets.attachment');
    Route::delete('/tickets/{ticket}/attachment/{attachment}', [TicketDetailController::class, 'destroyAttachment'])->name('tickets.attachment.destroy');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
});

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'admin'])->name('profile');
    Route::get('/users', [UserRoleController::class, 'index'])->name('users');
    Route::get('/sla', [SlaPolicyController::class, 'index'])->name('sla');
    Route::get('/audit-trail', [AuditTrailController::class, 'index'])->name('audit-trail');

    Route::prefix('integrations')->name('integrations.')->group(function () {
        Route::post('/test', [IntegrationController::class, 'test'])->name('test');
        Route::post('/sync', [IntegrationController::class, 'sync'])->name('sync');
    });
    Route::get('/integrations', [IntegrationController::class, 'index'])->name('integrations');
    Route::get('/service-catalog', [ServiceCatalogController::class, 'index'])->name('service-catalog');
    Route::get('/ticket-management', [TicketManagementController::class, 'index'])->name('ticket-management');
    Route::get('/ticket-management/export', [TicketManagementController::class, 'export'])->name('ticket-management.export');
    Route::patch('/ticket-management/{ticket}/rating-toggle', [TicketManagementController::class, 'toggleRating'])->name('ticket-management.rating-toggle');

    Route::prefix('sla-policies')->name('sla-policies.')->group(function () {
        Route::get('/', [SlaPolicyController::class, 'list'])->name('list');
        Route::post('/', [SlaPolicyController::class, 'store'])->name('store');
        Route::put('/{slaPolicy}', [SlaPolicyController::class, 'update'])->name('update');
        Route::post('/{slaPolicy}/toggle', [SlaPolicyController::class, 'toggle'])->name('toggle');
    });

    Route::prefix('service-catalog/subjects')->name('service-catalog.subjects.')->group(function () {
        Route::post('/', [ServiceCatalogController::class, 'store'])->name('store');
        Route::put('/{subject}', [ServiceCatalogController::class, 'update'])->name('update');
        Route::patch('/{subject}/support', [ServiceCatalogController::class, 'updateSupport'])->name('support');
        Route::post('/{subject}/toggle', [ServiceCatalogController::class, 'toggleStatus'])->name('toggle');
        Route::delete('/{subject}', [ServiceCatalogController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('users')->name('users.')->group(function () {
        // Declared before the /{user} routes so "sync" is never read as an id.
        Route::post('/sync', [UserRoleController::class, 'syncEmployees'])->name('sync');
        Route::post('/', [UserRoleController::class, 'storeUser'])->name('store');
        Route::put('/{user}', [UserRoleController::class, 'updateUser'])->name('update');
        Route::post('/{user}/toggle', [UserRoleController::class, 'toggleUserStatus'])->name('toggle');
        Route::patch('/{user}/roles', [UserRoleController::class, 'updateUserRoles'])->name('roles');
    });

    Route::prefix('roles')->name('roles.')->group(function () {
        Route::post('/', [UserRoleController::class, 'storeRole'])->name('store');
        Route::put('/{role}', [UserRoleController::class, 'updateRole'])->name('update');
        Route::post('/{role}/toggle', [UserRoleController::class, 'toggleRoleStatus'])->name('toggle');
    });
});

/*
| EVA Knowledge Admin Console.
|
| Satu route nyata per layar, mengikuti pola console tim (ServiceCatalogConsole,
| AuditTrailConsole, dst) — bukan satu SPA dengan pergantian view di klien
| seperti mockup.
*/
Route::prefix('eva')->name('eva.')->middleware('eva.access')->group(function () {
    Route::get('/', fn () => redirect()->route('eva.coverage'));
    Route::get('/coverage', [EvaCoverageController::class, 'index'])->name('coverage');
    Route::get('/articles', [EvaArticleController::class, 'index'])->name('articles');
    Route::get('/faq', [EvaFaqController::class, 'index'])->name('faq');
    Route::get('/documents', [EvaDocumentController::class, 'index'])->name('documents');
    Route::get('/preview', [EvaPreviewController::class, 'index'])->name('preview');
    Route::get('/unanswered', [EvaUnansweredController::class, 'index'])->name('unanswered');
    Route::get('/conversations', [EvaConversationController::class, 'index'])->name('conversations');
    // Menghapus TRANSKRIP percakapan. kb_answer_logs tidak ikut — lihat
    // ConversationController::destroy.
    Route::delete('/api/conversations/{conversation}', [EvaConversationController::class, 'destroy'])->name('conversations.destroy');
    Route::get('/ratings', [EvaRatingController::class, 'index'])->name('ratings');
    Route::get('/analytics', [EvaAnalyticsController::class, 'index'])->name('analytics');
    Route::get('/apps', [EvaAppsController::class, 'index'])->name('apps');
    Route::get('/search-settings', [EvaSearchSettingsController::class, 'index'])->name('search-settings');
    Route::get('/taxonomy', [EvaTaxonomyController::class, 'index'])->name('taxonomy');
    Route::get('/recommendation', [EvaRecommendationController::class, 'index'])->name('recommendation');
    Route::get('/training', [EvaTrainingController::class, 'index'])->name('training');

    Route::post('/api/recommendation/test', [EvaRecommendationController::class, 'test'])->name('recommendation.test');

    // Menyingkirkan pertanyaan dari DAFTAR KERJA — bukan menghapusnya dari
    // kb_answer_logs. Riwayat dan angka Analytics tidak tersentuh.
    Route::post('/api/unanswered/dismiss', [EvaUnansweredController::class, 'dismiss'])->name('unanswered.dismiss');
    Route::post('/api/unanswered/dismiss-many', [EvaUnansweredController::class, 'dismissMany'])->name('unanswered.dismiss-many');
    Route::post('/api/unanswered/restore', [EvaUnansweredController::class, 'restore'])->name('unanswered.restore');
    Route::post('/api/training/toggle', [EvaTrainingController::class, 'toggle'])->name('training.toggle');

    Route::prefix('api/tags')->name('tags.')->group(function () {
        Route::post('/materials', [EvaTaxonomyController::class, 'tagMaterials'])->name('materials');
        Route::post('/rename', [EvaTaxonomyController::class, 'renameTag'])->name('rename');
        Route::post('/delete', [EvaTaxonomyController::class, 'deleteTag'])->name('delete');
    });

    Route::prefix('api/synonyms')->name('synonyms.')->group(function () {
        Route::post('/', [EvaSearchSettingsController::class, 'store'])->name('store');
        Route::put('/{synonym}', [EvaSearchSettingsController::class, 'update'])->name('update');
        Route::delete('/{synonym}', [EvaSearchSettingsController::class, 'destroy'])->name('destroy');
    });

    Route::post('/api/search/test', [EvaSearchSettingsController::class, 'test'])->name('search.test');

    Route::prefix('api/articles')->name('articles.')->group(function () {
        Route::put('/{article}', [EvaArticleController::class, 'update'])->name('update');
        Route::post('/{article}/toggle', [EvaArticleController::class, 'toggleVisibility'])->name('toggle');
        Route::post('/{article}/publish', [EvaArticleController::class, 'publish'])->name('publish');
        Route::delete('/{article}', [EvaArticleController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('api/faqs')->name('faqs.')->group(function () {
        Route::post('/', [EvaFaqController::class, 'store'])->name('store');
        Route::put('/{faq}', [EvaFaqController::class, 'update'])->name('update');
        Route::post('/{faq}/toggle', [EvaFaqController::class, 'toggleVisibility'])->name('toggle');
        Route::delete('/{faq}', [EvaFaqController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('api/documents')->name('documents.')->group(function () {
        Route::post('/', [EvaDocumentController::class, 'store'])->name('store');
        // Dipanggil berulang oleh layar Documents selama masih ada dokumen
        // berstatus `processing` — indexing berjalan di antrean, jadi hasilnya
        // tidak ikut di balasan unggah.
        Route::get('/{document}', [EvaDocumentController::class, 'show'])->name('show');
        Route::put('/{document}', [EvaDocumentController::class, 'update'])->name('update');
        Route::post('/{document}/reindex', [EvaDocumentController::class, 'reindex'])->name('reindex');
        Route::delete('/{document}', [EvaDocumentController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('api/preview')->name('preview.')->group(function () {
        // Endpoint terberat di konsol: satu request = satu putaran Pencarian A
        // (FULLTEXT + fallback LIKE) atas seluruh materi. Diberi throttle
        // tersendiri supaya banjir pertanyaan tidak menyeret database.
        Route::post('/ask', [EvaPreviewController::class, 'ask'])
            ->middleware('throttle:20,1')
            ->name('ask');
        Route::post('/rate', [EvaPreviewController::class, 'rate'])->name('rate');
        Route::post('/rate/note', [EvaPreviewController::class, 'note'])->name('note');
        Route::post('/ticket-draft', [EvaPreviewController::class, 'ticketDraft'])->name('ticket-draft');
    });

    // Harus terakhir: pola {key} akan menelan segmen apa pun di atasnya.
    Route::get('/{key}', [EvaPlaceholderController::class, 'show'])->name('placeholder');
});

/*
| Widget EVA di portal — permukaan untuk KARYAWAN.
|
| SENGAJA di luar grup `eva/` yang dijaga `eva.access`: grup itu adalah konsol
| admin, sedangkan widget harus bisa dipakai siapa pun yang membuka portal.
| Menaruhnya di dalam grup akan menolak setiap karyawan dengan 401 begitu SSO
| memberi identitas non-admin.
|
| Segmen `/api/` di tengah path bukan kebetulan: penangan exception di
| bootstrap/app.php merender error sebagai JSON untuk pola bintang-slash-api.
| Tanpa segmen itu, error validasi dibalas HTML dan apiFetch gagal memparsenya.
*/
Route::prefix('assistant/api')->name('eva.assistant.')->group(function () {
    // Endpoint terberat: satu request = satu putaran Pencarian A (FULLTEXT +
    // fallback LIKE) atas seluruh materi. Ini juga endpoint EVA pertama yang
    // terbuka tanpa penjaga identitas, jadi throttle-nya bukan pelengkap.
    Route::post('/ask', [EvaAssistantController::class, 'ask'])
        ->middleware('throttle:20,1')
        ->name('ask');
    Route::post('/rate', [EvaAssistantController::class, 'rate'])
        ->middleware('throttle:30,1')
        ->name('rate');
    Route::post('/rate/note', [EvaAssistantController::class, 'note'])
        ->middleware('throttle:30,1')
        ->name('note');
    Route::post('/ticket-draft', [EvaAssistantController::class, 'ticketDraft'])
        ->middleware('throttle:20,1')
        ->name('ticket-draft');
});

// Read by any workspace that needs live SLA data (Requester new-ticket form).
Route::get('/api/sla-policies/active', [SlaPolicyController::class, 'activeForRequester'])->name('sla-policies.active');
Route::post('/api/tickets', [TicketController::class, 'store'])->name('tickets.store');
Route::get('/api/catalog', [CatalogController::class, 'tree'])->name('catalog.tree');
Route::get('/api/approvers', [CatalogController::class, 'approvers'])->name('approvers.index');

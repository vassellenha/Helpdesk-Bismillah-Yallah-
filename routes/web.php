<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuditTrailController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\ServiceCatalogController;
use App\Http\Controllers\SlaPolicyController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\UserRoleController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PortalController::class, 'index'])->name('portal.index');

Route::prefix('dashboard')->name('dashboard.')->group(function () {
    Route::get('/requester', [DashboardController::class, 'requester'])->name('requester');
    Route::get('/approver', [DashboardController::class, 'approver'])->name('approver');
    Route::get('/support', [DashboardController::class, 'support'])->name('support');
    Route::get('/team-lead', [DashboardController::class, 'teamLead'])->name('team-lead');
    Route::get('/eva', [DashboardController::class, 'eva'])->name('eva');
});

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/users', [UserRoleController::class, 'index'])->name('users');
    Route::get('/sla', [SlaPolicyController::class, 'index'])->name('sla');
    Route::get('/audit-trail', [AuditTrailController::class, 'index'])->name('audit-trail');
    Route::get('/service-catalog', [ServiceCatalogController::class, 'index'])->name('service-catalog');
    Route::get('/ticket-management', fn () => app(AdminController::class)->placeholder('Ticket Management'))->name('ticket-management');

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

// Read by any workspace that needs live SLA data (Requester new-ticket form).
Route::get('/api/sla-policies/active', [SlaPolicyController::class, 'activeForRequester'])->name('sla-policies.active');
Route::post('/api/tickets', [TicketController::class, 'store'])->name('tickets.store');

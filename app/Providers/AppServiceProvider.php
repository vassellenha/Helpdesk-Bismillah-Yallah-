<?php

namespace App\Providers;

use App\Models\Role;
use App\Models\SupportAgent;
use App\Models\User;
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
        //
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
        $options = Role::where('name', 'Approver')->firstOrFail()
            ->users()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['users.id', 'users.name']);

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
        $options = SupportAgent::where('type', $type)
            ->whereNotNull('user_id')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'user_id']);

        return [
            'currentAgentId' => $options->first(fn (SupportAgent $a) => $a->user_id === $currentUser->id)?->id,
            'options' => $options,
            'switchUrl' => route($routeName),
        ];
    }
}

<?php

namespace App\Support;

use App\Models\Role;
use App\Models\SupportAgent;
use Illuminate\Support\Collection;

/**
 * Who's available to "act as" for a switchable role — support/support-bpo's
 * agent switcher and approver's user switcher both need this same list, and
 * so does the 403 page when the CURRENT persona for that role can't load at
 * all (see errors/403.blade.php). Pulled out of AppServiceProvider so the
 * error page doesn't need a working CurrentActor::*() call just to offer an
 * alternative — that call is exactly what's failing when this page renders.
 */
class ActingSwitcher
{
    /**
     * Every active agent of the given type who has a linked, active user
     * account — a valid person to "act as". Two independent switches
     * checked (SupportAgent::is_active and the linked user's own
     * active-ness): an agent left "active" while their helpdesk account is
     * disabled would otherwise show up as a destination tickets can never
     * reach.
     */
    public static function agentOptions(string $type): Collection
    {
        return SupportAgent::where('type', $type)
            ->where('is_active', true)
            ->whereHas('user', fn ($q) => $q->active())
            ->orderBy('name')
            ->get(['id', 'name', 'user_id']);
    }

    /** Every active user holding the Approver role. */
    public static function approverOptions(): Collection
    {
        return Role::where('name', 'Approver')->firstOrFail()
            ->users()
            ->active()
            ->orderBy('name')
            ->get(['users.id', 'users.name']);
    }
}

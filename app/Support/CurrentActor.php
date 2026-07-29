<?php

namespace App\Support;

use App\Models\SupportAgent;
use App\Models\User;

/**
 * This mockup has no login/auth flow — every admin screen acts as the same
 * seeded Administrator. Audit trail rows need a real actor_id FK, so this
 * resolves that single persona to its actual users row instead of the
 * display-only DummyData::currentAdmin() array.
 *
 * support()/supportBpo()/approver() are the exception: since a ticket can
 * be routed to any of several agents/approvers (not just one fixed
 * persona), who you're "acting as" is switchable at runtime via the
 * switcher in the Support/Support BPO/Approver layouts — see
 * SupportController::switchAgent(), SupportBpoController::switchAgent(),
 * ApprovalController::switchApprover(). The session holds the chosen id;
 * an invalid or missing selection falls back to the original fixed persona.
 */
class CurrentActor
{
    public static function admin(): User
    {
        return User::where('email', 'marcell.laforteza@adhi.co.id')->firstOrFail();
    }

    public static function requester(): User
    {
        return User::where('email', 'andi.pratama@adhi.co.id')->firstOrFail();
    }

    public static function approver(): User
    {
        return self::actingApproverUser() ?? User::where('email', 'karina.putri@adhi.co.id')->firstOrFail();
    }

    public static function support(): User
    {
        return self::actingAgentUser('it', 'acting_support_agent_id')
            ?? User::where('email', 'aditya.nugraha@adhi.co.id')->firstOrFail();
    }

    /**
     * Dedicated Team Lead persona — kept separate from approver() (Karina,
     * who also holds the Team Lead role) so the supervisor's own
     * notification feed never mixes with the approver inbox.
     */
    public static function teamLead(): User
    {
        return User::where('email', 'raka.mahendra@adhi.co.id')->firstOrFail();
    }

    public static function supportBpo(): User
    {
        return self::actingAgentUser('bpo', 'acting_support_bpo_agent_id')
            ?? User::where('email', 'denny.firmansyah@adhi.co.id')->firstOrFail();
    }

    public static function knowledgeAdmin(): User
    {
        return User::where('email', 'nina.amelia@adhi.co.id')->firstOrFail();
    }

    private static function actingAgentUser(string $type, string $sessionKey): ?User
    {
        $agentId = session($sessionKey);
        if (! $agentId) {
            return null;
        }

        $agent = SupportAgent::find($agentId);
        if (! $agent || $agent->type !== $type || ! $agent->user_id) {
            return null;
        }

        return User::find($agent->user_id);
    }

    private static function actingApproverUser(): ?User
    {
        $userId = session('acting_approver_id');
        if (! $userId) {
            return null;
        }

        $user = User::find($userId);
        if (! $user || $user->status !== 'active' || ! $user->roles()->where('name', 'Approver')->exists()) {
            return null;
        }

        return $user;
    }
}

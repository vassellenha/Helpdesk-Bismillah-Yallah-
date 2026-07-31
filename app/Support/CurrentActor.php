<?php

namespace App\Support;

use App\Models\SupportAgent;
use App\Models\User;
use App\Support\Sso\SsoAuthenticator;

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
    /**
     * A SINTA-authenticated person takes precedence over the fixed persona for
     * whichever role screen they actually hold — so once SSO is live, actions
     * are attributed to the real user instead of a seeded stand-in.
     *
     * Gated on holding the role, not merely being logged in: a Requester opening
     * an Admin URL must not silently become the Admin actor. Without a session,
     * or without the role, this returns null and the persona applies as before,
     * which is what keeps the mockup usable with no login at all.
     */
    private static function ssoUserWithRole(string $role): ?User
    {
        $user = SsoAuthenticator::user();

        if (! $user) {
            return null;
        }

        return $user->roles->contains('name', $role) ? $user : null;
    }

    /*
     | Tujuh persona tetap di bawah ini dicari lewat NIP, BUKAN email.
     |
     | Sampai 31 Juli 2026 dicari lewat email, dan itu pecah begitu fitur
     | admin-overridden-fields masuk: seorang admin mengubah nama & email
     | "Karina Putri" jadi "Karina AESPA" lewat konsol User Management, dan
     | approver() langsung firstOrFail() — approver() melempar
     | ModelNotFoundException yang Laravel ubah jadi halaman 404 polos, bukan
     | error yang jelas mengarah ke penyebabnya.
     |
     | NIP dipilih bukan asal beda kolom: itu KUNCI PENCOCOKAN EmployeeSync
     | sendiri (`$matchBy = 'nip'` bawaan di config/integrations.php).
     | Mengubah NIP seseorang berarti memutus pencocokan sinkronisasi
     | karyawannya sendiri — jauh lebih jarang disentuh iseng dibanding nama
     | tampilan atau email yang memang dirancang bisa admin ubah bebas.
     |
     | Ini bukan jaminan mutlak (NIP secara teknis tetap kolom yang bisa
     | diedit lewat form yang sama), tapi memindahkan risikonya dari "kolom
     | yang orang ubah untuk iseng/uji coba" ke "kolom yang mengubahnya
     | berarti sengaja mematahkan identitas sinkronisasi karyawan itu
     | sendiri".
    */

    public static function admin(): User
    {
        return self::ssoUserWithRole('Administrator')
            ?? User::where('nip', '19870114001')->firstOrFail();
    }

    public static function requester(): User
    {
        return self::ssoUserWithRole('Requester')
            ?? User::where('nip', '19950418102')->firstOrFail();
    }

    public static function approver(): User
    {
        return self::ssoUserWithRole('Approver')
            ?? self::actingApproverUser()
            ?? User::where('nip', '19900322014')->firstOrFail();
    }

    public static function support(): User
    {
        return self::ssoUserWithRole('Support IT')
            ?? self::actingAgentUser('it', 'acting_support_agent_id')
            ?? User::where('nip', '10027761')->firstOrFail();
    }

    /**
     * Dedicated Team Lead persona — kept separate from approver() (Karina,
     * who also holds the Team Lead role) so the supervisor's own
     * notification feed never mixes with the approver inbox.
     */
    public static function teamLead(): User
    {
        return self::ssoUserWithRole('Team Lead')
            ?? User::where('nip', '19891117033')->firstOrFail();
    }

    public static function supportBpo(): User
    {
        return self::ssoUserWithRole('Support BPO')
            ?? self::actingAgentUser('bpo', 'acting_support_bpo_agent_id')
            ?? User::where('nip', '19960130096')->firstOrFail();
    }

    public static function knowledgeAdmin(): User
    {
        return self::ssoUserWithRole('Knowledge Administrator')
            ?? User::where('nip', '19920504052')->firstOrFail();
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

<?php

namespace App\Support;

use App\Exceptions\AccountInactive;
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
 * ApprovalController::switchApprover(). The session holds the chosen id.
 *
 * When that selection is missing or invalid, approver() still falls back to a
 * fixed persona, but support()/supportBpo() no longer do: they fall back to the
 * first active agent of their team (see firstActiveAgentUser()). A fixed NIP is
 * only as durable as the seeded row it names, and one of them has already been
 * deleted once.
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
     | LIMA persona tetap di bawah ini dicari lewat NIP, BUKAN email.
     |
     | Dulu tujuh. support() dan supportBpo() keluar dari daftar ini pada
     | 10 Agu 2026 — keduanya kini jatuh ke agent aktif pertama timnya, karena
     | mereka punya daftar yang bisa dipilih dan NIP tetapnya terbukti rapuh.
     | Lima sisanya belum punya penggantinya sampai SSO aktif.
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
        return self::mustBeActive(
            self::ssoUserWithRole('Administrator')
                ?? User::where('nip', '19870114001')->firstOrFail(),
        );
    }

    public static function requester(): User
    {
        return self::mustBeActive(
            self::ssoUserWithRole('Requester')
                ?? User::where('nip', '19950418102')->firstOrFail(),
        );
    }

    public static function approver(): User
    {
        return self::mustBeActive(
            self::ssoUserWithRole('Approver')
                ?? self::actingApproverUser()
                ?? User::where('nip', '19900322014')->firstOrFail(),
        );
    }

    public static function support(): User
    {
        return self::mustBeActive(
            self::ssoUserWithRole('Support IT')
                ?? self::actingAgentUser('it', 'acting_support_agent_id')
                ?? self::firstActiveAgentUser('it'),
        );
    }

    /**
     * Dedicated Team Lead persona — kept separate from approver() (Karina,
     * who also holds the Team Lead role) so the supervisor's own
     * notification feed never mixes with the approver inbox.
     */
    public static function teamLead(): User
    {
        return self::mustBeActive(
            self::ssoUserWithRole('Team Lead')
                ?? User::where('nip', '19891117033')->firstOrFail(),
        );
    }

    public static function supportBpo(): User
    {
        return self::mustBeActive(
            self::ssoUserWithRole('Support BPO')
                ?? self::actingAgentUser('bpo', 'acting_support_bpo_agent_id')
                ?? self::firstActiveAgentUser('bpo'),
        );
    }

    public static function knowledgeAdmin(): User
    {
        return self::mustBeActive(
            self::ssoUserWithRole('Knowledge Administrator')
                ?? User::where('nip', '19920504052')->firstOrFail(),
        );
    }

    /**
     * Gerbang terakhir sebelum siapa pun menjadi aktor.
     *
     * Ditaruh di sini, bukan di middleware, karena INI satu-satunya pintu yang
     * dilewati setiap controller untuk tahu siapa yang sedang bertindak. Sebuah
     * middleware harus didaftarkan per rute dan pasti ada yang terlewat; gerbang
     * di sini berlaku otomatis untuk seluruh layar dan endpoint, termasuk yang
     * ditulis besok.
     *
     * Menjaga TIGA jalur sekaligus, dan ketiganya bisa berubah setelah sesi
     * dimulai:
     *   - Persona tetap (mode mockup) — sebelumnya tidak diperiksa sama sekali.
     *   - Approver/agent yang sedang "diperankan" lewat switcher — orangnya bisa
     *     dinonaktifkan setelah terlanjur terpilih ke sesi.
     *   - User SSO — sudah dijaga SsoAuthenticator, diperiksa ulang di sini
     *     supaya aturannya berdiri di satu tempat, bukan bergantung pada urutan
     *     pemanggilan.
     *
     * KONSEKUENSI yang disengaja: di mode persona, mematikan akses seorang
     * persona mematikan SELURUH layar role itu — persona itulah satu-satunya
     * identitas yang ada di sana. Itu memang yang diminta: user nonaktif tidak
     * boleh berinteraksi dengan fitur apa pun.
     */
    private static function mustBeActive(User $user): User
    {
        if ($user->isActive()) {
            return $user;
        }

        throw new AccountInactive(
            "Akun {$user->name} tidak dapat mengakses Helpdesk: "
            // lcfirst, BUKAN strtolower: alasannya memuat kata "Admin" yang harus
            // tetap berhuruf besar.
            .lcfirst((string) $user->inactiveReason()).'.'
        );
    }

    /**
     * Agent aktif pertama dari satu tim, dipakai saat belum ada yang dipilih.
     *
     * Menggantikan fallback NIP tetap yang dulu ada di sini. NIP itu menunjuk
     * satu baris hasil seed, dan begitu barisnya hilang — Denny Firmansyah
     * dihapus 10 Agu 2026 setelah switcher agent membuatnya tidak diperlukan —
     * `firstOrFail()` melempar ModelNotFoundException yang Laravel ubah jadi 404
     * polos. Bukan di layar mana pun yang sedang dipakai, melainkan di SESI YANG
     * BARU: `acting_support_bpo_agent_id` hanya ditulis oleh switchAgent(), jadi
     * browser yang belum pernah memilih siapa pun jatuh ke sini pada permintaan
     * pertamanya — sebelum switcher-nya sempat muncul untuk dipakai.
     *
     * Diurutkan berdasarkan id supaya seseorang yang membuka dua tab tidak
     * bertindak sebagai dua orang berbeda.
     *
     * Ini juga melepas dua dari tujuh persona hardcoded. Lima sisanya (admin,
     * requester, approver, teamLead, knowledgeAdmin) masih menunggu SSO, karena
     * mereka bukan agent dan tidak punya daftar yang bisa dipilih otomatis.
     */
    private static function firstActiveAgentUser(string $type): User
    {
        $user = User::query()
            ->join('support_agents', 'support_agents.user_id', '=', 'users.id')
            ->where('support_agents.type', $type)
            ->where('support_agents.is_active', true)
            ->active()
            ->orderBy('support_agents.id')
            ->select('users.*')
            ->first();

        if (! $user) {
            throw new \RuntimeException(
                "Tidak ada agent {$type} aktif yang tertaut ke akun pengguna. "
                .'Tambahkan satu lewat Admin → Service Catalog, atau aktifkan kembali '
                .'salah satu agent yang ada, sebelum layar Support bisa dibuka.'
            );
        }

        return $user;
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

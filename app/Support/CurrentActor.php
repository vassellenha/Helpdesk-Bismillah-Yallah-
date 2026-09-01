<?php

namespace App\Support;

use App\Exceptions\AccountInactive;
use App\Models\User;
use App\Support\Sso\SsoAuthenticator;
use Illuminate\Support\Facades\Auth;

/**
 * Siapa yang sedang bertindak di layar sebuah role.
 *
 * SEBELUMNYA kelas ini memegang tujuh persona tetap yang dicari lewat NIP:
 * tanpa login sama sekali, membuka /dashboard/support membuat siapa pun menjadi
 * agent IT, dan membuka /admin membuat siapa pun menjadi Administrator. Itu
 * memang disengaja selagi repo ini mockup tanpa autentikasi.
 *
 * SEKARANG jawabannya selalu orang yang benar-benar masuk. Persona tetapnya
 * dicabut seluruhnya — bukan sekadar dilewati, karena selama masih ada jalur
 * yang mengembalikan identitas tanpa login, pertanyaan "apakah user X boleh
 * membuka layar Y" tidak punya jawaban yang bisa diuji: URL-nya selalu tembus.
 * Itulah yang membuat pencabutan ini jadi syarat, bukan pilihan.
 *
 * Gerbang sebenarnya ada di middleware (`auth` + `role:`) yang menjaga setiap
 * grup rute. Pemeriksaan di sini adalah lapis keduanya, dan sengaja
 * dipertahankan: rute baru yang lupa dipasangi middleware akan tetap ditolak di
 * sini, bukan diam-diam menjalankan aksi atas nama orang yang tidak berhak.
 */
class CurrentActor
{
    public static function admin(): User
    {
        return self::forRole('admin');
    }

    public static function requester(): User
    {
        return self::forRole('requester');
    }

    public static function approver(): User
    {
        return self::forRole('approver');
    }

    public static function support(): User
    {
        return self::forRole('support');
    }

    public static function teamLead(): User
    {
        return self::forRole('team-lead');
    }

    public static function teamLeadBpo(): User
    {
        return self::forRole('team-lead-bpo');
    }

    public static function supportBpo(): User
    {
        return self::forRole('support-bpo');
    }

    public static function knowledgeAdmin(): User
    {
        return self::forRole('eva');
    }

    /**
     * User yang sedang masuk, atau null kalau tidak ada — tanpa memeriksa role.
     *
     * Dipakai layar yang tidak terikat satu role tertentu (portal, tombol
     * switch role) dan oleh forRole() di bawah.
     */
    public static function user(): ?User
    {
        // Guard Laravel adalah sumber utamanya; SsoAuthenticator ikut mengisi
        // guard itu sejak login SSO disambungkan. Kunci sesi SSO tetap dibaca
        // sebagai cadangan supaya sesi yang sudah berjalan sebelum penyambungan
        // itu tidak ikut terputus di tengah jalan.
        return Auth::user() ?? SsoAuthenticator::user();
    }

    /**
     * Aktor untuk sebuah kunci role, atau tolak.
     *
     * 401 untuk tamu dan 403 untuk orang yang masuk tapi tidak memegang
     * role-nya — dua keadaan yang berbeda dan butuh jawaban berbeda: yang
     * pertama diantar ke halaman masuk, yang kedua diberi tahu bahwa akunnya
     * memang tidak berhak dan tidak ada gunanya masuk ulang.
     */
    private static function forRole(string $key): User
    {
        $user = self::user();

        if (! $user) {
            abort(401, 'Silakan masuk terlebih dahulu.');
        }

        $roleName = RoleRegistry::roleNameFor($key);

        if (! $user->roles->contains('name', $roleName)) {
            abort(403, "Akun Anda tidak punya role {$roleName}.");
        }

        return self::mustBeActive($user);
    }

    /**
     * Gerbang terakhir sebelum siapa pun menjadi aktor.
     *
     * Dipertahankan apa adanya dari versi persona. Middleware `auth` menerima
     * sesi yang sah, tapi tidak tahu apa-apa soal DUA saklar milik helpdesk ini
     * (`status` dari data kepegawaian dan `helpdesk_access` dari Admin), dan
     * keduanya bisa berubah SETELAH sesi dimulai. Diperiksa di sini, bukan di
     * middleware, karena ini satu-satunya pintu yang dilewati setiap controller
     * untuk tahu siapa yang bertindak — termasuk controller yang ditulis besok.
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
}

<?php

namespace App\Support;

use App\Models\User;

/**
 * Read-only "My Profile" card shown to every role — same field set as the
 * Admin "Detail Pengguna" tab (UserRoleController::presentUser()), just
 * scoped to a single self-lookup instead of the whole user list.
 */
class ProfilePresenter
{
    public static function present(User $user): array
    {
        return [
            'name' => $user->name,
            'initials' => self::initials($user->name),
            'email' => $user->email,
            'username' => $user->username ?: $user->email,
            'nip' => $user->nip ?: '-',
            'address' => $user->address ?: '-',
            'phone' => $user->phone ?: '-',
            'status' => $user->isActive() ? 'Aktif' : 'Nonaktif',
            'statusReason' => $user->inactiveReason(),
            'jabatan' => $user->jabatan ?: '-',
            // Nama, bukan kode. Layar ini dibaca pemilik akunnya sendiri, dan
            // "07 / 210 / 2107000001" tidak memberi tahu dia apa pun. Kodenya
            // tetap tersimpan di kolomnya masing-masing untuk pencocokan
            // antarsistem, hanya tidak ditampilkan di sini.
            //
            // Departemen dibaca dari `unit`: di direktori pegawai, `dept_name`
            // memang dipetakan ke sana.
            'departemen' => $user->unit ?: '-',
            'divisi' => $user->nama_divisi ?: '-',
            'proyek' => $user->nama_proyek ?: '-',
            'lastLogin' => $user->last_login_at?->format('d M Y, H:i') ?? '-',
            'roles' => $user->roles->pluck('name')->all(),
        ];
    }

    private static function initials(string $name): string
    {
        $parts = explode(' ', trim($name));

        return strtoupper(substr($parts[0] ?? '', 0, 1).substr($parts[1] ?? '', 0, 1));
    }
}

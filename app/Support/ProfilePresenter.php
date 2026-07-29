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
            'nip' => $user->nip ?: '-',
            'email' => $user->email,
            'whatsapp' => $user->whatsapp ?: '-',
            'unit' => $user->unit ?: '-',
            'jabatan' => $user->jabatan ?: '-',
            'status' => $user->status === 'active' ? 'Aktif' : 'Nonaktif',
            'joinedAt' => $user->created_at->format('d F Y'),
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

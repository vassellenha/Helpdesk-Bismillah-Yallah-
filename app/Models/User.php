<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $username
 * @property string|null $nip
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $unit
 * @property string|null $jabatan
 * @property string|null $kode_departemen
 * @property string|null $kode_divisi
 * @property string|null $kode_proyek
 * @property string|null $nama_proyek
 * @property array<int, string>|null $admin_overridden_fields
 * @property string $status
 * @property string $helpdesk_access
 * @property \Illuminate\Support\Carbon|null $last_login_at
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Role> $roles
 */
#[Fillable([
    'name', 'email', 'username', 'password', 'nip', 'phone', 'address',
    'unit', 'jabatan', 'kode_departemen', 'kode_divisi', 'kode_proyek',
    'nama_proyek', 'status', 'helpdesk_access', 'last_login_at',
    'admin_overridden_fields',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'admin_overridden_fields' => 'array',
        ];
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    /**
     * Effective access. Two independent owners each hold a veto:
     * `status` is employment, written by EmployeeSync from the company API;
     * `helpdesk_access` is this helpdesk's own switch, written only by an Admin.
     * Either one saying no locks the account.
     */
    public function isActive(): bool
    {
        return $this->status === 'active' && $this->helpdesk_access === 'enabled';
    }

    /**
     * Why the account is locked, for screens that need to explain it — null
     * when the account is fully active.
     *
     * Reports every reason, not just the first: hiding the second one means an
     * Admin who disabled access on an already-resigned employee never learns
     * the account stays locked after the API marks them employed again.
     */
    public function inactiveReason(): ?string
    {
        $reasons = [];

        if ($this->status !== 'active') {
            $reasons[] = 'nonaktif di data kepegawaian';
        }

        if ($this->helpdesk_access !== 'enabled') {
            $reasons[] = 'akses helpdesk dinonaktifkan Admin';
        }

        return $reasons === [] ? null : ucfirst(implode(' dan ', $reasons));
    }
}

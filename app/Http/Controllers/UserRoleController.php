<?php

namespace App\Http\Controllers;

use App\Models\AuditTrail;
use App\Models\Role;
use App\Models\User;
use App\Support\AuditDescriber;
use App\Support\CurrentActor;
use App\Support\DummyData;
use App\Support\EmployeeSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserRoleController extends Controller
{
    private const FIELD_LABELS = [
        'name' => 'Nama',
        'nip' => 'NIP',
        'email' => 'Email',
        'username' => 'Username',
        'phone' => 'Nomor Telepon',
        'address' => 'Alamat',
        'unit' => 'Unit Kerja',
        'jabatan' => 'Jabatan',
        'kode_departemen' => 'Kode Departemen',
        'kode_divisi' => 'Kode Divisi',
        'kode_proyek' => 'Kode Proyek',
        'status' => 'Status Kepegawaian',
        'helpdesk_access' => 'Akses Helpdesk',
    ];

    public function index(): View
    {
        $users = User::with('roles')->orderBy('name')->get();
        $roles = Role::withCount('users')->orderBy('name')->get();

        return view('admin.users', [
            'role' => 'admin',
            'currentUser' => DummyData::currentAdmin(),
            'users' => $users->map($this->presentUser(...)),
            'roles' => $roles->map($this->presentRole(...)),
            'permissionModules' => DummyData::permissionModules(),
            'permissionActions' => DummyData::permissionActions(),
            'unitOrganisasi' => DummyData::unitOrganisasi(),
        ]);
    }

    /**
     * Pull the latest employee master data from the company directory. Returns
     * the refreshed user list alongside the run summary so the console can
     * repaint without a page reload. EmployeeSync writes its own audit row.
     */
    public function syncEmployees(): JsonResponse
    {
        $summary = EmployeeSync::run();

        if ($summary['fetched'] === 0) {
            return response()->json([
                'message' => 'Tidak ada data pegawai yang diterima dari sumber. Cek konfigurasi integrasi atau log aplikasi.',
            ], 422);
        }

        $users = User::with('roles')->orderBy('name')->get();

        return response()->json([
            'summary' => $summary,
            'users' => $users->map($this->presentUser(...)),
        ]);
    }

    public function storeUser(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'nip' => 'nullable|string|max:50',
            'email' => 'required|email|unique:users,email',
            'username' => 'nullable|string|max:255|unique:users,username',
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:1000',
            'unit' => 'nullable|string|max:255',
            'jabatan' => 'nullable|string|max:255',
            'kode_departemen' => 'nullable|string|max:100',
            'kode_divisi' => 'nullable|string|max:100',
            'kode_proyek' => 'nullable|string|max:100',
            'nama_proyek' => 'nullable|string|max:255',
            'helpdesk_access' => ['required', Rule::in(['enabled', 'disabled'])],
            'role_ids' => 'nullable|array',
            'role_ids.*' => 'integer|exists:roles,id',
        ]);
        $roleIds = $data['role_ids'] ?? [];
        unset($data['role_ids']);
        // Username doubles as the corporate email unless the admin overrides it.
        $data['username'] = ($data['username'] ?? null) ?: $data['email'];
        // Employment status is the company API's to set; a hand-made account has
        // no directory record yet, so it starts as an active employee.
        $data['status'] = 'active';
        $actor = CurrentActor::admin();

        $user = DB::transaction(function () use ($data, $roleIds, $actor) {
            $user = User::create([
                ...$data,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]);

            if ($roleIds === []) {
                $requesterRole = Role::where('name', 'Requester')->first();
                $roleIds = $requesterRole ? [$requesterRole->id] : [];
            }
            $user->roles()->attach($roleIds);

            $roleNames = Role::whereIn('id', $roleIds)->pluck('name')->implode(', ');
            AuditTrail::record($actor, [
                'module' => 'user_role_management',
                'action' => 'create',
                'target_type' => 'user',
                'target_id' => $user->id,
                'target_name' => $user->name,
                'new_value' => [...$data, 'roles' => $roleNames],
                'description' => "{$actor->name} menambahkan user \"{$user->name}\" dengan role {$roleNames}.",
            ]);

            return $user;
        });

        return response()->json($this->presentUser($user->fresh('roles')), 201);
    }

    public function updateUser(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'nip' => 'nullable|string|max:50',
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'username' => ['nullable', 'string', 'max:255', Rule::unique('users', 'username')->ignore($user->id)],
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:1000',
            'unit' => 'nullable|string|max:255',
            'jabatan' => 'nullable|string|max:255',
            'kode_departemen' => 'nullable|string|max:100',
            'kode_divisi' => 'nullable|string|max:100',
            'kode_proyek' => 'nullable|string|max:100',
            'helpdesk_access' => ['required', Rule::in(['enabled', 'disabled'])],
        ]);
        $data['username'] = ($data['username'] ?? null) ?: $data['email'];
        $actor = CurrentActor::admin();
        $before = $user->only(array_keys(self::FIELD_LABELS));

        $user = DB::transaction(function () use ($data, $user, $before, $actor) {
            $user->update($data);
            $after = $user->only(array_keys(self::FIELD_LABELS));

            $changes = AuditDescriber::diff($before, $after, self::FIELD_LABELS, [
                'status' => fn ($v) => $v === 'active' ? 'Aktif' : 'Nonaktif',
                'helpdesk_access' => fn ($v) => $v === 'enabled' ? 'Aktif' : 'Nonaktif',
            ]);

            if ($changes !== []) {
                AuditTrail::record($actor, [
                    'module' => 'user_role_management',
                    'action' => 'update',
                    'target_type' => 'user',
                    'target_id' => $user->id,
                    'target_name' => $user->name,
                    ...AuditDescriber::presentDiff($changes),
                    'description' => AuditDescriber::describe($actor->name, 'user', $user->name, $changes),
                ]);
            }

            return $user;
        });

        return response()->json($this->presentUser($user->fresh('roles')));
    }

    /**
     * Toggles helpdesk access — the half of "aktif/nonaktif" the Admin owns.
     * Employment status belongs to the company API and is never written here,
     * otherwise the next EmployeeSync would silently undo the Admin's decision.
     */
    public function toggleUserStatus(User $user): JsonResponse
    {
        $actor = CurrentActor::admin();

        $user = DB::transaction(function () use ($user, $actor) {
            $wasEnabled = $user->helpdesk_access === 'enabled';
            $user->helpdesk_access = $wasEnabled ? 'disabled' : 'enabled';
            $user->save();

            $verb = $wasEnabled ? 'menonaktifkan' : 'mengaktifkan';
            AuditTrail::record($actor, [
                'module' => 'user_role_management',
                'action' => $wasEnabled ? 'deactivate' : 'activate',
                'target_type' => 'user',
                'target_id' => $user->id,
                'target_name' => $user->name,
                'old_value' => ['helpdesk_access' => $wasEnabled ? 'enabled' : 'disabled'],
                'new_value' => ['helpdesk_access' => $user->helpdesk_access],
                'description' => "{$actor->name} {$verb} akses helpdesk user \"{$user->name}\".",
            ]);

            return $user;
        });

        return response()->json($this->presentUser($user->fresh('roles')));
    }

    /**
     * Dedicated, user_id-scoped endpoint: reassigning roles for one user
     * must never affect any other user's access.
     */
    public function updateUserRoles(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'role_ids' => 'required|array',
            'role_ids.*' => 'integer|exists:roles,id',
        ]);
        $actor = CurrentActor::admin();

        $user = DB::transaction(function () use ($data, $user, $actor) {
            $oldRoles = $user->roles->pluck('name')->sort()->values();

            $user->roles()->sync($data['role_ids']);
            $user->load('roles');
            $newRoles = $user->roles->pluck('name')->sort()->values();

            if ($oldRoles->all() !== $newRoles->all()) {
                $oldLabel = $oldRoles->isEmpty() ? '—' : $oldRoles->implode(', ');
                $newLabel = $newRoles->isEmpty() ? '—' : $newRoles->implode(', ');

                AuditTrail::record($actor, [
                    'module' => 'user_role_management',
                    'action' => 'change_role',
                    'target_type' => 'user',
                    'target_id' => $user->id,
                    'target_name' => $user->name,
                    'old_value' => ['roles' => $oldRoles->all()],
                    'new_value' => ['roles' => $newRoles->all()],
                    'description' => "{$actor->name} mengubah role user \"{$user->name}\" dari {$oldLabel} menjadi {$newLabel}.",
                ]);
            }

            return $user;
        });

        return response()->json($this->presentUser($user));
    }

    public function storeRole(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
        $actor = CurrentActor::admin();

        $role = DB::transaction(function () use ($data, $actor) {
            $role = Role::create([...$data, 'type' => 'custom']);

            AuditTrail::record($actor, [
                'module' => 'user_role_management',
                'action' => 'create',
                'target_type' => 'role',
                'target_id' => $role->id,
                'target_name' => $role->name,
                'new_value' => $data,
                'description' => "{$actor->name} membuat role \"{$role->name}\".",
            ]);

            return $role;
        });

        return response()->json($this->presentRole($role->loadCount('users')), 201);
    }

    public function updateRole(Request $request, Role $role): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($role->id)],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
        $actor = CurrentActor::admin();
        $before = $role->only(['name', 'status']);

        $role = DB::transaction(function () use ($data, $role, $before, $actor) {
            $role->update($data);
            $after = $role->only(['name', 'status']);

            $changes = AuditDescriber::diff($before, $after, ['name' => 'Nama Role', 'status' => 'Status'], [
                'status' => fn ($v) => $v === 'active' ? 'Aktif' : 'Nonaktif',
            ]);

            if ($changes !== []) {
                AuditTrail::record($actor, [
                    'module' => 'user_role_management',
                    'action' => 'update',
                    'target_type' => 'role',
                    'target_id' => $role->id,
                    'target_name' => $role->name,
                    ...AuditDescriber::presentDiff($changes),
                    'description' => AuditDescriber::describe($actor->name, 'role', $role->name, $changes),
                ]);
            }

            return $role;
        });

        return response()->json($this->presentRole($role->loadCount('users')));
    }

    public function toggleRoleStatus(Role $role): JsonResponse
    {
        $actor = CurrentActor::admin();

        $role = DB::transaction(function () use ($role, $actor) {
            $wasActive = $role->status === 'active';
            $role->status = $wasActive ? 'inactive' : 'active';
            $role->save();

            $verb = $wasActive ? 'menonaktifkan' : 'mengaktifkan';
            AuditTrail::record($actor, [
                'module' => 'user_role_management',
                'action' => $wasActive ? 'deactivate' : 'activate',
                'target_type' => 'role',
                'target_id' => $role->id,
                'target_name' => $role->name,
                'old_value' => ['status' => $wasActive ? 'active' : 'inactive'],
                'new_value' => ['status' => $role->status],
                'description' => "{$actor->name} {$verb} role \"{$role->name}\".",
            ]);

            return $role;
        });

        return response()->json($this->presentRole($role->loadCount('users')));
    }

    private function presentUser(User $u): array
    {
        return [
            'id' => $u->id,
            'name' => $u->name,
            'nip' => $u->nip,
            'email' => $u->email,
            'username' => $u->username ?: $u->email,
            'phone' => $u->phone,
            'address' => $u->address ?: '-',
            'unit' => $u->unit,
            'jabatan' => $u->jabatan,
            'kode_departemen' => $u->kode_departemen ?: '-',
            'kode_divisi' => $u->kode_divisi ?: '-',
            'kode_proyek' => $u->kode_proyek ?: '-',
            'nama_proyek' => $u->nama_proyek ?: '-',
            'roles' => $u->roles->pluck('name')->all(),
            'role_ids' => $u->roles->pluck('id')->all(),
            // "status" stays the effective, human-readable verdict so existing
            // filters and badges keep working; the two sources are exposed
            // alongside it for screens that need to explain the verdict.
            'status' => $u->isActive() ? 'Aktif' : 'Nonaktif',
            'status_reason' => $u->inactiveReason(),
            'employment_status' => $u->status === 'active' ? 'Aktif' : 'Nonaktif',
            'helpdesk_access' => $u->helpdesk_access,
            'last_login' => $u->last_login_at?->format('d M Y, H:i') ?? '-',
            'joined_at' => $u->created_at->format('d F Y'),
        ];
    }

    private function presentRole(Role $r): array
    {
        return [
            'id' => $r->id,
            'key' => $r->id,
            'name' => $r->name,
            'type' => $r->type === 'system' ? 'System Role' : 'Role Kustom',
            'status' => $r->status === 'active' ? 'Aktif' : 'Nonaktif',
            'status_raw' => $r->status,
            'locked' => $r->locked,
            'user_count' => $r->users_count,
        ];
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\AuditTrail;
use App\Models\Role;
use App\Models\User;
use App\Support\AuditDescriber;
use App\Support\CurrentActor;
use App\Support\DummyData;
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
        'whatsapp' => 'WhatsApp',
        'unit' => 'Unit Kerja',
        'jabatan' => 'Jabatan',
        'status' => 'Status',
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

    public function storeUser(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'nip' => 'nullable|string|max:50',
            'email' => 'required|email|unique:users,email',
            'whatsapp' => 'nullable|string|max:30',
            'unit' => 'nullable|string|max:255',
            'jabatan' => 'nullable|string|max:255',
            'kode_proyek' => 'nullable|string|max:100',
            'nama_proyek' => 'nullable|string|max:255',
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'role_ids' => 'nullable|array',
            'role_ids.*' => 'integer|exists:roles,id',
        ]);
        $roleIds = $data['role_ids'] ?? [];
        unset($data['role_ids']);
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
            'whatsapp' => 'nullable|string|max:30',
            'unit' => 'nullable|string|max:255',
            'jabatan' => 'nullable|string|max:255',
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
        $actor = CurrentActor::admin();
        $before = $user->only(array_keys(self::FIELD_LABELS));

        $user = DB::transaction(function () use ($data, $user, $before, $actor) {
            $user->update($data);
            $after = $user->only(array_keys(self::FIELD_LABELS));

            $changes = AuditDescriber::diff($before, $after, self::FIELD_LABELS, [
                'status' => fn ($v) => $v === 'active' ? 'Aktif' : 'Nonaktif',
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

    public function toggleUserStatus(User $user): JsonResponse
    {
        $actor = CurrentActor::admin();

        $user = DB::transaction(function () use ($user, $actor) {
            $wasActive = $user->status === 'active';
            $user->status = $wasActive ? 'inactive' : 'active';
            $user->save();

            $verb = $wasActive ? 'menonaktifkan' : 'mengaktifkan';
            AuditTrail::record($actor, [
                'module' => 'user_role_management',
                'action' => $wasActive ? 'deactivate' : 'activate',
                'target_type' => 'user',
                'target_id' => $user->id,
                'target_name' => $user->name,
                'old_value' => ['status' => $wasActive ? 'active' : 'inactive'],
                'new_value' => ['status' => $user->status],
                'description' => "{$actor->name} {$verb} user \"{$user->name}\".",
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
            'whatsapp' => $u->whatsapp,
            'unit' => $u->unit,
            'jabatan' => $u->jabatan,
            'kode_proyek' => $u->kode_proyek ?: '-',
            'nama_proyek' => $u->nama_proyek ?: '-',
            'roles' => $u->roles->pluck('name')->all(),
            'role_ids' => $u->roles->pluck('id')->all(),
            'status' => $u->status === 'active' ? 'Aktif' : 'Nonaktif',
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

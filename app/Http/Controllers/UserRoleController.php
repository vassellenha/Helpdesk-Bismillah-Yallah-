<?php

namespace App\Http\Controllers;

use App\Models\AuditTrail;
use App\Models\Role;
use App\Models\User;
use App\Support\AuditDescriber;
use App\Support\CurrentActor;
use App\Support\DummyData;
use App\Support\EmployeeSync;
use App\Support\SupportAgentSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserRoleController extends Controller
{
    private const FIELD_LABELS = [
        'name' => 'Nama',
        'nip' => 'NPP',
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

    /**
     * Berapa user dikirim per halaman.
     *
     * Sebelum ada paginasi, layar ini memuat SELURUH tabel `users` sekaligus.
     * Dengan 28 akun seed itu tak terasa; begitu sinkronisasi direktori masuk
     * angkanya jadi 3.847, dan tiap kali halaman dibuka seluruhnya ditarik ke
     * memori lalu dikirim ke React sebagai satu blok JSON berukuran megabyte.
     * Batasnya di sini, bukan di komponen, karena yang mahal adalah query dan
     * payload-nya — memotong di sisi klien tidak menghemat apa pun.
     */
    private const PER_PAGE = 25;

    public function index(Request $request): View
    {
        $page = $this->paginateUsers($request);
        $roles = Role::withCount('users')->orderBy('name')->get();

        return view('admin.users', [
            'role' => 'admin',
            'users' => collect($page->items())->map($this->presentUser(...)),
            'usersMeta' => $this->pageMeta($page),
            // Statistik dihitung dengan COUNT di database, bukan dari daftar yang
            // dikirim. Sejak daftarnya cuma satu halaman, menghitungnya di React
            // akan melaporkan "25 user" untuk perusahaan berisi 3.847 orang.
            'userStats' => $this->userStats(),
            'roles' => $roles->map($this->presentRole(...)),
            'permissionModules' => DummyData::permissionModules(),
            'permissionActions' => DummyData::permissionActions(),
            'unitOrganisasi' => $this->unitOptions(),
        ]);
    }

    /**
     * Satu halaman user beserta meta-nya, untuk pencarian/filter/pindah halaman
     * tanpa memuat ulang seluruh layar.
     */
    public function list(Request $request): JsonResponse
    {
        $page = $this->paginateUsers($request);

        return response()->json([
            'users' => collect($page->items())->map($this->presentUser(...)),
            'meta' => $this->pageMeta($page),
            'stats' => $this->userStats(),
        ]);
    }

    /**
     * Query bersama untuk layar penuh dan endpoint JSON, supaya keduanya tidak
     * bisa menyaring dengan aturan berbeda.
     *
     * @return LengthAwarePaginator<int,User>
     */
    private function paginateUsers(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', '');
        $role = (string) $request->query('role', '');
        $unit = (string) $request->query('unit', '');

        return User::with('roles')
            ->when($search !== '', function ($query) use ($search) {
                // Kolom yang sama dengan yang dulu dicari di sisi klien, plus NIP
                // dan jabatan: dengan ribuan baris, NIP/NPP jadi cara tercepat
                // menemukan satu orang, dan jabatan (mis. "Manager") ditampilkan
                // sebagai kolom sendiri di tabel tapi sebelumnya tidak ikut dicari.
                $query->where(function ($q) use ($search) {
                    foreach (['name', 'email', 'unit', 'nip', 'jabatan'] as $column) {
                        $q->orWhere($column, 'like', '%'.$search.'%');
                    }
                });
            })
            // "Aktif" di layar ini adalah putusan gabungan dua kolom milik dua
            // pemilik berbeda (User::isActive()), jadi penyaringannya harus
            // menirukan aturan yang sama — bukan hanya melihat `status`.
            ->when($status === 'Aktif', fn ($q) => $q->active())
            ->when($status === 'Nonaktif', fn ($q) => $q->where(fn ($w) => $w->where('users.status', '!=', 'active')->orWhere('users.helpdesk_access', '!=', 'enabled')))
            ->when($role !== '', fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('name', $role)))
            // Cocok persis dengan nilai yang dikirim dropdown, dan dropdown itu
            // kini disusun dari kolom yang sama (lihat unitOptions()) — jadi
            // setiap pilihan yang bisa diklik pasti punya baris.
            ->when($unit !== '', fn ($q) => $q->where('unit', $unit))
            ->orderBy('name')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * Pilihan "Unit Kerja" — diambil dari NILAI YANG BENAR-BENAR ADA di kolom
     * `users.unit`, bukan dari daftar tetap.
     *
     * Sebelumnya isinya DummyData::unitOrganisasi(): delapan nama karangan yang
     * ditulis saat repo ini masih mockup. Penyaringnya mencocokkan persis ke
     * `users.unit`, jadi dropdown itu hanya bekerja selama isi tabelnya kebetulan
     * memakai kata yang sama.
     *
     * Begitu data pegawai ditarik dari API perusahaan, `unit` diisi `dept_name`
     * milik ADHI (lihat claim map di config/integrations.php) — nama departemen
     * sungguhan yang tidak satu pun ada di daftar karangan itu. Hasilnya:
     * setiap pilihan mengembalikan nol baris, dan filternya tampak "tidak
     * berfungsi" padahal justru bekerja dengan benar atas nilai yang memang tidak
     * pernah ada.
     *
     * Di lokal pun daftar itu sebenarnya sudah bocor — empat dari dua belas unit
     * yang ada di tabel tidak terdaftar, sehingga user di unit tersebut tidak
     * pernah bisa disaring sama sekali. Yang membedakan hanyalah di lokal
     * delapan sisanya kebetulan cocok, jadi kerusakannya tidak terlihat.
     *
     * DISTINCT atas satu kolom ber-index rendah: jumlah departemen berkisar
     * puluhan, bukan ribuan, jadi ini bukan daftar yang perlu dipaginasi.
     *
     * @return list<string>
     */
    private function unitOptions(): array
    {
        return User::query()
            ->whereNotNull('unit')
            ->where('unit', '!=', '')
            ->distinct()
            ->orderBy('unit')
            ->pluck('unit')
            ->all();
    }

    /** @return array<string,mixed> */
    private function pageMeta($page): array
    {
        return [
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
            'from' => $page->firstItem(),
            'to' => $page->lastItem(),
        ];
    }

    /** @return array<string,int> */
    private function userStats(): array
    {
        $total = User::count();
        $active = User::active()->count();

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $total - $active,
            'from_directory' => User::whereNotNull('synced_at')->count(),
        ];
    }

    /**
     * Pull the latest employee master data from the company directory. Returns
     * the refreshed first page alongside the run summary so the console can
     * repaint without a page reload. EmployeeSync writes its own audit row.
     *
     * Satu halaman, bukan seluruh daftar. Justru inilah permintaan yang paling
     * berbahaya kalau tidak dibatasi: sinkronisasi yang baru saja membuat 3.846
     * akun akan langsung mengirim balik ketiga-ribu-delapan-ratusan itu dalam
     * responsnya sendiri — tepat pada saat tabelnya paling besar.
     */
    public function syncEmployees(Request $request): JsonResponse
    {
        $summary = EmployeeSync::run();

        if ($summary['fetched'] === 0) {
            return response()->json([
                'message' => 'Tidak ada data pegawai yang diterima dari sumber. Cek konfigurasi integrasi atau log aplikasi.',
            ], 422);
        }

        $page = $this->paginateUsers($request);

        return response()->json([
            'summary' => $summary,
            'users' => collect($page->items())->map($this->presentUser(...)),
            'meta' => $this->pageMeta($page),
            'stats' => $this->userStats(),
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
            SupportAgentSync::reconcile($user);

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

            $this->lockAdminOverrides($user, $before, $after);

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
     * Marks every synced column an Admin just hand-edited so EmployeeSync
     * skips it on every future run — the same protection the sync already
     * gives a column the API sends empty, extended to one the API sends a
     * (different) real value for. `status` is deliberately excluded: it is
     * the one column meant to always track the company API, with
     * `helpdesk_access` as the Admin's own independent override for access.
     *
     * @param  array<string,mixed>  $before
     * @param  array<string,mixed>  $after
     */
    private function lockAdminOverrides(User $user, array $before, array $after): void
    {
        $syncedColumns = array_values(config('integrations.employee_directory.field_map', []));
        $lockable = array_diff($syncedColumns, ['status']);

        $justChanged = array_values(array_filter(
            $lockable,
            fn ($column) => array_key_exists($column, $after) && ($before[$column] ?? null) !== $after[$column]
        ));

        if ($justChanged === []) {
            return;
        }

        $locked = collect($user->admin_overridden_fields ?? [])->merge($justChanged)->unique()->sort()->values()->all();
        $user->update(['admin_overridden_fields' => $locked]);
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
            SupportAgentSync::reconcile($user);
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
            // Asal-usul akun. Setelah sync pertama tabel ini bercampur antara
            // 3.847 pegawai sungguhan dan sisa akun seed, dan bedanya menentukan
            // keputusan nyata — siapa yang aman dihapus saat bersih-bersih, dan
            // siapa yang akan ikut dinonaktifkan kalau deactivate_missing
            // dinyalakan. Dibaca dari kolom, bukan ditebak dari bentuk NPP:
            // satu dari 3.847 NPP asli ternyata murni angka seperti NIP seed.
            'from_directory' => $u->synced_at !== null,
            'synced_at' => $u->synced_at?->format('d M Y, H:i'),
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

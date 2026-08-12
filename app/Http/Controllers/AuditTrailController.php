<?php

namespace App\Http\Controllers;

use App\Models\AuditTrail;
use App\Models\User;
use App\Support\DummyData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class AuditTrailController extends Controller
{
    private const PER_PAGE = 15;

    public function index(Request $request): View
    {
        $page = $this->paginateLogs($request);

        return view('admin.audit-trail', [
            'role' => 'admin',
            'currentUser' => DummyData::currentAdmin(),
            'logs' => collect($page->items())->map($this->presentLog(...)),
            'logsMeta' => $this->pageMeta($page),
            'listUrl' => route('admin.audit-trail.list'),
            'administrators' => $this->administratorOptions(),
        ]);
    }

    public function list(Request $request): JsonResponse
    {
        $page = $this->paginateLogs($request);

        return response()->json([
            'logs' => collect($page->items())->map($this->presentLog(...)),
            'meta' => $this->pageMeta($page),
        ]);
    }

    /**
     * Penyaringan dilakukan di SQL, bukan di browser.
     *
     * Sebelumnya layar ini memuat 500 baris terbaru lalu menyaringnya di React.
     * Dua akibatnya, dan keduanya diam: log yang lebih tua dari 500 baris itu
     * tidak pernah bisa ditemukan lewat filter apa pun, dan daftar pilihan
     * "Pengguna" hanya berisi orang yang kebetulan muncul di jendela itu —
     * sehingga menyaring atas nama seseorang yang aktivitasnya sudah lewat
     * mengembalikan kosong, seolah-olah orang itu tidak pernah berbuat apa-apa.
     *
     * Mengikuti pola yang sudah dipakai User & Role Management: filter jadi
     * query string, hasilnya dipaginasi server.
     */
    private function paginateLogs(Request $request): LengthAwarePaginator
    {
        $search = trim((string) $request->query('search', ''));
        $module = (string) $request->query('module', '');
        $action = (string) $request->query('action', '');
        $administrator = (string) $request->query('administrator', '');
        $from = (string) $request->query('from', '');
        $to = (string) $request->query('to', '');

        return AuditTrail::with('actor')
            ->when($search !== '', fn ($q) => $q->where(function ($w) use ($search) {
                $like = '%'.$search.'%';
                $w->where('target_name', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    // Nama pelaku hidup di tabel users, bukan di baris audit —
                    // dicari lewat relasinya supaya kotak pencarian tetap
                    // mencakup ketiga kolom yang dulu dicari di browser.
                    ->orWhereHas('actor', fn ($a) => $a->where('name', 'like', $like));
            }))
            ->when($module !== '', fn ($q) => $q->where('module', $module))
            ->when($action !== '', fn ($q) => $q->where('action', $action))
            ->when($administrator !== '', fn ($q) => $q->whereHas('actor', fn ($a) => $a->where('name', $administrator)))
            // Batas tanggal memakai keseluruhan hari yang dipilih: pengguna
            // memilih "1 Agustus", bukan "1 Agustus pukul 00:00".
            ->when($from !== '', fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to !== '', fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->latest('created_at')
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * Pilihan "Pengguna" — setiap orang yang PERNAH tercatat di audit trail,
     * dibaca dari seluruh tabel, bukan dari halaman yang sedang tampil.
     *
     * Dipasangkan dengan penyaringan di server di atas: karena filternya kini
     * menjangkau seluruh tabel, setiap nama yang ditawarkan di sini dijamin
     * mengembalikan baris saat diklik.
     *
     * @return list<string>
     */
    private function administratorOptions(): array
    {
        return User::query()
            ->whereIn('id', AuditTrail::query()->select('actor_id'))
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    /** @return array<string,mixed> */
    private function presentLog(AuditTrail $log): array
    {
        return [
            'id' => $log->id,
            'waktu' => $log->created_at->format('d M Y, H:i'),
            'timestamp' => $log->created_at->timestamp,
            'administrator' => $log->actor?->name,
            'ip_address' => $log->ip_address,
            'url' => $log->url,
            'module' => $log->module,
            'module_label' => $this->moduleLabel($log->module),
            'action' => $log->action,
            'action_label' => $this->actionLabel($log->action),
            'target_type' => $log->target_type,
            'target_name' => $log->target_name,
            'description' => $log->description,
            'old_value' => $log->old_value,
            'new_value' => $log->new_value,
        ];
    }

    /** @return array<string,mixed> */
    private function pageMeta(LengthAwarePaginator $page): array
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

    /**
     * Stored module codes never change; only their display label is translated.
     * An unknown code falls through to itself rather than to a lang key, so a
     * new module added elsewhere still reads sensibly before it is translated.
     */
    private function moduleLabel(string $module): string
    {
        $key = match ($module) {
            'service_catalog' => 'catalog',
            'sla_configuration' => 'sla',
            'user_role_management' => 'users',
            'ticket_approval' => 'approval',
            'ticket_support' => 'support',
            'team_lead' => 'teamlead',
            'ticket_management' => 'tickets',
            'integration' => 'integration',
            'auth' => 'auth',
            default => null,
        };

        return $key === null ? $module : __('admin.audit.module_name.'.$key);
    }

    private function actionLabel(string $action): string
    {
        $key = match ($action) {
            'create' => 'create',
            'update' => 'edit',
            'activate' => 'activate',
            'deactivate' => 'deactivate',
            'assign_support' => 'update_support',
            'change_level' => 'update_level',
            'change_role' => 'update_role',
            'approve' => 'approve',
            'request_revision' => 'revision',
            'reject' => 'reject',
            'resolve' => 'resolve',
            'escalate' => 'escalate',
            'remind' => 'remind',
            'reassign' => 'reassign',
            'raise_priority' => 'raise',
            'remind_rating' => 'rating_remind',
            'return' => 'returned',
            'sync' => 'sync',
            'login' => 'login',
            'start' => 'start',
            default => null,
        };

        if ($key === null) {
            return $action;
        }

        return $key === 'edit' ? __('admin.audit.edit') : __('admin.audit.action.'.$key);
    }
}

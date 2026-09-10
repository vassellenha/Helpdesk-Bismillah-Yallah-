<?php

namespace App\Http\Controllers;

use App\Models\AuditTrail;
use App\Models\IssueCategory;
use App\Models\ServiceCatalogService;
use App\Models\ServiceCatalogSubcategory;
use App\Models\ServiceCatalogSubject;
use App\Models\SupportAgent;
use App\Models\Ticket;
use App\Models\User;
use App\Support\AuditDescriber;
use App\Support\CurrentActor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ServiceCatalogController extends Controller
{
    /**
     * Label untuk Subkategori yang belum ditugaskan, dipakai di deskripsi
     * Audit Trail supaya terbaca sebagai kalimat, bukan sebagai tanda hubung.
     *
     * Bukan "Semua Team Lead BPO" — itu justru kebalikan dari yang terjadi.
     * NULL di kolom itu berarti TIDAK ADA Team Lead yang berwenang atasnya
     * (lihat App\Support\TeamLeadScope), dan label yang mengatakan
     * sebaliknya membuat Admin mengira barisnya sudah aman ditinggal.
     */
    private const TEAM_LEAD_UNSET = 'Belum ditugaskan';

    private const FIELD_LABELS = [
        'issue_category' => 'Issue Category',
        'layanan' => 'Layanan',
        'subcategory' => 'Sub Category',
        'subject' => 'Subject',
        'requires_approval' => 'Approval',
        'status' => 'Status',
    ];

    public function index(): View
    {
        $subjects = $this->subjectsQuery()->get();

        return view('admin.service-catalog', [
            'role' => 'admin',
            'subjects' => $subjects->map($this->presentSubject(...)),
            'issueCategories' => IssueCategory::orderBy('name')->pluck('name'),
            'services' => ServiceCatalogService::orderBy('name')->get(['id', 'name']),
            // Diperkaya, bukan diganti bentuknya: modal Subjek hanya membaca
            // id/service_id/name, jadi tambahan kunci di sini aman baginya.
            'subcategories' => ServiceCatalogSubcategory::with(['service:id,name', 'teamLeadBpo:id,name'])
                ->withCount([
                    'subjects',
                    'subjects as active_subjects_count' => fn ($q) => $q->where('is_active', true),
                ])
                ->orderBy('name')->get()->map($this->presentSubcategory(...)),
            'teamLeadBpoOptions' => User::active()
                ->whereHas('roles', fn ($q) => $q->where('name', 'Team Lead BPO'))
                ->orderBy('name')->get(['users.id', 'users.name']),
            'supportAgents' => SupportAgent::where('is_active', true)->orderBy('name')->get(['id', 'name', 'type']),
            'stats' => [
                'total_subject' => $subjects->count(),
                'aktif' => $subjects->where('is_active', true)->count(),
                'requires_approval' => $subjects->where('requires_approval', true)->count(),
                'total_layanan' => ServiceCatalogService::count(),
                'subkategori_tanpa_team_lead' => ServiceCatalogSubcategory::whereNull('team_lead_bpo_user_id')->count(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $actor = CurrentActor::admin();

        $subject = DB::transaction(function () use ($data, $actor) {
            $service = ServiceCatalogService::firstOrCreate(['name' => $data['layanan']]);
            $subcategory = ServiceCatalogSubcategory::firstOrCreate([
                'service_id' => $service->id,
                'name' => $data['subcategory'],
            ]);
            $issueCategory = IssueCategory::where('name', $data['issue_category'])->firstOrFail();

            $subject = ServiceCatalogSubject::create([
                'issue_category_id' => $issueCategory->id,
                'service_id' => $service->id,
                'subcategory_id' => $subcategory->id,
                'name' => $data['subject'],
                'requires_approval' => $data['requires_approval'],
                'support_agent_id' => $data['support_agent_id'] ?? null,
                'it_agent_id' => $data['it_agent_id'] ?? null,
                'support_level' => $data['support_level'],
                'is_active' => $data['status'] === 'active',
            ]);

            AuditTrail::record($actor, [
                'module' => 'service_catalog',
                'action' => 'create',
                'target_type' => 'subject',
                'target_id' => $subject->id,
                'target_name' => $subject->name,
                'new_value' => [
                    'issue_category' => $data['issue_category'],
                    'layanan' => $data['layanan'],
                    'subcategory' => $data['subcategory'],
                    'subject' => $data['subject'],
                ],
                'description' => "{$actor->name} menambahkan layanan \"{$data['layanan']} — {$data['subject']}\".",
            ]);

            return $subject;
        });

        return response()->json($this->presentSubject($subject->fresh(['issueCategory', 'service', 'subcategory.teamLeadBpo', 'supportAgent', 'itAgent'])), 201);
    }

    public function update(Request $request, ServiceCatalogSubject $subject): JsonResponse
    {
        $data = $this->validated($request);
        $actor = CurrentActor::admin();
        $before = $this->displaySnapshot($subject);

        $subject = DB::transaction(function () use ($data, $subject, $before, $actor) {
            $service = ServiceCatalogService::firstOrCreate(['name' => $data['layanan']]);
            $subcategory = ServiceCatalogSubcategory::firstOrCreate([
                'service_id' => $service->id,
                'name' => $data['subcategory'],
            ]);
            $issueCategory = IssueCategory::where('name', $data['issue_category'])->firstOrFail();

            $subject->update([
                'issue_category_id' => $issueCategory->id,
                'service_id' => $service->id,
                'subcategory_id' => $subcategory->id,
                'name' => $data['subject'],
                'requires_approval' => $data['requires_approval'],
                'is_active' => $data['status'] === 'active',
            ]);

            // Tickets only ever store a denormalized copy of the catalog's
            // Layanan/Sub Category/Subject names (for fast listing/export
            // without joins) — a rename here must be pushed to every ticket
            // still pointing at this Subject via catalog_subject_id, or
            // Admin Ticket Management / Requester's own ticket detail would
            // keep showing the stale name.
            Ticket::where('catalog_subject_id', $subject->id)->update([
                'service_name' => $service->name,
                'subcategory_name' => $subcategory->name,
                'subject_name' => $subject->name,
                'title' => $subject->name,
                'issue_category' => $issueCategory->name,
                'category' => $issueCategory->name,
            ]);

            $after = $this->displaySnapshot($subject->fresh());
            $changes = AuditDescriber::diff($before, $after, self::FIELD_LABELS, $this->formatters());

            if ($changes !== []) {
                AuditTrail::record($actor, [
                    'module' => 'service_catalog',
                    'action' => 'update',
                    'target_type' => 'subject',
                    'target_id' => $subject->id,
                    'target_name' => $subject->name,
                    ...AuditDescriber::presentDiff($changes),
                    'description' => AuditDescriber::describe($actor->name, 'subject', $subject->name, $changes),
                ]);
            }

            return $subject;
        });

        return response()->json($this->presentSubject($subject->fresh(['issueCategory', 'service', 'subcategory.teamLeadBpo', 'supportAgent', 'itAgent'])));
    }

    /**
     * Dedicated, subject_id-scoped endpoint: updating Support/Level for one
     * Subject must never ripple to sibling subjects under the same Layanan
     * or Sub Category. Route-model-bound by {subject}, nothing else.
     */
    /**
     * Menugaskan (atau mencabut) Team Lead BPO yang mengawasi satu
     * Subkategori. Dari sinilah seluruh cakupan Team Lead diturunkan —
     * siapa yang dia awasi, tiket mana yang dia lihat, dan ke siapa dia
     * boleh memindahkan PIC (App\Support\TeamLeadScope).
     *
     * Endpoint tersendiri, mengikuti updateSupport(): satu perkara, tidak
     * pernah merembet ke kolom Subjek. Mengirim null berarti mengembalikan
     * Subkategori ini menjadi milik semua Team Lead BPO.
     */
    public function updateTeamLead(Request $request, ServiceCatalogSubcategory $subcategory): JsonResponse
    {
        $data = $request->validate([
            /*
             | Rule::in atas daftar yang SUDAH disaring, bukan
             | Rule::exists('users','id'). Yang harus dijamin bukan "user ini
             | ada", melainkan "user ini memegang role Team Lead BPO DAN
             | akunnya masih hidup". Menugaskan Subkategori ke akun terkunci
             | menghasilkan Subkategori yang tampak bertuan padahal tidak
             | diawasi siapa pun — dan tidak ada satu layar pun yang akan
             | berbunyi soal itu.
             */
            'team_lead_bpo_user_id' => ['nullable', 'integer', Rule::in($this->teamLeadBpoOptionIds())],
        ]);

        $actor = CurrentActor::admin();

        $subcategory = DB::transaction(function () use ($data, $subcategory, $actor) {
            $old = $subcategory->teamLeadBpo?->name ?? self::TEAM_LEAD_UNSET;

            $subcategory->update(['team_lead_bpo_user_id' => $data['team_lead_bpo_user_id'] ?? null]);
            $subcategory->refresh()->load('teamLeadBpo:id,name');

            $new = $subcategory->teamLeadBpo?->name ?? self::TEAM_LEAD_UNSET;

            if ($old !== $new) {
                AuditTrail::record($actor, [
                    'module' => 'service_catalog',
                    'action' => 'assign_team_lead',
                    'target_type' => 'subcategory',
                    'target_id' => $subcategory->id,
                    'target_name' => $subcategory->name,
                    'old_value' => ['team_lead_bpo' => $old],
                    'new_value' => ['team_lead_bpo' => $new],
                    'description' => "{$actor->name} mengubah Team Lead BPO Sub Category \"{$subcategory->name}\" dari {$old} menjadi {$new}.",
                ]);
            }

            return $subcategory;
        });

        // loadCount ikut dipanggil: presentSubcategory() membaca
        // `subjects_count`/`active_subjects_count`, dan tanpa keduanya ia
        // menjawab 0. Layar menggabungkan jawaban ini ke barisnya, jadi
        // kolom "Subjek" berubah jadi 0 tepat setelah penugasan tersimpan —
        // seolah Subkategori itu baru saja dikosongkan.
        $subcategory->loadMissing(['service:id,name', 'teamLeadBpo:id,name'])
            ->loadCount([
                'subjects',
                'subjects as active_subjects_count' => fn ($q) => $q->where('is_active', true),
            ]);

        return response()->json($this->presentSubcategory($subcategory));
    }

    /**
     * id user yang boleh dipasang sebagai Team Lead BPO: memegang rolenya,
     * dan akunnya masih bisa masuk Helpdesk.
     *
     * @return list<int>
     */
    private function teamLeadBpoOptionIds(): array
    {
        return User::active()
            ->whereHas('roles', fn ($q) => $q->where('name', 'Team Lead BPO'))
            ->pluck('users.id')->all();
    }

    private function presentSubcategory(ServiceCatalogSubcategory $s): array
    {
        return [
            'id' => $s->id,
            'service_id' => $s->service_id,
            'service_name' => $s->service?->name,
            'name' => $s->name,
            'team_lead_bpo_user_id' => $s->team_lead_bpo_user_id,
            'team_lead_bpo_name' => $s->teamLeadBpo?->name,
            'subject_count' => $s->subjects_count ?? 0,
            'active_subject_count' => $s->active_subjects_count ?? 0,
        ];
    }

    public function updateSupport(Request $request, ServiceCatalogSubject $subject): JsonResponse
    {
        $data = $request->validate([
            'support_agent_id' => ['nullable', 'integer', Rule::exists('support_agents', 'id')->where('type', 'bpo')],
            'it_agent_id' => ['nullable', 'integer', Rule::exists('support_agents', 'id')->where('type', 'it')],
            'support_level' => 'required|integer|min:1|max:2',
        ]);
        $this->assertPicAssigned((int) $data['support_level'], $data['support_agent_id'] ?? null, $data['it_agent_id'] ?? null);
        $actor = CurrentActor::admin();

        $subject = DB::transaction(function () use ($data, $subject, $actor) {
            $oldLabel = $this->supportLabel($subject);
            $oldLevel = $subject->support_level;

            $subject->update([
                'support_agent_id' => $data['support_agent_id'] ?? null,
                'it_agent_id' => $data['it_agent_id'] ?? null,
                'support_level' => $data['support_level'],
            ]);
            $subject->refresh();
            $newLabel = $this->supportLabel($subject);

            if ($oldLabel !== $newLabel) {
                AuditTrail::record($actor, [
                    'module' => 'service_catalog',
                    'action' => 'assign_support',
                    'target_type' => 'subject',
                    'target_id' => $subject->id,
                    'target_name' => $subject->name,
                    'old_value' => ['support' => $oldLabel],
                    'new_value' => ['support' => $newLabel],
                    'description' => "{$actor->name} mengubah Support subject \"{$subject->name}\" dari {$oldLabel} menjadi {$newLabel}.",
                ]);
            }

            if ($oldLevel !== $subject->support_level) {
                AuditTrail::record($actor, [
                    'module' => 'service_catalog',
                    'action' => 'change_level',
                    'target_type' => 'subject',
                    'target_id' => $subject->id,
                    'target_name' => $subject->name,
                    'old_value' => ['support_level' => $oldLevel],
                    'new_value' => ['support_level' => $subject->support_level],
                    'description' => "{$actor->name} mengubah Level subject \"{$subject->name}\" dari Level {$oldLevel} menjadi Level {$subject->support_level}.",
                ]);
            }

            return $subject;
        });

        return response()->json($this->presentSubject($subject->fresh(['issueCategory', 'service', 'subcategory.teamLeadBpo', 'supportAgent', 'itAgent'])));
    }

    public function toggleStatus(ServiceCatalogSubject $subject): JsonResponse
    {
        $actor = CurrentActor::admin();

        $subject = DB::transaction(function () use ($subject, $actor) {
            $wasActive = $subject->is_active;
            $subject->is_active = ! $wasActive;
            $subject->save();

            $verb = $wasActive ? 'menonaktifkan' : 'mengaktifkan';
            AuditTrail::record($actor, [
                'module' => 'service_catalog',
                'action' => $wasActive ? 'deactivate' : 'activate',
                'target_type' => 'subject',
                'target_id' => $subject->id,
                'target_name' => $subject->name,
                'old_value' => ['status' => $wasActive ? 'active' : 'inactive'],
                'new_value' => ['status' => $subject->is_active ? 'active' : 'inactive'],
                'description' => "{$actor->name} {$verb} subject \"{$subject->name}\".",
            ]);

            return $subject;
        });

        return response()->json($this->presentSubject($subject->fresh(['issueCategory', 'service', 'subcategory.teamLeadBpo', 'supportAgent', 'itAgent'])));
    }

    /**
     * Membuang Sub Kategori dan Layanan yang jadi kosong setelah Subjek
     * terakhirnya dihapus.
     *
     * store() MEMBUAT keduanya lewat firstOrCreate saat Admin menambah
     * Subjek, tapi destroy() dulu hanya menghapus Subjeknya. Wadahnya
     * tertinggal selamanya — tidak ketahuan selama tidak ada layar yang
     * menampilkan Sub Kategori, lalu muncul semua begitu tab "Cakupan Team
     * Lead" ada, sebagai baris berisi 0 Subjek yang menunggu ditugaskan.
     *
     * Hanya yang benar-benar nol. Foreign key `subjects.service_id` dan
     * `subjects.subcategory_id` sengaja tanpa cascade, jadi kalau perhitungan
     * di sini keliru, basis datanya sendiri yang menolak — bukan diam-diam
     * ikut menghapus Subjek orang.
     *
     * @return list<string> kalimat untuk ditempelkan ke deskripsi audit
     */
    private function pruneEmptyContainers(ServiceCatalogSubject $subject): array
    {
        $catatan = [];

        $subcategory = ServiceCatalogSubcategory::find($subject->subcategory_id);

        if ($subcategory && $subcategory->subjects()->count() === 0) {
            $catatan[] = "Sub Kategori \"{$subcategory->name}\" ikut dihapus karena tidak lagi berisi Subjek.";
            $subcategory->delete();
        }

        /*
         | LAYANAN SENGAJA TIDAK IKUT DIHAPUS, meski jadi kosong.
         |
         | Layanan tanpa Sub Kategori TETAP muncul di pemilih Aplikasi pada
         | form Tiket Baru — Sub Category-nya jatuh ke "Other" (lihat
         | MASTER_APPLICATIONS di ServiceCatalogSeeder dan NewTicketModal).
         | Di data nyata ada 28 Layanan seperti itu, dan semuanya disengaja:
         | daftar aplikasi perusahaan yang belum punya definisi Subjek.
         |
         | Membuangnya otomatis berarti menghapus 28 pilihan dari layar
         | requester tanpa ada yang meminta. Layanan yang benar-benar sampah
         | dibuang lewat `catalog:prune-empty --drop-service=ID`, yang
         | menuntut Admin menyebut id-nya satu per satu.
         */

        return $catatan;
    }

    public function destroy(ServiceCatalogSubject $subject): JsonResponse
    {
        $actor = CurrentActor::admin();

        DB::transaction(function () use ($subject, $actor) {
            // Dicatat SEBELUM baris hilang: setelah dihapus, old_value ini
            // satu-satunya jejak isi subjek yang tersisa.
            $jejak = AuditTrail::record($actor, [
                'module' => 'service_catalog',
                'action' => 'delete',
                'target_type' => 'subject',
                'target_id' => $subject->id,
                'target_name' => $subject->name,
                'old_value' => $this->displaySnapshot($subject),
                'new_value' => null,
                'description' => "{$actor->name} menghapus subject \"{$subject->name}\".",
            ]);

            $subject->delete();

            $dibersihkan = $this->pruneEmptyContainers($subject);

            if ($dibersihkan !== []) {
                // Disebut di jejak yang SUDAH ada, bukan jadi baris audit
                // sendiri: menghapus satu Subjek tidak boleh meledak jadi
                // tiga baris yang harus dibaca satu per satu.
                $jejak->update([
                    'description' => $jejak->description.' '.implode(' ', $dibersihkan),
                ]);
            }
        });

        return response()->json(['deleted' => true]);
    }

    private function subjectsQuery()
    {
        return ServiceCatalogSubject::with(['issueCategory', 'service', 'subcategory.teamLeadBpo', 'supportAgent', 'itAgent'])->orderBy('id');
    }

    private function displaySnapshot(ServiceCatalogSubject $s): array
    {
        return [
            'issue_category' => $s->issueCategory->name,
            'layanan' => $s->service->name,
            'subcategory' => $s->subcategory->name,
            'subject' => $s->name,
            'requires_approval' => $s->requires_approval,
            'status' => $s->is_active ? 'active' : 'inactive',
        ];
    }

    private function supportLabel(ServiceCatalogSubject $s): string
    {
        $names = collect([$s->supportAgent?->name, $s->itAgent?->name])->filter()->values();

        return $names->isEmpty() ? 'Belum ditentukan' : $names->implode(' & ');
    }

    private function formatters(): array
    {
        return [
            'requires_approval' => fn ($v) => $v ? 'Yes' : 'No',
            'status' => fn ($v) => $v === 'active' ? 'Aktif' : 'Nonaktif',
        ];
    }

    private function presentSubject(ServiceCatalogSubject $s): array
    {
        return [
            'id' => $s->id,
            'issue_category' => $s->issueCategory->name,
            'layanan' => $s->service->name,
            'subcategory' => $s->subcategory->name,
            'subject' => $s->name,
            'support_agent_id' => $s->support_agent_id,
            'support_name' => $s->supportAgent?->name,
            'support_type' => $s->supportAgent?->type,
            'it_agent_id' => $s->it_agent_id,
            'it_name' => $s->itAgent?->name,
            'support_level' => $s->support_level,
            // Dari Subkategori induknya — penugasan Team Lead hidup di sana,
            // bukan di Subjek. Ditampilkan supaya Admin bisa melihat siapa
            // yang mengawasi tanpa berpindah tab.
            'team_lead_bpo_name' => $s->subcategory?->teamLeadBpo?->name,
            'requires_approval' => $s->requires_approval,
            'status' => $s->is_active ? 'active' : 'inactive',
        ];
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'issue_category' => ['required', Rule::in(['Incident', 'Service Request', 'Access Request'])],
            'layanan' => 'required|string|max:255',
            'subcategory' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'requires_approval' => 'required|boolean',
            'support_agent_id' => ['nullable', 'integer', Rule::exists('support_agents', 'id')->where('type', 'bpo')],
            'it_agent_id' => ['nullable', 'integer', Rule::exists('support_agents', 'id')->where('type', 'it')],
            'support_level' => 'required|integer|min:1|max:2',
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $this->assertPicAssigned((int) $data['support_level'], $data['support_agent_id'] ?? null, $data['it_agent_id'] ?? null);

        return $data;
    }

    /**
     * A Subject with no PIC leaves a ticket with nowhere to route — the
     * form UI already blocks saving without one, but this is the
     * authoritative check: a Level 1 Subject needs whichever single agent
     * field it's using, a Level 2 Subject (handled by BPO and IT together)
     * needs both.
     */
    private function assertPicAssigned(int $level, ?int $supportAgentId, ?int $itAgentId): void
    {
        $missing = $level === 2
            ? (! $supportAgentId || ! $itAgentId)
            : (! $supportAgentId && ! $itAgentId);

        if ($missing) {
            throw ValidationException::withMessages([
                'support_agent_id' => 'PIC Support BPO/IT wajib dipilih sebelum layanan bisa disimpan — tiket tidak boleh dibuat tanpa arah PIC yang jelas.',
            ]);
        }
    }
}

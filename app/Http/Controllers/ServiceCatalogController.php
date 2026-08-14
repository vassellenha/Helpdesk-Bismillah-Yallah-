<?php

namespace App\Http\Controllers;

use App\Models\AuditTrail;
use App\Models\IssueCategory;
use App\Models\ServiceCatalogService;
use App\Models\ServiceCatalogSubcategory;
use App\Models\ServiceCatalogSubject;
use App\Models\SupportAgent;
use App\Models\Ticket;
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
            'subcategories' => ServiceCatalogSubcategory::orderBy('name')->get(['id', 'service_id', 'name']),
            'supportAgents' => SupportAgent::where('is_active', true)->orderBy('name')->get(['id', 'name', 'type']),
            'stats' => [
                'total_subject' => $subjects->count(),
                'aktif' => $subjects->where('is_active', true)->count(),
                'requires_approval' => $subjects->where('requires_approval', true)->count(),
                'total_layanan' => ServiceCatalogService::count(),
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

        return response()->json($this->presentSubject($subject->fresh(['issueCategory', 'service', 'subcategory', 'supportAgent', 'itAgent'])), 201);
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

        return response()->json($this->presentSubject($subject->fresh(['issueCategory', 'service', 'subcategory', 'supportAgent', 'itAgent'])));
    }

    /**
     * Dedicated, subject_id-scoped endpoint: updating Support/Level for one
     * Subject must never ripple to sibling subjects under the same Layanan
     * or Sub Category. Route-model-bound by {subject}, nothing else.
     */
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

        return response()->json($this->presentSubject($subject->fresh(['issueCategory', 'service', 'subcategory', 'supportAgent', 'itAgent'])));
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

        return response()->json($this->presentSubject($subject->fresh(['issueCategory', 'service', 'subcategory', 'supportAgent', 'itAgent'])));
    }

    public function destroy(ServiceCatalogSubject $subject): JsonResponse
    {
        $subject->delete();

        return response()->json(['deleted' => true]);
    }

    private function subjectsQuery()
    {
        return ServiceCatalogSubject::with(['issueCategory', 'service', 'subcategory', 'supportAgent', 'itAgent'])->orderBy('id');
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

<?php

namespace App\Http\Controllers;

use App\Models\AuditTrail;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Support\AuditDescriber;
use App\Support\CurrentActor;
use App\Support\DummyData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SlaPolicyController extends Controller
{
    private const FIELD_LABELS = [
        'policy_name' => 'Nama Policy',
        'priority' => 'Priority',
        'response_time_minutes' => 'Response Time',
        'resolution_time_minutes' => 'Resolution Time',
        'warning_threshold_percent' => 'Warning Threshold',
        'status' => 'Status',
    ];

    public function index(): View
    {
        return view('admin.sla', [
            'role' => 'admin',
            'currentUser' => DummyData::currentAdmin(),
            'policies' => SlaPolicy::orderBy('id')->get(),
            'ticketSlaBreakdown' => $this->ticketSlaBreakdown(),
        ]);
    }

    /**
     * Same "active tickets with a computable SLA state" scope used by the
     * Admin Dashboard and Requester's own SLA donut, so this page's
     * within/warning/breach percentages never disagree with theirs.
     */
    private function ticketSlaBreakdown(): array
    {
        $active = Ticket::whereIn('status', Ticket::ACTIVE_STATUSES)->get()
            ->filter(fn (Ticket $t) => $t->sla_minutes_remaining !== null);

        $onTrack = $active->filter(fn (Ticket $t) => $t->sla_kind === 'ontrack')->count();
        $warning = $active->filter(fn (Ticket $t) => $t->sla_kind === 'warning')->count();
        $breach = $active->filter(fn (Ticket $t) => $t->sla_kind === 'breach')->count();
        $total = max($onTrack + $warning + $breach, 1);

        return [
            ['label' => 'Within SLA', 'percent' => (int) round($onTrack / $total * 100), 'color' => '#10b981'],
            ['label' => 'Warning', 'percent' => (int) round($warning / $total * 100), 'color' => '#f59e0b'],
            ['label' => 'Breach', 'percent' => (int) round($breach / $total * 100), 'color' => '#ef4444'],
        ];
    }

    public function list(): JsonResponse
    {
        return response()->json(SlaPolicy::orderBy('id')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $actor = CurrentActor::admin();

        $policy = DB::transaction(function () use ($data, $actor) {
            $policy = SlaPolicy::create($data);

            AuditTrail::record($actor, [
                'module' => 'sla_configuration',
                'action' => 'create',
                'target_type' => 'sla_policy',
                'target_id' => $policy->id,
                'target_name' => $policy->policy_name,
                'new_value' => $policy->only(array_keys(self::FIELD_LABELS)),
                'description' => "{$actor->name} menambahkan SLA Policy \"{$policy->policy_name}\".",
            ]);

            return $policy;
        });

        return response()->json($policy, 201);
    }

    public function update(Request $request, SlaPolicy $slaPolicy): JsonResponse
    {
        $data = $this->validated($request, $slaPolicy->id);
        $actor = CurrentActor::admin();
        $before = $slaPolicy->only(array_keys(self::FIELD_LABELS));

        $slaPolicy = DB::transaction(function () use ($data, $slaPolicy, $before, $actor) {
            $slaPolicy->update($data);
            $after = $slaPolicy->only(array_keys(self::FIELD_LABELS));

            $changes = AuditDescriber::diff($before, $after, self::FIELD_LABELS, $this->formatters());

            if ($changes !== []) {
                AuditTrail::record($actor, [
                    'module' => 'sla_configuration',
                    'action' => 'update',
                    'target_type' => 'sla_policy',
                    'target_id' => $slaPolicy->id,
                    'target_name' => $slaPolicy->policy_name,
                    ...AuditDescriber::presentDiff($changes),
                    'description' => AuditDescriber::describe($actor->name, 'SLA', $slaPolicy->policy_name, $changes),
                ]);
            }

            return $slaPolicy;
        });

        return response()->json($slaPolicy);
    }

    public function toggle(SlaPolicy $slaPolicy): JsonResponse
    {
        $actor = CurrentActor::admin();

        $slaPolicy = DB::transaction(function () use ($slaPolicy, $actor) {
            $wasActive = $slaPolicy->status === 'active';
            $slaPolicy->status = $wasActive ? 'inactive' : 'active';
            $slaPolicy->save();

            $verb = $wasActive ? 'menonaktifkan' : 'mengaktifkan';
            AuditTrail::record($actor, [
                'module' => 'sla_configuration',
                'action' => $wasActive ? 'deactivate' : 'activate',
                'target_type' => 'sla_policy',
                'target_id' => $slaPolicy->id,
                'target_name' => $slaPolicy->policy_name,
                'old_value' => ['status' => $wasActive ? 'active' : 'inactive'],
                'new_value' => ['status' => $slaPolicy->status],
                'description' => "{$actor->name} {$verb} SLA Policy \"{$slaPolicy->policy_name}\".",
            ]);

            return $slaPolicy;
        });

        return response()->json($slaPolicy);
    }

    public function activeForRequester(): JsonResponse
    {
        return response()->json(
            SlaPolicy::active()->orderByRaw("field(priority,'Critical','High','Medium','Low')")->get()
        );
    }

    private function formatters(): array
    {
        return [
            'response_time_minutes' => fn ($v) => AuditDescriber::minutesLabel((int) $v),
            'resolution_time_minutes' => fn ($v) => AuditDescriber::minutesLabel((int) $v),
            'warning_threshold_percent' => fn ($v) => "{$v}%",
            'status' => fn ($v) => $v === 'active' ? 'Aktif' : 'Nonaktif',
        ];
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'policy_name' => 'required|string|max:255',
            'priority' => ['required', Rule::in(['Critical', 'High', 'Medium', 'Low'])],
            'response_time_minutes' => 'required|integer|min:1',
            'resolution_time_minutes' => 'required|integer|gt:response_time_minutes',
            'warning_threshold_percent' => 'required|integer|min:1|max:100',
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        // Scoped to *active* rows only. A Requester picks an SLA policy by
        // priority alone (no service is bound to a specific SLA — see
        // NewTicketModal), so only one active policy per priority may exist.
        if ($data['status'] === 'active') {
            $duplicate = SlaPolicy::active()
                ->where('priority', $data['priority'])
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists();

            if ($duplicate) {
                abort(422, 'Sudah ada SLA Policy aktif untuk priority ini.');
            }
        }

        return $data;
    }
}

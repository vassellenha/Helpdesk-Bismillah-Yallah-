<?php

namespace App\Http\Controllers;

use App\Models\AuditTrail;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Support\AuditDescriber;
use App\Support\CurrentActor;
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
        'escalation_extension_minutes' => 'Escalated Time',
        'warning_threshold_percent' => 'Warning Threshold',
        'status' => 'Status',
    ];

    public function index(): View
    {
        return view('admin.sla', [
            'role' => 'admin',
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
            $renamedFrom = $before['priority'];
            $slaPolicy->update($data);
            $after = $slaPolicy->only(array_keys(self::FIELD_LABELS));

            $movedTickets = $this->renamePriorityOnTickets($renamedFrom, $slaPolicy->priority);

            $changes = AuditDescriber::diff($before, $after, self::FIELD_LABELS, $this->formatters());

            if ($changes !== []) {
                AuditTrail::record($actor, [
                    'module' => 'sla_configuration',
                    'action' => 'update',
                    'target_type' => 'sla_policy',
                    'target_id' => $slaPolicy->id,
                    'target_name' => $slaPolicy->policy_name,
                    ...AuditDescriber::presentDiff($changes),
                    'description' => AuditDescriber::describe($actor->name, 'SLA', $slaPolicy->policy_name, $changes)
                        .($movedTickets > 0 ? " Prioritas {$movedTickets} tiket ikut diganti namanya." : ''),
                ]);
            }

            return $slaPolicy;
        });

        return response()->json($slaPolicy);
    }

    /**
     * Ikut mengganti nama prioritas pada tiket yang memakainya.
     *
     * Tanpa ini, mengganti nama sebuah prioritas meninggalkan tiket lama
     * memegang nama yang sudah tidak dimiliki policy mana pun: prioritasnya
     * hilang dari semua filter, lencananya berubah netral, dan tiketnya
     * berhenti terhitung di grafik distribusi — padahal Admin merasa hanya
     * mengganti sebutan, bukan mencabut prioritas ratusan tiket.
     *
     * Tidak dilakukan kalau masih ada policy LAIN yang memakai nama lama:
     * di situ nama itu masih sah, dan tiketnya memang milik policy tersebut.
     */
    private function renamePriorityOnTickets(string $from, string $to): int
    {
        if ($from === $to) {
            return 0;
        }

        $stillInUse = SlaPolicy::where('priority', $from)->exists();

        if ($stillInUse) {
            return 0;
        }

        return Ticket::where('priority', $from)->update(['priority' => $to]);
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

    /**
     * Menghapus SLA Policy — hanya kalau benar-benar tidak dipakai tiket mana pun.
     *
     * Tiket menyimpan `sla_policy_id` sebagai foreign key, dan kolom itu tidak
     * boleh kosong. Menghapus policy yang masih dipakai berarti salah satu dari
     * dua hal buruk: basis data menolaknya dengan galat SQL mentah yang tidak
     * berarti apa-apa bagi Admin, atau — kalau kelak dipasang cascade — tiketnya
     * ikut terhapus. Keduanya tidak pernah yang dimaksud Admin ketika ia hanya
     * ingin merapikan daftar SLA.
     *
     * Jadi penghalangnya dipasang di sini, dengan angka yang bisa ditindaklanjuti:
     * Admin diberi tahu berapa tiket yang menahannya, lalu bisa memilih
     * menonaktifkan policy itu saja — yang menyembunyikannya dari pemilih
     * prioritas tanpa menyentuh riwayat tiket sama sekali.
     */
    public function destroy(SlaPolicy $slaPolicy): JsonResponse
    {
        $actor = CurrentActor::admin();
        $usage = $this->ticketsHeldBy($slaPolicy);

        if ($usage > 0) {
            return response()->json([
                'message' => "SLA Policy ini masih dipakai {$usage} tiket, jadi tidak bisa dihapus. "
                    .'Nonaktifkan saja kalau tidak ingin dipakai lagi — tiket lama tetap utuh.',
                'tickets_using' => $usage,
            ], 422);
        }

        DB::transaction(function () use ($slaPolicy, $actor) {
            AuditTrail::record($actor, [
                'module' => 'sla_configuration',
                'action' => 'delete',
                'target_type' => 'sla_policy',
                'target_id' => $slaPolicy->id,
                'target_name' => $slaPolicy->policy_name,
                'old_value' => $slaPolicy->only(array_keys(self::FIELD_LABELS)),
                'new_value' => null,
                'description' => "{$actor->name} menghapus SLA Policy \"{$slaPolicy->policy_name}\".",
            ]);

            $slaPolicy->delete();
        });

        return response()->json(['deleted' => true]);
    }

    /**
     * Berapa tiket yang menahan sebuah policy agar tidak bisa dihapus.
     *
     * Dihitung dari DUA arah, karena ada dua cara tiket bergantung pada policy
     * ini dan hanya satu di antaranya terlihat lewat foreign key:
     *
     * 1. Tiket yang memang menunjuk policy ini (`sla_policy_id`) — jalur biasa,
     *    requester memilih prioritasnya saat membuat tiket.
     * 2. Tiket yang prioritasnya sama dengan prioritas policy ini, tapi
     *    `sla_policy_id`-nya menunjuk policy lain. Ini terjadi ketika Team Lead
     *    menaikkan prioritas tiket: yang diganti hanya kolom `priority`, SLA
     *    aslinya tetap dipegang. Tanpa hitungan kedua, policy-nya bisa dihapus
     *    dan tiket itu tertinggal memegang prioritas yang tidak diakui siapa
     *    pun — hilang dari filter, lencananya netral, tak terhitung di grafik.
     *
     * Arah kedua diabaikan kalau masih ada policy LAIN dengan prioritas yang
     * sama: di situ nama prioritasnya tetap sah tanpa policy ini.
     */
    private function ticketsHeldBy(SlaPolicy $slaPolicy): int
    {
        $query = Ticket::where('sla_policy_id', $slaPolicy->id);

        $priorityStillCovered = SlaPolicy::where('priority', $slaPolicy->priority)
            ->whereKeyNot($slaPolicy->id)
            ->exists();

        if (! $priorityStillCovered) {
            $query->orWhere('priority', $slaPolicy->priority);
        }

        return $query->count();
    }

    public function activeForRequester(): JsonResponse
    {
        // Diurutkan menurut target penyelesaian, bukan daftar nama tetap.
        // `field(priority,'Critical',…)` menaruh setiap prioritas buatan Admin
        // di urutan terakhir berapa pun ketatnya SLA-nya — "Urgent" dengan
        // target 60 menit akan muncul di bawah "Low" pada pemilih tiket baru.
        return response()->json(
            SlaPolicy::active()->orderBy('resolution_time_minutes')->get()
        );
    }

    private function formatters(): array
    {
        return [
            'response_time_minutes' => fn ($v) => AuditDescriber::minutesLabel((int) $v),
            'resolution_time_minutes' => fn ($v) => AuditDescriber::minutesLabel((int) $v),
            'escalation_extension_minutes' => fn ($v) => AuditDescriber::minutesLabel((int) $v),
            'warning_threshold_percent' => fn ($v) => "{$v}%",
            'status' => fn ($v) => $v === 'active' ? 'Aktif' : 'Nonaktif',
        ];
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'policy_name' => 'required|string|max:255',
            // Teks bebas: Admin boleh membuat tingkat baru ("Urgent"). Dibatasi
            // panjangnya saja, mengikuti kolomnya yang kini varchar(50).
            'priority' => 'required|string|max:50',
            'response_time_minutes' => 'required|integer|min:1',
            'resolution_time_minutes' => 'required|integer|gt:response_time_minutes',
            'escalation_extension_minutes' => 'required|integer|min:0',
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

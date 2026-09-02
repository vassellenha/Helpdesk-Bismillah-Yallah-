<?php

namespace App\Http\Controllers;

use App\Models\ServiceCatalogSubject;
use App\Models\SlaPolicy;
use App\Models\SupportAgent;
use App\Models\Ticket;
use App\Models\User;
use App\Support\CurrentActor;
use App\Support\NotificationService;
use App\Support\TicketAudit;
use App\Support\TicketBroadcast;
use App\Support\TicketNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    /**
     * Aturan kelengkapan tiket — dipakai bersama oleh pembuatan dan penyuntingan.
     *
     * Kolom yang diwajibkan di sini sama persis dengan yang diwajibkan layar
     * Requester. Sebelumnya hanya `title` dan `sla_policy_id` yang wajib,
     * sehingga tiket tanpa layanan, sub kategori, maupun subjek bisa lahir
     * lewat pemanggilan langsung ke endpoint — tombol yang dimatikan di layar
     * hanya menutupi jalan yang lewat tampilan, bukan menutup aturannya.
     *
     * Draf dikecualikan dengan sengaja. Draf ADALAH pekerjaan yang belum
     * selesai; satu-satunya yang tetap dituntut darinya adalah Layanan, karena
     * dari situlah nomor tiket dan penyalurannya nanti diturunkan.
     *
     * @return array<string, mixed>
     */
    private function ticketRules(Request $request): array
    {
        $isDraft = $request->boolean('is_draft');
        $requiredUnlessDraft = Rule::requiredIf(fn (): bool => ! $isDraft);

        return [
            'title' => 'required|string|max:255',
            'category' => 'nullable|string|max:255',
            'requester_name' => 'nullable|string|max:255',
            'sla_policy_id' => 'required|integer|exists:sla_policies,id',
            // Layanan dituntut lewat NAMANYA, bukan lewat kunci asingnya.
            // `service_catalog_service_id` boleh kosong pada tiket yang sah —
            // tiket lama maupun tiket yang layanannya sudah tidak ada lagi di
            // katalog tetap menyimpan namanya. Mewajibkan kunci asing di sini
            // akan menolak pengiriman ulang tiket Returned yang sepenuhnya sah.
            'service_name' => 'required|string|max:255',
            'service_id' => 'nullable|integer|exists:service_catalog_services,id',
            'subcategory_name' => [$requiredUnlessDraft, 'string', 'max:255'],
            'subject_name' => [$requiredUnlessDraft, 'string', 'max:255'],
            'issue_category' => [$requiredUnlessDraft, 'string', 'max:255'],
            'description' => 'nullable|string|max:5000',
            'approver_id' => [
                Rule::requiredIf(fn (): bool => ! $isDraft && $request->boolean('requires_approval')),
                'nullable',
                'integer',
                'exists:users,id',
            ],
            'requires_approval' => 'nullable|boolean',
            'is_draft' => 'nullable|boolean',
            'catalog_subject_id' => ['nullable', 'integer', Rule::exists('service_catalog_subjects', 'id')->where('is_active', true)],
        ];
    }

    public function store(Request $request): JsonResponse
    {
        // Aktornya ditetapkan SEBELUM validasi, bukan sesudah. Urutan sebaliknya
        // membuat akun yang aksesnya dicabut dijawab 422 berisi rincian kolom —
        // menjawab isi formulir kepada orang yang seharusnya sudah dihentikan di
        // depan pintu, dan membuat gerbangnya bergantung pada muatan permintaan.
        $requester = CurrentActor::requester();

        $data = $request->validate($this->ticketRules($request));

        /** @var SlaPolicy $policy */
        $policy = SlaPolicy::findOrFail($data['sla_policy_id']);

        if ($policy->status !== 'active') {
            return response()->json([
                'message' => 'SLA Policy yang dipilih sudah tidak aktif. Silakan pilih priority lain.',
            ], 422);
        }

        $isDraft = (bool) ($data['is_draft'] ?? false);
        $requiresApproval = (bool) ($data['requires_approval'] ?? false);

        $now = Carbon::now();
        $resolutionDueAt = $now->clone()->addMinutes($policy->resolution_time_minutes);
        $warningAt = $now->clone()->addMinutes((int) round($policy->resolution_time_minutes * $policy->warning_threshold_percent / 100));

        $prefix = TicketNumber::prefixFor($data['issue_category'] ?? null);

        $status = match (true) {
            $isDraft => 'Draft',
            $requiresApproval => 'Waiting for Approval',
            default => 'Open',
        };

        $assignedAgentId = $this->resolveAssignedAgentId($data['catalog_subject_id'] ?? null);

        $ticket = Ticket::create([
            'ticket_no' => TicketNumber::next($prefix, $data['service_name'] ?? null, $now),
            'title' => $data['title'],
            'requester_name' => $requester->name,
            'requester_id' => $requester->id,
            'category' => $data['category'] ?? $data['issue_category'] ?? null,
            'service_name' => $data['service_name'] ?? null,
            'service_catalog_service_id' => $data['service_id'] ?? null,
            'subcategory_name' => $data['subcategory_name'] ?? null,
            'subject_name' => $data['subject_name'] ?? null,
            'issue_category' => $data['issue_category'] ?? null,
            'description' => $data['description'] ?? null,
            'sla_policy_id' => $policy->id,
            'priority' => $policy->priority,
            'approver_id' => $requiresApproval ? ($data['approver_id'] ?? null) : null,
            'assigned_agent_id' => $assignedAgentId,
            'catalog_subject_id' => $data['catalog_subject_id'] ?? null,
            'response_time_minutes' => $policy->response_time_minutes,
            'resolution_time_minutes' => $policy->resolution_time_minutes,
            'warning_threshold_percent' => $policy->warning_threshold_percent,
            'response_due_at' => $now->clone()->addMinutes($policy->response_time_minutes),
            'resolution_due_at' => $resolutionDueAt,
            'warning_at' => $warningAt,
            'sla_started_at' => $now,
            'status' => $status,
            'is_draft' => $isDraft,
        ]);

        if (! $isDraft) {
            $message = $requiresApproval
                ? "Tiket {$ticket->ticket_no} berhasil dibuat dan menunggu persetujuan."
                : "Tiket {$ticket->ticket_no} berhasil dibuat dan dikirim ke Tim Support.";

            NotificationService::notify($requester, 'requester', $ticket, 'ticket_created', 'Tiket Dibuat', $message);
            $this->notifyApproverOfNewRequest($ticket, $requester);
            $this->notifyAgentOfNewAssignment($ticket, $requiresApproval);
        }

        // Draft ikut dicatat. Ia belum terlihat siapa pun selain pembuatnya,
        // tapi ia sudah memakai nomor tiket — dan nomor yang muncul di jejak
        // tanpa asal-usul lebih membingungkan daripada draf yang tercatat.
        TicketAudit::action($requester, 'ticket_requester', 'create', $ticket,
            "{$requester->name} membuat tiket \"{$ticket->ticket_no}\" (".($isDraft ? 'draf' : $ticket->status).').',
            ['status' => $ticket->status, 'prioritas' => $ticket->priority, 'layanan' => $ticket->service_name]);

        return response()->json([
            ...$ticket->toArray(),
            'sla_status' => $ticket->sla_status,
        ], 201);
    }

    /**
     * Drafts and Returned (sent back for revision) are the only tickets a
     * Requester can still change after creation — once submitted and past
     * approval (Open/Waiting for Approval/...), the ticket is locked in,
     * matching TicketDetailController's comment/reopen/close actions which
     * never touch the original request fields. ticket_no is never
     * regenerated here, even if issue_category changes, since it must stay
     * fixed once assigned.
     */
    public function update(Request $request, Ticket $ticket): JsonResponse
    {
        $requester = CurrentActor::requester();
        abort_unless($ticket->requester_id === $requester->id, 403);
        abort_unless(in_array($ticket->status, ['Draft', 'Returned'], true), 422, 'Only draft or returned tickets can be edited.');

        // Aturan yang sama persis dengan pembuatan tiket. Inilah jalur yang
        // mengubah draf menjadi tiket terkirim, jadi kelengkapannya harus
        // dituntut di sini juga — kalau tidak, draf setengah jadi tetap bisa
        // dikirim lewat jalur ini dan lubangnya sekadar berpindah tempat.
        $data = $request->validate($this->ticketRules($request));

        /** @var SlaPolicy $policy */
        $policy = SlaPolicy::findOrFail($data['sla_policy_id']);

        if ($policy->status !== 'active') {
            return response()->json([
                'message' => 'SLA Policy yang dipilih sudah tidak aktif. Silakan pilih priority lain.',
            ], 422);
        }

        $isDraft = (bool) ($data['is_draft'] ?? false);
        $requiresApproval = (bool) ($data['requires_approval'] ?? false);

        $now = Carbon::now();
        $status = match (true) {
            $isDraft => 'Draft',
            $requiresApproval => 'Waiting for Approval',
            default => 'Open',
        };

        $newCatalogSubjectId = $data['catalog_subject_id'] ?? null;

        /*
         | Tiket "Returned" sudah punya PIC sungguhan — bisa BPO (rute biasa)
         | ATAU IT (kalau sempat dieskalasi sebelum dikembalikan). Menghitung
         | ulang lewat resolveAssignedAgentId() di sini SELALU jatuh ke slot
         | BPO default Subject-nya, jadi tiket yang tadinya sudah di tangan IT
         | diam-diam kembali ke BPO begitu requester mengirim ulang — padahal
         | mestinya tetap di tangan siapa pun yang terakhir mengembalikannya.
         | Hanya dihitung ulang kalau kategorinya benar-benar berubah, atau
         | kalau ini submission Draft yang memang belum pernah dirutekan.
         */
        $assignedAgentId = ($ticket->status === 'Returned' && $newCatalogSubjectId === $ticket->catalog_subject_id)
            ? $ticket->assigned_agent_id
            : $this->resolveAssignedAgentId($newCatalogSubjectId);

        $ticket->update([
            'title' => $data['title'],
            'category' => $data['category'] ?? $data['issue_category'] ?? null,
            'service_name' => $data['service_name'] ?? null,
            'service_catalog_service_id' => $data['service_id'] ?? null,
            'subcategory_name' => $data['subcategory_name'] ?? null,
            'subject_name' => $data['subject_name'] ?? null,
            'issue_category' => $data['issue_category'] ?? null,
            'description' => $data['description'] ?? null,
            'sla_policy_id' => $policy->id,
            'priority' => $policy->priority,
            'approver_id' => $requiresApproval ? ($data['approver_id'] ?? null) : null,
            'assigned_agent_id' => $assignedAgentId,
            'catalog_subject_id' => $newCatalogSubjectId,
            'response_time_minutes' => $policy->response_time_minutes,
            'resolution_time_minutes' => $policy->resolution_time_minutes,
            'warning_threshold_percent' => $policy->warning_threshold_percent,
            'response_due_at' => $now->clone()->addMinutes($policy->response_time_minutes),
            'resolution_due_at' => $now->clone()->addMinutes($policy->resolution_time_minutes),
            // A resubmitted ticket gets a fresh SLA, so last round's response
            // stamp and escalation credit must not carry over into it.
            'sla_started_at' => $now,
            'first_response_at' => null,
            'sla_extension_minutes' => 0,
            'warning_at' => $now->clone()->addMinutes((int) round($policy->resolution_time_minutes * $policy->warning_threshold_percent / 100)),
            'status' => $status,
            'is_draft' => $isDraft,
        ]);

        if (! $isDraft) {
            $message = $requiresApproval
                ? "Tiket {$ticket->ticket_no} berhasil dikirim dan menunggu persetujuan."
                : "Tiket {$ticket->ticket_no} berhasil dikirim ke Tim Support.";

            NotificationService::notify($requester, 'requester', $ticket, 'ticket_created', 'Tiket Dikirim', $message);
            $this->notifyApproverOfNewRequest($ticket->fresh(), $requester);
            $this->notifyAgentOfNewAssignment($ticket->fresh(), $requiresApproval);
        }

        TicketAudit::action($requester, 'ticket_requester', 'update', $ticket->fresh(),
            "{$requester->name} mengubah tiket \"{$ticket->ticket_no}\" (".($isDraft ? 'disimpan sebagai draf' : 'dikirim').').',
            ['status' => $ticket->fresh()->status, 'prioritas' => $ticket->fresh()->priority]);

        return response()->json([
            ...$ticket->fresh()->toArray(),
            'sla_status' => $ticket->fresh()->sla_status,
        ]);
    }

    /**
     * Only a Draft or Returned ticket is still purely "mine" to discard
     * outright — same status boundary update() already enforces, since
     * anything past that has already reached an Approver/Support queue.
     */
    public function destroy(Ticket $ticket): JsonResponse
    {
        $requester = CurrentActor::requester();
        abort_unless($ticket->requester_id === $requester->id, 403);
        abort_unless(in_array($ticket->status, ['Draft', 'Returned'], true), 422, 'Only draft or returned tickets can be deleted.');

        /*
         | Dicatat SEBELUM dihapus, dan ini bukan sekadar urutan yang rapi.
         |
         | Sesudah delete() tidak ada lagi tiket untuk ditanyai: judulnya,
         | statusnya, nomornya — semuanya ikut hilang. Inilah satu-satunya
         | tindakan di aplikasi ini yang membuat objeknya lenyap, jadi barisnya
         | harus lahir selagi objek itu masih bisa bicara.
         */
        TicketAudit::action($requester, 'ticket_requester', 'delete', $ticket,
            "{$requester->name} menghapus tiket \"{$ticket->ticket_no}\" berstatus {$ticket->status}.",
            ['judul' => $ticket->title, 'status' => $ticket->status, 'lampiran' => $ticket->attachments->count()]);

        foreach ($ticket->attachments as $attachment) {
            Storage::disk('local')->delete($attachment->path);
        }
        $ticket->delete();

        return response()->json(['deleted' => true]);
    }

    /**
     * `Ticket::count() + 1` broke as soon as any ticket got deleted (e.g. a
     * Requester clearing out old drafts): the count drops, so the next
     * ticket reuses a number that's still taken by a surviving row, hitting
     * the ticket_no unique constraint. Basing this on the highest number
     * actually in use for the year is immune to gaps from deletions — it
     * only ever moves forward, regardless of how many rows disappeared.
     */

    /**
     * A catalog subject may only have one of its two agent slots filled —
     * some subjects route straight to IT with no BPO slot at all (support_
     * agent_id null, it_agent_id set). Falling back to it_agent_id here
     * keeps that ticket from being created with no PIC whatsoever; a Level 2
     * subject (both slots filled) still resolves to the BPO slot first,
     * matching the existing BPO-first-line handling.
     */
    private function resolveAssignedAgentId(?int $catalogSubjectId): ?int
    {
        if (! $catalogSubjectId) {
            return null;
        }

        $subject = ServiceCatalogSubject::find($catalogSubjectId);

        return $subject?->support_agent_id ?? $subject?->it_agent_id;
    }

    /**
     * Lets the assigned approver's notification bell (and Approval Inbox
     * metrics) reflect a new request the moment it lands in their queue,
     * mirroring the "Menunggu keputusan" alert from the Approval Workspace
     * mockup — otherwise they'd only find out by checking the inbox cold.
     */
    private function notifyApproverOfNewRequest(Ticket $ticket, User $requester): void
    {
        if ($ticket->status !== 'Waiting for Approval' || ! $ticket->approver) {
            return;
        }

        NotificationService::notify(
            $ticket->approver,
            'approver',
            $ticket,
            'waiting_decision',
            'Menunggu Keputusan Anda',
            "Tiket {$ticket->ticket_no} dari {$requester->name} menunggu persetujuan Anda."
        );
    }

    /**
     * A ticket that skips approval lands directly in Support's queue — its
     * PIC needs the same "new work landed" alert an Approver gets for
     * "Waiting for Approval". Approved-and-forwarded tickets are handled
     * separately by ApprovalController::decide(), since routing only
     * happens there once a decision is made.
     */
    /**
     * Tiket katalog biasa sudah punya satu assigned_agent_id begitu dibuat
     * — cukup satu notifikasi. Tiket "Lainnya" untuk Layanan yang punya PIC
     * BPO terdaftar TIDAK dapat assigned_agent_id (lihat TicketBroadcast) —
     * semua PIC-nya perlu tahu, siapa pun boleh mengambilnya duluan.
     */
    private function notifyAgentOfNewAssignment(Ticket $ticket, bool $requiresApproval): void
    {
        if ($requiresApproval || $ticket->status !== 'Open') {
            return;
        }

        $pics = TicketBroadcast::eligiblePics($ticket);

        if ($pics->isNotEmpty()) {
            $pics->each(function (SupportAgent $pic) use ($ticket) {
                if (! $pic->user_id) {
                    return;
                }

                NotificationService::notify(
                    User::find($pic->user_id),
                    NotificationService::roleForAgent($pic),
                    $ticket,
                    'ticket_created',
                    'Tiket Baru Menunggu PIC',
                    "Tiket {$ticket->ticket_no} \"{$ticket->title}\" ({$ticket->service_name}) belum ada yang menangani — siapa pun dari tim BPO bisa mengambilnya."
                );
            });

            return;
        }

        NotificationService::notifyAssignedAgent(
            $ticket,
            'ticket_created',
            'Tiket Baru Ditugaskan',
            "Tiket {$ticket->ticket_no} \"{$ticket->title}\" telah ditugaskan ke Anda."
        );
    }
}

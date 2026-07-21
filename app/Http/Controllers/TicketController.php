<?php

namespace App\Http\Controllers;

use App\Models\ServiceCatalogSubject;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Support\CurrentActor;
use App\Support\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TicketController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'nullable|string|max:255',
            'requester_name' => 'nullable|string|max:255',
            'sla_policy_id' => 'required|integer|exists:sla_policies,id',
            'service_name' => 'nullable|string|max:255',
            'subcategory_name' => 'nullable|string|max:255',
            'subject_name' => 'nullable|string|max:255',
            'issue_category' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:5000',
            'attachment_name' => 'nullable|string|max:255',
            'approver_id' => 'nullable|integer|exists:users,id',
            'requires_approval' => 'nullable|boolean',
            'is_draft' => 'nullable|boolean',
            'catalog_subject_id' => 'nullable|integer|exists:service_catalog_subjects,id',
        ]);

        /** @var SlaPolicy $policy */
        $policy = SlaPolicy::findOrFail($data['sla_policy_id']);

        if ($policy->status !== 'active') {
            return response()->json([
                'message' => 'SLA Policy yang dipilih sudah tidak aktif. Silakan pilih priority lain.',
            ], 422);
        }

        $isDraft = (bool) ($data['is_draft'] ?? false);
        $requiresApproval = (bool) ($data['requires_approval'] ?? false);
        $requester = CurrentActor::requester();

        $now = Carbon::now();
        $resolutionDueAt = $now->clone()->addMinutes($policy->resolution_time_minutes);
        $warningAt = $now->clone()->addMinutes((int) round($policy->resolution_time_minutes * $policy->warning_threshold_percent / 100));

        $prefix = match ($data['issue_category'] ?? null) {
            'Access Request' => 'AR',
            'Service Request' => 'SR',
            default => 'INC',
        };

        $status = match (true) {
            $isDraft => 'Draft',
            $requiresApproval => 'Waiting for Approval',
            default => 'Open',
        };

        $assignedAgentId = isset($data['catalog_subject_id'])
            ? ServiceCatalogSubject::find($data['catalog_subject_id'])?->support_agent_id
            : null;

        $ticket = Ticket::create([
            'ticket_no' => $prefix.'-'.$now->format('Y').'-'.str_pad((string) (Ticket::count() + 1), 4, '0', STR_PAD_LEFT),
            'title' => $data['title'],
            'requester_name' => $requester->name,
            'requester_id' => $requester->id,
            'category' => $data['category'] ?? $data['issue_category'] ?? null,
            'service_name' => $data['service_name'] ?? null,
            'subcategory_name' => $data['subcategory_name'] ?? null,
            'subject_name' => $data['subject_name'] ?? null,
            'issue_category' => $data['issue_category'] ?? null,
            'description' => $data['description'] ?? null,
            'attachment_name' => $data['attachment_name'] ?? null,
            'sla_policy_id' => $policy->id,
            'priority' => $policy->priority,
            'approver_id' => $requiresApproval ? ($data['approver_id'] ?? null) : null,
            'assigned_agent_id' => $assignedAgentId,
            'response_time_minutes' => $policy->response_time_minutes,
            'resolution_time_minutes' => $policy->resolution_time_minutes,
            'warning_threshold_percent' => $policy->warning_threshold_percent,
            'response_due_at' => $now->clone()->addMinutes($policy->response_time_minutes),
            'resolution_due_at' => $resolutionDueAt,
            'warning_at' => $warningAt,
            'status' => $status,
            'is_draft' => $isDraft,
        ]);

        if (! $isDraft) {
            $message = $requiresApproval
                ? "Tiket {$ticket->ticket_no} berhasil dibuat dan menunggu persetujuan."
                : "Tiket {$ticket->ticket_no} berhasil dibuat dan dikirim ke Tim Support.";

            NotificationService::notify($requester, $ticket, 'ticket_created', 'Tiket Dibuat', $message);
        }

        return response()->json([
            ...$ticket->toArray(),
            'sla_status' => $ticket->sla_status,
        ], 201);
    }
}

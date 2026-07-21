<?php

namespace App\Http\Controllers;

use App\Models\SlaPolicy;
use App\Models\Ticket;
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
        ]);

        /** @var SlaPolicy $policy */
        $policy = SlaPolicy::findOrFail($data['sla_policy_id']);

        if ($policy->status !== 'active') {
            return response()->json([
                'message' => 'SLA Policy yang dipilih sudah tidak aktif. Silakan pilih priority lain.',
            ], 422);
        }

        $now = Carbon::now();
        $resolutionDueAt = $now->clone()->addMinutes($policy->resolution_time_minutes);
        $warningAt = $now->clone()->addMinutes((int) round($policy->resolution_time_minutes * $policy->warning_threshold_percent / 100));

        $ticket = Ticket::create([
            'ticket_no' => 'TCK-'.$now->format('Y').'-'.str_pad((string) (Ticket::count() + 1), 4, '0', STR_PAD_LEFT),
            'title' => $data['title'],
            'requester_name' => $data['requester_name'] ?? 'Requester Demo',
            'category' => $data['category'] ?? null,
            'sla_policy_id' => $policy->id,
            'priority' => $policy->priority,
            'response_time_minutes' => $policy->response_time_minutes,
            'resolution_time_minutes' => $policy->resolution_time_minutes,
            'warning_threshold_percent' => $policy->warning_threshold_percent,
            'response_due_at' => $now->clone()->addMinutes($policy->response_time_minutes),
            'resolution_due_at' => $resolutionDueAt,
            'warning_at' => $warningAt,
            'status' => 'Open',
        ]);

        return response()->json([
            ...$ticket->toArray(),
            'sla_status' => $ticket->sla_status,
        ], 201);
    }
}

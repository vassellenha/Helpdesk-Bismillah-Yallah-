<?php

namespace App\Http\Controllers;

use App\Models\TicketNotification;
use App\Support\CurrentActor;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class NotificationController extends Controller
{
    public function markRead(TicketNotification $notification): JsonResponse
    {
        $requester = CurrentActor::requester();
        abort_unless($notification->user_id === $requester->id, 403);

        if ($notification->read_at === null) {
            $notification->update(['read_at' => Carbon::now()]);
        }

        return response()->json(['read' => true]);
    }

    public function markAllRead(): JsonResponse
    {
        $requester = CurrentActor::requester();

        TicketNotification::where('user_id', $requester->id)
            ->where('role', 'requester')
            ->whereNull('read_at')
            ->update(['read_at' => Carbon::now()]);

        return response()->json(['read' => true]);
    }
}

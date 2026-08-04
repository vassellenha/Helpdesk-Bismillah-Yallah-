<?php

/*
|--------------------------------------------------------------------------
| Ticket Flow — English
|--------------------------------------------------------------------------
|
| Mirror of lang/id/flow.php. Keys must stay identical in both files: a key
| present here but missing there (or the reverse) renders as the raw key.
|
*/

return [
    'title' => 'Status History',
    'no_pic' => 'no PIC yet',

    'stage' => [
        'requester' => 'Requester',
        'approver' => 'Approver',
        'no_approval' => 'No Approval',
        'support' => 'Support',
        'done' => 'Done',
    ],

    'sub' => [
        'submitted' => 'Submission',
        'approval' => 'Approval',
        'direct' => 'Straight to Support',
        'handling' => 'Handling',
        'handovers' => 'Handling · :count PIC handover(s)',
        'awaiting_confirmation' => 'Awaiting requester confirmation',
        'closed' => 'Closed',
    ],

    'note' => [
        'draft' => 'Still a draft — not submitted by the requester yet.',
        'waiting' => 'Awaiting the decision from :approver.',
        'returned_approver' => 'Sent back by the approver for revision.',
        'returned_support' => 'Sent back by Support for the requester to complete.',
        'rejected' => 'Rejected by the approver — never forwarded to Support.',
        'with_support' => 'Being handled by Support — :pic.',
        'resolved' => 'Resolved by Support, awaiting requester confirmation.',
        'closed' => 'Ticket finished and closed.',
    ],
];

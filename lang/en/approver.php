<?php

/*
|--------------------------------------------------------------------------
| Approver — English
|--------------------------------------------------------------------------
|
| Mirror of lang/id/approver.php. Keys must stay identical in both files: a key
| present here but missing there (or the reverse) renders as the raw key.
|
*/

return [
    'inbox' => [
        'summary' => ':period Summary',
        'periods' => [
            'week' => 'Week',
            'month' => 'Month',
            'year' => 'Year',
        ],
        'title' => 'Approval Inbox',
        'pending' => 'Awaiting Your Approval',
        'search' => 'Search',
        'search_placeholder' => 'Ticket no., subject…',
        'category' => 'Category',
        'priority' => 'Priority',
        'trend' => 'Approval Decision Trend',
        'priority_distribution' => 'Priority Distribution · This :period',
        'trend_sub' => 'Across this :period',
        'approved' => 'Approved',
        'rejected_revision' => 'Rejected & Revision',
        'stat_pending' => 'Awaiting Approval',
        'stat_approved_month' => 'Approved This Month',
        'stat_rejected_month' => 'Rejected This Month',
        'stat_oldest' => 'Longest Waiting',
    ],

    'history' => [
        'title' => 'My Tickets',
        'search_placeholder' => 'Search tickets, title, or service…',
        'empty' => 'No tickets match these filters.',
        'all_service' => 'All Services',
    ],

    'cards' => [
        'total' => 'Total Tickets',
        'waiting' => 'Awaiting Decision',
        'open' => 'Open',
        'in_progress' => 'In Progress',
        'resolved' => 'Resolved',
        'closed' => 'Closed',
    ],

    'columns' => [
        'id' => 'Ticket',
        'service' => 'Service',
        'status' => 'Status',
        'decision' => 'Decision',
        'note' => 'Note',
        'forwarded_to' => 'Forwarded To',
        'created_at' => 'Time',
    ],

    'periods' => [
        'last_30_days' => 'Last 30 days',
        'last_3_months' => 'Last 3 months',
        'last_6_months' => 'Last 6 months',
        'this_year' => 'This year',
    ],

    'detail' => [
        'mode' => 'Approval Mode',
        'ticket_info' => 'Ticket Information',
        'requester' => 'Requester',
        'unit' => 'Work Unit',
        'catalog_service' => 'Catalog Service',
        'contact' => 'Contact',
        'attachments' => 'Attachments',
        'forum' => 'Discussion Forum',
        'forum_empty' => 'No discussion yet.',
        'forum_placeholder' => 'Write a question or feedback for the requester…',
        'decision_panel' => 'Decision Panel',
        'your_decision' => 'Your Decision',
        'note_label' => 'Note / Feedback',
        'note_placeholder' => 'Required before approving, requesting changes, or rejecting',
        'note_required' => 'A note is required before continuing.',
        'your_note' => 'Your Note',
        'forwarded_to' => 'Forwarded to: ',
        'status_history' => 'Status History',
        'sla' => 'SLA',
        'people' => 'People',
    ],

    'confirm' => [
        'sending' => 'Sending…',
        'no' => 'No',
        'approved' => [
            'title' => 'Approve this request?',
            'body' => 'Ticket :id will be approved and forwarded to the next stage (Support).',
            'button' => 'Yes, Approve',
        ],
        'revision_requested' => [
            'title' => 'Request changes?',
            'body' => 'Ticket :id will be sent back to the requester for revision based on your note.',
            'button' => 'Yes, Request Changes',
        ],
        'rejected' => [
            'title' => 'Reject this request?',
            'body' => 'Ticket :id will be rejected and closed. The requester will be notified.',
            'button' => 'Yes, Reject',
        ],
    ],
];

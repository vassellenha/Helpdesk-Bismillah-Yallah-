<?php

/*
|--------------------------------------------------------------------------
| Support IT & BPO — English
|--------------------------------------------------------------------------
|
| Mirror of lang/id/support.php. Keys must stay identical in both files: a key
| present here but missing there (or the reverse) renders as the raw key.
|
*/

return [
    'nav' => [
        'dashboard' => 'Dashboard',
        'my_tickets' => 'My Tickets',
        'notifications' => 'Notifications',
        'no_notifications' => 'No notifications yet.',
    ],

    'dashboard' => [
        'title' => 'Dashboard',
        'assigned_to_me' => 'Assigned to Me',
        'in_progress' => 'In Progress',
        'near_sla' => 'Near / Past SLA',
        'resolved' => 'Resolved',
        'queue' => 'Awaiting Your Action',
        'search' => 'Search',
        'search_placeholder' => 'Ticket no., subject…',
        'category' => 'Category',
        'priority' => 'Priority',
        'summary' => 'Summary · This :period',
        'by_priority' => 'Tickets by Priority',
        'category_distribution' => 'Category Distribution',
        'total' => 'Total :count',
        'no_rating' => 'No rating yet',
        'reviews' => ':count reviews',
        'showing' => 'Showing :shown of :total tickets',
        'empty' => 'No tickets match these filters.',
    ],

    'periods' => [
        'week' => 'Week',
        'month' => 'Month',
        'year' => 'Year',
    ],

    'filters' => [
        'all_category' => 'All Categories',
        'all_priority' => 'All Priorities',
    ],

    'history' => [
        'title' => 'My Tickets',
        'subtitle' => ':count tickets have been assigned to you.',
        'search_placeholder' => 'Search tickets, title, or service…',
        'all_service' => 'All Services',
        'empty' => 'No tickets match these filters.',
        'periods' => [
            'last_30_days' => 'Last 30 days',
            'last_3_months' => 'Last 3 months',
            'last_6_months' => 'Last 6 months',
            'this_year' => 'This year',
        ],
    ],

    'cards' => [
        'all' => 'All',
        'total' => 'Total Tickets',
        'open' => 'Open',
        'in_progress' => 'In Progress',
        'resolved' => 'Resolved',
        'closed' => 'Closed',
    ],

    'columns' => [
        'id' => 'Ticket No.',
        'ticket' => 'Ticket',
        'subject' => 'Subject',
        'service' => 'Service',
        'requester' => 'Requester',
        'priority' => 'Priority',
        'status' => 'Status',
        'sla' => 'Resolution SLA',
        'created' => 'Created',
    ],

    'detail' => [
        'back' => 'My Tickets',
        'mode' => 'Support Mode',
        'created_at' => 'Created :at',
        'ticket_info' => 'Ticket Information',
        'no_description' => 'No description provided.',
        'requester' => 'Requester',
        'unit' => 'Work Unit',
        'service' => 'Service',
        'contact' => 'Contact',
        'attachments' => 'Attachments',
        'forum' => 'Discussion Forum',
        'forum_hint' => 'The conversation between Requester, Approver, and Support is recorded here.',
        'forum_empty' => 'No discussion yet.',
        'forum_placeholder' => 'Write a reply for the requester… (e.g. ask for transaction details, confirm resolution)',
        'send_reply' => 'Send Reply',
        'sending' => 'Sending…',
        'send_failed' => 'Failed to send the message.',
        'action_failed' => 'Failed to submit the action.',
        'actions' => 'Handling Actions',
        'btn_resolve' => 'Service Closed',
        'btn_escalate' => 'Escalate to IT',
        'btn_return' => 'Returned',
        'actions_footnote' => 'Actions are recorded in the status history & audit trail. The requester is notified.',
        'current_status' => 'Current status',
        'pic' => 'PIC',
        'no_pic' => 'No PIC yet',
        'note_label' => 'Note (required)',
        'note_placeholder' => 'Write a handling note…',
        'note_hint' => 'Fill in the note to enable the buttons below.',
        'your_note' => 'Your Note',
        'people' => 'People',
        'status_history' => 'Status History',
        'sla' => 'SLA',
        'escalated_banner' => 'Ticket Has Been Escalated to the IT Team.',
        'escalated_check' => 'Escalated to the Advanced IT Team',
        'escalated_from_bpo' => 'Escalated from Support BPO',
        'reopened_banner' => 'The requester reopened this ticket',
    ],

    'start_modal' => [
        'title' => 'Start working on this ticket?',
        'body' => 'Ticket :id will be marked "In Progress" and the requester gets an automatic note in the discussion. Choose Later to go back to your ticket list — the ticket stays Open until you start it.',
        'later' => 'Later',
        'now' => 'Work On It Now',
        'starting' => 'Starting…',
    ],

    'confirm' => [
        'sending' => 'Sending…',
        'no' => 'No',
        'resolve' => [
            'title' => 'Close this service (Service Closed)?',
            'body' => 'Ticket :id will be marked as resolved and closed. The requester will be asked to rate the service. This action is recorded in the status history.',
            'button' => 'Yes, Close Service',
        ],
        'escalate' => [
            'title' => 'Escalate to the IT Team?',
            'body' => 'Ticket :id will be escalated to the Advanced IT Team for deeper handling. This action is recorded in the status history.',
            'button' => 'Yes, Escalate',
        ],
        'return' => [
            'title' => 'Return to the Requester?',
            'body' => 'Ticket :id will be sent back to the requester for revision. The requester will be notified and can edit and resubmit the ticket. This action is recorded in the status history.',
            'button' => 'Yes, Return',
        ],
    ],
];

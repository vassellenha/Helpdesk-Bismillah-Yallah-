<?php

/*
|--------------------------------------------------------------------------
| Requester — English
|--------------------------------------------------------------------------
|
| Mirror of lang/id/requester.php. Keys must stay identical in both files:
| a key present here but missing there (or vice versa) renders as the raw key
| on screen, which is how half-translated pages happen.
|
*/

return [
    'my_tickets' => 'My Tickets',
    'subtitle' => 'Track the history and progress of every ticket you have submitted.',

    'search_placeholder' => 'Search tickets, title, or service…',
    'showing' => 'Showing :shown of :total tickets',
    'empty' => 'No tickets match these filters.',

    'columns' => [
        'id' => 'Ticket No.',
        'title' => 'Subject',
        'category' => 'Category',
        'priority' => 'Priority',
        'status' => 'Status',
        'sla' => 'SLA',
        'created' => 'Created',
    ],

    'filters' => [
        'all_service' => 'All Services',
        'all_subcategory' => 'All Sub Categories',
        'all_category' => 'All Issue Categories',
        'all_priority' => 'All Priorities',
    ],

    'periods' => [
        'last_30_days' => 'Last 30 days',
        'last_3_months' => 'Last 3 months',
        'last_6_months' => 'Last 6 months',
        'this_year' => 'This year',
    ],

    'returned_banner' => ':count ticket(s) returned by the Support team for revision.',
    'returned_hint' => 'Open the ticket, read the Support note, then press “Edit & Resubmit” to send it again.',

    'nav' => [
        'notifications' => 'Notifications',
        'no_notifications' => 'No notifications yet.',
    ],

    'dashboard' => [
        'active_tickets' => 'Active Tickets',
        'awaiting_approval' => 'Awaiting Approval',
        'needs_response' => 'Needs My Response',
        'resolved' => 'Resolved',
        'closed' => 'Closed',
        'hint_awaiting_confirmation' => 'Awaiting your confirmation',
        'hint_last_6_months' => 'Last 6 months',
        'hint_waiting_response' => 'Waiting for your response',
        'hint_all_caught_up' => 'All caught up',
    ],

    'sla_table' => [
        'title' => 'Tickets Approaching SLA Limit',
        'subtitle' => 'Sorted by least time remaining',
        'search' => 'Search tickets…',
        'empty' => 'No tickets are approaching their SLA limit.',
        'view_all' => 'View all tickets →',
    ],

    'charts' => [
        'created_vs_resolved' => 'Tickets Created vs Resolved',
        'created' => 'Created',
        'resolved' => 'Resolved',
        'sla_distribution' => 'SLA Distribution',
        'sla_active' => 'SLA active',
    ],

    'detail' => [
        'service' => 'Service',
        'category' => 'Category',
        'subject' => 'Subject',
        'close' => 'Close',
        'unassigned' => 'Unassigned',
        'support_team' => 'Support Team',
        'resolved_banner' => 'Your Ticket Has Been Resolved',
        'confirm_title' => 'Confirm Completion',
        'confirm_question' => 'Has your issue been resolved?',
        'not_yet' => 'Not yet',
        'not_yet_hint' => 'Still having problems',
        'yes_done' => 'Yes, resolved',
        'yes_done_hint' => 'Rate & close',
        'rate_question' => 'How would you rate this service?',
        'rate_hint' => 'Tap a star to rate',
        'note_required' => 'Note for the Support team *',
        'note_optional' => 'Note for the Support team (optional)',
        'reopened' => 'Reopened — sent back to the Support team',
    ],

    'bulk' => [
        'selected' => ':count ticket(s) selected',
        'delete' => 'Delete Selected',
        'deleting' => 'Deleting…',
        'confirm_title' => 'Delete :count :label ticket(s)?',
        'confirm_body' => 'The selected tickets will be permanently deleted. This cannot be undone.',
        'label_draft' => 'draft',
        'label_returned' => 'returned',
        'no' => 'No',
        'yes' => 'Yes, Delete',
        'failed' => ':count ticket(s) could not be deleted. Please try again.',
    ],
];

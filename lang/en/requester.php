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
    'empty' => 'No tickets match these filters.',

    'auto_close' => [
        'label' => 'Auto-closes in :time',
        'tooltip' => 'If left unconfirmed, this ticket closes itself on :at.',
        'closing' => 'Closing shortly',
        'closing_long' => 'The confirmation window has run out — this ticket will be closed automatically.',
        'column' => 'Auto-Close',
        'unit_day' => 'd',
        'unit_hour' => 'h',
        'unit_minute' => 'm',
        'unit_second' => 's',
    ],

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
        'reset' => 'Reset Filters',
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
        'summary' => ':period Summary',
        'periods' => [
            'week' => 'Week',
            'month' => 'Month',
            'year' => 'Year',
        ],
        'active_tickets' => 'Active Tickets',
        'awaiting_approval' => 'Awaiting Approval',
        'needs_response' => 'Needs My Response',
        'resolved' => 'Resolved',
        'closed' => 'Closed',
        'hint_awaiting_confirmation' => 'Awaiting your confirmation',
        'hint_last_6_months' => 'Last 6 months',
        'hint_waiting_response' => 'Waiting for your response',
        'hint_all_caught_up' => 'All caught up',
        'greeting' => 'Good morning, :name 👋',
        'subtitle' => 'Your ticket summary and SLA status as of today, :date.',
    ],

    'sla_table' => [
        'title' => 'Tickets Approaching SLA Limit',
        'subtitle' => 'Sorted by least time remaining',
        'search' => 'Search tickets…',
        'empty' => 'No tickets are approaching their SLA limit.',
        'view_all' => 'View all tickets →',
    ],

    'charts' => [
        'granularity' => [
            'week' => 'Daily',
            'month' => 'Weekly',
            'year' => 'Monthly',
        ],
        'created_vs_resolved' => 'Tickets Created vs Resolved',
        'created' => 'Created',
        'resolved' => 'Resolved',
        'sla_distribution' => 'SLA Distribution',
        'sla_active' => 'SLA active',
        'sla_subtitle' => 'Tickets with an SLA clock running (excludes those awaiting approval)',
        'sla_empty' => 'No active tickets',
        'sla_within_target' => ':pct% of your active tickets are still within SLA targets.',
        'sla_on_track' => 'On Track',
        'sla_warning' => 'SLA Warning',
        'sla_breach' => 'SLA Breach',
    ],

    'detail' => [
        'status_history' => 'Status History',
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
        'ticket_info' => 'Ticket Information',
        'no_description' => 'No description was provided.',
        'discussion' => 'Discussion',
        'forum_empty' => 'No discussion yet. Add a note if you have more details to share.',
        'forum_placeholder' => 'Write a note or reply…',
        'send_reply' => 'Send Reply',
        'sending' => 'Sending…',
        'send_failed' => 'Failed to send reply.',
        'send_to_support_failed' => 'Failed to send to Support.',
        'close_failed' => 'Failed to close the ticket.',
        'people' => 'People',
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
    'priority_help' => [
        'Low' => 'A routine request. Your work can carry on while you wait.',
        'Medium' => 'It disrupts your work, but there is a workaround for now.',
        'High' => 'Your work or your team is stopped and there is no workaround.',
        'Critical' => 'The service is fully down, many people are affected, or it reaches customers/production.',
        'sla' => 'Target: respond within :response · resolve within :resolution',
        'hour' => ':count hour',
        'hours' => ':count hours',
        'day' => ':count day',
        'days' => ':count days',
        'inactive' => 'The SLA for this priority is currently disabled by an Admin.',
    ],

    'sla_help' => [
        'ontrack' => 'Still safe. The resolution deadline has not passed and the warning threshold has not been reached.',
        'warning' => 'Running out of time. Past the warning threshold, but the deadline has not passed yet.',
        'breach' => 'The resolution deadline has passed. The ticket is still being worked on, but it is now recorded as late.',
    ],

    'attachment_error' => [
        'too_many' => 'You can attach up to :count files.',
        'bad_type' => 'Only PNG, JPG, PDF, or video (MP4, MOV, WEBM) files are supported.',
        'too_large' => 'File size exceeds 30MB.',
    ],

];

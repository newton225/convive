<?php

return [
    'title' => 'My events',
    'description' => 'Create an event, set its seats and deadlines, then hand out its public link.',
    'duplicate_name' => ':name (copy)',
    'empty' => 'No event yet. Create the first one.',

    'statuses' => [
        'draft' => 'Draft',
        'open' => 'Open',
        'ongoing' => 'Ongoing',
        'closed' => 'Closed',
    ],

    'steps' => [
        'identity' => 'Identity',
        'seating' => 'Seats and price',
        'deadlines' => 'Deadlines',
    ],

    'sections' => [
        'identity' => 'Name, date and venue of the event.',
        'seating' => 'Capacity is tables × seats per table: the room plan is what counts.',
        'deadlines' => 'Deadline, purge, hold duration and invitation sending.',
    ],

    'fields' => [
        'name' => 'Event name',
        'name_placeholder' => 'For example: Gala dinner 2026',
        'subtitle' => 'Subtitle',
        'starts_at' => 'Date and time',
        'venue' => 'Venue',
        'venue_address' => 'Address',
        'primary_color' => 'Primary colour',
        'secondary_color' => 'Secondary colour',
        'override_colors' => 'Customise this event\'s colours',
        'override_colors_hint' => "Without customisation, the organisation's colours apply.",
        'visual' => 'Event visual',
        'table_count' => 'Number of tables',
        'seats_per_table' => 'Seats per table',
        'price_per_person' => 'Price per person (CFA francs)',
        'companion_limit' => 'Companion limit',
        'registration_deadline' => 'Registration deadline',
        'purge_at' => 'Purge of unfinished registrations',
        'invitations_send_at' => 'Invitation sending',
        'hold_duration_minutes' => 'Hold duration (minutes)',
        'payment_accounts' => 'Payment accounts offered',
    ],

    'help' => [
        'table_count' => 'The event capacity is worked out for you: number of tables times seats per table. On the day, the seating plan is what counts.',
        'seats_per_table' => 'Every table has the same number of seats. A guest and their companions always sit at the same table.',
        'price_per_person' => 'Amount in CFA francs, with no decimals. Each companion pays this price too: the guest pays the price times the number of people registered.',
        'companion_limit' => 'Maximum number of people a guest can register with them (10 at most). Each one takes a seat and gets their own ticket.',
        'payment_accounts' => 'The accounts your guests pay into. You create them under Organisation, Payment accounts. A new or changed account only shows after a 24-hour security delay.',
        'registration_deadline' => 'After this date, the public link no longer accepts registrations. Registrations already made carry on as usual.',
        'purge_at' => 'On this date, unfinished registrations (no proof, expired, or with a rejected proof) are deleted and their seats released. Approved registrations are never touched.',
        'invitations_send_at' => 'When invitation cards are sent, by WhatsApp and email, to every approved registration. A registration approved after this date gets its card right away.',
        'hold_duration_minutes' => 'Time the guest has to upload their payment proof. Their seats are held meanwhile, then go back to the pool. 10 minutes by default.',
    ],

    'visual' => [
        'hint' => "Poster or photo specific to this event. Without one, the organisation's banner applies.",
        'choose' => 'Choose a visual',
        'replace' => 'Replace',
        'remove' => 'Remove',
        'empty' => 'No visual',
    ],

    'summary' => [
        'capacity' => '{0} No seat|{1} 1 seat|[2,*] :count seats',
        'no_date' => 'Date to be set',
        'public_link' => 'Public link',
    ],

    'actions' => [
        'create' => 'New event',
        'edit' => 'Edit event',
        'publish' => 'Hand out the public link',
        'announce' => 'Announce on the showcase',
        'withdraw_announcement' => 'Withdraw from showcase',
        'close' => 'Close the event',
        'duplicate' => 'Duplicate',
        'delete' => 'Delete event',
        'copy_link' => 'Copy the link',
        'proofs' => 'Proofs',
        'seating' => 'Seating plan',
        'scan' => 'Scan',
        'registrations' => 'Registrations',
        'report' => 'Report',
        'reconciliation' => 'Reconciliation',
    ],

    'card' => [
        'fill_label' => 'Filled',
        'fill_value' => ':occupied / :capacity seats',
        'collected' => 'Collected',
        'proofs_to_check' => '{1} 1 proof to check|[2,*] :count proofs to check',
        'draft_hint' => 'Draft: finish the details, then publish the link to open registrations.',
        'continue' => 'Continue preparing',
        'check_proofs' => '{1} Check the proof|[2,*] Check the :count proofs',
        'open_scan' => 'Open the scanner',
        'view_report' => 'View the report',
        'open' => 'Open the event',
        'more_actions' => 'More actions for :name',
        'group_follow' => 'Follow-up',
        'group_day' => 'On the day',
        'group_event' => 'Event',
        'link_copied' => 'Public link copied.',
        'link_copy_failed' => 'Could not copy the link: open the event to get it.',
    ],

    'filters' => [
        'label' => 'Filter events',
        'active' => 'Ongoing and upcoming',
        'closed' => 'Closed',
        'all' => 'All',
        'empty' => 'No event in this category.',
    ],

    'badges' => [
        'published' => 'Link handed out',
        'not_ready' => 'Not publishable yet',
        'announced' => 'On the showcase',
    ],

    'publishing' => [
        'ready' => 'This event can be published.',
        'blocked' => 'Complete the legal identity of the organisation, the capacity, the date and at least one visible payment account before publishing.',
        'frozen_subdomain' => 'Once the link is handed out, the subdomain of the organisation can no longer change.',
    ],

    'announcing' => [
        'description' => 'Make this event appear on the product site showcase, to reach an audience that never received the link.',
        'announced' => 'This event appears on the product site showcase.',
    ],

    'flash' => [
        'created' => 'Event created.',
        'updated' => 'Event updated.',
        'published' => 'Public link handed out.',
        'announced' => 'Event announced on the showcase.',
        'announcement_withdrawn' => 'Event withdrawn from the showcase.',
        'closed' => 'Event closed.',
        'duplicated' => 'Event duplicated.',
        'deleted' => 'Event deleted.',
        'visual_updated' => 'Event visual updated.',
        'visual_deleted' => 'Event visual removed.',
    ],

    'errors' => [
        'deadline_after_event' => 'The registration deadline cannot be later than the event itself.',
        'unknown_payment_account' => 'One of the selected payment accounts does not belong to this organisation.',
        'not_ready_to_publish' => 'This event cannot be published yet: legal identity, capacity, date and a visible payment account are required.',
        'not_published_yet' => 'This event must be published first before it can appear on the showcase.',
    ],

    'confirm_delete' => [
        'title' => 'Delete event',
        'description' => 'The ":name" event will be deleted. This cannot be undone.',
    ],

    'templates' => [
        'title' => 'Start from a template',
        'description' => 'Reuses tables, price, companions and payment accounts. Name and dates are left for you to fill in; the ticket template is already shared across the organisation.',
        'meta' => ':tables tables · :price',
        'blank' => 'Blank template',
        'blank_hint' => 'Set everything up manually',
    ],

    'confirm_publish' => [
        'title' => 'Publish the event?',
        'description' => "The public link opens for registration. Once published, the event can no longer be deleted (only closed) and the organisation's subdomain is locked.",
    ],

    'confirm_close' => [
        'title' => 'Close event',
        'description' => 'Registrations will be closed for ":name". This cannot be undone.',
    ],

    'settings' => [
        'link' => 'Settings',
    ],
];

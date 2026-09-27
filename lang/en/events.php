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

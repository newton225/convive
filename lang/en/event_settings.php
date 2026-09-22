<?php

return [
    'title' => 'Event settings',
    'edit_event' => 'Edit the event',

    'identity' => [
        'title' => 'Visual identity',
        'description' => 'Colours come from the organisation\'s brand.',
        'primary' => 'Primary colour',
        'secondary' => 'Secondary colour',
        'edit' => 'Edit the brand',
    ],

    'seating' => [
        'title' => 'Seats',
        'description' => 'Capacity is derived from the room plan: tables times seats per table.',
        'tables' => 'Tables',
        'per_table' => 'Seats per table',
        'capacity' => 'Total capacity',
        'open' => 'Open the room plan',
    ],

    'deadlines' => [
        'title' => 'Deadlines',
        'description' => 'Changed from the event page.',
        'registration_deadline' => 'Registration deadline',
        'purge_at' => 'Purge of unfinished registrations',
        'invitations_send_at' => 'Sending of invitation cards',
        'hold_duration' => 'Reservation duration',
        'minutes' => ':count minutes',
        'not_set' => 'Not set',
    ],

    'reminders' => [
        'title' => 'Automatic reminders',
        'description' => 'Sent by WhatsApp, and by email when the guest gave one.',
        'd7' => 'D-7, to guests without a proof',
        'd2' => 'D-2, to guests without a proof',
        'd1' => 'D-1, to guests without a proof',
        'day_of' => 'Event day minus 3 h, to approved tickets',
    ],

    'rules' => [
        'title' => 'Rules',
        'scheduled_send' => 'Send cards at the scheduled time',
        'auto_seating' => 'Assign tables automatically on approval',
        'allow_without_proof' => 'Accept a registration saved without a proof',
        'proof_legibility' => 'Require a legible receipt before sending',
        'purge_on_exhaustion' => 'Purge as soon as seats run out',
        'temporary_hold' => 'Hold the seat during the reservation',
        'not_enforced' => 'Saved, but does not act on the event yet.',
    ],

    'flash' => [
        'updated' => 'Settings updated.',
    ],
];

<?php

return [
    'title' => 'Event settings',
    'edit_event' => 'Edit the event',

    'identity' => [
        'title' => 'Visual identity',
        'description' => 'The visual and colours guests will see for this event.',
        'primary' => 'Primary colour',
        'secondary' => 'Secondary colour',
        'edit' => "Edit the organisation's brand",
        'edit_event_visual' => 'Edit visual and colours',
        'visual_alt' => 'Visual of :name',
        'no_visual' => "No visual: the organisation's banner is used",
        'own_colors' => 'This event has its own colours.',
        'brand_colors' => "This event uses the organisation's brand colours.",
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
        'phone_verification' => 'Verify the phone with a code before booking',
        'temporary_hold' => 'Hold the seat during the reservation',
        'not_enforced' => 'Saved, but does not act on the event yet.',
    ],

    'rules_help' => [
        'scheduled_send' => "Invitation cards go out on their own on the event's sending date, then with each approval made after that date. Unticked, no card goes out automatically.",
        'auto_seating' => 'With each approved proof, the guest and their companions get a table, in approval order and with units grouped. You can always move someone by hand.',
        'purge_on_exhaustion' => 'As soon as approved registrations fill every seat, unfinished registrations are deleted without waiting for the purge date: they no longer had any chance of getting a seat.',
        'phone_verification' => 'The guest gets a code by WhatsApp and must enter it before booking. It stops a bot from blocking every seat, at the cost of one more step for the guest and one message sent per code.',
    ],

    'flash' => [
        'updated' => 'Settings updated.',
    ],
];

<?php

return [
    'title' => 'Dashboard',
    'event_label' => 'Event',

    'kpis' => [
        'registrations' => 'Registered',
        'validated' => 'Approved proofs',
        'to_check' => 'Proofs to check',
        'without_proof' => 'Without proof',
        'seats_left' => 'Seats left',
    ],

    'hold_expiry' => [
        'rate' => ':rate% of reservations expired without a proof (:lapsed out of :holds).',
        'none' => 'No reservation yet.',
        'help' => 'A rate that jumps suddenly can signal reservations made by a bot to block the seats.',
    ],

    'charts' => [
        'per_day' => 'Registrations per day',
        'per_day_summary' => 'Registrations per day over the last :days days, :total in total.',
        'by_channel' => 'Proofs by channel',
        'by_channel_summary' => 'Submitted proofs split by payment channel.',
        'tables' => 'Table occupancy',
        'table_label' => 'Table :number',
        'seated' => ':seated of :capacity',
        'registrations_series' => 'Registrations',
        'proofs_series' => 'Proofs',
    ],

    'activity' => [
        'title' => 'Recent activity',
        'empty' => 'No activity yet.',
        'types' => [
            'proof_received' => ':name submitted a payment proof.',
            'proof_approved' => 'The proof from :name was approved.',
            'proof_rejected' => 'The proof from :name was rejected.',
            'registration_cancelled' => 'The registration of :name was cancelled.',
            'hold_expired' => 'The reservation of :name expired.',
            'scan_refused' => 'A ticket was refused at the entrance.',
        ],
    ],

    'empty' => [
        'title' => 'No event yet',
        'description' => 'Create your first event to see your figures here.',
        'cta' => 'Create an event',
    ],
];

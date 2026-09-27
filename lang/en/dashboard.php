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

    'countdown' => '{0} Today|[1,*] D-:days',
    'check_proofs' => 'Check proofs (:count)',

    'hints' => [
        'this_week' => '{0} None this week|{1} +1 this week|[2,*] +:count this week',
        'validated' => ':share% of total · :amount collected',
        'waiting' => '{1} including 1 waiting for over 24 h|[2,*] including :count waiting for over 24 h',
        'purge' => 'automatic purge :when',
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

    'getting_started' => [
        'title' => 'Getting started',
        'description' => 'Five steps to open registrations for your first event.',
        'progress' => ':done of :total',
        'done' => 'Done',
        'steps' => [
            'identity' => [
                'title' => "Complete your organisation's identity",
                'hint' => 'Legal name, legal form, numbers, address and subdomain: they appear on receipts and tickets.',
                'action' => 'Complete',
            ],
            'payment_account' => [
                'title' => 'Add a payout account',
                'hint' => 'This is where guests will send their contribution (active 24 hours after creation).',
                'action' => 'Add',
            ],
            'event' => [
                'title' => 'Create an event',
                'hint' => 'Name, date, venue, seats and price: it stays a draft until you publish.',
                'action' => 'Create',
            ],
            'publish' => [
                'title' => 'Publish the public link',
                'hint' => "From the event's page, then copy the link to send it to your guests.",
                'action' => 'See my events',
            ],
            'team' => [
                'title' => 'Invite your team',
                'hint' => 'A treasurer for proofs, a host for the entrance: each with their own profile.',
                'action' => 'Invite',
            ],
        ],
    ],

    'empty' => [
        'title' => 'No event yet',
        'description' => 'Create your first event to see your figures here.',
        'cta' => 'Create an event',
    ],
];

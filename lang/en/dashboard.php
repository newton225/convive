<?php

return [
    'title' => 'Dashboard',
    'event_label' => 'Event',
    'event_picker' => [
        'label' => 'Event shown',
        'automatic' => 'The nearest one (automatic)',
    ],

    'kpis' => [
        'registrations' => 'Registered',
        'validated' => 'Approved proofs',
        'to_check' => 'Proofs to check',
        'without_proof' => 'Without proof',
        'seats_left' => 'Seats left',
    ],

    'countdown' => '{0} Today|{1} Tomorrow|[2,*] In :days days',
    'check_proofs' => 'Check proofs (:count)',

    'refunds_due' => '{1} 1 cancelled registration is awaiting its refund: :amount to return.|[2,*] :count cancelled registrations are awaiting their refund: :amount to return.',
    'refunds_due_action' => 'See cancellations',

    'hints' => [
        'this_week' => '{0} None this week|{1} +1 this week|[2,*] +:count this week',
        'validated' => ':share% of total · :amount collected',
        'waiting' => '{1} including 1 waiting for over 24 h|[2,*] including :count waiting for over 24 h',
        'purge' => 'automatic purge :when',
        'capacity' => '{0} no seat on the seating plan|{1} out of 1 seat in total|[2,*] out of :count seats in total',
    ],

    // Ce que chaque bloc compte exactement, dans sa bulle d'aide.
    'help' => [
        'registrations' => 'Every registration received for this event, whatever its state: confirmed, pending, expired or cancelled. One registration may cover several people, the guest and their companions.',
        'validated' => 'Registrations whose proof of payment has been approved: their seats are secured. The collected amount adds up what these registrations paid, including those cancelled after approval.',
        'to_check' => 'Registrations whose proof has been submitted and is waiting to be approved or rejected in the proof queue.',
        'without_proof' => 'Registrations without a valid proof: unfinished form, hold in progress or expired, rejected proof. At the purge, those that still have submitted nothing are removed.',
        'seats_left' => 'What a new guest can still book: the capacity of the seating plan, minus the seats of confirmed registrations and of holds in progress.',
        'hold_expiry' => 'A hold blocks seats during its countdown. Without a proof submitted before the end, it expires and its seats are released. A registration restarted after expiry counts as one more hold.',
        'per_day' => 'The number of registrations created each day over the period shown, in every state. Useful to see the effect of an announcement or a reminder.',
        'by_channel' => 'Every proof submitted for this event, approved or not, broken down by the payment method used.',
        'tables' => 'For each table of the seating plan, the seats already assigned out of its capacity. A confirmed registration that is not seated yet does not appear here.',
        'activity' => 'The latest facts of this event: proofs submitted, approved or rejected, cancelled registrations, expired holds and tickets refused at the door.',
    ],

    'hold_expiry' => [
        'title' => 'Expired holds',
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

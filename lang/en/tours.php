<?php

return [
    'replay' => 'Replay the tour',
    'next' => 'Next',
    'previous' => 'Previous',
    'done' => 'Finish',
    // The braces are filled in by driver.js, not by the translation.
    'progress' => 'Step {{current}} of {{total}}',

    'welcome' => [
        'intro' => [
            'title' => 'Welcome to Convive',
            'body' => 'Two minutes to find your way around. You can replay this tour at any time from the dashboard.',
        ],
        'tenant' => [
            'title' => 'Your organisation',
            'body' => "If you work for several organisations, switch here. One organisation's data is never visible from another.",
        ],
        'dashboard' => [
            'title' => 'Dashboard',
            'body' => 'The key figures of the current event: registrations, proofs to check, seats left and today\'s alerts.',
        ],
        'events' => [
            'title' => 'Events',
            'body' => 'Create an event, publish its link, then reach its proofs, seating plan, registrations and report from its row.',
        ],
        'entry_control' => [
            'title' => 'Entry control',
            'body' => 'On the night, the host opens this shortcut on their phone and scans tickets.',
        ],
        'organisation' => [
            'title' => 'Organisation',
            'body' => 'Legal identity and brand, payout accounts, units, team, subscription: everything about your organisation lives here. Fill them in before publishing your first event.',
        ],
        'notifications' => [
            'title' => 'Alerts',
            'body' => 'New proof submitted, payout account changed, ticket refused at the door: it all lands here.',
        ],
        'account' => [
            'title' => 'Your account',
            'body' => 'Profile, two-factor authentication, scan code and alert preferences.',
        ],
    ],

    'first_event' => [
        'intro' => [
            'title' => 'Create an event',
            'body' => 'Three steps, then publishing. Guests see nothing until you publish.',
        ],
        'identity' => [
            'title' => '1. Identity',
            'body' => 'Name, date, time and venue, as guests will read them on the link and the ticket.',
        ],
        'seating' => [
            'title' => '2. Seats and price',
            'body' => 'Describe the room in groups of tables of the same size: capacity is the sum of their seats, and the seating plan is what counts on the day.',
        ],
        'payment_accounts' => [
            'title' => 'Payout accounts',
            'body' => 'Tick at least one account: this is where guests will send their contribution.',
        ],
        'deadlines' => [
            'title' => '3. Deadlines',
            'body' => 'Registration deadline, purge of registrations without proof, how long a seat stays held.',
        ],
        'save' => [
            'title' => 'Save',
            'body' => 'The event stays a draft: come back to it as often as you need.',
        ],
        'publish' => [
            'title' => 'Publish',
            'body' => 'Once the organisation identity and a payout account are ready, this button opens registrations.',
        ],
        'share' => [
            'title' => 'Share the link',
            'body' => 'In the event list, "Copy link" gives the address to send to guests.',
        ],
    ],

    'proofs' => [
        'intro' => [
            'title' => 'Check proofs',
            'body' => 'Each guest uploads a screenshot of their payment. Nothing is confirmed without your approval.',
        ],
        'queue' => [
            'title' => 'The queue',
            'body' => 'Pending proofs, with the expected amount, the channel and the declared reference.',
        ],
        'receipt' => [
            'title' => 'The receipt',
            'body' => 'Open the screenshot and compare it with the amount and reference on the row.',
        ],
        'signals' => [
            'title' => 'Warning signs',
            'body' => 'Reference already used, screenshot already seen, amount different from the statement: a sign asks for a closer look.',
        ],
        'approve' => [
            'title' => 'Approve',
            'body' => 'Approving confirms the registration, assigns a table and issues one ticket per person.',
        ],
        'reject' => [
            'title' => 'Reject',
            'body' => 'Give the reason: the guest reads it and can upload a new proof.',
        ],
        'after' => [
            'title' => 'What next',
            'body' => 'Matching against your bank statement happens from the event row, "Reconciliation".',
        ],
    ],

    'entry_control' => [
        'intro' => [
            'title' => 'Entry control',
            'body' => 'Scan the QR code of each ticket. Everyone has their own, companions included, and each ticket works once.',
        ],
        'pin' => [
            'title' => 'Your 4-digit code',
            'body' => 'It locks the screen if you put the phone down. Choose it once, it then works for every event.',
        ],
        'viewfinder' => [
            'title' => 'The viewfinder',
            'body' => 'Hold the QR code in front of the camera: it reads automatically, no button to press.',
        ],
        'results' => [
            'title' => 'Three results',
            'body' => 'Valid: the person comes in. Already scanned: the time of the first entry is shown. Refused: send the guest to the welcome desk.',
        ],
        'station' => [
            'title' => 'Your station',
            'body' => 'Name your entrance (gate A, car park): the log will show who scanned where.',
        ],
        'counter' => [
            'title' => 'The counter',
            'body' => 'People who entered out of people expected.',
        ],
        'recent' => [
            'title' => 'Latest entries',
            'body' => 'Recent scans, to settle a doubt without scanning again.',
        ],
        'offline' => [
            'title' => 'No network',
            'body' => 'Scanning keeps working offline: tickets are checked on the phone and sent as soon as the network is back.',
        ],
    ],
];

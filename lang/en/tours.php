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
            'body' => 'Create an event, publish its link, then reach its proofs, seating plan, registrations, claims and report from its row.',
        ],
        'treasury' => [
            'title' => 'Treasury',
            'body' => 'The money of the whole organisation: what was collected, refunded and what is still owed, by event and by payout account.',
        ],
        'entry_control' => [
            'title' => 'Entry control',
            'body' => 'On the night, the host opens this shortcut on their phone and scans tickets. If a QR code cannot be read, they can find the guest by name, depending on their profile.',
        ],
        'organisation' => [
            'title' => 'Organisation',
            'body' => 'Legal identity and brand, payout accounts, units, team and profiles, ticket template, subscription and action history: everything about your organisation lives here. Fill in the legal identity and a payout account before publishing your first event.',
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
            'body' => 'Name, date, start time, optional end and venue (with a map link if you like), as guests will read them on the link and the ticket. The ticket entry window is set here too.',
        ],
        'seating' => [
            'title' => '2. Seats and prices',
            'body' => 'Describe the room in groups of tables of the same size, or choose an event without tables and enter a number of seats. Then create your price categories (name, price, quota): each person picks theirs, and a price of 0 means free.',
        ],
        'payment_accounts' => [
            'title' => 'Payout accounts',
            'body' => 'Tick at least one account: this is where guests will send their contribution. A fully free event needs none.',
        ],
        'deadlines' => [
            'title' => '3. Deadlines',
            'body' => 'Registration deadline, purge of registrations without proof, how long a seat stays held and when cards are sent.',
        ],
        'save' => [
            'title' => 'Save',
            'body' => 'The event stays a draft: come back to it as often as you need.',
        ],
        'publish' => [
            'title' => 'Publish',
            'body' => 'Once the organisation legal identity, its subdomain, a venue, an upcoming date and, for a paid event, a payout account are ready, this button opens registrations. The page names what is still missing.',
        ],
        'share' => [
            'title' => 'Share the link',
            'body' => 'In the event list, "Copy link" gives the address to send to guests. You can also announce the event on the Convive showcase.',
        ],
    ],

    'proofs' => [
        'intro' => [
            'title' => 'Check proofs',
            'body' => 'Each guest uploads a screenshot of their payment. Nothing is confirmed without your approval (a free event does not ask for one).',
        ],
        'queue' => [
            'title' => 'The queue',
            'body' => 'Pending proofs, with the expected amount, the channel and the declared reference.',
        ],
        'receipt' => [
            'title' => 'The receipt',
            'body' => 'Open the screenshot and compare it with the amount and reference on the row. It must be the original and the amount complete, in a single payment: otherwise, reject.',
        ],
        'signals' => [
            'title' => 'Warning signs',
            'body' => 'Reference already used, screenshot already seen, reference missing from the statement, different amount, a note left by the guest: a sign asks for a closer look.',
        ],
        'approve' => [
            'title' => 'Approve',
            'body' => 'Approving confirms the registration, assigns a table (except for an event without tables) and issues one ticket per person.',
        ],
        'reject' => [
            'title' => 'Reject',
            'body' => 'Give the reason: the guest reads it and can upload a new proof.',
        ],
        'after' => [
            'title' => 'What next',
            'body' => 'Matching against your bank statement happens from the event row, "Reconciliation". Refunds owed after a cancellation are followed in Treasury.',
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
        'lookup' => [
            'title' => 'Without a scan',
            'body' => 'QR code unreadable or ticket forgotten: search the guest by name or booking reference, then confirm their entry. This needs a dedicated permission and alerts the managers.',
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
            'body' => 'Scanning keeps working offline: tickets are checked on the phone and sent as soon as the network is back. The search without a scan, however, needs the network.',
        ],
    ],

    'seating' => [
        'intro' => [
            'title' => 'The seating plan',
            'body' => 'Each confirmed registration is seated at a table automatically. Here you see the room on the day and adjust by hand.',
        ],
        'tables' => [
            'title' => 'The tables',
            'body' => 'Each table with its occupants, grouped by unit (one colour per unit). You can change the number of seats of a table, never below the people already seated.',
        ],
        'unseated' => [
            'title' => 'Without a table',
            'body' => 'Confirmed registrations that have no table: seat them by hand.',
        ],
        'constraints' => [
            'title' => 'Keep units apart',
            'body' => 'Two separated units never share a table, automatic seating included.',
        ],
        'after' => [
            'title' => 'Everything is kept',
            'body' => 'Every seating, move or rule is written to the action history, with its author.',
        ],
    ],

    'treasury' => [
        'intro' => [
            'title' => 'Treasury',
            'body' => 'The money of the whole organisation, all events together, in CFA francs.',
        ],
        'period' => [
            'title' => 'The period',
            'body' => 'Limit the figures to events whose date falls within the chosen period.',
        ],
        'totals' => [
            'title' => 'The totals',
            'body' => 'Collected (validated proofs, cancellations included), refunded, to refund, and the net: collected minus refunded.',
        ],
        'events' => [
            'title' => 'By event',
            'body' => 'One row per event, with a direct link to its registrations to understand a gap.',
        ],
        'accounts' => [
            'title' => 'By payout account',
            'body' => "Each amount is filed under the account of the registration's payment proof.",
        ],
        'refunds' => [
            'title' => 'Refunds to make',
            'body' => 'Cancellations whose refund is still to be made, oldest first. Open the registration to record it once paid back.',
        ],
        'export' => [
            'title' => 'Export',
            'body' => 'Download the PDF of the period; each table also has its own export. Every export is written to the action history.',
        ],
    ],
];

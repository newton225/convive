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

    'registrations' => [
        'intro' => [
            'title' => 'The registrations',
            'body' => 'Every registration of the event, with its status, amount and unit. The list is paginated by 25 and filtered by the server.',
        ],
        'filters' => [
            'title' => 'Search and filter',
            'body' => 'Search by name, phone, email, registration reference or transaction reference, accents ignored, then filter by status: reservation in progress, proof to check, confirmed, cancelled.',
        ],
        'table' => [
            'title' => 'The registrations',
            'body' => 'Unfold a row to see each person of the group with their price and attendance. From the row: the registration history (what happened and who did it), the invitation card, resending the booking link and cancelling.',
        ],
        'exports' => [
            'title' => 'Export',
            'body' => 'Excel, CSV, PDF and check lists follow the filter shown. Every export is written to the action history.',
        ],
        'purge' => [
            'title' => 'Purge',
            'body' => 'Deletes unfinished registrations, those without an approved proof: all of them, or the ones you tick. "Purge all" spares reservations whose countdown is still running, in case the guest is paying.',
        ],
        'after' => [
            'title' => 'What next',
            'body' => 'A cancellation asks what becomes of the payment; refunds still to make are followed in Treasury.',
        ],
    ],

    'report' => [
        'intro' => [
            'title' => 'The report',
            'body' => 'The event summary: attendance, no-shows, takings and entry control.',
        ],
        'cards' => [
            'title' => 'Key figures',
            'body' => 'Confirmed registrations, present and absent, takings, refunded, to refund, and the net: takings minus refunds. The average check time measures throughput at the door.',
        ],
        'units' => [
            'title' => 'By unit',
            'body' => "Each registration is counted in its main guest's unit: confirmed, present and takings.",
        ],
        'export' => [
            'title' => 'Export',
            'body' => "Download the report as a PDF, on the organisation's letterhead. The export is written to the action history.",
        ],
    ],

    'team' => [
        'intro' => [
            'title' => 'The team',
            'body' => 'The people who work with your organisation, and what each can do through their profile.',
        ],
        'members' => [
            'title' => 'The members',
            'body' => 'Change a member\'s profile, or remove them. An organisation always keeps at least one Owner, and you cannot change your own profile.',
        ],
        'invite' => [
            'title' => 'Invite',
            'body' => "Enter the person's address and choose their profile: they receive a link. Until they accept, the invitation stays pending.",
        ],
        'invitations' => [
            'title' => 'Pending invitations',
            'body' => 'Those not accepted yet. You can cancel one as long as it has not been accepted.',
        ],
        'after' => [
            'title' => 'Everything is kept',
            'body' => 'Every invitation, removal and profile change is written to the action history, with its author.',
        ],
    ],

    'profiles' => [
        'intro' => [
            'title' => 'Profiles',
            'body' => 'A profile is a set of permissions you give to members. You build your own from a fixed catalogue.',
        ],
        'create' => [
            'title' => 'Create a profile',
            'body' => 'Name it and tick its permissions. You can only grant permissions you hold yourself.',
        ],
        'list' => [
            'title' => 'Your profiles',
            'body' => 'The Owner and the base profiles (treasurer, host, read-only) are locked: duplicate one for a variant. You can hide a base profile; its holders keep it. A profile held by members cannot be deleted until they are reassigned.',
        ],
        'two_factor' => [
            'title' => 'Two-factor authentication',
            'body' => 'A profile can require it: its holders only reach the organisation once two-factor authentication is on. The Owner and treasurer profiles require it by default, as they handle money.',
        ],
        'after' => [
            'title' => 'Immediate effect',
            'body' => 'A change takes effect at once and is written to the action history, with the before and after.',
        ],
    ],

    'claims' => [
        'intro' => [
            'title' => 'Claims',
            'body' => 'The messages your guests write from their booking page. None of your contact details is shown: you call them back.',
        ],
        'filters' => [
            'title' => 'Search and filter',
            'body' => 'Search for a claim, and show the ones to handle or the ones already handled.',
        ],
        'table' => [
            'title' => 'The list',
            'body' => 'Each row gives the subject, the message and the number to call back. Once the person has been called, mark the claim as handled: the action is written to the action history.',
        ],
        'after' => [
            'title' => 'No reply in the application',
            'body' => 'The application does not answer the guest: the state only says "to handle" or "handled". A booking can have three open claims at most.',
        ],
    ],

    'audit' => [
        'intro' => [
            'title' => 'The action history',
            'body' => "The organisation's sensitive actions: who, what, when and from which address. It is kept for 24 months and nobody can change or erase it.",
        ],
        'filters' => [
            'title' => 'Search and filter',
            'body' => 'Search for a name or a word, or narrow the list to one type of action: payment account, team, proof, entry without scan...',
        ],
        'table' => [
            'title' => 'The actions',
            'body' => 'Sort the list. Each row keeps the before and after of a change: this is how a discreet change gets noticed.',
        ],
        'after' => [
            'title' => 'Who can read it',
            'body' => 'Only members whose profile allows it see this screen. Entries without a scan and payment account changes also alert the team.',
        ],
    ],

    'units' => [
        'intro' => [
            'title' => 'Units',
            'body' => 'The groups your guests pick when they register. Each organisation builds its own.',
        ],
        'create' => [
            'title' => 'Add a unit',
            'body' => 'Type a name: two units cannot share the same name within an organisation.',
        ],
        'list' => [
            'title' => 'Your units',
            'body' => '"Special guest" is protected: always last, never renamed, deactivated or deleted. Rename a unit, or deactivate it to remove it from the form: registrations already made keep it.',
        ],
        'after' => [
            'title' => 'At least one unit',
            'body' => 'At least one always remains, otherwise the registration form could no longer be filled in.',
        ],
    ],

    'payment_accounts' => [
        'intro' => [
            'title' => 'Payment accounts',
            'body' => 'The numbers where your guests send their payment. This is the most protected part of the application: a number changed quietly would divert the money of an event.',
        ],
        'add' => [
            'title' => 'Add an account',
            'body' => 'Choose the channel, the number and the holder. The number must match the network of the channel (for example 07 for Orange Money).',
        ],
        'delay' => [
            'title' => 'The 24-hour delay',
            'body' => 'Once a paid event has been published, changing the channel, the number or the holder only takes effect after 24 hours. The old number stays displayed meanwhile, and everyone holding this permission is alerted.',
        ],
        'list' => [
            'title' => 'Your accounts',
            'body' => 'A second Owner can lift the delay, never the one who asked for the change. A change stays flagged for seven days. Each action asks for your password and is written to the history.',
        ],
    ],

    'reconciliation' => [
        'intro' => [
            'title' => 'Reconciliation',
            'body' => 'Compare a Mobile Money or bank statement with the proofs submitted, to spot a missing payment or a different amount.',
        ],
        'import' => [
            'title' => 'Import a statement',
            'body' => 'A CSV file with the columns date, reference, issuer and amount. The lines are compared with the proofs submitted for this event.',
        ],
        'stats' => [
            'title' => 'The statement status',
            'body' => 'Matched lines, amount mismatch, approximate name, no registration. The lines to decide are the ones with a different amount, an approximate name or no matching registration.',
        ],
        'table' => [
            'title' => 'The lines',
            'body' => 'Filter by outcome. For a line to decide, pick the matching registration or say that none matches.',
        ],
        'after' => [
            'title' => 'Nothing validates by itself',
            'body' => 'Reconciliation informs your decision, it does not validate any proof for you.',
        ],
    ],

    'organisation' => [
        'intro' => [
            'title' => 'The organisation',
            'body' => 'Who you are in your guests\' eyes: legal identity, brand and address. Nothing is required when typing; what is missing is required the day you publish.',
        ],
        'legal' => [
            'title' => 'Legal identity',
            'body' => 'Display name, company name, legal form, signatory, city, country and phone are required to publish. They appear on tickets and receipts: a change is written to the history.',
        ],
        'brand' => [
            'title' => 'The brand',
            'body' => 'Colours, logo, banner, stamp and signature. They apply to the guest journey, the ticket and the messages, never to the back office. JPEG, PNG or WebP images, 5 MB at most; SVG is refused.',
        ],
        'subdomain' => [
            'title' => 'The subdomain',
            'body' => 'The address of your public links. Publishing an event freezes it: changing it would break the links already handed out.',
        ],
        'after' => [
            'title' => 'Ready to publish',
            'body' => 'The banner at the top of the page names what is still missing before you can publish.',
        ],
    ],

    'billing' => [
        'intro' => [
            'title' => 'Billing',
            'body' => 'Your plan, your usage and your invoices. Nothing is deleted when a limit is reached: the organisation simply falls back to the limits of its plan.',
        ],
        'plan' => [
            'title' => 'Your plan',
            'body' => 'The current plan, the trial period and the subscription actions: payment method, plan change, cancellation.',
        ],
        'usage' => [
            'title' => 'Your usage',
            'body' => 'Active events, registrations, members and messages of the month, against the plan limits. An event whose end has passed no longer counts as active, even if it has not been closed, and neither do its registrations.',
        ],
        'invoices' => [
            'title' => 'Invoices',
            'body' => 'The invoices of your subscription, with a link to view each one online.',
        ],
    ],

    'support_access' => [
        'intro' => [
            'title' => 'Support access',
            'body' => 'You alone decide whether the Convive team may look at your organisation, for how long and on what.',
        ],
        'rules' => [
            'title' => 'The rules',
            'body' => 'Read only, limited time, a named person, every consultation logged and a banner shown during the access.',
        ],
        'grant' => [
            'title' => 'Grant an access',
            'body' => 'Pick the support person, the duration, and if needed a single event. One access at a time; you can revoke or extend it, with your password.',
        ],
        'history' => [
            'title' => 'History',
            'body' => 'Past accesses: who, when, and for what scope.',
        ],
    ],

    'console' => [
        'intro' => [
            'title' => 'The operations console',
            'body' => 'The publisher\'s space: follow the client organisations and run the platform. It is reserved for authorised accounts, and without a support access granted by an organisation you read none of its data.',
        ],
        'clients' => [
            'title' => 'Clients',
            'body' => 'Organisations, accounts, account recovery and the plan catalogue.',
        ],
        'platform' => [
            'title' => 'Platform',
            'body' => 'Technical health, messages, security, the showcase, the console log and the console team.',
        ],
        'filters' => [
            'title' => 'Search and filter',
            'body' => 'Find an organisation by name, or narrow the list to one state: trial, up to date, unpaid, suspended, deletion scheduled.',
        ],
        'table' => [
            'title' => 'The organisations',
            'body' => 'Each row gives the plan, the state and the usage. The row menu opens the actions: change plan, extend the trial, set limits, suspend, schedule deletion. Every action is written to the console log.',
        ],
        'after' => [
            'title' => 'No immediate erasure',
            'body' => 'A deletion goes through a 30-day bin: the organisation disappears at once for its members, but can be restored at the request of an Owner.',
        ],
    ],
];

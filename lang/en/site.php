<?php

return [
    'nav' => [
        'features' => 'Features',
        'steps' => 'How it works',
        'pricing' => 'Pricing',
        'showcase' => 'Featured events',
        'login' => 'Log in',
        'register' => 'Create my workspace',
        'dashboard' => 'My workspace',
        'menu' => 'Open the menu',
    ],

    'theme' => [
        'label' => 'Change theme',
    ],

    'hero' => [
        'eyebrow' => 'Event registration',
        'badge' => 'Built for Wave, Orange Money, MTN and Moov',
        'title_lines' => [
            'fill' => 'Fill the room.',
            'verify' => 'Check every proof.',
            'control' => 'Control every entry.',
        ],
        'description' => 'Convive handles registrations for your paid, limited-seat events: timed reservations, verified payment proofs, seating plan, signed tickets and entrance control, even offline.',
        'primary' => 'Create my workspace',
        'secondary' => 'See pricing',
        'reassurance' => 'Essential plan free, with no time limit.',
        'stage' => [
            'hold' => 'Reservation in progress',
            'proof_title' => 'Proof approved',
            'proof_body' => 'Table 7 assigned, ticket sent',
            'scan' => 'Entry accepted',
            'scanning' => 'Scanning the ticket',
        ],
    ],

    'figures' => [
        'no_collection' => ['value' => '0', 'label' => 'money collected in the app: payments go to your own accounts'],
        'hold' => ['value' => '10 min', 'label' => 'reservation by default, adjustable per event'],
        'reminders' => ['value' => 'D-7, D-2, D-1', 'label' => 'automatic reminders to guests without a proof'],
        'signature' => ['value' => 'Ed25519', 'label' => 'ticket signature, verifiable offline'],
    ],

    'features' => [
        'title' => 'Everything between "I want to come" and "I\'m in"',
        'hold' => [
            'title' => 'A held seat, a countdown',
            'body' => 'The guest registers with companions. The seat is held while they pay, then released on its own.',
        ],
        'proofs' => [
            'title' => 'Proofs that are verified, not just received',
            'body' => 'Reference already used, screenshot already seen, amount different from the statement: warning signals show before you approve.',
        ],
        'seating' => [
            'title' => 'A seating plan that fills itself',
            'body' => 'On approval, each guest gets a table, units grouped together. You move people by hand when needed.',
        ],
        'ticket' => [
            'title' => 'A ticket nobody can forge',
            'body' => 'QR signed on the server, checked at the entrance on the agent\'s phone, even offline. A ticket already scanned is flagged.',
        ],
        'reports' => [
            'title' => 'Reconciliation and reports',
            'body' => 'Import the Mobile Money or bank statement: each line finds its registration. After the event, attendance, no-shows and revenue by unit.',
        ],
    ],

    'preview' => [
        'ticket' => [
            'event' => 'Gala dinner',
            'date' => 'Saturday 14 November, 7 pm',
            'guest' => 'Guest',
            'table' => 'Table',
            'seats' => 'Seats',
            'valid' => 'Valid ticket',
            'pending' => 'Awaiting validation',
        ],
        'hold' => [
            'label' => 'Reservation in progress',
            'help' => '9 minutes left to submit the payment proof.',
        ],
        'proofs' => [
            'title' => 'Proofs to check',
            'duplicate' => 'Reference already used',
            'mismatch' => 'Statement amount differs',
            'clean' => 'No anomaly',
        ],
        'seating' => [
            'title' => 'Seating plan',
            'table' => 'Table :number',
        ],
        'reports' => [
            'title' => 'Attendance by unit',
        ],
        'scan' => [
            'accepted' => 'Entry accepted',
            'already' => 'Already scanned at 7:12 pm',
        ],
    ],

    'steps' => [
        'title' => 'From the public link to the last scan',
        'publish' => ['title' => 'Publish the link', 'body' => 'One link per event, in your organisation\'s colours.'],
        'register' => ['title' => 'Guests register', 'body' => 'Name, unit, companions. The amount is worked out for them.'],
        'pay' => ['title' => 'They pay you directly', 'body' => 'Into your Mobile Money or bank account, then they submit their proof.'],
        'validate' => ['title' => 'You approve', 'body' => 'The ticket is sent, the table is assigned.'],
        'scan' => ['title' => 'You scan', 'body' => 'At the entrance, one scan, one result: valid, already scanned or refused.'],
    ],

    'pricing' => [
        'title' => 'A plan for every size of organisation',
        'subtitle' => 'Change plan when your activity changes.',
        'recommended' => 'Recommended',
        'free' => 'Free',
        'on_quote' => 'On quote',
        'per_month' => ':price per month',
        'unlimited' => 'Unlimited',
        'events' => 'Active events: :count',
        'registrations' => 'Registered guests: :count',
        'members' => 'Members: :count',
        'messages' => 'Guest messages per month: :count',
        'reconciliation' => 'Statement reconciliation',
        'reports' => 'Post-event reports',
        'custom_domain' => 'Custom domain',
        'sso' => 'Single sign-on (SSO)',
        'choose' => 'Get started',
    ],

    'cta' => [
        'title' => 'Ready to fill your next room?',
        'body' => 'Create your workspace in a few minutes and publish your first link right away.',
        'button' => 'Create my workspace',
    ],

    'footer' => [
        'tagline' => 'Event registration for associations, churches and companies.',
        'rights' => 'All rights reserved.',
        'product' => 'Product',
        'account' => 'Account',
        'analytics' => 'Audience measurement',
        'legal' => 'Legal information',
        'privacy' => 'Privacy',
        'terms' => 'Terms of use',
        'notice' => 'Legal notice',
    ],

    'consent' => [
        'title' => 'Measure visits to this site?',
        'body' => 'With your consent, Google Analytics counts visits to these presentation pages to help us improve them. Nothing is measured in your workspace or during a registration, and no data is used for advertising.',
        'accept' => 'Accept',
        'decline' => 'Decline',
    ],
];

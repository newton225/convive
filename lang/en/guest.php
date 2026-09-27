<?php

return [
    'event' => [
        'no_date' => 'Date to be announced',
        'venue' => 'Venue',
        'price_per_person' => 'Price per person',
        'capacity' => 'Capacity',
        'seats' => [
            'remaining' => '{0} Full|{1} 1 seat remaining|[2,*] :count seats remaining',
            'full' => 'This event is full',
        ],
        'companion_limit' => '{0} No companion|{1} 1 companion allowed|[2,*] :count companions allowed',
        'deadline' => [
            'label' => 'Registration deadline',
            'passed' => 'The registration deadline has passed.',
        ],
        'register' => 'Register',
        'registration_closed' => 'Registrations are closed for this event.',
        'payment_accounts' => [
            'title' => 'Payment accounts',
            'reference_hint' => 'Put ":name" as the transfer reference.',
        ],
    ],

    'not_found' => [
        'title' => 'This event does not exist',
        'description' => 'The link you followed is no longer valid, or never existed.',
    ],

    'registration' => [
        'errors' => [
            'phone_already_active' => 'A reservation is already in progress for this number. Finish it, or wait for its time limit to end before creating another.',
            'registrations_paused' => 'Online registration is temporarily paused for this event. Contact the organiser to book your seat.',
            'phone_backoff' => '{1} Several reservations expired for this number without a payment proof. Try again in 1 minute.|[2,*] Several reservations expired for this number without a payment proof. Try again in :minutes minutes.',
        ],
        'title' => 'Your registration',
        'fields' => [
            'name' => 'Full name',
            'phone' => 'Phone',
            'email' => 'Email (optional)',
            'unit' => 'Unit',
            'unit_placeholder' => 'Choose a unit',
            'companion_name' => "Companion's name",
        ],
        'companions' => [
            'title' => 'Companions',
            'add' => 'Add a companion',
            'remove' => 'Remove',
            'limit_reached' => '{1} 1 companion maximum for this event.|[2,*] :count companions maximum for this event.',
        ],
        'total' => [
            'label' => 'Total amount due',
        ],
        'submit' => 'Continue registration',
        'show' => [
            'countdown_label' => 'Time left to send your payment proof',
            'expired' => 'The reservation window has closed.',
            'retry' => 'Check availability and try again',
            'proof_submitted' => 'Your proof was received. It is being verified.',
            'proof_rejected' => 'Your proof could not be validated. Please send a new one.',
            'recap_title' => 'Summary',
            'seats_available' => '{0} No seat left right now|{1} 1 seat left|[2,*] :count seats left',
            'cancelled_title' => 'This registration has been cancelled.',
            'cancelled_description' => 'The organisation cancelled this registration. Contact them if you believe this is a mistake.',
        ],
    ],

    'ticket' => [
        'title' => 'Your ticket',
        'guests_title' => 'Guests',
        'table' => 'Table :number',
        'no_table' => 'Table to be assigned',
        'scheduled_send' => 'Your card will be sent by WhatsApp (and by email if you provided one) on :date.',
    ],

    'mail' => [
        'invitation_card' => [
            'subject' => 'Your ticket for :event',
            'intro' => 'Hello :name, your registration for :event is confirmed.',
            'table' => 'You are seated at table :number.',
            'action' => 'View my ticket',
        ],
        'proof_reminder' => [
            'subject' => 'Your payment proof for :event is still missing',
            'intro' => 'Hello :name, your registration for :event does not have a validated payment proof yet.',
            'action' => 'Send my proof',
        ],
        'ticket_reminder' => [
            'subject' => ':event is coming up soon!',
            'intro' => 'Hello :name, :event starts in three hours. Keep your ticket handy.',
            'action' => 'View my ticket',
        ],
    ],

    'whatsapp' => [
        'invitation_card' => 'Hello :name, your registration for :event is confirmed. Your ticket: :link',
        'proof_reminder' => 'Hello :name, your payment proof for :event is still missing. Send it here: :link',
        'ticket_reminder' => 'Hello :name, :event starts in three hours. Your ticket: :link',
    ],

    'proof' => [
        'title' => 'Payment proof',
        'fields' => [
            'payment_account' => 'Account you paid into',
            'payment_account_placeholder' => 'Choose the account you used',
            'channel' => 'Channel used',
            'channel_placeholder' => 'Choose the channel',
            'reference' => 'Transaction reference',
            'amount_declared' => 'Amount sent',
            'receipt' => 'Receipt capture',
        ],
        'submit' => 'Send my proof',
    ],

    'waitlist' => [
        'join' => 'Join the waitlist',
        'title' => 'Waitlist',
        'description' => 'This event is full. As soon as a seat frees up, the first person on the list gets a link valid for six hours to finalize their registration.',
        'submit' => 'Join the waitlist',
        'show' => [
            'position_label' => 'Your position on the list',
            'invited' => 'A seat is available for you!',
            'finalize' => 'Finalize my registration',
            'expired' => 'The window to finalize has closed.',
            'converted' => 'Your registration has been finalized.',
        ],
    ],

    'deleted' => [
        'title' => 'This registration no longer exists',
        'description' => 'It may have been removed after the deadline, or once every seat was taken. Your data has been erased.',
        'register' => 'Register again',
        'waitlist' => 'Join the waiting list',
        'back' => 'See the event',
    ],

    'resume_link' => [
        'title' => 'Keep this link',
        'description' => 'It lets you pick your registration back up at any time before the deadline, from any device.',
        'copy' => 'Copy the link',
        'share' => 'Send it to me on WhatsApp',
        'deadline' => 'Resume before :date.',
    ],
];

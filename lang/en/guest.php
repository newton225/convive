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
        'hosted_by' => 'Hosted by :name',
        'when' => 'Date',
        'per_person' => 'per person',
        'seats_taken' => ':taken of :capacity seats taken',
        'how' => [
            'title' => 'How it works',
            'form' => ['title' => 'Your details', 'body' => 'Name, phone, unit and companions. The amount is worked out for you.'],
            'pay' => ['title' => 'Your payment', 'body' => "You pay into the organiser's account, then upload the proof."],
            'ticket' => ['title' => 'Your ticket', 'body' => 'It reaches you as soon as your proof is approved.'],
        ],
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
            'phone_invalid' => 'Enter a 10-digit Ivorian number, for example 07 07 12 34 56.',
            'phone_already_active' => 'A reservation is already in progress for this number. Finish it, or wait for its time limit to end before creating another.',
            'registrations_paused' => 'Online registration is temporarily paused for this event. Contact the organiser to book your seat.',
            'phone_backoff' => '{1} Several reservations expired for this number without a payment proof. Try again in 1 minute.|[2,*] Several reservations expired for this number without a payment proof. Try again in :minutes minutes.',
        ],
        'title' => 'Your registration',
        'reference' => 'Ref. :reference',
        'fields' => [
            'name' => 'Full name',
            'phone' => 'Phone',
            'email' => 'Email (optional)',
            'unit' => 'Unit',
            'unit_placeholder' => 'Choose a unit',
            'companion_name' => "Companion's name",
        ],
        'help' => [
            'phone' => 'It is used to send your invitation card and reminders by WhatsApp. One reservation at a time per number.',
            'unit' => 'It is used to seat members of the same unit at the same tables. If none fits you, choose "None".',
            'companions' => 'The people coming with you. Each one takes a seat, pays the price and gets their own ticket, which you can pass on to them.',
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

    'flash' => [
        'phone_verified' => 'Number verified: your seat is booked.',
        'code_resent' => 'A new code has been sent to you on WhatsApp.',
        'proof_sent' => 'Proof sent. The organisation will verify it.',
        'proof_too_late' => 'The reservation window had closed: the proof was not saved. Check availability and restart your reservation.',
        'no_seats_left' => 'There are not enough seats left for your registration. You can join the waitlist if it is open.',
        'hold_restarted' => 'Your reservation is back on: the countdown restarts.',
        'seats_available' => 'Seats are available: register directly, no need for the waitlist.',
        'waitlist_joined' => 'You are on the waitlist. You will be notified as soon as a seat frees up.',
        'waitlist_seat_taken' => 'The seat offered to you is no longer available. You will still be notified if another frees up.',
    ],

    'phone_verification' => [
        'title' => 'Verify your number',
        'description' => 'We sent a 6-digit code on WhatsApp to :phone. Enter it to book your seat.',
        'code_label' => 'Verification code',
        'expires' => 'The code expires in :minutes minutes.',
        'submit' => 'Verify and book',
        'resend' => 'Resend the code',
        'errors' => [
            'invalid' => 'This code does not match. Check the WhatsApp message and try again.',
            'expired' => 'This code has expired. Ask for a new one.',
            'too_many_attempts' => 'Too many attempts. Ask for a new code.',
        ],
    ],

    'ticket' => [
        'title' => 'Your ticket',
        'guests_title' => 'Guests',
        'table' => 'Table :number',
        'no_table' => 'Table to be assigned',
        'scheduled_send' => 'Your card will be sent by WhatsApp (and by email if you provided one) on :date.',
        'passes_title' => 'Companion tickets',
        'passes_description' => 'Everyone enters with their own ticket. Send one to a companion who will arrive without you.',
        'pass_alt' => 'Ticket for :name',
        'share_whatsapp' => 'Send by WhatsApp',
        'copy_link' => 'Copy link',
        'share_message' => 'Your ticket for :event: :url',
        'share_unavailable' => 'Show this code from your phone: the individual link will be available once the organisation has chosen its address.',
        'guest_of' => 'Guest of :name',
        'single_title' => 'Ticket for :name',
        'single_notice' => 'This ticket admits one person and can only be used once. Show it at the entrance.',
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
        'phone_code' => 'Your Convive code: :code. It expires in :minutes minutes. Do not share it with anyone.',
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
        'help' => [
            'reference' => 'The transaction code, shown in the confirmation message from Wave, Orange Money, MTN or Moov, or on your bank receipt. It lets the organiser find your payment.',
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

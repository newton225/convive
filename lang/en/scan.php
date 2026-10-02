<?php

return [
    'title' => 'Door check-in',

    'viewfinder' => [
        'placeholder' => "Aim at the ticket's QR code",
        'scanning' => 'Looking for a code...',
        'camera_denied' => 'The camera could not be activated. Check the browser permissions.',
        'phases' => [
            'starting' => 'Starting the camera...',
            'searching' => 'Looking for a QR code',
            'checking' => 'QR code detected, checking...',
            'paused' => 'Scanning paused',
            'error' => 'Camera unavailable',
        ],
    ],

    'results' => [
        'accepted' => 'Entry granted',
        'already_scanned' => 'Ticket already scanned',
        'refused' => 'Ticket refused',
    ],

    'entry_control' => [
        'title' => 'Entry control',
        'description' => 'Choose the event whose entrance you are checking.',
        'empty_title' => 'No event today',
        'empty_description' => 'Entry control opens on the day of a published event. For another day, go through the event list.',
        'open' => 'Open scanner',
        'all_events' => 'See all events',
        'others_today' => 'Other checks today: :events',
        'change' => 'Change event',
    ],
    'result' => [
        'table' => 'Table :number',
        'no_table' => 'Not seated',
        'guest_of' => 'Guest of :name',
        'first_scanned_at' => 'First passage: :time',
        'first_scanned_by' => 'By :name',
        'force' => 'Force entry',
        'forced_badge' => 'Forced entry',
        'manual_badge' => 'Not scanned',
        'refused_help' => 'Unknown signature or unconfirmed payment. Send the guest to the welcome desk.',
        'other_event' => 'Valid ticket, but for another event: ":name", :place. Tell the guest where they are expected.',
        'next' => 'Scan next',
    ],

    'lookup' => [
        'title' => 'Ticket cannot be read?',
        'description' => 'Broken screen, dead phone, forgotten ticket: find the guest by their registration reference or by name, then confirm their entry. It will be recorded as "not scanned", under your name, and the managers will be told.',
        'label' => 'Registration reference or name',
        'placeholder' => 'For example: SP-2026-0008 or Kouassi',
        'submit' => 'Search',
        'no_permission' => 'Ticket cannot be read? Only a manager can let a guest in without scanning their ticket. Send the guest to them.',
        'offline' => 'Searching needs a connection. Without network, only scanning the ticket works.',
        'too_short' => 'Type at least :count characters.',
        'empty' => 'No valid ticket matches. Check the spelling or the reference. If the payment is not confirmed, send the guest to the welcome desk.',
        'truncated' => 'Only the first :count tickets are shown. Narrow your search.',
        'reference' => 'Registration :reference',
        'arrived' => 'Already in: :time',
        'arrived_by' => 'Already in: :time, confirmed by :name',
        'admit' => 'Confirm entry',
        'confirm_title' => 'Confirm entry without scanning?',
        'confirm_body' => 'Check the identity of the person before confirming. The entry will be recorded as "not scanned", under your name, and cannot be undone.',
        'force_title' => 'Force entry?',
        'force_body' => 'This ticket has already been used. Forcing the entry lets a second person in with the same ticket. It will be recorded under your name and cannot be undone.',
    ],

    'counter' => [
        'label' => 'tickets already scanned at the door',
        'value' => ':entered of :expected',
    ],

    'station' => [
        'label' => 'Checkpoint',
        'placeholder' => 'For example: Main entrance',
    ],

    'recent' => [
        'title' => 'Recent passages',
        'empty' => 'No passage yet.',
    ],

    'pin_setup' => [
        'title' => 'Choose your scan code',
        'description' => 'Before scanning, choose a 4-digit code. It will unlock this screen after 5 minutes of inactivity, even offline. Tickets are not read until it is chosen.',
    ],

    'lock' => [
        'title' => 'Screen locked',
        'description' => 'Enter your scan code to resume.',
        'pin_label' => 'Scan code',
        'checking' => 'Checking…',
        'wrong' => '{1} Wrong code. 1 more try before signing out.|[2,*] Wrong code. :count more tries before signing out.',
        'exhausted' => 'Too many wrong codes: sign in again with your email and password.',
        'exhausted_offline' => 'Too many wrong codes. Sign in again with your email and password once the network is back.',
    ],

    'rotate_key' => [
        'title' => 'Ticket key',
        'body' => 'Version :version. Change the key if a scanning phone was lost or you believe it leaked.',
        'button' => 'Change the key',
        'confirm_title' => 'Change the ticket key?',
        'confirm_body' => 'Every ticket already sent, downloaded or printed will stop opening the door. Each guest will have to reopen their ticket to get the new code, and each scanning phone will have to reconnect to the network.',
        'confirm' => 'Change the key',
        'flash' => 'Ticket key changed. Old codes are refused.',
    ],
];

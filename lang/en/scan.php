<?php

return [
    'title' => 'Door check-in',

    'viewfinder' => [
        'placeholder' => "Aim at the ticket's QR code",
        'scanning' => 'Looking for a code...',
        'camera_denied' => 'The camera could not be activated. Check the browser permissions.',
    ],

    'results' => [
        'accepted' => 'Entry granted',
        'already_scanned' => 'Ticket already scanned',
        'refused' => 'Ticket refused',
    ],

    'result' => [
        'table' => 'Table :number',
        'no_table' => 'Not seated',
        'first_scanned_at' => 'First passage: :time',
        'first_scanned_by' => 'By :name',
        'force' => 'Force entry',
        'forced_badge' => 'Forced entry',
        'refused_help' => 'Unknown signature or unconfirmed payment. Send the guest to the welcome desk.',
        'next' => 'Scan next',
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

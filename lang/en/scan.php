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
    ],

    'counter' => [
        'label' => 'Entries granted',
    ],

    'recent' => [
        'title' => 'Recent passages',
        'empty' => 'No passage yet.',
    ],
];

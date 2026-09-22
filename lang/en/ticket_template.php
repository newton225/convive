<?php

return [
    'title' => 'Ticket template',
    'description' => 'Choose how the ticket your guests receive looks, and which elements appear on it.',

    'models' => [
        'title' => 'Model',
        'classic' => ['label' => 'Classic', 'hint' => 'Centred title, thin frame.'],
        'sober' => ['label' => 'Sober', 'hint' => 'No ornament, quick to read.'],
        'elegant' => ['label' => 'Elegant', 'hint' => 'Serif title, golden rule.'],
    ],

    'elements' => [
        'title' => 'Elements shown',
        'logo' => 'Logo',
        'stamp' => 'Stamp',
        'signature' => 'Signatory\'s signature',
        'companions' => 'List of companions',
        'missing' => 'No file yet: add it under Organisation and brand.',
        'brand_link' => 'Edit the brand',
    ],

    'preview' => [
        'title' => 'Preview',
        'guest' => 'Guest',
        'table' => 'Table',
        'seats' => 'Seats',
        'companions' => 'Companions',
        'scheduled' => 'Scheduled send',
        'valid' => 'Valid ticket',
    ],

    'print' => [
        'title' => 'Checklists by table',
        'description' => 'Print, for each event, the confirmed guests table by table, with stamp and signature.',
        'button' => 'Print',
        'empty' => 'No event to print yet.',
    ],

    'flash' => [
        'updated' => 'Ticket template updated.',
    ],
];

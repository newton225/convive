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
        'price_category' => 'Price',
        'seats' => 'Seats',
        'companions' => 'Companions',
        'scheduled' => 'Scheduled send',
        'qr' => 'Ticket QR code',
        'qr_of' => 'Ticket QR code for :name',
        'host' => 'Invited by',
        'holder' => 'Ticket to show',
        'holder_guest' => 'Main guest',
        'holder_companion' => 'Companion',
        'valid' => 'Valid ticket',
        'copyright' => '© :year Convive',
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

    // The template of a single event: when enabled, it takes precedence over the organisation's.
    'event' => [
        'title' => 'Event ticket template',
        'description' => 'Give :event a ticket that differs from your organisation’s.',
        'enable' => 'Use a template specific to this event',
        'enable_hint' => 'When enabled, this template takes precedence over the organisation’s for this event’s tickets. When disabled, the organisation’s applies.',
        'organisation_applies' => 'The organisation’s template applies to this event: it is shown here as is.',
        'organisation_link' => 'Edit the organisation’s template',
        'save_first' => 'Save to upload backgrounds specific to this event.',
        'background_fallback' => 'Without an image specific to this event, the organisation’s applies.',
        'remove_confirm' => 'The organisation’s background will apply to this event again. To bring this one back, you will have to upload it again.',
        'global_note' => 'An event can have its own template, from its “Ticket template” menu.',
    ],
];

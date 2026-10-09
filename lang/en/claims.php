<?php

return [
    'title' => 'Claims',
    'description' => 'Messages from guests about their registration. Call the person back on the number shown, then mark the claim as handled.',

    'categories' => [
        'payment' => 'My payment',
        'refund' => 'My refund',
        'ticket' => 'My ticket',
        'other' => 'Another question',
    ],

    'statuses' => [
        'open' => 'To handle',
        'resolved' => 'Handled',
    ],

    'filters' => [
        'status_label' => 'Filter by status',
        'open' => 'To handle',
        'resolved' => 'Handled',
        'all' => 'All',
    ],

    'toolbar' => [
        'search' => 'Search',
        'search_placeholder' => 'Name, reference or phone',
    ],

    'columns' => [
        'date' => 'Received',
        'guest' => 'Guest',
        'category' => 'Subject',
        'message' => 'Message',
        'status' => 'Status',
        'actions' => 'Actions',
    ],

    'empty' => [
        'open' => 'No claim to handle.',
        'other' => 'No claim for this filter.',
    ],

    'resolve' => [
        'action' => 'Mark as handled',
        'title' => 'Mark this claim as handled?',
        'description' => 'You confirm that you have answered :name. The claim stays visible in the list of handled claims.',
        'confirm' => 'Mark as handled',
    ],

    'resolved_on' => 'Handled on :date',

    'flash' => [
        'resolved' => 'Claim marked as handled.',
    ],
];

<?php

return [
    'title' => 'Treasury',
    'description' => 'The money of the whole organisation: what was collected, refunded and what is still owed, by event and by payment account.',

    'period' => [
        'title' => 'Period',
        'from' => 'From',
        'to' => 'To',
        'hint' => 'The events whose date falls within the period.',
        'apply' => 'Show',
        'reset' => 'Whole period',
    ],

    'totals' => [
        'collected' => 'Collected',
        'collected_detail' => 'Every validated proof, cancellations included',
        'refunded' => 'Refunded',
        'fees' => '{0} No fees withheld|[1,*] of which :amount of fees withheld',
        'due' => 'To refund',
        'net' => 'Net',
        'net_detail' => 'Collected minus refunded',
    ],

    'events' => [
        'title' => 'By event',
        'name' => 'Event',
        'date' => 'Date',
        'collected' => 'Collected',
        'refunded' => 'Refunded',
        'fees' => 'Fees withheld',
        'due' => 'To refund',
        'net' => 'Net',
        'open' => 'See registrations',
        'empty' => 'No event in this period.',
    ],

    'accounts' => [
        'title' => 'By payment account',
        'description' => 'Each amount is filed under the account of the file\'s payment proof.',
        'label' => 'Account',
        'channel' => 'Channel',
        'collected' => 'Collected',
        'refunded' => 'Refunded',
        'due' => 'To refund',
        'unknown' => 'Unknown account',
        'empty' => 'No payment collected in this period.',
    ],

    'refunds' => [
        'title' => 'Refunds to make',
        'count' => '{0} No refund to make|{1} 1 refund to make|[2,*] :count refunds to make',
        'more' => 'Only the :limit oldest are listed: open the event\'s registrations for the rest.',
        'name' => 'Guest',
        'reference' => 'File',
        'event' => 'Event',
        'amount' => 'Amount',
        'cancelled_on' => 'Cancelled on',
        'open' => 'Open',
        'empty' => 'No refund to make.',
    ],

    'export' => [
        'pdf' => 'Export as PDF',
    ],

    'pdf' => [
        'title' => 'Treasury summary',
        'period' => 'Events from :from to :to',
        'whole_period' => 'Whole period',
    ],
];

<?php

return [
    'title' => 'Support access',
    'description' => 'Give the Convive team temporary access to your space, so they can help you with a specific issue.',

    'rules' => [
        'title' => 'What an access allows',
        'read_only' => 'Read only: support sees your space and cannot change anything.',
        'limited' => '24 hours at most, and you can revoke it at any time.',
        'named' => 'Named: it is open to a single person of the Convive team.',
        'logged' => 'Every page viewed is listed below and in your audit log.',
        'banner' => 'While the access is open, a banner reminds every member.',
    ],

    'active' => [
        'title' => 'Current access',
        'summary' => ':operator can view your space until :expires.',
        'granted' => 'Opened by :granted_by on :date.',
        'revoke' => 'Revoke the access',
        'views' => 'Pages viewed',
        'views_empty' => 'No page viewed yet.',
    ],

    'pages' => [
        'events' => 'Events',
        'registrations' => 'Registrations',
        'proofs' => 'Proofs to check',
        'audit' => 'Audit log',
        'organisation' => 'Space and brand',
    ],

    'grant' => [
        'title' => 'Open an access',
        'none_active' => 'No access is open right now.',
        'operator' => 'Person of the Convive team',
        'operator_placeholder' => 'Choose a person',
        'duration' => 'Duration',
        'hours' => '{1} 1 hour|[2,*] :count hours',
        'submit' => 'Open the access',
        'one_at_a_time' => 'One access at a time: revoke the current one to open another.',
    ],

    'history' => [
        'title' => 'Past accesses',
        'empty' => 'No access has been opened yet.',
        'columns' => [
            'operator' => 'Person',
            'granted_at' => 'Opened on',
            'ended_at' => 'Closed on',
            'end_reason' => 'End',
            'views' => 'Pages viewed',
        ],
        'end_reasons' => [
            'expired' => 'Expired',
            'revoked' => 'Revoked',
        ],
    ],
];

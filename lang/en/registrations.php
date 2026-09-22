<?php

return [
    'title' => 'Registrations',
    'description' => 'Search, filter and export this event\'s registrations.',

    'columns' => [
        'name' => 'Name',
        'unit' => 'Unit',
        'party_size' => 'Party',
        'amount_due' => 'Amount due',
        'status' => 'Status',
        'table' => 'Table',
        'actions' => 'Actions',
        'export' => [
            'name' => 'Name',
            'phone' => 'Phone',
            'email' => 'Email',
            'unit' => 'Unit',
            'party_size' => 'Party size',
            'amount_due' => 'Amount due',
            'status' => 'Status',
            'table' => 'Table',
            'cancellation_reason' => 'Cancellation reason',
        ],
    ],

    'filters' => [
        'all' => 'All',
        'confirmed' => 'Confirmed',
        'proof_submitted' => 'Pending review',
        'without_proof' => 'Without proof',
        'cancelled' => 'Cancelled',
        'search_placeholder' => 'Name, phone or email',
    ],

    'statuses' => [
        'draft' => 'Draft',
        'held' => 'Reservation in progress',
        'proof_submitted' => 'Proof pending review',
        'confirmed' => 'Confirmed',
        'expired' => 'Expired',
        'proof_rejected' => 'Proof rejected',
        'cancelled' => 'Cancelled',
    ],

    'actions' => [
        'export' => 'Export',
        'export_excel' => 'Excel',
        'export_csv' => 'CSV',
        'export_pdf' => 'PDF',
        'export_checklists' => 'Door checklists',
        'purge' => 'Purge',
        'cancel' => 'Cancel',
        'no_table' => 'None',
    ],

    'modals' => [
        'cancel' => [
            'title' => 'Cancel this registration',
            'description' => 'This immediately releases :name\'s seat and table. The reason is kept in the log.',
            'reason_label' => 'Reason',
            'submit' => 'Confirm cancellation',
        ],
        'purge' => [
            'title' => 'Purge unfinalized registrations',
            'description' => 'Draft, expired and rejected-proof records are deleted and their seats returned.',
            'submit' => 'Confirm purge',
        ],
    ],

    'flash' => [
        'cancelled' => 'Registration cancelled.',
        'purged' => '{0} No record to purge.|{1} 1 record purged.|[2,*] :count records purged.',
    ],

    'pdf' => [
        'title' => 'Registrations',
        'empty' => 'No registration matches this filter.',
        'total' => '{1} 1 registration|[0,*] :count registrations',
        'checklists_title' => 'Entrance checklists by table',
        'checklists_empty' => 'No confirmed registration is seated at a table yet.',
        'table_heading' => 'Table :number (:seats / :capacity seats)',
        'present' => 'Present',
    ],

    'empty' => [
        'all' => [
            'title' => 'No registrations yet',
            'description' => 'Registrations will appear here as guests sign up.',
        ],
        'confirmed' => [
            'title' => 'No confirmed registrations',
            'description' => 'Confirmed registrations will appear here once a proof is validated.',
        ],
        'proof_submitted' => [
            'title' => 'No proof pending review',
            'description' => 'Proofs awaiting review will appear here.',
        ],
        'without_proof' => [
            'title' => 'No registration without proof',
            'description' => 'Drafts, expired reservations and rejected proofs will appear here.',
        ],
        'cancelled' => [
            'title' => 'No cancelled registrations',
            'description' => 'Registrations cancelled by the organisation will appear here.',
        ],
    ],
];

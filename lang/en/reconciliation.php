<?php

return [
    'title' => 'Statement reconciliation',
    'description' => 'Compare a Mobile Money or bank statement against the submitted proofs.',

    'import' => [
        'title' => 'Import a statement',
        'hint' => 'CSV file with the columns date, reference, issuer and amount.',
        'file_label' => 'Statement file',
        'submit' => 'Import and reconcile',
        'current' => 'Statement shown',
        'rows' => 'Rows: :count',
    ],

    'toolbar' => [
        'search' => 'Search a line',
        'search_placeholder' => 'Reference or sender',
        'filter_label' => 'Filter by reconciliation result',
        'all' => 'All lines',
        'count' => '{0} No line|{1} 1 line|[2,*] :count lines',
    ],

    'stats' => [
        'matched' => 'Matched',
        'amount_mismatch' => 'Amount mismatch',
        'approximate_name' => 'Approximate name',
        'no_registration' => 'No registration',
    ],

    'outcomes' => [
        'matched' => 'Matched',
        'amount_mismatch' => 'Amount mismatch',
        'approximate_name' => 'Approximate name',
        'no_registration' => 'No registration',
    ],

    'columns' => [
        'line' => 'Line',
        'date' => 'Date',
        'reference' => 'Reference',
        'issuer' => 'Issuer',
        'amount' => 'Amount',
        'outcome' => 'Outcome',
        'registration' => 'Registration',
        'actions' => 'Actions',
    ],

    'actions' => [
        'resolve' => 'Resolve',
        'seen' => 'Seen',
    ],

    'modals' => [
        'resolve' => [
            'title' => 'Resolve line :line',
            'description' => 'Pick the registration matching this payment from :issuer, or say that none matches.',
            'registration_label' => 'Registration',
            'none' => 'No registration matches',
            'submit' => 'Save',
        ],
    ],

    'flash' => [
        'imported' => 'Statement imported and reconciled.',
        'resolved' => 'Line resolved.',
    ],

    'errors' => [
        'already_imported' => 'This statement has already been imported for this event.',
        'empty' => 'The file contains no data row.',
        'too_many_rows' => 'The statement exceeds the limit of :max rows. Split it into several files.',
        'missing_columns' => 'Missing columns in the header: :columns.',
        'invalid_date' => 'Line :line: the date is unreadable (accepted formats: 2026-09-20 or 20/09/2026). The header is not counted. No row was imported.',
        'invalid_amount' => 'Line :line: the amount is unreadable or zero. The header is not counted. No row was imported.',
        'registration_other_event' => 'This registration does not belong to this event.',
    ],

    'empty' => [
        'title' => 'No statement imported',
        'description' => 'Import a CSV statement to match payments against the submitted proofs.',
    ],
];

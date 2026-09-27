<?php

return [
    'title' => 'Proofs to verify',

    'empty' => [
        'title' => 'No proof pending',
        'description' => 'Every proof submitted for this event has been processed.',
    ],

    'columns' => [
        'name' => 'Registrant',
        'unit' => 'Unit',
        'party_size' => 'Seats',
        'amount_due' => 'Amount due',
        'submitted_at' => 'Submitted on',
        'channel' => 'Channel',
        'reference' => 'Reference',
        'amount_declared' => 'Amount sent',
        'payment_account' => 'Account targeted',
        'signals' => 'Signals',
        'actions' => 'Actions',
    ],

    'signals' => [
        'none' => 'No anomaly detected',
        'duplicate_reference' => 'Reference already used',
        'duplicate_image' => 'Capture already seen',
        'reference_missing_from_statement' => 'Reference missing from the statement',
        'statement_amount_mismatch' => 'Statement amount differs',
    ],

    'companions' => [
        'none' => 'No companion',
        'list' => 'Companions: :names',
    ],

    'actions' => [
        'approve' => 'Validate',
        'reject' => 'Reject',
        'open_receipt' => 'Open receipt',
        'approve_confirm_title' => 'Validate this proof?',
        'approve_confirm_description' => 'Check the amount and the reference on the statement. The registration will be confirmed, a ticket issued and a table assigned: this validation cannot be undone.',
        'reject_confirm_title' => 'Reject this proof?',
        'reject_confirm_description' => 'The registrant will need to submit a new proof; their seats are not released immediately.',
    ],

    'flash' => [
        'validated' => 'Proof validated, registration confirmed.',
        'rejected' => 'Proof rejected.',
        'no_longer_pending' => 'This proof is no longer awaiting verification.',
    ],
];

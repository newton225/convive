<?php

return [
    'title' => 'Proofs to verify',

    'empty' => [
        'title' => 'No proof pending',
        'description' => 'Every proof submitted for this event has been processed.',
    ],

    'toolbar' => [
        'search' => 'Search',
        'search_placeholder' => 'Name, reference, phone, unit, companion',
        'filter_label' => 'Filter by signal',
        'count' => '{0} No proof|{1} 1 proof|[2,*] :count proofs',
    ],

    'filters' => [
        'all' => 'All proofs',
        'anomaly' => 'With an anomaly',
        'clean' => 'Without anomaly',
        'note' => 'With a guest note',
    ],

    'no_match' => [
        'title' => 'No proof matches',
        'description' => 'Change the search or the filter to see other proofs.',
        'reset' => 'Clear the search and filter',
    ],

    'columns' => [
        'name' => 'Registrant',
        'unit' => 'Unit',
        'party_size' => 'Seats',
        'amount_due' => 'Amount due',
        'submitted_at' => 'Submitted on',
        'channel' => 'Channel',
        'reference' => 'Reference',
        'guest_note' => 'Guest’s note',
        'payment_account' => 'Account targeted',
        'payment' => 'Payment',
        'signals' => 'Signals',
        'actions' => 'Actions',
    ],

    'signals' => [
        'none' => 'No anomaly detected',
        'duplicate_reference' => 'Reference already used',
        'duplicate_image' => 'Capture already seen',
        'reference_missing_from_statement' => 'Reference missing from the statement',
        'statement_amount_mismatch' => 'Statement amount differs',
        'guest_note' => 'Guest’s note',
    ],

    'details' => [
        'column' => 'Details',
        'show' => 'Show details for :name',
        'hide' => 'Hide details for :name',
        'companions' => 'Companions',
        'no_note' => 'No note.',
        'single_expand' => 'Only one row open at a time',
    ],

    'preview' => [
        'title' => 'Receipt from :name',
        'description' => 'Compare the capture with the registration details before deciding.',
        'alt' => 'Capture of the receipt submitted by :name',
        'loading' => 'Loading the receipt',
        'error' => 'The receipt could not be displayed. Check the connection, then try again.',
        'retry' => 'Try again',
        'download' => 'Download',
        'enlarge' => 'Enlarge the receipt from :name',
        'event' => 'Event',
        'status' => 'Registration status',
    ],

    'duplicate_image' => [
        'title' => 'Same capture elsewhere',
        'description' => 'The capture submitted by :name looks like the one of these other payments. Compare the details, and click a capture to enlarge it.',
        'show' => 'See the payments carrying this capture',
        'current' => 'Capture under review',
        'others' => '{0} No other payment|{1} 1 other payment with this capture|[2,*] :count other payments with this capture',
        'same_event' => 'This event',
        'no_receipt' => 'Capture no longer available',
        'empty' => 'No other payment carries this capture.',
    ],

    'confirm' => [
        'registration' => 'Registration',
        'phone' => 'Phone',
    ],

    'companions' => [
        'none' => 'No companion',
        'count' => '{0} No companion|{1} 1 companion|[2,*] :count companions',
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

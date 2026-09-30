<?php

return [
    'title' => 'Registrations',
    'description' => 'Search, filter and export this event\'s registrations.',

    'entry' => [
        'entered' => 'Entered at :time',
        'group' => ':count of :total entered, from :time',
        'not_yet' => 'Not entered yet',
    ],

    'cancellations' => [
        'title' => 'Cancellations',
        'empty_title' => 'No cancelled registration',
        'empty_description' => 'Cancellations will appear here with their reason, who did it and what happened to the payment.',
        'no_reason' => 'No reason given',
        'by' => 'By :name, on :date',
        'unknown_author' => 'a removed member',
    ],

    'columns' => [
        'name' => 'Name',
        'unit' => 'Unit',
        'party_size' => 'Party',
        'amount_due' => 'Amount due',
        'status' => 'Status',
        'table' => 'Table',
        'channel' => 'Channel',
        'entry' => 'Entry',
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
        'search_placeholder' => 'Name, reference, phone or email',
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

    'confirm' => [
        'reference' => 'Registration',
        'phone' => 'Phone',
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

    'card' => [
        'column' => 'Card',
        'sent_on' => 'Sent on :date',
        'not_sent' => 'Not sent yet',
        'open' => 'Card',
        'title' => 'Invitation card',
        'description' => 'For :name and their group. Use this if the automatic sending did not work or the card was lost.',
        'last_sent' => 'Last sent',
        'holder' => 'Main guest',
        'holder_hint' => "Their card opens the whole group's tickets, and the message includes the companions' tickets.",
        'companion' => 'Companion',
        'companion_hint' => 'Their own ticket only. No number is known: pick the recipient in WhatsApp.',
        'send' => 'Send from the application',
        'resend' => 'Send again from the application',
        'send_hint' => 'By WhatsApp, and by email if the guest gave one.',
        'copy' => 'Copy the link',
        'copied' => 'Link copied',
        'whatsapp' => 'Open WhatsApp',
        'no_link' => 'The link cannot be built: the organisation has no subdomain or the event is not published.',
        'traced' => 'Every sending, copy or WhatsApp opening is logged: the link lets someone in.',
        'flash' => [
            'sent' => 'Card sent to :name.',
        ],
        'errors' => [
            'not_confirmed' => 'Only a validated registration has an invitation card.',
            'not_sent' => 'The card was not sent: the plan\'s sending quota is reached, or the link cannot be built. Copy the link to send it yourself.',
            'copy_failed' => 'The link could not be copied. Select it and copy it by hand.',
        ],
    ],

    'refund' => [
        'title' => 'Payment',
        'question' => 'What happens to the :amount payment?',
        'no_permission' => 'The :amount payment will be marked "to refund". An authorised member will then decide what happens to it.',
        'statuses' => [
            'due' => 'To refund',
            'refunded' => 'Refunded',
            'kept' => 'Kept',
        ],
        'hints' => [
            'due' => 'The organisation owes this amount. It stays flagged until it is refunded.',
            'refunded' => 'The money has already gone back to the guest.',
            'kept' => 'The organisation keeps this amount, for a reason to state.',
        ],
        'fields' => [
            'channel' => 'Refund method',
            'channel_placeholder' => 'Choose the method',
            'refunded_on' => 'Refund date',
            'fee' => 'Transaction fees charged (CFA francs)',
            'fee_help' => 'The exact amount the operator charged. The guest bears them: they are deducted from what the guest receives.',
            'reference' => 'Transaction reference (optional)',
            'kept_reason' => 'Reason',
            'kept_reason_placeholder' => 'For example: late cancellation, donation to the association',
        ],
        'net' => 'The guest receives :net (:amount minus :fee in fees).',
        'amount_paid' => 'Amount paid',
        'mark_refunded' => 'Mark as refunded',
        'mark_title' => 'Record the refund',
        'mark_description' => 'Do this once the money has gone to :name. The registration stays cancelled; the guest is notified.',
        'submit' => 'Record the refund',
        'summary' => [
            'due' => ':amount to refund',
            'refunded' => ':net refunded on :date by :channel (:fee in fees)',
            'kept' => ':amount kept: :reason',
        ],
        'errors' => [
            'fee_too_high' => 'Fees must stay below the amount paid: otherwise nothing would be refunded.',
            'future_date' => 'The refund date cannot be in the future: record it once the money has gone.',
            'kept_reason_required' => 'Say why the organisation keeps this payment.',
            'not_due' => 'This payment is no longer to refund: it has already been handled.',
        ],
    ],

    'flash' => [
        'refunded' => 'Refund recorded. The guest has been notified.',
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

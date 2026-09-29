<?php

return [
    'title' => 'Payment accounts',
    'description' => 'The accounts your guests pay into. Any change only becomes public after a :hours hour delay.',

    'channels' => [
        'wave' => 'Wave',
        'orange_money' => 'Orange Money',
        'mtn_money' => 'MTN MoMo',
        'moov_money' => 'Moov Money',
        'bank_transfer' => 'Bank transfer',
        'cash' => 'Cash',
    ],

    'fields' => [
        'label' => 'Label',
        'label_placeholder' => 'For example: Main Wave account',
        'channel' => 'Channel',
        'account_number' => 'Account number',
        'holder_name' => 'Account holder',
        'instructions' => 'Instruction shown to your guests',
        'instructions_placeholder' => 'Put your name as the transfer reference.',
        'is_active' => 'Offered to guests',
    ],

    'help' => [
        'label' => 'A free name to recognise this account. It does not say which network the account belongs to: the channel is what counts.',
        'channel' => 'The network or payment method. It is what the guest sees and what statement reconciliation relies on. Changing it starts the 24-hour security delay.',
        'account_number' => 'The number your guests pay into. A change only takes effect after 24 hours: the old number stays on show until then, and account managers are alerted straight away.',
        'holder_name' => 'The name the guest sees when transferring: it lets them check they are paying the right person. Subject to the same 24-hour delay.',
        'instructions' => 'A short instruction shown under the number, for instance the reference to use. It applies straight away.',
    ],

    'actions' => [
        'create' => 'Add an account',
        'save' => 'Request the change',
        'approve' => 'Approve and apply now',
        'cancel_change' => 'Cancel the change',
        'delete' => 'Delete account',
    ],

    'badges' => [
        'visible' => 'Visible to guests',
        'hidden' => 'Not visible',
        'pending' => 'Change pending',
        'recent_change' => 'Recently changed',
    ],

    'pending' => [
        'title' => 'Change pending',
        'requested_by' => 'Requested by :name.',
        'activates_at' => 'It will apply on :date. Until then, the previous number stays visible.',
        'new_number' => 'New number: :value',
        'approve_hint' => 'A second Owner can apply it immediately.',
        'not_the_requester' => 'You cannot approve a change you requested yourself.',
    ],

    'notice' => [
        'recent_change' => 'A payment account was changed less than :days days ago. Check that this change is legitimate.',
    ],

    'flash' => [
        'change_requested' => 'Change recorded. It will apply after the activation delay.',
        'updated' => 'Account updated.',
        'change_approved' => 'Change applied.',
        'change_cancelled' => 'Change cancelled.',
        'deleted' => 'Account deleted.',
    ],

    'errors' => [
        'account_number_required' => 'This channel requires an account number.',
        'number_invalid' => 'Enter a 10-digit Ivorian number, for example 07 07 12 34 56.',
        'number_network' => 'A :channel number starts with :prefixes. Check the number or the channel chosen.',
    ],

    'confirm_approve' => [
        'title' => 'Activate this change now?',
        'description' => 'The new number of the account ":label" will show on public links right away, without waiting for the 24-hour delay. Check it with the person who requested it, through another channel, before confirming.',
    ],

    'confirm_delete' => [
        'title' => 'Delete payment account',
        'description' => 'The ":name" account will be deleted. This cannot be undone.',
    ],

    'mail' => [
        'subject' => 'Payment account changed: :tenant',
        'intro' => ':actor requested a change to the ":label" account of :tenant.',
        'before' => 'Previous number: :value',
        'after' => 'New number: :value',
        'activates_at' => 'This change will take effect on :date.',
        'none' => 'none',
        'outro' => 'If you did not request this, cancel it immediately and review who has access to your organisation.',
    ],

    'whatsapp' => [
        'alert' => 'Convive: the payment account ":label" of :tenant has changed. Previous number: :before. New: :after. If you did not request this, cancel it immediately.',
    ],
];

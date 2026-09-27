<?php

return [
    'title' => 'Subscription',
    'back' => 'Back to settings',

    'statuses' => [
        'active' => 'Up to date',
        'past_due' => 'Payment overdue',
        'suspended' => 'Suspended',
        'canceled' => 'Canceled',
    ],

    'invoice_statuses' => [
        'open' => 'To pay',
        'paid' => 'Paid',
        'failed' => 'Failed',
        'void' => 'Void',
    ],

    'banner' => [
        'past_due' => 'The last payment failed. Update your payment method: without payment, the workspace is suspended after ten days.',
        'suspended' => 'This workspace is suspended for non-payment: events, proofs and scanning are stopped, and public registrations are closed. Pay the subscription to reopen it.',
    ],

    'confirm_cancel' => [
        'title' => 'Cancel the subscription?',
        'description' => 'Billing stops. To get this plan back later, you will need to subscribe again.',
    ],

    'subscription' => [
        'renews' => 'Next payment on :date.',
        'canceled' => 'Subscription canceled on :date.',
        'payment_method' => 'Payment method: :brand ending in :last4.',
        'no_payment_method' => 'No payment method on file.',
        'change_payment_method' => 'Change payment method',
        'cancel' => 'Cancel subscription',
    ],

    'usage' => [
        'title' => 'Usage',
        'events' => 'Active events',
        'registrations' => 'Registered guests',
        'members' => 'Members and invitations',
        'of' => ':used of :max',
        'unlimited' => ':used (unlimited)',
    ],

    'plans' => [
        'title' => 'Plans',
        'current' => 'Current plan',
        'currency' => 'Currency',
        'free' => 'Free',
        'on_quote' => 'On quote',
        'per_month' => ':price per month',
        'unlimited' => 'Unlimited',
        'events' => 'Active events: :count',
        'registrations' => 'Registered guests: :count',
        'members' => 'Members: :count',
        'reconciliation' => 'Statement reconciliation',
        'reports' => 'Post-event reports',
        'custom_domain' => 'Custom domain',
        'sso' => 'Single sign-on (SSO)',
        'choose' => 'Choose this plan',
        'contact' => 'Contact us',
    ],

    'invoices' => [
        'title' => 'Invoices',
        'empty' => 'No invoices yet.',
        'number' => 'Number',
        'date' => 'Date',
        'amount' => 'Amount',
        'status' => 'Status',
        'open' => 'Open',
    ],

    'errors' => [
        'suspended' => 'This workspace is suspended for non-payment. Pay the subscription to reopen it.',
        'event_quota' => 'The :plan plan does not allow another active event. Close an event or move to a higher plan.',
        'member_quota' => 'The :plan plan cannot take another member, pending invitations included. Move to a higher plan.',
        'not_purchasable' => 'This plan cannot be bought online: it is free, or negotiated on quote.',
        'not_configured' => 'Online payment is not available yet. Try again later or contact us.',
        'no_provider_customer' => 'No payment has been recorded for this organisation yet.',
        'no_subscription' => 'This organisation has no subscription to cancel.',
    ],

    'flash' => [
        'canceled' => 'Subscription canceled.',
    ],

    'mail' => [
        'action' => 'Open the subscription',
        'overdue' => [
            'subject' => 'The subscription payment of :tenant is overdue',
            'line' => 'The subscription payment of :tenant failed.',
            'deadline' => '{0} Without payment, the workspace is suspended today.|{1} Without payment, the workspace is suspended tomorrow.|[2,*] Without payment within :days days, the workspace is suspended.',
        ],
        'suspended' => [
            'subject' => 'The :tenant workspace is suspended',
            'line' => 'The subscription of :tenant remains unpaid: the workspace is suspended. Pay the subscription to reopen it.',
        ],
    ],
];

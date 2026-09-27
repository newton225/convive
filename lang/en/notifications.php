<?php

return [
    'bell' => [
        'title' => 'Notifications',
        'label' => '{0} Notifications|{1} Notifications, 1 unread|[2,*] Notifications, :count unread',
        'read_all' => 'Mark all as read',
        'empty' => 'No notifications yet.',
        'unread' => 'Unread',
    ],

    'types' => [
        'proof_received' => ':name submitted a payment proof for :event.',
        'holds_expired' => '{1} 1 reservation expired for :event.|[2,*] :count reservations expired for :event.',
        'proof_rejected' => 'The proof from :name for :event was rejected.',
        'seats_low' => '{1} Only 1 seat left for :event.|[2,*] Only :count seats left for :event.',
        'purge_scheduled' => '{1} Purge scheduled within 24 h for :event: 1 registration without proof affected.|[2,*] Purge scheduled within 24 h for :event: :count registrations without proof affected.',
        'seats_exhausted' => 'No seats are left for :event.',
        'registrations_purged' => '{1} 1 unfinished registration was purged for :event.|[2,*] :count unfinished registrations were purged for :event.',
        'team_invitation_pending' => 'You are invited to join :tenant with the :profile profile.',
        'ticket_refused' => 'A ticket was refused at the entrance of :event.',
        'large_export' => ':name exported :count registrations of :event (:format).',
    ],

    'preferences' => [
        'head' => 'Notifications',
        'title' => 'Notifications',
        'description' => 'Choose where to receive each alert. This setting follows you across all your organisations.',
        'saved' => 'Preferences saved.',
        'types' => [
            'proof_received' => 'Payment proof received',
            'holds_expired' => 'Reservation expired',
            'proof_rejected' => 'Proof rejected',
            'seats_exhausted' => 'Seats exhausted',
            'seats_low' => 'Seats running low',
            'purge_scheduled' => 'Purge scheduled',
            'registrations_purged' => 'Purge done',
            'team_invitation_pending' => 'Pending team invitation',
            'ticket_refused' => 'Ticket refused at the entrance',
            'large_export' => 'Large export of the registration base',
        ],
        'channels' => [
            'app' => 'In the app',
            'mail' => 'By email',
            'both' => 'In the app and by email',
        ],
    ],

    'mail' => [
        'subject' => 'New notification on :app',
        'action' => 'Open',
    ],
];

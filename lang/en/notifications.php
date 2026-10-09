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
        'claim_received' => ":name sent a claim for :event.",
        'holds_expired' => '{1} 1 reservation expired for :event.|[2,*] :count reservations expired for :event.',
        'proof_rejected' => 'The proof from :name for :event was rejected.',
        'seats_low' => '{1} Only 1 seat left for :event.|[2,*] Only :count seats left for :event.',
        'purge_scheduled' => '{1} Purge scheduled within 24 h for :event: 1 registration without proof affected.|[2,*] Purge scheduled within 24 h for :event: :count registrations without proof affected.',
        'seats_exhausted' => 'No seats are left for :event.',
        'registrations_purged' => '{1} 1 unfinished registration was purged for :event.|[2,*] :count unfinished registrations were purged for :event.',
        'team_invitation_pending' => 'You are invited to join :tenant with the :profile profile.',
        'team_invitation_accepted' => ':name accepted the invitation and joined :tenant with the :profile profile.',
        'ticket_refused' => 'A ticket was refused at the entrance of :event.',
        'entry_without_scan' => ':agent let :guest in without scanning their ticket, at :event.',
        'large_export' => ':name exported :count registrations of :event (:format).',
        'message_quota_reached' => 'The :plan plan sending quota is reached (:count messages this month): cards and reminders to guests will resume next month, or as soon as the plan changes.',
        'plan_limits_lowered' => 'The limits of the :plan plan have been lowered and your organisation exceeds at least one of them. Nothing is closed or removed, but you can no longer publish an event, take a new registration or invite a member beyond the limit. Your usage is on the Subscription screen.',
        'trial_ending' => '{1} Your trial period ends tomorrow. Without a subscription, your organisation will move to the free plan: nothing will be removed, but its limits will apply.|[2,*] Your trial period ends in :count days. Without a subscription, your organisation will move to the free plan: nothing will be removed, but its limits will apply.',
        'trial_ended' => 'Your trial period has ended: your organisation is now on the :plan plan. Nothing was removed. To get back what the trial allowed, choose a subscription.',
    ],

    'preferences' => [
        'head' => 'Notifications',
        'title' => 'Notifications',
        'description' => 'Choose where to receive each alert. This setting follows you across all your organisations.',
        'saved' => 'Preferences saved.',
        'types' => [
            'proof_received' => 'Payment proof received',
            'claim_received' => "Guest claim",
            'holds_expired' => 'Reservation expired',
            'proof_rejected' => 'Proof rejected',
            'seats_exhausted' => 'Seats exhausted',
            'seats_low' => 'Seats running low',
            'purge_scheduled' => 'Purge scheduled',
            'registrations_purged' => 'Purge done',
            'team_invitation_pending' => 'Pending team invitation',
            'team_invitation_accepted' => 'Team invitation accepted',
            'ticket_refused' => 'Ticket refused at the entrance',
            'entry_without_scan' => 'Entry confirmed without scanning',
            'large_export' => 'Large export of the registration base',
            'message_quota_reached' => 'Sending quota reached',
            'plan_limits_lowered' => 'Plan limits lowered',
            'trial_ending' => 'Trial period ending in a few days',
            'trial_ended' => 'Trial period ended',
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

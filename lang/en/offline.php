<?php

return [
    'banner' => [
        'title' => 'You are offline',
        'body' => 'Some actions wait for the network to come back.',
    ],

    'scan' => [
        'queued' => '{0} No scan waiting|{1} 1 scan waiting to sync|[2,*] :count scans waiting to sync',
        'sync_now' => 'Sync now',
        'syncing' => 'Syncing',
        'sync_done' => 'Synced.',
        'unsupported' => 'This phone cannot verify a ticket offline. Come back to a covered area.',
        'no_key' => 'The verification key is not available yet for this event.',
        'verified_offline' => 'Valid, verified offline',
        'verified_offline_help' => 'The entry will be confirmed when the network is back.',
        'already_local' => 'Already scanned on this device',
        'forged' => 'Invalid ticket',
        'wrong_event' => 'Ticket for another event',
        'outdated' => 'Ticket issued with an old key: the guest must reopen their ticket to get the new code.',
        'expired' => 'Ticket expired',
        'revoked' => 'Ticket cancelled',
        'review_title' => 'To review after syncing',
        'review_item' => 'A ticket accepted offline was refused or already used according to the server.',
    ],
];

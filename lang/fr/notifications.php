<?php

return [
    'bell' => [
        'title' => 'Notifications',
        'label' => '{0} Notifications|{1} Notifications, 1 non lue|[2,*] Notifications, :count non lues',
        'read_all' => 'Tout marquer comme lu',
        'empty' => 'Aucune notification pour le moment.',
        'unread' => 'Non lue',
    ],

    'types' => [
        'proof_received' => ':name a déposé une preuve de paiement pour :event.',
        'holds_expired' => '{1} 1 réservation a expiré pour :event.|[2,*] :count réservations ont expiré pour :event.',
        'proof_rejected' => 'La preuve de :name pour :event a été rejetée.',
        'seats_exhausted' => 'Il ne reste plus aucune place pour :event.',
        'registrations_purged' => '{1} 1 dossier non finalisé a été purgé pour :event.|[2,*] :count dossiers non finalisés ont été purgés pour :event.',
        'team_invitation_pending' => 'Vous êtes invité à rejoindre :tenant avec le profil :profile.',
        'ticket_refused' => 'Un billet a été refusé à l\'entrée de :event.',
        'large_export' => ':name a exporté :count inscrits de :event (:format).',
    ],

    'preferences' => [
        'head' => 'Notifications',
        'title' => 'Notifications',
        'description' => 'Choisissez, pour chaque alerte, où la recevoir. Ce réglage vous suit dans toutes vos organisations.',
        'saved' => 'Préférences enregistrées.',
        'types' => [
            'proof_received' => 'Preuve de paiement reçue',
            'holds_expired' => 'Réservation expirée',
            'proof_rejected' => 'Preuve rejetée',
            'seats_exhausted' => 'Places épuisées',
            'registrations_purged' => 'Purge effectuée',
            'team_invitation_pending' => 'Invitation d\'équipe en attente',
            'ticket_refused' => 'Billet refusé à l\'entrée',
            'large_export' => 'Export volumineux de la base d\'inscrits',
        ],
        'channels' => [
            'app' => 'Dans l\'application',
            'mail' => 'Par email',
            'both' => 'Dans l\'application et par email',
        ],
    ],

    'mail' => [
        'subject' => 'Nouvelle notification sur :app',
        'action' => 'Ouvrir',
    ],
];

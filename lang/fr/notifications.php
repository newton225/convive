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
        'seats_low' => '{1} Il ne reste plus qu\'1 place pour :event.|[2,*] Il ne reste plus que :count places pour :event.',
        'purge_scheduled' => '{1} Purge programmée dans moins de 24 h pour :event : 1 inscription sans preuve concernée.|[2,*] Purge programmée dans moins de 24 h pour :event : :count inscriptions sans preuve concernées.',
        'registrations_purged' => '{1} 1 dossier non finalisé a été purgé pour :event.|[2,*] :count dossiers non finalisés ont été purgés pour :event.',
        'team_invitation_pending' => 'Vous êtes invité à rejoindre :tenant avec le profil :profile.',
        'team_invitation_accepted' => ':name a accepté l\'invitation et rejoint :tenant avec le profil :profile.',
        'ticket_refused' => 'Un billet a été refusé à l\'entrée de :event.',
        'entry_without_scan' => ':agent a fait entrer :guest sans scanner son billet, à :event.',
        'large_export' => ':name a exporté :count inscrits de :event (:format).',
        'message_quota_reached' => 'Le quota d\'envois du plan :plan est atteint (:count messages ce mois-ci) : les cartes et rappels aux invités reprendront le mois prochain, ou dès un changement de plan.',
        'plan_limits_lowered' => 'Les limites du plan :plan ont été abaissées et votre organisation en dépasse au moins une. Rien n’est fermé ni supprimé, mais vous ne pourrez plus publier d’événement, accueillir de nouvelle inscription ou inviter de membre au-delà de la limite. Votre consommation est sur l’écran Abonnement.',
        'trial_ending' => '{1} Votre période d’essai se termine demain. Sans abonnement, votre organisation passera sur le plan gratuit : rien ne sera supprimé, mais ses limites s’appliqueront.|[2,*] Votre période d’essai se termine dans :count jours. Sans abonnement, votre organisation passera sur le plan gratuit : rien ne sera supprimé, mais ses limites s’appliqueront.',
        'trial_ended' => 'Votre période d’essai a pris fin : votre organisation est maintenant sur le plan :plan. Rien n’a été supprimé. Pour retrouver ce que l’essai permettait, choisissez un abonnement.',
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
            'seats_low' => 'Places bientôt épuisées',
            'purge_scheduled' => 'Purge programmée',
            'registrations_purged' => 'Purge effectuée',
            'team_invitation_pending' => 'Invitation d\'équipe en attente',
            'team_invitation_accepted' => 'Invitation d\'équipe acceptée',
            'ticket_refused' => 'Billet refusé à l\'entrée',
            'entry_without_scan' => 'Entrée validée sans scan',
            'large_export' => 'Export volumineux de la base d\'inscrits',
            'message_quota_reached' => 'Quota d\'envois atteint',
            'plan_limits_lowered' => 'Limites du plan abaissées',
            'trial_ending' => 'Fin de la période d’essai dans quelques jours',
            'trial_ended' => 'Période d’essai terminée',
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

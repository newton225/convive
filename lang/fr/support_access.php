<?php

return [
    'title' => 'Accès du support',
    'description' => "Ouvrez à l'équipe Convive un accès temporaire à votre espace, pour qu'elle vous aide sur un problème précis.",

    'rules' => [
        'title' => 'Ce que permet un accès',
        'read_only' => 'Lecture seule : le support voit votre espace, il ne peut rien modifier.',
        'limited' => '24 heures au plus, et vous pouvez le révoquer à tout moment.',
        'named' => 'Nominatif : il est ouvert à une seule personne de l’équipe Convive.',
        'logged' => 'Chaque page consultée est inscrite ci-dessous et dans votre journal.',
        'banner' => 'Tant que l’accès est ouvert, un bandeau le rappelle à tous les membres.',
    ],

    'active' => [
        'title' => 'Accès en cours',
        'summary' => ':operator peut consulter votre espace jusqu’au :expires.',
        'granted' => 'Ouvert par :granted_by le :date.',
        'reason' => 'Motif',
        'revoke' => 'Révoquer l’accès',
        'views' => 'Pages consultées',
        'views_empty' => 'Aucune page consultée pour le moment.',
    ],

    'pages' => [
        'events' => 'Événements',
        'registrations' => 'Base d’inscrits',
        'proofs' => 'Preuves à vérifier',
        'audit' => 'Journalisation',
        'organisation' => 'Espace et marque',
        'dashboard' => 'Tableau de bord',
        'seating' => 'Plan de salle',
        'reports' => 'Rapport',
        'other' => 'Autre page',
    ],

    'grant' => [
        'title' => 'Ouvrir un accès',
        'none_active' => 'Aucun accès n’est ouvert en ce moment.',
        'operator' => 'Personne de l’équipe Convive',
        'operator_placeholder' => 'Choisir une personne',
        'no_operator' => 'Personne de l’équipe Convive n’est disponible pour le moment. Écrivez au support : la personne qui vous aide se rendra disponible, et son nom apparaîtra ici.',
        'duration' => 'Durée',
        'hours' => '{1} 1 heure|[2,*] :count heures',
        'reason' => 'Pourquoi ouvrez-vous cet accès ?',
        'reason_hint' => 'Une ou deux phrases : le problème et l’événement concerné. La personne de l’équipe Convive lira ce motif avant d’entrer, et il reste dans votre historique. N’y mettez ni mot de passe ni numéro de compte.',
        'submit' => 'Ouvrir l’accès',
        'confirm_title' => 'Ouvrir un accès à :operator ?',
        'confirm_description' => 'Cette personne de l’équipe Convive pourra lire vos événements, vos inscrits, vos preuves et votre journal pendant :duration. Elle ne pourra rien modifier, et vous pourrez révoquer l’accès à tout moment.',
        'one_at_a_time' => 'Un seul accès à la fois : révoquez celui en cours pour en ouvrir un autre.',
    ],

    'history' => [
        'title' => 'Accès passés',
        'empty' => 'Aucun accès n’a encore été ouvert.',
        'columns' => [
            'operator' => 'Personne',
            'reason' => 'Motif',
            'granted_at' => 'Ouvert le',
            'ended_at' => 'Fermé le',
            'end_reason' => 'Fin',
            'views' => 'Pages consultées',
            'closing_note' => 'Conclusion du support',
        ],
        'end_reasons' => [
            'expired' => 'Arrivé à échéance',
            'revoked' => 'Révoqué',
            'finished' => 'Fermé par le support',
        ],
    ],
    'errors' => [
        'note' => 'Écrivez en une phrase ce que vous avez constaté (10 caractères au moins).',
        'reason' => 'Dites en une phrase pourquoi vous ouvrez cet accès (10 caractères au moins).',
        'already_open' => 'Un accès est déjà ouvert. Révoquez-le avant d’en ouvrir un autre.',
        'operator' => 'Choisissez une personne de l’équipe Convive.',
        'duration' => 'Choisissez l’une des durées proposées, 24 heures au plus.',
        'read_only' => 'Un accès de support est en lecture seule : cette action n’est pas permise.',
    ],

    'flash' => [
        'finished' => 'L’accès à :organisation est fermé. L’organisation est prévenue.',
        'opened' => 'Accès ouvert à :operator.',
        'revoked' => 'Accès révoqué.',
    ],

    'banner' => [
        'member' => ':operator, de l’équipe Convive, peut consulter cet espace en lecture seule jusqu’au :expires.',
        'viewing' => 'Accès de support : vous consultez :organisation en lecture seule jusqu’au :expires. Chaque page consultée est journalisée.',
        'manage' => 'Gérer l’accès',
    ],
    'finish' => [
        'button' => 'J’ai terminé',
        'title' => 'Fermer l’accès à :organisation ?',
        'description' => 'Vous ne pourrez plus consulter cet espace : il faudrait que l’organisation vous rouvre un accès. Les Propriétaires sont prévenus et lisent votre note.',
        'note' => 'Ce que vous avez constaté',
        'note_hint' => 'En une ou deux phrases : la cause du problème, ou ce qu’il reste à faire. La note est lue par l’organisation : ni donnée d’invité, ni information interne.',
        'submit' => 'Fermer l’accès',
    ],
    'mail' => [
        'opened' => [
            'subject' => ':organisation vous a ouvert un accès de support',
            'intro' => ':granted_by vous a ouvert un accès en lecture seule à l’espace de :organisation.',
            'reason' => 'Motif : :reason',
            'until' => 'L’accès se ferme le :expires.',
            'action' => 'Ouvrir la console',
            'outro' => 'Chaque page que vous consultez est inscrite au journal de l’organisation. Quand vous avez fini, fermez l’accès avec « J’ai terminé ».',
        ],
        'ended' => [
            'subject' => 'L’accès de support à :organisation est fermé',
            'reasons' => [
                'finished' => ':operator, de l’équipe Convive, a terminé et a fermé son accès à :organisation.',
                'revoked' => 'L’accès de :operator, de l’équipe Convive, à :organisation a été révoqué.',
                'expired' => 'L’accès de :operator, de l’équipe Convive, à :organisation est arrivé à échéance.',
            ],
            'views' => '{0} Aucune page n’a été consultée.|{1} 1 page a été consultée.|[2,*] :count pages ont été consultées.',
            'note' => 'Conclusion du support : :note',
            'action' => 'Voir le détail',
        ],
    ],
];

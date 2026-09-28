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
    ],

    'grant' => [
        'title' => 'Ouvrir un accès',
        'none_active' => 'Aucun accès n’est ouvert en ce moment.',
        'operator' => 'Personne de l’équipe Convive',
        'operator_placeholder' => 'Choisir une personne',
        'duration' => 'Durée',
        'hours' => '{1} 1 heure|[2,*] :count heures',
        'submit' => 'Ouvrir l’accès',
        'one_at_a_time' => 'Un seul accès à la fois : révoquez celui en cours pour en ouvrir un autre.',
    ],

    'history' => [
        'title' => 'Accès passés',
        'empty' => 'Aucun accès n’a encore été ouvert.',
        'columns' => [
            'operator' => 'Personne',
            'granted_at' => 'Ouvert le',
            'ended_at' => 'Fermé le',
            'end_reason' => 'Fin',
            'views' => 'Pages consultées',
        ],
        'end_reasons' => [
            'expired' => 'Arrivé à échéance',
            'revoked' => 'Révoqué',
        ],
    ],
];

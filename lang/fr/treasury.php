<?php

return [
    'title' => 'Trésorerie',
    'description' => 'L’argent de toute l’organisation : ce qui a été encaissé, remboursé et ce qui reste à rendre, par événement et par compte de versement.',

    'period' => [
        'title' => 'Période',
        'from' => 'Du',
        'to' => 'Au',
        'hint' => 'Les événements dont la date est dans la période.',
        'apply' => 'Afficher',
        'reset' => 'Toute la période',
    ],

    'totals' => [
        'collected' => 'Encaissé',
        'collected_detail' => 'Toutes les preuves validées, annulations comprises',
        'refunded' => 'Remboursé',
        'fees' => '{0} Aucuns frais retenus|[1,*] dont :amount de frais retenus',
        'due' => 'À rembourser',
        'net' => 'Net',
        'net_detail' => 'Encaissé moins remboursé',
    ],

    'events' => [
        'title' => 'Par événement',
        'name' => 'Événement',
        'date' => 'Date',
        'collected' => 'Encaissé',
        'refunded' => 'Remboursé',
        'fees' => 'Frais retenus',
        'due' => 'À rembourser',
        'net' => 'Net',
        'open' => 'Voir les inscrits',
        'empty' => 'Aucun événement dans cette période.',
    ],

    'accounts' => [
        'title' => 'Par compte de versement',
        'description' => 'Chaque montant est rangé sous le compte de la preuve de paiement du dossier.',
        'label' => 'Compte',
        'channel' => 'Canal',
        'collected' => 'Encaissé',
        'refunded' => 'Remboursé',
        'due' => 'À rembourser',
        'unknown' => 'Compte inconnu',
        'empty' => 'Aucun paiement encaissé dans cette période.',
    ],

    'refunds' => [
        'title' => 'Remboursements à faire',
        'count' => '{0} Aucun remboursement à faire|{1} 1 remboursement à faire|[2,*] :count remboursements à faire',
        'more' => 'Seuls les :limit plus anciens sont listés : ouvrez la base d’inscrits de l’événement pour le reste.',
        'name' => 'Invité',
        'reference' => 'Dossier',
        'event' => 'Événement',
        'amount' => 'Montant',
        'cancelled_on' => 'Annulé le',
        'open' => 'Ouvrir',
        'empty' => 'Aucun remboursement à faire.',
    ],

    'export' => [
        'pdf' => 'Exporter en PDF',
    ],

    'pdf' => [
        'title' => 'Synthèse de trésorerie',
        'period' => 'Événements du :from au :to',
        'whole_period' => 'Toute la période',
    ],
];

<?php

return [
    'title' => 'Réclamations',
    'description' => 'Les messages des invités sur leur dossier. Rappelez la personne au numéro indiqué, puis marquez la réclamation comme traitée.',

    'categories' => [
        'payment' => 'Mon paiement',
        'refund' => 'Mon remboursement',
        'ticket' => 'Mon billet',
        'other' => 'Autre question',
    ],

    'statuses' => [
        'open' => 'À traiter',
        'resolved' => 'Traitée',
    ],

    'filters' => [
        'status_label' => 'Filtrer par état',
        'open' => 'À traiter',
        'resolved' => 'Traitées',
        'all' => 'Toutes',
    ],

    'toolbar' => [
        'search' => 'Rechercher',
        'search_placeholder' => 'Nom, référence ou téléphone',
    ],

    'columns' => [
        'date' => 'Reçue le',
        'guest' => 'Invité',
        'category' => 'Sujet',
        'message' => 'Message',
        'status' => 'État',
        'actions' => 'Actions',
    ],

    'empty' => [
        'open' => 'Aucune réclamation à traiter.',
        'other' => 'Aucune réclamation pour ce filtre.',
    ],

    'resolve' => [
        'action' => 'Marquer comme traitée',
        'title' => 'Marquer cette réclamation comme traitée ?',
        'description' => 'Vous confirmez avoir répondu à :name. La réclamation reste visible dans la liste des réclamations traitées.',
        'confirm' => 'Marquer comme traitée',
    ],

    'resolved_on' => 'Traitée le :date',

    'flash' => [
        'resolved' => 'Réclamation marquée comme traitée.',
    ],
];

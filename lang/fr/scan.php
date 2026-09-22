<?php

return [
    'title' => "Contrôle à l'entrée",

    'viewfinder' => [
        'placeholder' => 'Visez le QR du billet',
        'scanning' => 'Recherche du code...',
        'camera_denied' => "La caméra n'a pas pu être activée. Vérifiez les autorisations du navigateur.",
    ],

    'results' => [
        'accepted' => 'Entrée validée',
        'already_scanned' => 'Billet déjà scanné',
        'refused' => 'Billet refusé',
    ],

    'result' => [
        'table' => 'Table :number',
        'no_table' => 'Non placée',
        'first_scanned_at' => 'Premier passage : :time',
        'first_scanned_by' => 'Par :name',
        'force' => "Forcer l'entrée",
        'forced_badge' => 'Entrée forcée',
    ],

    'counter' => [
        'label' => 'Entrées validées',
    ],

    'recent' => [
        'title' => 'Derniers passages',
        'empty' => 'Aucun passage pour le moment.',
    ],
];

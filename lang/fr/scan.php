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

    'rotate_key' => [
        'title' => 'Clé des billets',
        'body' => "Version :version. Changez la clé si un téléphone d'agent a été perdu ou si vous pensez qu'elle a fuité.",
        'button' => 'Changer la clé',
        'confirm_title' => 'Changer la clé des billets ?',
        'confirm_body' => "Tous les billets déjà envoyés, téléchargés ou imprimés cesseront d'ouvrir la porte. Chaque invité devra rouvrir son billet pour obtenir le nouveau code, et chaque téléphone de scan devra se reconnecter au réseau.",
        'confirm' => 'Changer la clé',
        'flash' => 'Clé des billets changée. Les anciens codes sont refusés.',
    ],
];

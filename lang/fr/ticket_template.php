<?php

return [
    'title' => 'Gabarit du billet',
    'description' => 'Choisissez l\'allure du billet que reçoivent vos invités et les éléments qui y figurent.',

    'models' => [
        'title' => 'Modèle',
        'classic' => ['label' => 'Classique', 'hint' => 'Titre centré, encadré fin.'],
        'sober' => ['label' => 'Sobre', 'hint' => 'Sans ornement, lecture rapide.'],
        'elegant' => ['label' => 'Élégant', 'hint' => 'Titre en serif, filet doré.'],
    ],

    'elements' => [
        'title' => 'Éléments affichés',
        'logo' => 'Logo',
        'stamp' => 'Cachet',
        'signature' => 'Signature du responsable',
        'companions' => 'Liste des accompagnateurs',
        'missing' => 'Aucun fichier : ajoutez-le dans Espace et marque.',
        'brand_link' => 'Modifier la marque',
    ],

    'preview' => [
        'title' => 'Aperçu',
        'guest' => 'Invité',
        'table' => 'Table',
        'seats' => 'Places',
        'companions' => 'Accompagnateurs',
        'scheduled' => 'Envoi programmé',
        'qr' => 'QR code du billet',
        'valid' => 'Billet valide',
    ],

    'print' => [
        'title' => 'Listes de contrôle par table',
        'description' => 'Imprimez, pour chaque événement, la liste des inscrits confirmés table par table, avec cachet et signature.',
        'button' => 'Imprimer',
        'empty' => 'Aucun événement à imprimer pour le moment.',
    ],

    'flash' => [
        'updated' => 'Gabarit du billet mis à jour.',
    ],
];

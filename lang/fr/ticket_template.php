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
        'price_category' => 'Tarif',
        'seats' => 'Places',
        'companions' => 'Accompagnateurs',
        'scheduled' => 'Envoi programmé',
        'qr' => 'QR code du billet',
        'qr_of' => 'QR code du billet de :name',
        'host' => 'Invité par',
        'holder' => 'Billet à afficher',
        'holder_guest' => 'Invité principal',
        'holder_companion' => 'Accompagnateur',
        'valid' => 'Billet valide',
        'copyright' => '© :year Convive',
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

    // Le gabarit propre a un evenement : active, il l'emporte sur celui de l'organisation.
    'event' => [
        'title' => 'Gabarit du billet de l’événement',
        'description' => 'Donnez à :event un billet différent de celui de votre organisation.',
        'enable' => 'Utiliser un gabarit propre à cet événement',
        'enable_hint' => 'Activé, ce gabarit l’emporte sur celui de l’organisation pour les billets de cet événement. Désactivé, celui de l’organisation s’applique.',
        'organisation_applies' => 'Le gabarit de l’organisation s’applique à cet événement : il s’affiche ici tel quel.',
        'organisation_link' => 'Modifier le gabarit de l’organisation',
        'save_first' => 'Enregistrez pour déposer des fonds propres à cet événement.',
        'background_fallback' => 'Sans image propre à cet événement, celle de l’organisation s’applique.',
        'remove_confirm' => 'Le fond de l’organisation s’appliquera de nouveau à cet événement. Pour remettre celui-ci, il faudra le déposer à nouveau.',
        'global_note' => 'Un événement peut avoir son propre gabarit, depuis son menu « Gabarit du billet ».',
    ],
];

<?php

return [
    'title' => 'Espace et marque',
    'description' => 'Identité légale, marque et adresse publique de votre organisation',

    'tabs' => [
        'legal' => 'Identité légale',
        'brand' => 'Marque',
        'subdomain' => 'Adresse publique',
    ],

    'sections' => [
        'legal' => [
            'title' => 'Identité légale',
            'description' => 'Ces informations figurent sur les reçus, les billets et les exports PDF.',
        ],
        'brand' => [
            'title' => 'Couleurs de marque',
            'description' => "Elles s'appliquent au parcours invité, au billet et aux messages. Le back-office reste neutre.",
        ],
        'subdomain' => [
            'title' => 'Adresse publique',
            'description' => "L'adresse à laquelle vos invités accèdent à vos événements.",
        ],
    ],

    'fields' => [
        'display_name' => 'Nom affiché',
        'display_name_hint' => "Le nom que voient vos invités, s'il diffère de la raison sociale.",
        'legal_name' => 'Raison sociale',
        'legal_form' => 'Forme juridique',
        'representative_name' => 'Responsable signataire',
        'representative_name_hint' => 'Son nom apparaît sous la signature sur les reçus.',
        'registration_number' => 'Numéro RCCM',
        'tax_number' => 'Numéro de contribuable',
        'address' => 'Adresse du siège',
        'city' => 'Ville',
        'country' => 'Pays',
        'email' => 'Email de contact',
        'phone' => 'Téléphone',
        'primary_color' => 'Couleur principale',
        'secondary_color' => 'Couleur secondaire',
        'subdomain' => 'Sous-domaine',
    ],

    'country' => [
        'placeholder' => 'Choisir un pays',
        'search' => 'Rechercher un pays',
        'empty' => 'Aucun pays ne correspond.',
    ],

    'help' => [
        'legal_form' => 'Elle figure sur vos reçus. Elle se choisit dans une liste plutôt que de se saisir, pour que chaque reçu reste conforme.',
        'registration_number' => 'Votre numéro au Registre du commerce et du crédit mobilier. Saisissez-le tel qu’il figure sur vos documents : le format change d’un pays à l’autre. Il apparaît sur les reçus.',
        'tax_number' => 'Votre identifiant fiscal, tel qu’il figure sur vos documents officiels. Il apparaît sur les reçus.',
        'subdomain' => 'Le début de l’adresse de vos liens d’inscription. Il se fige dès votre premier lien publié, pour ne jamais casser les liens déjà envoyés à vos invités.',
    ],

    'files' => [
        'section' => 'Fichiers de marque',
        'section_description' => "Ils apparaissent sur les liens d'invitation, les billets et les reçus. Formats acceptés : JPG, PNG et WebP, 5 Mo au maximum.",
        'replace' => 'Remplacer',
        'choose' => 'Choisir un fichier',
        'remove' => 'Retirer',
        'remove_confirm' => [
            'title' => 'Retirer « :label » ?',
            'description' => 'Ce fichier n’apparaîtra plus sur vos liens d’invitation, vos billets et vos reçus. Pour le remettre, il faudra le déposer à nouveau.',
            'confirm' => 'Retirer le fichier',
        ],
        'crop' => [
            'title' => 'Recadrer : :label',
            'description' => 'Glissez l’image pour la cadrer, zoomez au besoin.',
            'stub_guide' => 'Le cadre a les proportions du haut du billet ; les zones claires marquent l’emplacement du QR et de la table, qui masqueront l’image à cet endroit.',
            'body_guide' => 'Le cadre a les proportions du bas du billet. L’image y apparaît estompée, comme ici, pour que le texte reste lisible.',
            'zoom_in' => 'Zoomer',
            'zoom_out' => 'Dézoomer',
            'confirm' => 'Recadrer et enregistrer',
        ],
        'empty' => 'Aucun fichier',
        'logo' => [
            'label' => 'Logo carré',
            'hint' => "Affiché en tête du lien d'inscription et sur le billet.",
        ],
        'banner' => [
            'label' => 'Bandeau des liens publics',
            'hint' => 'Image large, en haut de la page vue par vos invités.',
        ],
        'ticket_background' => [
            'label' => 'Fond du haut du billet',
            'hint' => 'Image placée derrière le QR, en haut du billet. Elle est recadrée aux proportions du billet ; le QR garde son cadre blanc pour rester lisible au scan.',
        ],
        'ticket_body_background' => [
            'label' => 'Fond du bas du billet',
            'hint' => 'Image placée derrière le texte, sous la ligne perforée. Elle est recadrée aux proportions du billet et estompée pour que le texte reste lisible.',
        ],
        'stamp' => [
            'label' => 'Cachet',
            'hint' => 'Apposé sur les reçus et les exports PDF.',
        ],
        'signature' => [
            'label' => 'Signature du responsable',
            'hint' => 'Apposée sous le nom du responsable signataire.',
        ],
    ],

    'legal_forms' => [
        'association' => 'Association',
        'ngo' => 'ONG',
        'religious_body' => 'Confession religieuse',
        'foundation' => 'Fondation',
        'cooperative' => 'Coopérative',
        'sole_proprietorship' => 'Entreprise individuelle',
        'sarl' => 'SARL',
        'sarlu' => 'SARL unipersonnelle',
        'sa' => 'SA',
        'sas' => 'SAS',
        'sasu' => 'SAS unipersonnelle',
        'gie' => "Groupement d'intérêt économique",
        'public_body' => 'Établissement public',
        'other' => 'Autre',
    ],

    'publishing' => [
        'ready' => 'Cette organisation peut publier un lien public.',
        'incomplete' => '{1} Il reste 1 information à renseigner avant de pouvoir publier un lien public.|[2,*] Il reste :count informations à renseigner avant de pouvoir publier un lien public.',
        'trial' => "Espace d'essai : renseignez votre identité légale et votre adresse publique quand vous serez prêt à publier.",
    ],

    'flash' => [
        'legal_updated' => 'Identité légale enregistrée.',
        'brand_updated' => 'Marque enregistrée.',
        'subdomain_updated' => 'Adresse publique enregistrée.',
        'file_updated' => 'Fichier enregistré.',
        'file_deleted' => 'Fichier retiré.',
    ],

    'errors' => [
        'registration_number' => 'Le numéro RCCM ne peut contenir que des lettres, des chiffres, des tirets et des barres obliques.',
        'tax_number' => 'Le numéro de contribuable ne peut contenir que des lettres, des chiffres et des tirets.',
        'phone' => 'Saisissez un numéro de téléphone valide, indicatif compris.',
        'color' => 'Saisissez une couleur au format hexadécimal, par exemple #7b1e3a.',
        'subdomain_format' => 'Le sous-domaine ne peut contenir que des lettres minuscules, des chiffres et des tirets, sans tiret au début ni à la fin.',
        'subdomain_reserved' => "Ce sous-domaine est réservé par l'application. Choisissez-en un autre.",
        'crop_outside' => 'La zone choisie dépasse de l’image. Recadrez-la, puis réessayez.',
        'crop_ratio' => 'La zone choisie n’a pas les proportions du billet. Recadrez-la, puis réessayez.',
        'file_not_an_image' => "Ce fichier n'est pas une image. Envoyez un JPG, un PNG ou un WebP.",
        'file_type' => 'Formats acceptés : JPG, PNG et WebP. Le SVG est refusé pour des raisons de sécurité.',
        'file_too_large' => 'Le fichier dépasse 5 Mo.',
        'subdomain_frozen' => 'Le sous-domaine ne peut plus changer : un lien public a déjà été distribué aux invités.',
        'subdomain_taken' => 'Ce sous-domaine est déjà utilisé par une autre organisation.',
    ],
];

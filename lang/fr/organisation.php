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

    'files' => [
        'section' => 'Fichiers de marque',
        'section_description' => "Ils apparaissent sur les liens d'invitation, les billets et les reçus. Formats acceptés : JPG, PNG et WebP, 5 Mo au maximum.",
        'replace' => 'Remplacer',
        'choose' => 'Choisir un fichier',
        'remove' => 'Retirer',
        'empty' => 'Aucun fichier',
        'logo' => [
            'label' => 'Logo carré',
            'hint' => "Affiché en tête du lien d'inscription et sur le billet.",
        ],
        'banner' => [
            'label' => 'Bandeau des liens publics',
            'hint' => 'Image large, en haut de la page vue par vos invités.',
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
        'file_not_an_image' => "Ce fichier n'est pas une image. Envoyez un JPG, un PNG ou un WebP.",
        'file_type' => 'Formats acceptés : JPG, PNG et WebP. Le SVG est refusé pour des raisons de sécurité.',
        'file_too_large' => 'Le fichier dépasse 5 Mo.',
        'subdomain_frozen' => 'Le sous-domaine ne peut plus changer : un lien public a déjà été distribué aux invités.',
        'subdomain_taken' => 'Ce sous-domaine est déjà utilisé par une autre organisation.',
    ],
];

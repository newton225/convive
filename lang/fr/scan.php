<?php

return [
    'title' => "Contrôle à l'entrée",

    'viewfinder' => [
        'placeholder' => 'Visez le QR du billet',
        'scanning' => 'Recherche du code...',
        'camera_denied' => "La caméra n'a pas pu être activée. Vérifiez les autorisations du navigateur.",
        'phases' => [
            'starting' => 'Démarrage de la caméra...',
            'searching' => 'Recherche d\'un QR code',
            'checking' => 'QR code détecté, vérification...',
            'paused' => 'Scan en pause',
            'error' => 'Caméra indisponible',
        ],
    ],

    'results' => [
        'accepted' => 'Entrée validée',
        'already_scanned' => 'Billet déjà scanné',
        'refused' => 'Billet refusé',
    ],

    'entry_control' => [
        'title' => "Contrôle à l'entrée",
        'description' => "Choisissez l'événement dont vous contrôlez l'entrée.",
        'empty_title' => "Aucun événement aujourd'hui",
        'empty_description' => "Le contrôle s'ouvre le jour d'un événement publié. Pour un autre jour, passez par la liste des événements.",
        'open' => 'Ouvrir le scan',
        'all_events' => 'Voir tous les événements',
        'others_today' => "Autres contrôles aujourd'hui : :events",
        'change' => "Changer d'événement",
    ],
    'result' => [
        'table' => 'Table :number',
        'no_table' => 'Non placée',
        'guest_of' => 'Accompagnateur de :name',
        'first_scanned_at' => 'Premier passage : :time',
        'first_scanned_by' => 'Par :name',
        'force' => "Forcer l'entrée",
        'forced_badge' => 'Entrée forcée',
        'refused_help' => "Signature inconnue ou paiement non confirmé. Orientez l'invité vers l'accueil.",
        'other_event' => "Billet valide, mais pour un autre événement : « :name », :place. Indiquez à l'invité où il est attendu.",
        'next' => 'Scanner suivant',
    ],

    'counter' => [
        'label' => "billets déjà scannés à l'entrée",
        'value' => ':entered sur :expected',
    ],

    'station' => [
        'label' => 'Poste de contrôle',
        'placeholder' => 'Par exemple : Entrée principale',
    ],

    'recent' => [
        'title' => 'Derniers passages',
        'empty' => 'Aucun passage pour le moment.',
    ],

    'pin_setup' => [
        'title' => 'Choisissez votre code de scan',
        'description' => "Avant de scanner, choisissez un code à 4 chiffres. Il déverrouillera cet écran après 5 minutes sans activité, même sans réseau. Les billets ne sont pas lus tant qu'il n'est pas choisi.",
    ],

    'lock' => [
        'title' => 'Écran verrouillé',
        'description' => 'Saisissez votre code de scan pour reprendre.',
        'pin_label' => 'Code de scan',
        'checking' => 'Vérification…',
        'wrong' => '{1} Code incorrect. Encore 1 essai avant la déconnexion.|[2,*] Code incorrect. Encore :count essais avant la déconnexion.',
        'exhausted' => 'Trop de codes incorrects : reconnectez-vous avec votre email et votre mot de passe.',
        'exhausted_offline' => 'Trop de codes incorrects. Reconnectez-vous avec votre email et votre mot de passe dès le retour du réseau.',
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

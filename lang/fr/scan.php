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
        'refused_help' => "Signature inconnue ou paiement non confirmé. Orientez l'invité vers l'accueil.",
        'next' => 'Scanner suivant',
    ],

    'counter' => [
        'label' => 'Entrées validées sur les inscriptions attendues',
        'value' => ':entered / :expected',
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

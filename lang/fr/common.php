<?php

return [
    'unsaved_changes' => 'Modifications non enregistrées',

    'actions' => [
        'save' => 'Enregistrer',
        'cancel' => 'Annuler',
        'confirm' => 'Confirmer',
        'close' => 'Fermer',
        'delete' => 'Supprimer',
        'edit' => 'Modifier',
        'view' => 'Voir',
        'copy' => 'Copier',
        'copied' => 'Copié',
        'send' => 'Envoyer',
        'accept' => 'Accepter',
        'decline' => 'Refuser',
        'continue' => 'Continuer',
        'back' => 'Retour',
    ],

    'states' => [
        'loading' => 'Chargement en cours',
        'saving' => 'Enregistrement en cours',
        'empty' => 'Rien à afficher pour le moment',
        'offline' => 'Vous êtes hors ligne',
    ],

    'language' => [
        'label' => 'Langue',
        'switch' => 'Changer de langue',
    ],

    'pagination' => [
        'page_of' => 'Page :current sur :last',
        'previous' => 'Précédent',
        'next' => 'Suivant',
    ],

    'sort' => [
        'label' => ':column, :state. Cliquer pour :action.',
        'state' => [
            'none' => 'non trié',
            'asc' => 'trié par ordre croissant',
            'desc' => 'trié par ordre décroissant',
        ],
        'action' => [
            'none' => 'retirer le tri',
            'asc' => 'trier par ordre croissant',
            'desc' => 'trier par ordre décroissant',
        ],
    ],

    'help' => [
        'about' => 'Aide : :subject',
    ],

    'sample' => [
        'title' => "Données d'exemple",
        'description' => "Ces chiffres sont fictifs : l'affichage est prêt, le serveur de cet écran arrive dans une prochaine étape.",
    ],

    'install' => [
        'title' => "Installer l'application",
        'description' => "Ajoutez Convive à votre écran d'accueil pour l'ouvrir comme une application, même avec un réseau faible.",
        'action' => 'Installer',
        'dismiss' => 'Plus tard',
    ],

    'feedback' => [
        'forbidden' => "Vous n'avez pas la permission de faire cette action. Demandez-la au Propriétaire de l'organisation.",
        'not_found' => "Cet élément n'existe plus. Il a peut-être été supprimé entre-temps : rechargez la page.",
        'session_expired' => 'Votre session a expiré. Rechargez la page, puis recommencez.',
        'server_error' => "Une erreur est survenue de notre côté. Réessayez dans un instant ; si elle persiste, prévenez l'équipe Convive.",
        'unexpected' => 'Cette action n\'a pas abouti. Réessayez dans un instant.',
        'network_error' => 'Le réseau ne répond pas. Vérifiez votre connexion, puis réessayez.',
    ],

    'errors' => [
        'image_too_large' => 'Cette image est trop grande (plus de 16 mégapixels). Envoyez plutôt une capture d’écran, ou une photo prise en qualité normale.',
    ],

    'rate_limit' => [
        'title' => 'Un peu de patience',
        'retry_in' => '{1} Cette action a été répétée trop souvent en peu de temps. Réessayez dans 1 minute.|[2,*] Cette action a été répétée trop souvent en peu de temps. Réessayez dans :minutes minutes.',
        'back' => "Revenir à l'accueil",
    ],

    'pdf' => [
        'watermark' => 'Exporté par :name le :date',
    ],
];

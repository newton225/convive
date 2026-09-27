<?php

return [
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

    'rate_limit' => [
        'title' => 'Un peu de patience',
        'retry_in' => '{1} Cette action a été répétée trop souvent en peu de temps. Réessayez dans 1 minute.|[2,*] Cette action a été répétée trop souvent en peu de temps. Réessayez dans :minutes minutes.',
        'back' => "Revenir à l'accueil",
    ],

    'pdf' => [
        'watermark' => 'Exporté par :name le :date',
    ],
];

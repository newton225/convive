<?php

return [
    'banner' => [
        'title' => 'Vous êtes hors ligne',
        'body' => 'Certaines actions attendent le retour du réseau.',
    ],

    'scan' => [
        'queued' => '{0} Aucun scan en attente|{1} 1 scan en attente de synchronisation|[2,*] :count scans en attente de synchronisation',
        'sync_now' => 'Synchroniser',
        'syncing' => 'Synchronisation en cours',
        'sync_done' => 'Synchronisé.',
        'unsupported' => 'Ce téléphone ne sait pas vérifier un billet hors ligne. Revenez dans une zone couverte.',
        'no_key' => 'La clé de vérification n\'est pas encore disponible pour cet événement.',
        'verified_offline' => 'Valide, vérifié hors ligne',
        'verified_offline_help' => 'Le passage sera confirmé au retour du réseau.',
        'already_local' => 'Déjà scanné sur cet appareil',
        'forged' => 'Billet invalide',
        'wrong_event' => 'Billet d\'un autre événement',
        'outdated' => 'Billet émis avec une ancienne clé : l\'invité doit rouvrir son billet pour obtenir le nouveau code.',
        'expired' => 'Billet expiré',
        'too_early' => 'Les portes ne sont pas encore ouvertes pour ce billet',
        'revoked' => 'Billet annulé',
        'review_title' => 'À revoir après synchronisation',
        'review_item' => 'Un billet accepté hors ligne a été refusé ou déjà utilisé par le serveur.',
    ],
];

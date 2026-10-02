<?php

return [
    'personal_name' => 'Organisation de :name',

    'flash' => [
        'switched' => 'Vous êtes maintenant dans « :name ».',
        'created' => 'Organisation créée.',
        'updated' => 'Organisation mise à jour.',
        'deleted' => 'Organisation supprimée. Ses données seront effacées dans 30 jours.',
        'left' => 'Vous avez quitté l\'organisation ":name".',
        'invitation_sent' => 'Invitation envoyée.',
        'invitation_cancelled' => 'Invitation annulée.',
        'invitation_accepted' => 'Invitation acceptée.',
        'invitation_declined' => 'Invitation refusée.',
        'member_role_updated' => 'Rôle du membre mis à jour.',
        'member_removed' => 'Membre retiré.',
    ],

    'errors' => [
        'owner_cannot_be_removed' => 'Le propriétaire de l\'organisation ne peut pas être retiré.',
        'name_mismatch' => 'Le nom de l\'organisation ne correspond pas.',
        'name_reserved' => 'Ce nom d\'organisation est réservé et ne peut pas être utilisé.',
        'already_member' => 'Cet utilisateur est déjà membre de l\'organisation.',
        'invitation_already_sent' => 'Une invitation a déjà été envoyée à cette adresse email.',
        'invitation_wrong_email' => 'Cette invitation a été envoyée à une autre adresse email.',
        'invitation_already_accepted' => 'Cette invitation a déjà été acceptée.',
        'invitation_expired' => 'Cette invitation a expiré.',
    ],

    'invitation_mail' => [
        'subject' => 'Vous êtes invité à rejoindre :tenant',
        'intro' => ':inviter vous invite à rejoindre l\'organisation :tenant.',
        'instruction' => 'Connectez-vous et ouvrez votre tableau de bord pour accepter ou refuser cette invitation.',
        'action' => 'Se connecter',
    ],

    'index' => [
        'title' => 'Organisations',
        'description' => 'Gérez vos organisations et vos appartenances',
        'empty' => 'Vous n\'appartenez encore à aucune organisation.',
    ],

    'badge' => [
        'personal' => 'Personnelle',
    ],

    'actions' => [
        'create' => 'Nouvelle organisation',
        'leave' => 'Quitter l\'organisation',
        'view' => 'Voir l\'organisation',
        'edit' => 'Modifier l\'organisation',
        'delete' => 'Supprimer l\'organisation',
    ],

    'edit' => [
        'title' => 'Modifier :name',
        'read_only_title' => 'Consulter :name',
    ],

    'settings' => [
        'title' => 'Réglages de l\'organisation',
        'description' => 'Modifiez le nom et les réglages de votre organisation',
        'name_label' => 'Nom de l\'organisation',
        'name_placeholder' => 'Mon organisation',
    ],

    'members' => [
        'title' => 'Membres de l\'organisation',
        'description' => 'Gérez qui appartient à cette organisation',
        'invite' => 'Inviter un membre',
        'remove' => 'Retirer le membre',
    ],

    'invitations' => [
        'title' => 'Invitations en attente',
        'description' => 'Invitations qui n\'ont pas encore été acceptées',
        'cancel' => 'Annuler l\'invitation',
    ],

    'delete' => [
        'title' => 'Supprimer l\'organisation',
        'description' => 'Supprimer définitivement votre organisation',
        'warning_title' => 'Attention',
        'warning_body' => 'L’organisation disparaît tout de suite pour tous ses membres, et ses invitations sont annulées. Ses données sont effacées définitivement dans 30 jours ; d’ici là, l’équipe Convive peut la restaurer à votre demande.',
    ],

    'mail' => [
        'deletion_scheduled' => [
            'subject' => ':organisation a été supprimée',
            'intro' => ':deleted_by a supprimé l’organisation :organisation. Elle n’est plus accessible à ses membres.',
            'erase_at' => 'Ses données seront effacées définitivement le :date.',
            'restore' => 'D’ici là, l’équipe Convive peut la restaurer, avec ses membres et ses événements : écrivez-lui avant cette date.',
            'restore_with_address' => 'D’ici là, l’équipe Convive peut la restaurer, avec ses membres et ses événements : écrivez à :email avant cette date.',
        ],
    ],

    'confirm' => [
        'name' => 'Nom',
        'email' => 'Adresse email',
        'profile' => 'Profil',
        'sent_at' => 'Envoyée le',
    ],

    'modals' => [
        'create' => [
            'title' => 'Créer une organisation',
            'description' => 'Créez une organisation pour gérer vos événements avec votre équipe.',
            'submit' => 'Créer l\'organisation',
        ],
        'delete' => [
            'title' => 'Confirmer la suppression',
            'description' => 'Cette action est définitive. Elle supprime l\'organisation ":name", ses membres et ses invitations.',
            'confirmation_label' => 'Saisissez ":name" pour confirmer',
        ],
        'leave' => [
            'title' => 'Quitter l\'organisation',
            'description' => 'Vous perdrez l\'accès à :name et à ses données.',
        ],
        'invite' => [
            'title' => 'Inviter un membre',
            'description' => 'Envoyez une invitation à rejoindre cette organisation.',
            'email_label' => 'Adresse email',
            'email_placeholder' => 'collegue@exemple.com',
            'role_label' => 'Rôle',
            'role_placeholder' => 'Choisir un rôle',
            'submit' => 'Envoyer l\'invitation',
        ],
        'remove_member' => [
            'title' => 'Retirer le membre',
            'description' => ':name perdra l\'accès à cette organisation et à ses données.',
        ],
        'cancel_invitation' => [
            'title' => 'Annuler l\'invitation',
            'description' => 'L\'invitation envoyée à :email sera supprimée et le lien deviendra inutilisable.',
            'keep' => 'Conserver l\'invitation',
        ],
        'pending' => [
            'title' => 'Invitations en attente',
            'description' => 'Acceptez ou refusez les organisations qui vous ont invité.',
            'invited_by' => ':inviter vous invite à rejoindre cette organisation.',
        ],
    ],

    'switcher' => [
        'label' => 'Organisations',
        'placeholder' => 'Choisir une organisation',
    ],

    'invitation_alert' => [
        'login' => 'Connectez-vous pour rejoindre l\'organisation ":name".',
        'register' => 'Créez votre compte pour rejoindre l\'organisation ":name".',
    ],
];

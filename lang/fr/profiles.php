<?php

return [
    'title' => 'Profils',
    'description' => 'Composez vos profils avec le catalogue de permissions de l\'application',
    'duplicate_name' => ':name (copie)',

    'starters' => [
        'owner' => 'Accès complet à l\'espace. Profil système, non modifiable.',
        'treasurer' => 'Vérifie les preuves de paiement, exporte et rapproche les relevés.',
        'host' => 'Contrôle les billets à l\'entrée le jour de l\'événement.',
        'reader' => 'Consulte les inscrits et les rapports, sans rien modifier.',
    ],

    'matrix' => [
        'title' => 'Comparer les profils',
        'description' => 'Ce que chaque profil peut faire, module par module. Pour modifier un profil, ouvrez-le.',
        'permission' => 'Module et action',
        'yes' => 'Autorisé',
        'no' => 'Non autorisé',
    ],

    'form' => [
        'create_title' => 'Nouveau profil',
        'edit_title' => 'Modifier le profil :name',
        'intro' => "Donnez un nom au profil, puis cochez pour chaque module ce que ses porteurs peuvent faire. Une action grisée est une permission que vous ne détenez pas vous-même : vous ne pouvez pas l'accorder.",
        'modules' => 'Modules et actions',
        'all' => 'Tout',
        'members_notice' => '{1} Ce profil est porté par 1 membre : la modification prend effet immédiatement pour lui.|[2,*] Ce profil est porté par :count membres : la modification prend effet immédiatement pour eux.',
        'back' => 'Retour aux profils',
    ],

    'fields' => [
        'name' => 'Nom du profil',
        'name_placeholder' => 'Par exemple : Accueil',
        'description' => 'Description',
        'description_placeholder' => 'À quoi sert ce profil',
        'permissions' => 'Permissions',
        'requires_two_factor' => "Exiger l'authentification à deux facteurs",
        'requires_two_factor_hint' => "Les porteurs de ce profil devront activer la double authentification pour accéder à l'organisation.",
    ],

    'actions' => [
        'create' => 'Nouveau profil',
        'duplicate' => 'Dupliquer',
        'edit' => 'Modifier le profil',
        'delete' => 'Supprimer le profil',
    ],

    'badges' => [
        'system' => 'Profil système',
        'two_factor' => 'Double authentification exigée',
        'members' => '{0} Aucun membre|{1} 1 membre|[2,*] :count membres',
        'permissions' => '{0} Aucune permission|{1} 1 permission|[2,*] :count permissions',
    ],

    'flash' => [
        'created' => 'Profil créé.',
        'updated' => 'Profil mis à jour.',
        'duplicated' => 'Profil dupliqué.',
        'deleted' => 'Profil supprimé.',
    ],

    'errors' => [
        'permission_not_held' => 'Vous ne pouvez pas accorder une permission que vous ne détenez pas vous-même.',
        'profile_stronger_than_actor' => 'Ce profil détient des permissions que vous ne détenez pas vous-même.',
        'still_assigned' => 'Ce profil est porté par :count membre(s). Réaffectez-les avant de le supprimer.',
        'system_profile' => 'Le profil Propriétaire est un profil système : il ne peut être ni modifié ni supprimé.',
        'own_profile' => 'Vous ne pouvez pas modifier le profil que vous portez.',
        'last_owner' => 'L\'organisation doit conserver au moins un Propriétaire actif.',
    ],

    'confirm' => [
        'name' => 'Profil',
        'description' => 'Description',
        'permissions' => 'Permissions',
        'permissions_count' => '{0} Aucune permission|{1} 1 permission|[2,*] :count permissions',
        'members' => 'Membres',
    ],

    'confirm_delete' => [
        'title' => 'Supprimer le profil',
        'description' => 'Le profil ":name" sera supprimé. Cette action est définitive.',
        'members' => '{0} Aucun membre ne porte ce profil.|{1} 1 membre porte ce profil : réaffectez-le avant de supprimer.|[2,*] :count membres portent ce profil : réaffectez-les avant de supprimer.',
    ],
];

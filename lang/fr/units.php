<?php

return [
    'title' => 'Unités',
    'description' => 'Les unités proposées à vos invités. Le participant et chacun de ses accompagnateurs en choisissent une, obligatoirement.',

    'fields' => [
        'name' => "Nom de l'unité",
        'name_placeholder' => 'Par exemple : BETHEL',
        'position' => 'Ordre',
        'is_active' => 'Proposée aux invités',
    ],

    'actions' => [
        'create' => 'Ajouter une unité',
        'edit' => "Modifier l'unité",
        'delete' => "Supprimer l'unité",
    ],

    'badges' => [
        'inactive' => 'Non proposée',
        'none' => 'Choix « aucune »',
    ],

    'flash' => [
        'created' => 'Unité ajoutée.',
        'updated' => 'Unité mise à jour.',
        'deleted' => 'Unité supprimée.',
    ],

    'errors' => [
        'in_use' => 'Cette unité a déjà été choisie par des invités : elle ne peut pas être supprimée. Désactivez-la pour la retirer du formulaire d’inscription.',
        'last_one' => "Une organisation conserve au moins une unité : sans elle, le formulaire d'inscription ne peut plus être rempli.",
    ],

    'confirm_delete' => [
        'title' => "Supprimer l'unité",
        'description' => "L'unité \":name\" sera supprimée. Cette action est définitive.",
    ],
];

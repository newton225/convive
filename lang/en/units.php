<?php

return [
    'title' => 'Units',
    'description' => 'The units offered to your guests. The participant and each companion must choose one.',

    'fields' => [
        'name' => 'Unit name',
        'name_placeholder' => 'For example: BETHEL',
        'position' => 'Order',
        'is_active' => 'Offered to guests',
    ],

    'actions' => [
        'create' => 'Add a unit',
        'edit' => 'Edit unit',
        'delete' => 'Delete unit',
    ],

    'badges' => [
        'inactive' => 'Not offered',
        'none' => '"None" choice',
    ],

    'none_locked' => 'Always offered last, for people who belong to no unit. It cannot be renamed, deactivated or deleted.',

    'flash' => [
        'created' => 'Unit added.',
        'updated' => 'Unit updated.',
        'deleted' => 'Unit deleted.',
    ],

    'errors' => [
        'in_use' => 'Guests have already chosen this unit: it cannot be deleted. Deactivate it to remove it from the registration form.',
        'last_one' => 'An organisation keeps at least one unit: without it, the registration form can no longer be filled in.',
    ],

    'confirm_delete' => [
        'title' => 'Delete unit',
        'description' => 'The ":name" unit will be deleted. This cannot be undone.',
    ],
];

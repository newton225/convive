<?php

return [
    'title' => 'Profiles',
    'description' => 'Build your profiles from the application permission catalogue',
    'duplicate_name' => ':name (copy)',

    'starters' => [
        'owner' => 'Full access to the workspace. System profile, not editable.',
        'treasurer' => 'Checks payment proofs, exports and reconciles statements.',
        'host' => 'Checks tickets at the door on the day of the event.',
        'reader' => 'Reads registrations and reports, changes nothing.',
    ],

    'form' => [
        'create_title' => 'New profile',
        'edit_title' => 'Edit the :name profile',
        'intro' => 'Name the profile, then tick for each module what its holders may do. A greyed-out action is a permission you do not hold yourself: you cannot grant it.',
        'modules' => 'Modules and actions',
        'all' => 'All',
        'members_notice' => '{1} This profile is held by 1 member: the change applies to them immediately.|[2,*] This profile is held by :count members: the change applies to them immediately.',
        'back' => 'Back to profiles',
    ],

    'fields' => [
        'name' => 'Profile name',
        'name_placeholder' => 'For example: Front desk',
        'description' => 'Description',
        'description_placeholder' => 'What this profile is for',
        'permissions' => 'Permissions',
        'requires_two_factor' => 'Require two-factor authentication',
        'requires_two_factor_hint' => 'Carriers of this profile will have to turn on two-factor authentication to reach the organisation.',
    ],

    'actions' => [
        'create' => 'New profile',
        'duplicate' => 'Duplicate',
        'edit' => 'Edit profile',
        'delete' => 'Delete profile',
    ],

    'badges' => [
        'system' => 'System profile',
        'two_factor' => 'Two-factor required',
        'members' => '{0} No member|{1} 1 member|[2,*] :count members',
        'permissions' => '{0} No permission|{1} 1 permission|[2,*] :count permissions',
    ],

    'flash' => [
        'created' => 'Profile created.',
        'updated' => 'Profile updated.',
        'duplicated' => 'Profile duplicated.',
        'deleted' => 'Profile deleted.',
    ],

    'errors' => [
        'permission_not_held' => 'You cannot grant a permission you do not hold yourself.',
        'profile_stronger_than_actor' => 'This profile holds permissions you do not hold yourself.',
        'still_assigned' => 'This profile is carried by :count member(s). Reassign them before deleting it.',
        'system_profile' => 'The Owner profile is a system profile: it can be neither edited nor deleted.',
        'own_profile' => 'You cannot edit the profile you carry yourself.',
        'last_owner' => 'The organisation must keep at least one active Owner.',
    ],

    'confirm_delete' => [
        'title' => 'Delete profile',
        'description' => 'The ":name" profile will be deleted. This cannot be undone.',
        'members' => '{0} No member carries this profile.|{1} 1 member carries this profile: reassign them before deleting.|[2,*] :count members carry this profile: reassign them before deleting.',
    ],
];

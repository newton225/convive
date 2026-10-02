<?php

return [
    'personal_name' => ':name\'s organisation',

    'flash' => [
        'switched' => 'You are now in ":name".',
        'created' => 'Organisation created.',
        'updated' => 'Organisation updated.',
        'deleted' => 'Organisation deleted.',
        'left' => 'You have left the ":name" organisation.',
        'invitation_sent' => 'Invitation sent.',
        'invitation_cancelled' => 'Invitation cancelled.',
        'invitation_accepted' => 'Invitation accepted.',
        'invitation_declined' => 'Invitation declined.',
        'member_role_updated' => 'Member role updated.',
        'member_removed' => 'Member removed.',
    ],

    'errors' => [
        'owner_cannot_be_removed' => 'The organisation owner cannot be removed.',
        'name_mismatch' => 'The organisation name does not match.',
        'name_reserved' => 'This organisation name is reserved and cannot be used.',
        'already_member' => 'This user is already a member of the organisation.',
        'invitation_already_sent' => 'An invitation has already been sent to this email address.',
        'invitation_wrong_email' => 'This invitation was sent to a different email address.',
        'invitation_already_accepted' => 'This invitation has already been accepted.',
        'invitation_expired' => 'This invitation has expired.',
    ],

    'invitation_mail' => [
        'subject' => 'You have been invited to join :tenant',
        'intro' => ':inviter has invited you to join the :tenant organisation.',
        'instruction' => 'Log in and open your dashboard to accept or decline this invitation.',
        'action' => 'Log in',
    ],

    'index' => [
        'title' => 'Organisations',
        'description' => 'Manage your organisations and memberships',
        'empty' => 'You do not belong to any organisation yet.',
    ],

    'badge' => [
        'personal' => 'Personal',
    ],

    'actions' => [
        'create' => 'New organisation',
        'leave' => 'Leave organisation',
        'view' => 'View organisation',
        'edit' => 'Edit organisation',
        'delete' => 'Delete organisation',
    ],

    'edit' => [
        'title' => 'Edit :name',
        'read_only_title' => 'View :name',
    ],

    'settings' => [
        'title' => 'Organisation settings',
        'description' => 'Update your organisation name and settings',
        'name_label' => 'Organisation name',
        'name_placeholder' => 'My organisation',
    ],

    'members' => [
        'title' => 'Organisation members',
        'description' => 'Manage who belongs to this organisation',
        'invite' => 'Invite a member',
        'remove' => 'Remove member',
    ],

    'invitations' => [
        'title' => 'Pending invitations',
        'description' => 'Invitations that have not been accepted yet',
        'cancel' => 'Cancel invitation',
    ],

    'delete' => [
        'title' => 'Delete organisation',
        'description' => 'Permanently delete your organisation',
        'warning_title' => 'Warning',
        'warning_body' => 'The organisation disappears right away for all its members, and its invitations are cancelled. Its data is erased for good in 30 days; until then, the Convive team can restore it at your request.',
    ],

    'mail' => [
        'deletion_scheduled' => [
            'subject' => ':organisation was deleted',
            'intro' => ':deleted_by deleted the organisation :organisation. Its members can no longer reach it.',
            'erase_at' => 'Its data will be erased for good on :date.',
            'restore' => 'Until then, the Convive team can restore it, with its members and its events: write to them before that date.',
            'restore_with_address' => 'Until then, the Convive team can restore it, with its members and its events: write to :email before that date.',
        ],
    ],

    'confirm' => [
        'name' => 'Name',
        'email' => 'Email address',
        'profile' => 'Profile',
        'sent_at' => 'Sent on',
    ],

    'modals' => [
        'create' => [
            'title' => 'Create an organisation',
            'description' => 'Create an organisation to run your events with your team.',
            'submit' => 'Create organisation',
        ],
        'delete' => [
            'title' => 'Confirm deletion',
            'description' => 'This cannot be undone. It deletes the ":name" organisation, its members and its invitations.',
            'confirmation_label' => 'Type ":name" to confirm',
        ],
        'leave' => [
            'title' => 'Leave organisation',
            'description' => 'You will lose access to :name and its data.',
        ],
        'invite' => [
            'title' => 'Invite a member',
            'description' => 'Send an invitation to join this organisation.',
            'email_label' => 'Email address',
            'email_placeholder' => 'colleague@example.com',
            'role_label' => 'Role',
            'role_placeholder' => 'Choose a role',
            'submit' => 'Send invitation',
        ],
        'remove_member' => [
            'title' => 'Remove member',
            'description' => ':name will lose access to this organisation and its data.',
        ],
        'cancel_invitation' => [
            'title' => 'Cancel invitation',
            'description' => 'The invitation sent to :email will be deleted and the link will stop working.',
            'keep' => 'Keep invitation',
        ],
        'pending' => [
            'title' => 'Pending invitations',
            'description' => 'Accept or decline the organisations that invited you.',
            'invited_by' => ':inviter has invited you to join this organisation.',
        ],
    ],

    'switcher' => [
        'label' => 'Organisations',
        'placeholder' => 'Choose an organisation',
    ],

    'invitation_alert' => [
        'login' => 'Log in to join the ":name" organisation.',
        'register' => 'Create your account to join the ":name" organisation.',
    ],
];

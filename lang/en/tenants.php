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
        'creation_failed' => 'The organisation could not be created. Please try again in a few minutes: our team has been notified.',
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
        'instruction' => 'Log in with the account of :email to accept or decline this invitation.',
        'action' => 'Log in',
        'instruction_register' => 'Create your Convive account with :email, then accept the invitation.',
        'action_register' => 'Create my account',
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
        'warning_body' => 'The organisation disappears right away for all its members, and its invitations are cancelled. Its data is erased for good in 30 days; until then, the Convive team can restore it at your request. Copies may remain for up to one year in our backups, which are only used after a failure.',
    ],

    'mail' => [
        'deletion_scheduled' => [
            'subject' => ':organisation was deleted',
            'intro' => ':deleted_by deleted the organisation :organisation. Its members can no longer reach it.',
            'erase_at' => 'Its data will be erased for good on :date. Copies may remain for up to one year in our backups, which are only used after a failure.',
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

    'invitations_home' => [
        'head' => 'Invitations',
        'title' => 'Your invitations',
        'description' => 'Join an organisation by accepting its invitation.',
        'item' => ':inviter invites you to join :tenant with the :profile profile.',
        'item_without_inviter' => 'You are invited to join :tenant with the :profile profile.',
        'accept' => 'Accept',
        'decline' => 'Decline',
        'confirm_decline' => [
            'title' => 'Decline this invitation?',
            'description' => 'The invitation from :tenant will be deleted. To join this organisation later, it will have to invite you again.',
        ],
        'empty' => 'No pending invitation for your address.',
        'no_organisation' => 'You do not belong to any organisation yet. You can create your own at any time.',
        'create_organisation' => 'Create my organisation',
        'to_dashboard' => 'Go to my dashboard',
        'mismatch' => [
            'title' => 'This invitation is addressed to another address',
            'body' => 'The invitation from :tenant was sent to :invited, but you are logged in as :account. It cannot be attached to this account.',
            'switch' => 'Log in as :invited',
            'no_account' => 'No account at :invited? Ask the organiser to cancel the invitation and send it again to :account.',
            'dismiss' => 'Ignore this invitation',
        ],
    ],
    'invitation_alert' => [
        'login' => 'Log in to join the ":name" organisation.',
        'register' => 'Create your account to join the ":name" organisation.',
    ],
];

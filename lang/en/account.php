<?php

return [
    'fields' => [
        'name' => 'Full name',
        'organisation_name' => 'Organisation name',
        'email' => 'Email address',
        'phone' => 'Phone',
        'password' => 'Password',
        'password_confirmation' => 'Confirm password',
        'current_password' => 'Current password',
        'new_password' => 'New password',
        'remember' => 'Remember me',
    ],

    'placeholders' => [
        'name' => 'Full name',
        'organisation_name' => 'For example: Soldiers of the Palace Association',
        'email' => 'email@example.com',
        'phone' => '+225 07 00 00 00 00',
        'password' => 'Password',
        'password_confirmation' => 'Confirm password',
        'current_password' => 'Current password',
        'new_password' => 'New password',
    ],

    'login' => [
        'head' => 'Log in',
        'title' => 'Log in to your account',
        'description' => 'Enter your email address and password',
        'forgot' => 'Forgot password?',
        'submit' => 'Log in',
        'no_account' => 'Don\'t have an account?',
        'sign_up' => 'Sign up',
    ],

    'register' => [
        'head' => 'Create an account',
        'title' => 'Create an account',
        'description' => 'Enter your details below to create your account',
        'submit' => 'Create account',
        'have_account' => 'Already have an account?',
        'sign_in' => 'Log in',
    ],

    'forgot_password' => [
        'head' => 'Forgot password',
        'title' => 'Forgot password',
        'description' => 'Enter your email address to receive a reset link',
        'submit' => 'Email password reset link',
        'return_to' => 'Or, return to',
        'login_link' => 'log in',
    ],

    'reset_password' => [
        'head' => 'Reset password',
        'title' => 'Reset password',
        'description' => 'Enter your new password below',
        'submit' => 'Reset password',
    ],

    'confirm_password' => [
        'head' => 'Confirm password',
        'title' => 'Confirm password',
        'description' => 'This is a secure area: confirm your password before continuing.',
        'submit' => 'Confirm password',
        'passkey' => 'Confirm with passkey',
    ],

    'verify_email' => [
        'head' => 'Email verification',
        'title' => 'Email verification',
        'description' => 'Verify your email address by clicking the link we just emailed to you.',
        'sent' => 'A new verification link has been sent to the email address you provided during registration.',
        'resend' => 'Resend verification email',
        'logout' => 'Log out',
    ],

    'two_factor' => [
        'head' => 'Two-factor authentication',
        'code_title' => 'Authentication code',
        'code_description' => 'Enter the code shown by your authenticator application.',
        'recovery_title' => 'Recovery code',
        'recovery_description' => 'Enter one of the recovery codes you saved when setting this up.',
        'recovery_placeholder' => 'Enter recovery code',
        'submit' => 'Continue',
        'or_you_can' => 'or you can',
        'use_recovery' => 'use a recovery code',
        'use_code' => 'use an authentication code',
        'required_by_profile' => 'Your profile in this organisation requires two-factor authentication. Turn it on to regain access.',
        'invalid_code' => 'This code is invalid or has expired.',
    ],

    'two_factor_reconfirm' => [
        'head' => 'Confirm with your two-factor code',
        'title' => 'Confirm your identity',
        'description' => 'This action touches a payment account: re-enter the code from your authenticator app to continue.',
        'submit' => 'Confirm',
    ],

    'settings_description' => 'Manage your profile and account settings',

    'profile' => [
        'email_unverified' => 'Your email address is unverified.',
        'resend_link' => 'Click here to re-send the verification email.',
        'phone_help' => 'Optional: only used to alert you over WhatsApp when a payment account changes.',
        'head' => 'Profile settings',
        'title' => 'Profile',
        'description' => 'Update your name and email address',
    ],

    'security' => [
        'head' => 'Security settings',
        'title' => 'Update password',
        'description' => 'Use a long, unique password to keep your account secure',
    ],

    'scan_pin' => [
        'title' => 'Scan code',
        'description' => 'Four digits to unlock the entrance check screen after 5 minutes of inactivity. Only you know it.',
        'pin' => '4-digit code',
        'confirmation' => 'Enter it a second time',
        'create' => 'Choose my code',
        'change' => 'Change my code',
        'flash' => 'Scan code saved.',
    ],

    'devices' => [
        'title' => 'Connected devices',
        'description' => "The browsers where your account is signed in. If you don't recognise one, sign it out and change your password.",
        'current' => 'This device',
        'unknown_browser' => 'Unknown browser',
        'unknown_platform' => 'unknown system',
        'last_active' => 'Last active: :time',
        'empty' => 'The device list is not available with this session storage.',
        'trigger' => 'Sign out other devices',
        'confirm_title' => 'Sign out other devices?',
        'confirm_description' => 'Every session open elsewhere will be closed. Enter your password to confirm.',
        'password_label' => 'Password',
        'confirm' => 'Sign out',
        'flash' => 'Other devices have been signed out.',
    ],

    'appearance_modes' => [
        'light' => 'Light',
        'dark' => 'Dark',
        'system' => 'System',
    ],

    'delete_account' => [
        'warning_title' => 'Warning',
        'warning_body' => 'Please proceed with caution, this cannot be undone.',
        'trigger' => 'Delete account',
        'confirm_title' => 'Are you sure you want to delete your account?',
        'confirm_description' => 'Once your account is deleted, all of its resources and data will also be permanently deleted. Please enter your password to confirm.',
        'password_label' => 'Password',
        'confirm' => 'Delete account',
        'title' => 'Delete account',
        'description' => 'Delete your account and all of its data',
    ],

    'passkeys' => [
        'title' => 'Passkeys',
        'description' => 'Manage your passkeys for passwordless sign-in',
        'name_label' => 'Passkey name',
        'name_placeholder' => 'e.g. MacBook Pro, iPhone',
        'register' => 'Register passkey',
        'registering' => 'Registering',
        'remove' => 'Remove',
        'remove_title' => 'Remove passkey',
        'remove_description' => 'The ":name" passkey will be removed and can no longer be used to sign in.',
        'remove_confirm' => 'Remove passkey',
        'removing' => 'Removing',
        'sign_in' => 'Sign in with a passkey',
        'authenticating' => 'Authenticating',
        'separator' => 'Or continue with email',
    ],

    'two_factor_setup' => [
        'section_title' => 'Two-factor authentication',
        'section_description' => 'Manage your two-factor authentication settings',
        'enabled_title' => 'Two-factor authentication enabled',
        'enabled_description' => 'Two-factor authentication is now enabled. Scan the QR code or enter the setup key in your authenticator app.',
        'verify_title' => 'Verify authentication code',
        'verify_description' => 'Enter the 6-digit code from your authenticator app',
        'enable_title' => 'Enable two-factor authentication',
        'enable_description' => 'To finish enabling two-factor authentication, scan the QR code or enter the setup key in your authenticator app',
        'manual_entry_separator' => 'Or enter the code manually',
        'enabled_hint' => 'You will be prompted for a six-digit code at login, generated by your authenticator app.',
        'disabled_hint' => 'Once enabled, you will be prompted for a six-digit code at login, generated by a TOTP-compatible authenticator app.',
        'disable' => 'Disable two-factor authentication',
        'enable' => 'Enable two-factor authentication',
        'continue_setup' => 'Continue setup',
    ],

    'recovery_codes' => [
        'title' => '2FA recovery codes',
        'description' => 'Recovery codes let you regain access if you lose your 2FA device. Store them in a secure password manager.',
        'show' => 'View recovery codes',
        'hide' => 'Hide recovery codes',
        'regenerate' => 'Regenerate codes',
        'list_label' => 'Recovery codes',
        'loading_label' => 'Loading recovery codes',
        'usage_warning' => 'Each recovery code can be used once to access your account and will be removed after use. If you need more, click :action above.',
    ],

    'session' => [
        'expired' => 'For your security, your session has ended. Sign in again to continue.',
    ],

    'flash' => [
        'profile_updated' => 'Profile updated.',
        'password_updated' => 'Password updated.',
    ],
];

<?php

return [
    'fields' => [
        'name' => 'Nom complet',
        'email' => 'Adresse email',
        'phone' => 'Téléphone',
        'password' => 'Mot de passe',
        'password_confirmation' => 'Confirmer le mot de passe',
        'current_password' => 'Mot de passe actuel',
        'new_password' => 'Nouveau mot de passe',
        'remember' => 'Se souvenir de moi',
    ],

    'placeholders' => [
        'name' => 'Nom complet',
        'email' => 'email@exemple.com',
        'phone' => '+225 07 00 00 00 00',
        'password' => 'Mot de passe',
        'password_confirmation' => 'Confirmer le mot de passe',
        'current_password' => 'Mot de passe actuel',
        'new_password' => 'Nouveau mot de passe',
    ],

    'login' => [
        'head' => 'Connexion',
        'title' => 'Connexion à votre espace',
        'description' => 'Saisissez votre adresse email et votre mot de passe',
        'forgot' => 'Mot de passe oublié ?',
        'submit' => 'Se connecter',
        'no_account' => 'Vous n\'avez pas de compte ?',
        'sign_up' => 'Créer un compte',
    ],

    'register' => [
        'head' => 'Créer un compte',
        'title' => 'Créer un compte',
        'description' => 'Saisissez vos informations pour créer votre compte',
        'submit' => 'Créer mon compte',
        'have_account' => 'Vous avez déjà un compte ?',
        'sign_in' => 'Se connecter',
    ],

    'forgot_password' => [
        'head' => 'Mot de passe oublié',
        'title' => 'Mot de passe oublié',
        'description' => 'Saisissez votre adresse email pour recevoir un lien de réinitialisation',
        'submit' => 'Recevoir le lien de réinitialisation',
        'return_to' => 'Ou revenir à',
        'login_link' => 'la connexion',
    ],

    'reset_password' => [
        'head' => 'Réinitialiser le mot de passe',
        'title' => 'Réinitialiser le mot de passe',
        'description' => 'Saisissez votre nouveau mot de passe',
        'submit' => 'Réinitialiser le mot de passe',
    ],

    'confirm_password' => [
        'head' => 'Confirmer le mot de passe',
        'title' => 'Confirmer le mot de passe',
        'description' => 'Cette zone est protégée : confirmez votre mot de passe pour continuer.',
        'submit' => 'Confirmer le mot de passe',
        'passkey' => 'Confirmer avec une clé d\'accès',
    ],

    'verify_email' => [
        'head' => 'Vérification de l\'adresse email',
        'title' => 'Vérification de l\'adresse email',
        'description' => 'Vérifiez votre adresse email en cliquant sur le lien que nous venons de vous envoyer.',
        'sent' => 'Un nouveau lien de vérification vient d\'être envoyé à l\'adresse email indiquée lors de votre inscription.',
        'resend' => 'Renvoyer l\'email de vérification',
        'logout' => 'Se déconnecter',
    ],

    'two_factor' => [
        'head' => 'Authentification à deux facteurs',
        'code_title' => 'Code d\'authentification',
        'code_description' => 'Saisissez le code affiché par votre application d\'authentification.',
        'recovery_title' => 'Code de secours',
        'recovery_description' => 'Saisissez l\'un des codes de secours conservés lors de la configuration.',
        'recovery_placeholder' => 'Saisir le code de secours',
        'submit' => 'Continuer',
        'or_you_can' => 'ou vous pouvez',
        'use_recovery' => 'utiliser un code de secours',
        'use_code' => 'utiliser un code d\'authentification',
        'required_by_profile' => "Votre profil dans cette organisation exige l'authentification à deux facteurs. Activez-la pour retrouver l'accès.",
        'invalid_code' => 'Ce code est invalide ou a expiré.',
    ],

    'two_factor_reconfirm' => [
        'head' => 'Confirmer avec le code à deux facteurs',
        'title' => 'Confirmer votre identité',
        'description' => 'Cette action touche un compte de versement : ressaisissez le code de votre application d\'authentification pour continuer.',
        'submit' => 'Confirmer',
    ],

    'settings_description' => 'Gérez votre profil et les réglages de votre compte',

    'profile' => [
        'head' => 'Réglages du profil',
        'title' => 'Profil',
        'description' => 'Modifiez votre nom et votre adresse email',
        'email_unverified' => 'Votre adresse email n\'est pas vérifiée.',
        'resend_link' => 'Cliquez ici pour renvoyer l\'email de vérification.',
        'phone_help' => 'Facultatif : sert uniquement à vous alerter par WhatsApp en cas de changement sur un compte de versement.',
    ],

    'security' => [
        'head' => 'Réglages de sécurité',
        'title' => 'Modifier le mot de passe',
        'description' => 'Utilisez un mot de passe long et unique pour protéger votre compte',
    ],

    'appearance' => [
        'head' => 'Réglages d\'apparence',
        'title' => 'Apparence',
        'description' => 'Choisissez l\'apparence de l\'interface',
    ],

    'appearance_modes' => [
        'light' => 'Clair',
        'dark' => 'Sombre',
        'system' => 'Système',
    ],

    'delete_account' => [
        'title' => 'Supprimer le compte',
        'description' => 'Supprimer votre compte et toutes ses données',
        'warning_title' => 'Attention',
        'warning_body' => 'Cette action est irréversible : procédez avec prudence.',
        'trigger' => 'Supprimer le compte',
        'confirm_title' => 'Voulez-vous vraiment supprimer votre compte ?',
        'confirm_description' => 'Une fois le compte supprimé, toutes ses ressources et ses données seront aussi supprimées définitivement. Saisissez votre mot de passe pour confirmer.',
        'password_label' => 'Mot de passe',
        'confirm' => 'Supprimer le compte',
    ],

    'passkeys' => [
        'title' => "Clés d'accès",
        'description' => "Gérez vos clés d'accès pour vous connecter sans mot de passe",
        'name_label' => "Nom de la clé d'accès",
        'name_placeholder' => 'par exemple : MacBook Pro, iPhone',
        'register' => "Enregistrer la clé d'accès",
        'registering' => 'Enregistrement en cours',
        'remove' => 'Retirer',
        'remove_title' => "Retirer la clé d'accès",
        'remove_description' => 'La clé d\'accès ":name" sera retirée et ne pourra plus servir à vous connecter.',
        'remove_confirm' => "Retirer la clé d'accès",
        'removing' => 'Retrait en cours',
        'sign_in' => "Se connecter avec une clé d'accès",
        'authenticating' => 'Authentification en cours',
        'separator' => 'Ou continuer avec votre adresse email',
    ],

    'two_factor_setup' => [
        'section_title' => 'Authentification à deux facteurs',
        'section_description' => 'Gérez votre authentification à deux facteurs',
        'enabled_title' => 'Authentification à deux facteurs activée',
        'enabled_description' => "L'authentification à deux facteurs est active. Scannez le QR code ou saisissez la clé de configuration dans votre application d'authentification.",
        'verify_title' => "Vérifier le code d'authentification",
        'verify_description' => "Saisissez le code à 6 chiffres de votre application d'authentification",
        'enable_title' => "Activer l'authentification à deux facteurs",
        'enable_description' => "Pour terminer l'activation, scannez le QR code ou saisissez la clé de configuration dans votre application d'authentification",
        'manual_entry_separator' => 'Ou saisissez le code manuellement',
        'enabled_hint' => "Un code à six chiffres vous sera demandé à la connexion, généré par votre application d'authentification.",
        'disabled_hint' => "Une fois activée, un code à six chiffres vous sera demandé à la connexion, généré par une application d'authentification compatible TOTP.",
        'disable' => 'Désactiver la double authentification',
        'enable' => 'Activer la double authentification',
        'continue_setup' => 'Reprendre la configuration',
    ],

    'recovery_codes' => [
        'title' => 'Codes de secours 2FA',
        'description' => "Les codes de secours permettent de retrouver l'accès au compte en cas de perte de l'appareil d'authentification. À conserver dans un gestionnaire de mots de passe.",
        'show' => 'Afficher les codes de secours',
        'hide' => 'Masquer les codes de secours',
        'regenerate' => 'Régénérer les codes',
        'list_label' => 'Codes de secours',
        'loading_label' => 'Chargement des codes de secours',
        'usage_warning' => "Chaque code de secours ne peut être utilisé qu'une fois et est retiré après usage. Pour en obtenir d'autres, cliquer sur :action ci-dessus.",
    ],

    'session' => [
        'expired' => 'Par sécurité, votre session a pris fin. Connectez-vous de nouveau pour continuer.',
    ],

    'flash' => [
        'profile_updated' => 'Profil mis à jour.',
        'password_updated' => 'Mot de passe mis à jour.',
    ],
];

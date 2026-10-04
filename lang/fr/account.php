<?php

return [
    'fields' => [
        'name' => 'Nom complet',
        'organisation_name' => "Nom de l'organisation",
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
        'organisation_name' => 'Par exemple : Association des Soldats du Palais',
        'email' => 'email@exemple.com',
        'phone' => '+225 07 00 00 00 00',
        'password' => 'Mot de passe',
        'password_confirmation' => 'Confirmer le mot de passe',
        'current_password' => 'Mot de passe actuel',
        'new_password' => 'Nouveau mot de passe',
    ],

    'login' => [
        'blocked' => 'Ce compte est bloqué. Contactez l’équipe Convive pour en savoir plus.',
        'head' => 'Connexion',
        'title' => 'Connexion à votre espace',
        'description' => 'Saisissez votre adresse email et votre mot de passe',
        'forgot' => 'Mot de passe oublié ?',
        'submit' => 'Se connecter',
        'no_account' => 'Vous n\'avez pas de compte ?',
        'sign_up' => 'Créer un compte',
    ],

    'password_strength' => [
        'label' => 'Solidité du mot de passe',
        'levels' => [
            'weak' => 'Faible',
            'medium' => 'Moyen',
            'strong' => 'Fort',
        ],
        'rules' => [
            'min' => ':min caractères au moins',
            'mixedCase' => 'Une majuscule et une minuscule',
            'numbers' => 'Un chiffre',
            'symbols' => 'Un symbole (! ? - @ # …)',
        ],
        'met' => '(fait)',
        'missing' => '(manquant)',
        'uncompromised' => 'Il ne doit pas déjà circuler sur Internet après une fuite de données : c’est vérifié à l’envoi.',
    ],

    'register' => [
        'head' => 'Créer un compte',
        'title' => 'Créer un compte',
        'description' => 'Saisissez vos informations pour créer votre compte',
        'submit' => 'Créer mon compte',
        'terms_prefix' => 'J’accepte les',
        'terms_link' => 'conditions d’utilisation',
        'terms_joiner' => 'et la',
        'privacy_link' => 'politique de confidentialité',
        'terms_required' => 'Acceptez les conditions d’utilisation et la politique de confidentialité pour créer votre compte.',
        'creation_failed' => 'Votre espace n’a pas pu être créé. Réessayez dans quelques minutes : notre équipe est prévenue.',
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

    'scan_pin' => [
        'title' => 'Code de scan',
        'description' => "Quatre chiffres pour déverrouiller l'écran de contrôle à l'entrée après 5 minutes sans activité. Vous seul le connaissez.",
        'pin' => 'Code à 4 chiffres',
        'confirmation' => 'Saisissez-le une seconde fois',
        'create' => 'Choisir mon code',
        'change' => 'Changer mon code',
        'flash' => 'Code de scan enregistré.',
    ],

    'devices' => [
        'title' => 'Appareils connectés',
        'description' => "Les navigateurs où votre compte est ouvert. Si vous n'en reconnaissez pas un, déconnectez-le et changez votre mot de passe.",
        'current' => 'Cet appareil',
        'unknown_browser' => 'Navigateur inconnu',
        'unknown_platform' => 'système inconnu',
        'last_active' => 'Dernière activité : :time',
        'empty' => 'La liste des appareils n\'est pas disponible avec ce mode de stockage des sessions.',
        'trigger' => 'Déconnecter les autres appareils',
        'confirm_title' => 'Déconnecter les autres appareils ?',
        'confirm_description' => 'Toutes vos sessions ouvertes ailleurs seront fermées. Saisissez votre mot de passe pour confirmer.',
        'password_label' => 'Mot de passe',
        'confirm' => 'Déconnecter',
        'flash' => 'Les autres appareils ont été déconnectés.',
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
        'last_owner' => 'Vous êtes le dernier Propriétaire de : :organisations. Donnez d’abord le profil Propriétaire à un autre membre, ou supprimez l’organisation, puis revenez supprimer votre compte.',
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
        'regenerate' => 'Générer de nouveaux codes',
        'list_label' => 'Codes de secours',
        'shown_once' => 'Notez ces codes maintenant : ils ne seront plus jamais affichés.',
        'remaining' => '{0} Il ne vous reste aucun code de secours.|{1} Il vous reste 1 code de secours.|[2,*] Il vous reste :count codes de secours.',
        'hidden_hint' => 'Par sécurité, vos codes ne peuvent plus être affichés : nous n’en gardons que l’empreinte. Si vous ne les avez pas notés, générez-en de nouveaux.',
        'usage_warning' => 'Chaque code ne sert qu’une fois : il est retiré dès qu’il a été utilisé.',
        'regenerate_title' => 'Générer de nouveaux codes de secours ?',
        'regenerate_body' => 'Vos codes actuels cesseront de fonctionner tout de suite. Les nouveaux s’afficheront une seule fois : préparez de quoi les noter.',
    ],

    'session' => [
        'expired' => 'Par sécurité, votre session a pris fin. Connectez-vous de nouveau pour continuer.',
    ],

    'flash' => [
        'profile_updated' => 'Profil mis à jour.',
        'password_updated' => 'Mot de passe mis à jour.',
    ],
];

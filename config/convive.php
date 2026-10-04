<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Version de l'application
    |--------------------------------------------------------------------------
    |
    | Affichee au pied du menu du back-office : c'est elle qu'un membre cite quand il
    | signale un probleme. Deux parties (decision du proprietaire du projet, 2026-10-02) :
    | - le numero, choisi a la main, a faire avancer a chaque livraison marquante, ici ou par
    |   APP_VERSION sur le serveur ;
    | - la date de livraison et l'identifiant de la modification, poses tout seuls par
    |   `php artisan convive:release` dans le fichier ci-dessous, hors du depot.
    |
    */

    'version' => env('APP_VERSION', '0.1.0'),

    'release_path' => env('CONVIVE_RELEASE_PATH', storage_path('framework/release.json')),

    /*
    |--------------------------------------------------------------------------
    | Double authentification exigee par le profil
    |--------------------------------------------------------------------------
    |
    | Un profil peut exiger la double authentification de ses porteurs. Le controle
    | est desactive en local pour ne pas bloquer le compte de developpement decrit
    | dans CLAUDE.md, dont la 2FA est volontairement absente. Partout ailleurs, y
    | compris en test, il est actif.
    |
    */

    'two_factor' => [
        'enforced' => env('CONVIVE_ENFORCE_TWO_FACTOR', env('APP_ENV') !== 'local'),

        // Duree pendant laquelle un code TOTP verifie reste valable pour les actions sensibles
        // (comptes de versement, SECURITY.md C1), sur le meme principe que `password.confirm`
        // de Laravel (`RequirePassword`). Courte, deliberement : c'est un rejeu juste avant la
        // modification, pas une session de confiance prolongee.
        'reconfirm_timeout' => env('CONVIVE_TWO_FACTOR_RECONFIRM_TIMEOUT', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Domaine des liens publics
    |--------------------------------------------------------------------------
    |
    | Les liens d'inscription vivent sous le sous-domaine de l'organisation :
    | `{sous-domaine}.{domaine}/e/{jeton}`. C'est ce sous-domaine qui resout le
    | locataire, comme le prescrit CLAUDE.md pour le TenantFinder.
    |
    | Hote seul, sans port : `Route::domain()` compare au host du header Host, qui
    | ne porte jamais le port (Symfony le retire avant comparaison). Le port de
    | l'application, lui, vient de `app.url` au moment de composer une URL complete.
    |
    | En local, `convive.localhost` fonctionne sans configuration DNS : les
    | navigateurs resolvent `*.localhost` vers la machine.
    |
    */

    'public_domain' => env('CONVIVE_PUBLIC_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: 'localhost'),

    /*
    |--------------------------------------------------------------------------
    | Application des quotas de plan
    |--------------------------------------------------------------------------
    |
    | Les quotas et les options des plans (README section 3) sont appliques, pas seulement
    | affiches. Ce reglage reste actif partout par defaut. La suite de tests le coupe (voir
    | `phpunit.xml`) : elle date d'avant les plans et cree librement plusieurs evenements, membres
    | et inscriptions dans une meme organisation ; les tests de facturation le rallument.
    |
    */

    // La periode d'essai (README section 3) : trente jours sur le plan Association (decision du
    // proprietaire du projet, 2026-10-02). Ce ne sont que les valeurs de DEPART : la migration des
    // reglages les pose une fois dans `App\Settings\TrialSettings`, que la console regle ensuite.
    // Une duree vide (`CONVIVE_TRIAL_DAYS=`) veut dire un essai sans date de fin.
    'trial' => [
        'enabled' => (bool) env('CONVIVE_TRIAL_ENABLED', true),
        'plan' => env('CONVIVE_TRIAL_PLAN', 'association'),
        'days' => env('CONVIVE_TRIAL_DAYS', 30),
    ],

    // L'adresse ou une organisation ecrit a l'equipe Convive (restauration, aide). Vide tant que
    // le proprietaire du projet ne l'a pas fournie : les messages s'en passent alors.
    'support_email' => env('CONVIVE_SUPPORT_EMAIL'),

    /*
    | L'editeur du service et ses prestataires, tels qu'ils figurent dans la politique de
    | confidentialite, les conditions d'utilisation et les mentions legales
    | (`App\Support\LegalDocument`). Une valeur vide s'affiche « [a completer] », et
    | `convive:production-check` refuse de partir en production tant qu'il en reste une.
    | `reviewed` passe a vrai quand un juriste a valide les textes : rien ne s'affiche a ce sujet
    | sur les pages (decision du proprietaire du projet, 2026-10-02), seule la verification de
    | production le rappelle.
    */
    'legal' => [
        'editor_name' => env('CONVIVE_LEGAL_EDITOR_NAME'),
        'legal_form' => env('CONVIVE_LEGAL_FORM'),
        'share_capital' => env('CONVIVE_LEGAL_SHARE_CAPITAL'),
        'registration_number' => env('CONVIVE_LEGAL_RCCM'),
        'tax_number' => env('CONVIVE_LEGAL_TAX_NUMBER'),
        'editor_address' => env('CONVIVE_LEGAL_ADDRESS'),
        'publication_director' => env('CONVIVE_LEGAL_PUBLICATION_DIRECTOR'),
        'contact_email' => env('CONVIVE_LEGAL_CONTACT_EMAIL'),
        'privacy_email' => env('CONVIVE_LEGAL_PRIVACY_EMAIL'),
        'host_name' => env('CONVIVE_LEGAL_HOST_NAME'),
        'host_address' => env('CONVIVE_LEGAL_HOST_ADDRESS'),
        'mail_provider' => env('CONVIVE_LEGAL_MAIL_PROVIDER'),
        'reviewed' => (bool) env('CONVIVE_LEGAL_REVIEWED', false),
    ],

    'billing' => [
        'enforce_plan_limits' => (bool) env('CONVIVE_ENFORCE_PLAN_LIMITS', true),

        // Devises proposees pour l'abonnement, la premiere etant celle par defaut. Le franc CFA est
        // la devise du produit ; l'euro et le dollar s'y ajoutent (decision du proprietaire).
        // Retirer une devise ici suffit si le fournisseur de paiement ne l'accepte pas.
        'currencies' => array_filter(array_map('trim', explode(',', (string) env('CONVIVE_BILLING_CURRENCIES', 'XOF,EUR,USD')))),

        // Plan Institution, sur devis (README section 3) : adresse affichee sur l'ecran
        // Abonnement a la place du bouton de souscription en ligne, absent pour ce plan. A
        // remplacer par la veritable adresse du proprietaire du projet des qu'elle est fournie.
        'sales_contact_email' => env('CONVIVE_SALES_CONTACT_EMAIL', 'institution@convive.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Proxys de confiance et sessions
    |--------------------------------------------------------------------------
    |
    | `trusted_proxies` : adresses des proxys reellement places devant l'application, separees
    | par des virgules. Vide par defaut, aucun n'est cru. `*` est refuse (SECURITY.md C3) : il
    | rendrait `X-Forwarded-For` falsifiable et toute limite de debit par adresse IP decorative.
    |
    | `session_absolute_lifetime` : duree maximale d'une session depuis la connexion, en minutes,
    | quelle que soit l'activite (SECURITY.md, « Deconnexion et sessions »).
    |
    */

    'security' => [
        'trusted_proxies' => (string) env('TRUSTED_PROXIES', ''),
        'session_absolute_lifetime' => (int) env('SESSION_ABSOLUTE_LIFETIME', 720),
    ],

    /*
    |--------------------------------------------------------------------------
    | Alertes d'anticipation
    |--------------------------------------------------------------------------
    |
    | Part de la capacite sous laquelle l'equipe est prevenue qu'il reste peu de places.
    |
    */

    // L'adresse de l'equipe qui recoit les alertes techniques (controles de sante, sauvegardes,
    // espace qui n'a pas pu etre ouvert). Vide : rien ne part, l'etat se lit sur l'ecran de sante.
    'alert_email' => env('CONVIVE_ALERT_EMAIL'),

    'alerts' => [
        'seats_low_ratio' => (float) env('CONVIVE_SEATS_LOW_RATIO', 0.25),
    ],

    /*
    |--------------------------------------------------------------------------
    | Surveillance des exports
    |--------------------------------------------------------------------------
    |
    | Au dela de ce nombre de lignes, un export de la base d'inscrits previent ceux qui
    | surveillent le journal (SECURITY.md M3) : un export autorise reste une fuite possible.
    |
    */

    'exports' => [
        'alert_rows' => (int) env('CONVIVE_EXPORT_ALERT_ROWS', 200),
    ],

    /*
    |--------------------------------------------------------------------------
    | Validite des billets
    |--------------------------------------------------------------------------
    |
    | Echeance portee par le jeton QR (`not_after`, SECURITY.md C2) : le debut de l'evenement
    | plus ce nombre d'heures. Un jeton copie ou photographie cesse d'ouvrir la porte meme sur un
    | appareil hors ligne qui n'aurait jamais appris la cloture.
    |
    */

    'tickets' => [
        'valid_hours_after_start' => (int) env('CONVIVE_TICKET_VALID_HOURS_AFTER_START', 24),
    ],

    /*
    |--------------------------------------------------------------------------
    | Console d'exploitation
    |--------------------------------------------------------------------------
    |
    | PROVISOIRE : le README (section 3, « Console d'exploitation ») veut des comptes editeur
    | distincts, avec 2FA obligatoire et un domaine dedie. Tant qu'ils n'existent pas, la console
    | s'ouvre aux adresses listees ici, separees par des virgules. Le compte de developpement y
    | figure en local seulement. Remplace, pas complete, quand les comptes editeur arrivent.
    |
    */

    'console' => [
        'operators' => array_filter(array_map(
            fn (string $email) => strtolower(trim($email)),
            explode(',', (string) env('CONVIVE_CONSOLE_OPERATORS', env('APP_ENV') === 'local' ? 'admin@convive.com' : '')),
        )),
    ],

];

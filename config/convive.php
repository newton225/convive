<?php

return [

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

    'billing' => [
        'enforce_plan_limits' => (bool) env('CONVIVE_ENFORCE_PLAN_LIMITS', true),

        // Devises proposees pour l'abonnement, la premiere etant celle par defaut. Le franc CFA est
        // la devise du produit ; l'euro et le dollar s'y ajoutent (decision du proprietaire).
        // Retirer une devise ici suffit si le fournisseur de paiement ne l'accepte pas.
        'currencies' => array_filter(array_map('trim', explode(',', (string) env('CONVIVE_BILLING_CURRENCIES', 'XOF,EUR,USD')))),
    ],

    'public_domain' => env('CONVIVE_PUBLIC_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: 'localhost'),

];

# Plan de mise en production

Rédigé le 2026-10-13 à partir de l'état réel du dépôt (`convive:production-check`, `.env.example`,
`CLAUDE.md`, `docs/plateformes-tiers.md`). **Aucun secret ici** : mots de passe, jetons et clés restent
dans le `.env` du serveur.

Légende : **[vous]** une décision ou une pièce que seul le propriétaire du projet peut fournir,
**[moi]** ce que je peux préparer ou exécuter, **[Meta]**, **[serveur]** une étape sur la machine.

## 0. Où on en est

Prêt :

- Le code est poussé sur `origin/main` (dernier commit : 97aa708).
- Tests : les fichiers liés aux derniers changements, Vitest (59) et Playwright (65) passent ; la suite
  PHP complète était en cours d'exécution au moment de la rédaction.
- Les migrations centrale et des organisations existent ; la livraison s'estampille par `convive:release`.

Pas prêt, et indépendant du code :

- Identité légale de l'éditeur, relecture par un juriste.
- Meta : application en mode développement, numéro de test, vérification d'entreprise non faite.
- Hébergement : `devultraapp.com` sert une page statique sur un VPS Contabo ; Convive n'y est pas installé.
- Clés Cloudflare Turnstile réelles, destination des sauvegardes, service de surveillance extérieur.

## 1. Décisions à prendre avant tout [vous]

| Décision | Pourquoi elle bloque | Ma recommandation |
| --- | --- | --- |
| Où héberger : le VPS Contabo existant, ou le second serveur OVH évoqué le 2026-10-03 | Fixe le système, le nginx, les sous-domaines et le coût | Le VPS Contabo : vous l'administrez déjà, `devultraapp.com` y pointe |
| Adresse de l'application (`APP_URL`) | Les organisations vivent en sous-domaines de ce domaine | Un sous-domaine dédié, par exemple `app.devultraapp.com`, plutôt que la racine déjà prise par la vitrine DevUltraApp |
| Base de données au lancement : rester sur SQLite ou passer à PostgreSQL | `CLAUDE.md` dit que la grille de `SECURITY.md` sera rejouée sur PostgreSQL avant production, et SQLite est mono-écrivain | À trancher explicitement : lancer en SQLite avec peu d'organisations et un seul serveur, ou migrer d'abord. Je ne le décide pas à votre place |
| Vérification du téléphone par défaut sur les nouveaux événements | Voir l'interrupteur proposé | Désactivée au lancement, activée seulement quand Meta transmet les vrais messages |
| Aucune offre de paiement en ligne au lancement | Stripe n'est pas disponible en Côte d'Ivoire | Paiement des abonnements à la main, comme prévu |

## 2. Préalables hors code

### 2.1 Identité légale et juridique [vous]

Renseigner dans le `.env` de production :
`CONVIVE_LEGAL_EDITOR_NAME`, `CONVIVE_LEGAL_FORM`, `CONVIVE_LEGAL_SHARE_CAPITAL`, `CONVIVE_LEGAL_RCCM`,
`CONVIVE_LEGAL_TAX_NUMBER`, `CONVIVE_LEGAL_ADDRESS`, `CONVIVE_LEGAL_PUBLICATION_DIRECTOR`,
`CONVIVE_LEGAL_CONTACT_EMAIL`, `CONVIVE_LEGAL_PRIVACY_EMAIL`, `CONVIVE_LEGAL_HOST_NAME`,
`CONVIVE_LEGAL_HOST_ADDRESS`, `CONVIVE_LEGAL_MAIL_PROVIDER`.

Puis faire relire `/confidentialite`, `/conditions` et `/mentions-legales` par un juriste, et seulement
alors mettre `CONVIVE_LEGAL_REVIEWED=true`. Le contrôle de production refuse de partir sans cela.
[moi] : je peux relire les textes contre ce que fait le code avant que le juriste les voie.

### 2.2 Meta et WhatsApp [vous] [Meta]

1. Compléter l'étape 2 : numéro WhatsApp dédié, nom d'affichage « Convive », moyen de paiement.
2. Faire la vérification de l'entreprise (étape 3).
3. **Publier l'application Meta (mode Live).** Tant qu'elle ne l'est pas, le webhook ne reçoit que les
   envois de test et la vérification du téléphone par message de l'invité ne peut pas fonctionner pour de
   vrais invités. Meta demandera une politique de confidentialité accessible par une adresse publique :
   `https://<application>/confidentialite` suffit une fois l'application en ligne, d'où l'ordre : héberger
   d'abord, publier ensuite.
4. Faire approuver le modèle `resume_link` (texte dans `docs/whatsapp-modeles.md`), puis renseigner
   `WHATSAPP_TEMPLATE_RESUME_LINK`. Les six autres modèles sont déjà approuvés.
5. Remplacer le numéro de test : `WHATSAPP_META_PHONE_NUMBER_ID`, `WHATSAPP_BUSINESS_NUMBER`,
   `WHATSAPP_WEBHOOK_VERIFY_TOKEN`, `WHATSAPP_META_APP_SECRET`, et enregistrer l'adresse
   `https://<application>/webhooks/whatsapp` comme URL de rappel (champ `messages` abonné).

### 2.3 Cloudflare [vous]

- Clés **Turnstile** réelles (`TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET_KEY`), un seul domaine déclaré couvre
  tous les sous-domaines des organisations.
- Optionnel mais recommandé : placer le domaine derrière Cloudflare (DNS, TLS, protection contre le DDoS,
  en-tête `CF-IPCountry` utilisé pour le pays préselectionné du téléphone). Si oui, déclarer ses adresses
  dans `TRUSTED_PROXIES` (jamais `*`).

### 2.4 Courriel [vous]

OVH E-mail Pro est branché (`support@devultraapp.com`). Limite d'envoi non vérifiée ; pour une carte
d'invitation envoyée à toute une salle, prévoir Resend comme prévu dans `docs/plateformes-tiers.md`.
Renseigner `CONVIVE_ALERT_EMAIL` (alertes de sauvegarde et de santé) et `CONVIVE_SUPPORT_EMAIL`.

### 2.5 Sauvegardes [vous] [moi]

`BACKUP_DISK` doit désigner un stockage **extérieur au serveur** (S3 compatible), avec
`BACKUP_ARCHIVE_PASSWORD`. Sur le seul serveur de l'application, une sauvegarde protège d'une erreur de
manipulation, pas de la perte du serveur. Un exercice de restauration est exigé (voir 5).

## 3. Le serveur [serveur] [moi pour le préparer]

Exigences de `CLAUDE.md` et de `composer.json` :

- PHP 8.3 ou plus avec les extensions de Laravel 13, SQLite, GD ou Imagick (réencodage des images), Redis.
- **Redis** (cache à étiquettes et files). En développement il tourne dans Docker ; en production un Redis
  installé sur la machine, avec mot de passe, écoutant en local seulement.
- **Un processus `php artisan queue:work`** surveillé (Supervisor ou systemd), redémarré à chaque livraison.
- **Un cron `php artisan schedule:run` chaque minute.** Toutes les tâches de Convive en dépendent : envoi des
  cartes, rappels, expiration des réservations, alertes de fin d'événement, sauvegardes, contrôles de santé.
- **nginx** avec un bloc pour le domaine de l'application **et un bloc générique `*.<domaine>`** pour les
  sous-domaines des organisations, un certificat TLS générique (Let's Encrypt par défi DNS), HSTS.
- Les dossiers `storage` et `database` (fichiers SQLite des organisations) inscriptibles par PHP, **hors de
  la racine web**. Racine web : `public` seulement.
- `TRUSTED_PROXIES` : les proxys réellement placés devant l'application.
- Node ou une machine de build : `npm ci && npm run build` (le dossier `public/build` est servi).

[moi] : je peux écrire le bloc nginx, l'unité Supervisor, le cron, le script de déploiement, et une checklist
d'installation pour le VPS, à vous de les appliquer ou de me guider pas à pas sur la machine.

## 4. Configuration `.env` de production

Obligatoire (contrôlé par `convive:production-check`) : `APP_ENV=production`, `APP_DEBUG=false`,
`APP_URL` en `https`, `SESSION_SECURE_COOKIE=true`, `LOG_LEVEL` différent de `debug` (`warning`),
`CONVIVE_ENFORCE_TWO_FACTOR` actif, Turnstile réel, identité légale, `CONVIVE_LEGAL_REVIEWED=true`,
livraison estampillée.

À régler aussi : `APP_KEY` neuve (jamais celle de développement), `CACHE_STORE=redis`,
`QUEUE_CONNECTION=redis`, `REDIS_*`, `MAIL_*`, `WHATSAPP_DRIVER=meta` et ses variables, `BACKUP_DISK`,
`BACKUP_ARCHIVE_PASSWORD`, `CONVIVE_ALERT_EMAIL`, `SCHEDULE_HEARTBEAT_URL`, `TRUSTED_PROXIES`,
`INERTIA_SSR_ENABLED` selon votre choix, `APP_VERSION` (le numéro que vous choisissez).

À ne pas copier du développement : le compte `admin@convive.com` du seeder (créé hors production seulement),
`CONVIVE_ENFORCE_TWO_FACTOR=false`, les clés d'essai Turnstile, le numéro de test Meta.

## 5. Avant d'ouvrir au public

1. Le `convive:production-check` passe sans erreur sur la machine.
2. La suite PHP complète, Vitest et Playwright passent sur le commit déployé.
3. Rejouer la grille de `SECURITY.md` (en particulier C1 comptes de versement, C2 jetons QR, C3 réservation,
   H3 lien de reprise) sur l'environnement de production ou un clone.
4. **Exercice de sauvegarde** : `php artisan convive:backup`, puis restauration sur une machine propre, à la
   main comme décrit dans `CLAUDE.md` (« Sauvegardes »).
5. L'écran de santé technique est au vert (planificateur, tâches, file, envois en échec, Redis, disque).
6. Un essai réel de bout en bout : créer une organisation, publier un événement, s'inscrire depuis un vrai
   téléphone, vérifier le numéro par WhatsApp, déposer une preuve, valider, recevoir la carte, scanner à
   l'entrée, annuler et purger.
7. Vérifier les limiteurs de débit en conditions réelles (réseau mobile partagé), voir `CLAUDE.md`.

## 6. Le jour de la livraison

Dans cet ordre, sur le serveur :

```
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan tenants:migrate --force
php artisan tenants:sync-permissions
php artisan convive:release
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart
```

Les migrations du dernier lot : `listed_until` de la vitrine (centrale, avec rattrapage des lignes
annoncées) et les repères d'alertes d'événements (par organisation).

Retour arrière : garder l'archive de `convive:backup` prise juste avant, et le commit précédent. Une
migration n'est jamais annulée à chaud : on restaure.

## 7. Ouverture progressive

1. Une organisation pilote (la vôtre, ou un partenaire de confiance) pendant quelques jours.
2. Surveiller : écran de santé, envois en échec, journal d'audit, alertes par courriel.
3. Ne **pas** activer par défaut la vérification du téléphone avant d'avoir vu passer un vrai invité par
   WhatsApp.
4. Ouvrir ensuite aux autres organisations, plan par plan.

## 8. Après l'ouverture

- Surveillance extérieure du planificateur (`SCHEDULE_HEARTBEAT_URL`), un service à choisir.
- Rotation des secrets, copie des clés hors du serveur.
- Mesure d'audience (`GOOGLE_ANALYTICS_ID`) si souhaité, avec consentement.
- Bascule PostgreSQL, quand vous la déciderez.

## 9. Points en attente de votre validation

Hors production, mais à trancher avant qu'ils ne divergent du code :

- `README.md` section 5 et écran 8 : les neuf nouveaux types d'alerte, le bouton « Retrouver ma réservation »,
  la purge ciblée.
- `SECURITY.md` ligne 113 cite encore « 20 requêtes par minute par IP » ; `public-link` est à 120.
- L'interrupteur du défaut de « Vérifier le téléphone avant de réserver ».
- La règle « Accepter une inscription enregistrée sans preuve », toujours sans effet.

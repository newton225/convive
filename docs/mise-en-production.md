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

> **Mise à jour du 2026-10-16.** Le pilote privé est en ligne sur un **VPS OVH** (`148.113.204.35`), à
> `app.devultraapp.com`, en conteneurs. Les sections 1 à 9 ci-dessous sont le plan d'origine (hébergement
> Contabo, installation sans conteneurs) et restent à lire comme une liste d'exigences. Ce qui a **réellement**
> été fait, dans l'ordre, commande par commande, est dans la section 10 « Journal de la mise en production du
> pilote ». Pour livrer une nouvelle version : `docs/conteneurisation.md` et `deploy/README.md`.

## 1. Décisions à prendre avant tout [vous]

| Décision                                                                             | Pourquoi elle bloque                                                             | Ma recommandation                                                                                                    |
| ------------------------------------------------------------------------------------ | -------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------- |
| Où héberger : le VPS Contabo existant, ou le second serveur OVH évoqué le 2026-10-03 | Fixe le système, le nginx, les sous-domaines et le coût                          | Le VPS Contabo : vous l'administrez déjà, `devultraapp.com` y pointe                                                 |
| Adresse de l'application (`APP_URL`)                                                 | Les organisations vivent en sous-domaines de ce domaine                          | Un sous-domaine dédié, par exemple `app.devultraapp.com`, plutôt que la racine déjà prise par la vitrine DevUltraApp |
| ~~Base de données au lancement~~                                                     | **Tranché le 2026-10-13 : PostgreSQL**, installé sur le VPS, hors des conteneurs | Chantier en cours, voir § 3 bis                                                                                      |
| Vérification du téléphone par défaut sur les nouveaux événements                     | Voir l'interrupteur proposé                                                      | Désactivée au lancement, activée seulement quand Meta transmet les vrais messages                                    |
| Aucune offre de paiement en ligne au lancement                                       | Stripe n'est pas disponible en Côte d'Ivoire                                     | Paiement des abonnements à la main, comme prévu                                                                      |

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

- **Obligatoire : le captcha doit être actif en production.** Créer un widget **Turnstile** pour
  `devultraapp.com` (un seul domaine couvre tous les sous-domaines des organisations), puis renseigner
  `TURNSTILE_SITE_KEY` et `TURNSTILE_SECRET_KEY`. Sans clés réelles (ou avec les clés d'essai `1x…`, `2x…`,
  `3x…`), `convive:production-check` échoue : aucune protection anti-robot ne s'applique.
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

- PHP 8.3 ou plus avec les extensions de Laravel 13, `pdo_pgsql`, GD ou Imagick (réencodage des images), Redis.
- **Redis** (cache à étiquettes et files). En développement il tourne dans Docker ; en production un Redis
  installé sur la machine, avec mot de passe, écoutant en local seulement.
- **Un processus `php artisan queue:work`** surveillé (Supervisor ou systemd), redémarré à chaque livraison.
- **Un cron `php artisan schedule:run` chaque minute.** Toutes les tâches de Convive en dépendent : envoi des
  cartes, rappels, expiration des réservations, alertes de fin d'événement, sauvegardes, contrôles de santé.
- **nginx** avec un bloc pour le domaine de l'application **et un bloc générique `*.<domaine>`** pour les
  sous-domaines des organisations, un certificat TLS générique (Let's Encrypt par défi DNS), HSTS.
- Le dossier `storage` inscriptible par PHP, **hors de la racine web** (les bases sont dans PostgreSQL, plus
  aucun fichier de base à protéger). Racine web : `public` seulement.
- `TRUSTED_PROXIES` : les proxys réellement placés devant l'application.
- Node ou une machine de build : `npm ci && npm run build` (le dossier `public/build` est servi).

[moi] : je peux écrire le bloc nginx, l'unité Supervisor, le cron, le script de déploiement, et une checklist
d'installation pour le VPS, à vous de les appliquer ou de me guider pas à pas sur la machine.

## 3 bis. PostgreSQL [serveur] [moi]

État au 2026-10-14 : le code tourne sur PostgreSQL 18 en local (migrations, création et effacement d'une
organisation, orphelins, `unaccent`, sauvegardes `pg_dump` et restauration). **Reste avant d'ouvrir** : la suite
de tests complète sur PostgreSQL et la grille de `SECURITY.md` rejouée (concurrence sur la dernière place).

Sur le VPS :

- Connaître la version (`psql --version`) : le `pg_dump` utilisé par l'application doit être **au moins aussi
  récent que le serveur** (`PG_DUMP_BINARY`, ou `postgresql-client` de l'image Docker).
- Extension **`unaccent`** : fournie par `postgresql-contrib` (dans le paquet principal sur Debian et Ubuntu).
  Elle est « de confiance » depuis PostgreSQL 13 : le rôle de l'application, propriétaire de ses bases, l'active
  sans être super-utilisateur.
- Un rôle applicatif avec **`CREATEDB`** (une base par organisation), propriétaire de la base centrale.
  `DB_CENTRAL_DRIVER=pgsql`, `DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD`, `DB_CENTRAL_DATABASE`.
- Port 5432 fermé sur Internet ; depuis les conteneurs, voir `docs/conteneurisation.md`.
- Restauration : `CLAUDE.md`, « Sauvegardes ».

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

## 9. Points en attente de votre validation

Hors production, mais à trancher avant qu'ils ne divergent du code :

- `README.md` section 5 et écran 8 : les neuf nouveaux types d'alerte, le bouton « Retrouver ma réservation »,
  la purge ciblée.
- `SECURITY.md` ligne 113 cite encore « 20 requêtes par minute par IP » ; `public-link` est à 120.
- L'interrupteur du défaut de « Vérifier le téléphone avant de réserver ».
- La règle « Accepter une inscription enregistrée sans preuve », toujours sans effet.

## 10. Journal de la mise en production du pilote

Tout ce qui a été fait entre le 2026-10-14 et le 2026-10-16, dans l'ordre, pour qu'une machine neuve puisse
être remontée à l'identique. **Aucun secret ici** : chaque mot de passe, clé ou jeton se génère ou se saisit
sur le serveur, jamais dans une conversation, un ticket ou le dépôt.

Légende : **[serveur]** terminal ouvert par `ssh almalinux@148.113.204.35` (utilisateur avec `sudo`, pas
`root`), **[navigateur]** une page web, **[poste]** le poste du propriétaire du projet.

Le serveur n'est **pas dédié** à Convive : il héberge déjà l'application `outfit` (`incarnationist.com`,
conteneurs `outfit-backend`, base `outfitdb`, rôle `admin`). Rien de ce qui suit ne doit la casser : un
fichier nginx séparé, des bases et un rôle à part, aucun redémarrage de PostgreSQL.

### 10.1 Décisions

| Décision                 | Valeur retenue                                                                                                                                       |
| ------------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------- |
| Forme de l'ouverture     | Pilote privé et guidé : une organisation de confiance, pas d'ouverture publique                                                                      |
| Serveur                  | VPS OVH, AlmaLinux 10.1, 22 Go de mémoire, 8 cœurs, partagé avec `outfit` (et non le VPS Contabo du plan d'origine)                                  |
| Adresse de l'application | `app.devultraapp.com`, les organisations en `<sous-domaine>.app.devultraapp.com`                                                                     |
| Base de données          | PostgreSQL 16.11 déjà installé sur le serveur (hors conteneurs), une base par organisation                                                           |
| Compte OVH de l'API      | Europe : point d'accès `ovh-eu`                                                                                                                      |
| WhatsApp                 | Mis de côté (pas d'entité juridique pour la vérification d'entreprise Meta) : les messages WhatsApp vont dans le journal, les courriels fonctionnent |

### 10.2 Diagnostic du serveur (lecture seule) [serveur]

Avant de toucher à quoi que ce soit :

```
cat /etc/os-release | head -3
psql --version; systemctl is-active postgresql* ; ss -ltn | grep 5432
docker --version; docker compose version
getenforce; systemctl is-active firewalld
ss -ltn | grep -E ':(80|443|8080) '
systemctl is-active nginx httpd
free -h | head -2; df -h / | tail -1; nproc
whoami; sudo -n true && echo sudo-ok
sudo nginx -v
sudo nginx -T 2>/dev/null | grep -E "server_name|listen|ssl_certificate " | sort | uniq -c
ls /etc/nginx/conf.d /etc/letsencrypt/live
```

Constats : AlmaLinux 10.1, Docker 29.2.1 et Compose v5, SELinux en `Enforcing`, firewalld inactif,
PostgreSQL 16.11 PGDG (données dans `/var/lib/pgsql/16/data/`, et non `/var/lib/pgsql/data`), nginx 1.26.3
déjà en place sur les ports 80 et 443 (configuration `incarnationist.conf`), port 8080 libre, `sudo`
disponible. Un conteneur d'une autre application publie le port 8001 : ce n'est pas le nôtre.

Le client `psql` demande le mot de passe de `postgres` : le taper **uniquement au prompt**, jamais dans une
conversation.

### 10.3 DNS chez OVH [navigateur]

Zone `devultraapp.com` (la racine reste sur un autre serveur, `194.238.26.3`, qu'on ne touche pas). Deux
enregistrements de type **A**, vers `148.113.204.35` :

| Sous-domaine | Type | Cible          |
| ------------ | ---- | -------------- |
| `app`        | A    | 148.113.204.35 |
| `*.app`      | A    | 148.113.204.35 |

Contrôle de propagation depuis un poste : `nslookup app.devultraapp.com` et `nslookup test.app.devultraapp.com`
doivent répondre `148.113.204.35`.

### 10.4 Fermer l'accès public à PostgreSQL [serveur]

Constat grave : `pg_hba.conf` contenait `host all all 0.0.0.0/0 scram-sha-256` et `listen_addresses = '*'`,
donc n'importe quelle machine d'Internet pouvait tenter de se connecter à n'importe quelle base. Contraire à
`CLAUDE.md` (le port 5432 n'est pas ouvert sur Internet).

Avant de fermer, vérifier **qui se connecte** : l'application `outfit` utilise `host.docker.internal`
(`docker exec outfit-backend-php-fpm-1 printenv | grep -E '^DB_(CONNECTION|HOST|PORT|DATABASE|USERNAME)='`),
donc le réseau Docker, pas une adresse publique. Remplacer la règle publique par le réseau Docker, sans
coupure (simple rechargement) :

```
sudo cp -a /var/lib/pgsql/16/data/pg_hba.conf /var/lib/pgsql/16/data/pg_hba.conf.bak-20261015
sudo sed -i 's|^host\s\+all\s\+all\s\+0\.0\.0\.0/0\s\+scram-sha-256|host    all             all             172.16.0.0/12           scram-sha-256|' /var/lib/pgsql/16/data/pg_hba.conf
sudo grep -vE '^\s*#|^\s*$' /var/lib/pgsql/16/data/pg_hba.conf
sudo -u postgres psql -Atc "select line_number, address, netmask, error from pg_hba_file_rules"
sudo systemctl reload postgresql-16
```

La requête de contrôle ne doit renvoyer aucune erreur. Retour arrière : recopier la sauvegarde
`pg_hba.conf.bak-20261015` et recharger. Chaque commande se colle **seule** (un terminal qui coupe la
commande en deux a déjà fait échouer une tentative).

Test depuis l'extérieur (depuis un poste, pas depuis le serveur) : la connexion doit être refusée avec
`no pg_hba.conf entry for host ...`. C'est ce qui a été constaté.

Restent ouverts, à décider : `listen_addresses = '*'` (le restreindre exige un **redémarrage** de PostgreSQL,
donc une coupure brève pour `outfit`) et `REVOKE CONNECT ON DATABASE outfitdb FROM PUBLIC; GRANT CONNECT ON
DATABASE outfitdb TO admin;` (proposé, non appliqué). On n'active pas firewalld : Docker pilote déjà nftables
et firewalld risque de casser son réseau.

### 10.5 Rôle et base de Convive [serveur]

Le mot de passe est généré sur le serveur dans un fichier à droits stricts, jamais affiché :

```
umask 077; openssl rand -hex 24 > ~/convive-db.pass; ls -l ~/convive-db.pass
printf "CREATE ROLE convive LOGIN CREATEDB PASSWORD '%s';\nCREATE DATABASE convive_central OWNER convive;\n" "$(cat ~/convive-db.pass)" | sudo -u postgres psql -v ON_ERROR_STOP=1
sudo -u postgres psql -Atc "select rolname, rolcreatedb, rolsuper from pg_roles where rolname='convive'" -c "select datname from pg_database where datname like 'convive%'"
```

Le rôle `convive` a `LOGIN` et `CREATEDB` (une base par organisation) et n'est **pas** super-utilisateur.

### 10.6 Clé de déploiement GitHub et clone du code

Le serveur lit le dépôt avec une **clé de déploiement en lecture seule**, propre à ce projet : si le serveur
est compromis, le dépôt ne peut pas être modifié.

1. **[serveur]** Installer git si besoin et créer la paire de clés (sans phrase de passe, c'est une clé de
   machine) :

```
sudo dnf install -y git
git --version
ssh-keygen -t ed25519 -N "" -f ~/.ssh/convive_deploy -C convive-deploy
cat ~/.ssh/convive_deploy.pub
```

2. **[navigateur]** Sur GitHub : `https://github.com/newton225/convive`, **Settings**, **Deploy keys**,
   **Add deploy key** (`https://github.com/newton225/convive/settings/keys`). Titre :
   `serveur-148.113.204.35`. Coller **la ligne publique** affichée par `cat ~/.ssh/convive_deploy.pub`
   (elle commence par `ssh-ed25519` : une clé publique n'a rien de secret). **Laisser « Allow write access »
   décoché**, puis **Add key**. La clé privée `~/.ssh/convive_deploy` ne quitte jamais le serveur.

    Si le clone répond `Permission denied (publickey)`, la clé n'a pas été ajoutée au bon dépôt : la
    vérifier avec `ssh-keygen -lf ~/.ssh/convive_deploy.pub` et
    `ssh -i ~/.ssh/convive_deploy -o IdentitiesOnly=yes -T git@github.com`.

3. **[serveur]** Cloner dans `/opt/convive` et fixer la clé pour tous les `git pull` à venir :

```
sudo mkdir -p /opt/convive && sudo chown almalinux:almalinux /opt/convive
GIT_SSH_COMMAND="ssh -i ~/.ssh/convive_deploy -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new" git clone git@github.com:newton225/convive.git /opt/convive
cd /opt/convive && git config core.sshCommand "ssh -i ~/.ssh/convive_deploy -o IdentitiesOnly=yes" && git log --oneline | head -2
```

Si `git clone` répond « destination path already exists », le premier clone avait réussi : vérifier avec
`git log`, ne pas recloner.

### 10.7 Fichier `.env.production` [serveur, dans /opt/convive]

Créé en deux temps : les variables sans secret, puis les secrets ajoutés **sans les afficher**.

```
cd /opt/convive
cat > .env.production <<'ENVFILE'
APP_NAME=Convive
APP_ENV=production
APP_DEBUG=false
APP_URL=https://app.devultraapp.com
LOG_STACK=daily
LOG_DAILY_DAYS=30
LOG_LEVEL=warning
DB_CONNECTION=central
DB_CENTRAL_DRIVER=pgsql
DB_CENTRAL_DATABASE=convive_central
DB_HOST=host.docker.internal
DB_PORT=5432
DB_USERNAME=convive
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_CLIENT=predis
REDIS_HOST=redis
REDIS_PORT=6379
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
SESSION_ABSOLUTE_LIFETIME=720
TRUSTED_PROXIES=172.16.0.0/12
CONVIVE_ENFORCE_TWO_FACTOR=true
CONVIVE_ENFORCE_PLAN_LIMITS=true
MAIL_MAILER=smtp
MAIL_HOST=pro3.mail.ovh.net
MAIL_PORT=587
MAIL_USERNAME=support@devultraapp.com
MAIL_FROM_ADDRESS="support@devultraapp.com"
MAIL_FROM_NAME="Convive"
CONVIVE_SUPPORT_EMAIL=support@devultraapp.com
CONVIVE_ALERT_EMAIL=support@devultraapp.com
ENVFILE
chmod 600 .env.production
printf 'APP_KEY=base64:%s\n' "$(openssl rand -base64 32)" >> .env.production
printf 'DB_PASSWORD=%s\n' "$(cat ~/convive-db.pass)" >> .env.production
printf 'REDIS_PASSWORD=%s\n' "$(openssl rand -hex 24)" >> .env.production
read -rsp "Mot de passe de la boite e-mail : " MP; echo; printf 'MAIL_PASSWORD=%s\n' "$MP" >> .env.production; unset MP
```

Les quatre lignes qui ajoutent à `.env.production` (`printf ... >>` et `read`) ne se jouent **qu'une fois** :
rejouées, elles doubleraient `APP_KEY` et la clé changerait, ce qui rend illisible tout ce qu'elle chiffre.
En cas d'erreur, `rm .env.production` et recommencer depuis le bloc `cat`. Contrôle sans rien révéler :
`sed -E 's/^((APP_KEY|DB_PASSWORD|REDIS_PASSWORD|MAIL_PASSWORD)=).*/\1(masque)/' .env.production | grep -c .`

Variables ajoutées ensuite, au fil des besoins :

| Variable                                        | Pourquoi                                                                                                                                 | Quand                                           |
| ----------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------- |
| `CONVIVE_CONSOLE_OPERATORS=isaacndri@gmail.com` | Le compte éditeur qui ouvre `/console`                                                                                                   | À l'ouverture de la console                     |
| `LARAVEL_PDF_DRIVER=dompdf`                     | Sans elle, les PDF passent par Chrome et Node, absents de l'image : le téléchargement d'un billet renvoie une page HTML (`tickets.html`) | 2026-10-16, après l'échec constaté sur mobile   |
| `APP_VERSION=0.2.0`                             | Numéro de version affiché dans le menu                                                                                                   | Écrit par `deploy/deploy.sh` à chaque livraison |

`APP_KEY` : **à copier hors du serveur** (gestionnaire de mots de passe). Elle chiffre les clés de signature
des billets, la double authentification et les sessions.

### 10.8 Première construction et migration [serveur, dans /opt/convive]

```
export COMPOSE_FILE=docker-compose.prod.yml COMPOSE_ENV_FILES=.env.production
git pull
docker compose build app web 2>&1 | tail -15
docker images | grep convive
docker compose run --rm migrate 2>&1 | tail -40
docker compose up -d --no-build
docker compose ps
curl -s -o /dev/null -w "%{http_code}\n" -H "Host: app.devultraapp.com" http://127.0.0.1:8080/up
docker compose logs --tail 30 app queue scheduler
```

Aucun seeder en production : `DatabaseSeeder` refuse de s'y exécuter, les plans se créent seuls
(`Plan::ensure()`). Le `export` se refait à chaque nouvelle session SSH. Résultat attendu : `convive-app` et
`convive-web` construites, toutes les migrations en `DONE` (dont `enable_unaccent_extension`), cinq services
`Up` avec `web` et `redis` en `healthy`, `/up` à 200.

**Incidents rencontrés et corrigés dans le code** (chacun a son commit, ne pas les refaire) :

| Symptôme                                                | Cause                                                                       | Correctif                                                                            |
| ------------------------------------------------------- | --------------------------------------------------------------------------- | ------------------------------------------------------------------------------------ |
| « Please provide a valid cache path » à la construction | Dossiers `storage/framework/*` exclus par `.dockerignore`                   | `d894469` : le Dockerfile les crée                                                   |
| « image already exists » (Docker 29)                    | `queue`, `scheduler`, `migrate` reconstruisaient la même image en parallèle | `b77b390` : seul `app` construit ; commandes `build app web` puis `up -d --no-build` |
| 502 « upstream sent too big header »                    | Tampons FastCGI trop petits                                                 | `9129345` : `fastcgi_buffer_size 32k` et suivants                                    |
| 502 après recréation du conteneur `app`                 | nginx gardait l'ancienne adresse du conteneur                               | `abaf040` : résolveur Docker `127.0.0.11` et adresse dynamique                       |
| Variables de base erronées dans l'exemple               | Exemple périmé (`DB_CONNECTION=pgsql`)                                      | `8e55490` : exemple et doc corrigés                                                  |
| Inscription impossible sur PostgreSQL                   | `CREATE DATABASE` lancé dans une transaction                                | `3f56765` : base créée hors transaction, test ajouté                                 |

### 10.9 Certificat HTTPS générique [serveur, navigateur]

Un certificat `*.app.devultraapp.com` exige un défi DNS : Certbot a besoin de l'API OVH.

1. **[serveur]** `sudo dnf install -y python3-certbot-dns-ovh`
2. **[navigateur]** Créer un jeton d'API sur `https://api.ovh.com/createToken/` (compte OVH qui gère
   `devultraapp.com`). Nom : `Convive certbot`. Validité : **Unlimited** (sinon le renouvellement s'arrêterait
   un jour). Droits : `GET`, `POST` et `DELETE` chacun sur `/domain/zone/*`. IP restreintes : vide. OVH affiche
   Application Key, Application Secret et Consumer Key **une seule fois** : ne pas fermer la page avant l'étape
   suivante.
3. **[serveur]** Saisir les trois valeurs sans les afficher, dans un fichier lisible par `root` seul :

```
read -rsp "Application Key: " AK; echo
read -rsp "Application Secret: " AS; echo
read -rsp "Consumer Key: " CK; echo
printf 'dns_ovh_endpoint = ovh-eu\ndns_ovh_application_key = %s\ndns_ovh_application_secret = %s\ndns_ovh_consumer_key = %s\n' "$AK" "$AS" "$CK" | sudo sh -c 'umask 077; cat > /etc/letsencrypt/ovh.ini'
unset AK AS CK
sudo ls -l /etc/letsencrypt/ovh.ini
```

Le contrôle doit afficher `-rw-------. 1 root root`. 4. **[serveur]** Obtenir le certificat, avec rechargement de nginx au renouvellement :

```
sudo certbot certonly --dns-ovh --dns-ovh-credentials /etc/letsencrypt/ovh.ini --dns-ovh-propagation-seconds 60 -d app.devultraapp.com -d '*.app.devultraapp.com' --cert-name app.devultraapp.com --deploy-hook "systemctl reload nginx"
sudo certbot certificates 2>&1 | grep -E 'Certificate Name|Domains|Expiry'
systemctl list-timers 2>/dev/null | grep -i certbot
```

Obtenu, valable jusqu'au **2027-01-13**, renouvelé par `certbot-renew.timer`. Le jeton OVH peut être révoqué
depuis l'espace client s'il est soupçonné compromis (il ne couvre que les zones DNS).

### 10.10 nginx de l'hôte devant les conteneurs [serveur]

Fichier séparé `/etc/nginx/conf.d/convive.conf` (le `incarnationist.conf` existant n'est **pas** touché) :
le port 80 redirige vers 443 ; le 443 transmet à `127.0.0.1:8080` avec `Host`, `X-Real-IP`,
`X-Forwarded-For` et `X-Forwarded-Proto`, des tampons de 32 Ko, `client_max_body_size 8m`, les journaux
`convive.access.log` et `convive.error.log`, TLS 1.2 et 1.3 et le certificat de 10.9, pour
`server_name app.devultraapp.com *.app.devultraapp.com`.

```
sudo nginx -t
sudo systemctl reload nginx
curl -sI https://app.devultraapp.com/ | head -5
```

Toujours `nginx -t` **avant** de recharger : nginx sert aussi l'autre application. SELinux reste en
`Enforcing` : `getsebool httpd_can_network_connect` répondait déjà `on`, rien à changer.

### 10.11 Vérifications externes

Depuis un poste : `/`, `/up`, `/login`, `/confidentialite` répondent 200 ; `/e/zzz` et un sous-domaine inconnu
répondent 404 ; le certificat est valide pour `<sous-domaine>.app.devultraapp.com` ; les en-têtes de
sécurité sont présents. À noter, non tranché : `test.app.devultraapp.com/` répond 200 (la page d'accueil
centrale est servie sur n'importe quel sous-domaine) : est-ce voulu ?

### 10.12 Comptes

1. **Compte éditeur** : `isaacndri@gmail.com` s'inscrit depuis le site, vérifie son courriel, **active la
   double authentification** (et note les codes de secours hors de l'ordinateur), puis
   `CONVIVE_CONSOLE_OPERATORS=isaacndri@gmail.com` dans `.env.production` et recréation des services : la
   console `/console` s'ouvre. Compte séparé de celui d'une organisation (option B retenue). Un Fondateur ne
   peut pas réinitialiser sa **propre** 2FA : prévoir un second Fondateur.
2. **Organisation pilote** : une autre personne s'inscrit et crée l'organisation
   `oscars-des-soldats-du-palais` (logo, couleurs, identité légale, sous-domaine). Le sous-domaine se fige à
   la première publication d'un événement.

### 10.13 Livrer les évolutions suivantes

Chaque livraison suit `docs/conteneurisation.md` (« Livrer une nouvelle version ») et `deploy/README.md` :

```
cd /opt/convive
git pull
bash deploy/deploy.sh --version 0.2.0
```

Le script sauvegarde, note le point de retour, construit, migre, recrée les services, écrit le numéro de
version (`APP_VERSION`) et vérifie. Corrections livrées depuis la mise en ligne : champ sous-domaine avec
exemple, minuscules et aperçu en direct (`02d6805`, `42ee0d5`), scripts et procédure de livraison
(`9790085`, `5d74114`), pilote PDF DomPDF dans l'exemple de production (`97286f9`).

### 10.14 Mis de côté ou resté ouvert

| Sujet                                                             | État                                                                                                                                                                                                                                                                           |
| ----------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| WhatsApp et Meta                                                  | Mis de côté : pas d'entité juridique. Le serveur n'a aucun réglage `WHATSAPP_*`. À reprendre avec vérification d'entreprise, numéro dédié, modèle `resume_link`, application en mode Live, webhook `https://app.devultraapp.com/webhooks/whatsapp` (répond 403 sans bon jeton) |
| Page blanche Android à la saisie d'un code 2FA expiré             | Pas reproduite ; à diagnostiquer seulement si elle se reproduit dans Chrome seul                                                                                                                                                                                               |
| `REVOKE CONNECT` sur `outfitdb`, `listen_addresses`               | Non appliqués, à décider                                                                                                                                                                                                                                                       |
| Sauvegardes                                                       | Destination externe, `BACKUP_ARCHIVE_PASSWORD` et exercice de restauration à faire (le `pg_dump` de l'image est plus récent que PostgreSQL 16 : vérifier la relecture)                                                                                                         |
| `APP_KEY`                                                         | À copier hors du serveur                                                                                                                                                                                                                                                       |
| Suite de tests complète sur PostgreSQL et grille de `SECURITY.md` | Jamais rejouées : à faire avant d'ouvrir à d'autres organisations                                                                                                                                                                                                              |
| `convive:production-check`                                        | Échoue encore (identité légale de l'éditeur, Turnstile réel, relecture juridique) : acceptable pour le pilote privé, pas pour l'ouverture publique                                                                                                                             |
| Incohérence de documentation                                      | `docs/whatsapp-modeles.md` (code de vérification par SMS) contre `CLAUDE.md` (message WhatsApp envoyé par l'invité) : à trancher                                                                                                                                               |

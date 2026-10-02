# Instructions de developpement — Convive

Ce fichier est lu automatiquement a chaque session. Il fixe les regles a respecter en
permanence. La specification fonctionnelle complete (regles metier, ecrans, modele SaaS) est
dans **`README.md`** : la lire avant toute implementation, et s'y referer plutot que de
deviner un comportement.

Maquette interactive de reference : **`Convive.dc.html`** (prototype, donnees factices).

---

## Pile imposee

- **Laravel 13** avec **React integre via Inertia.js** et TypeScript. Pas d'API REST separee
  pour le back-office : les controleurs rendent des pages Inertia.
- **Vite** pour le bundling, **Tailwind CSS** pour le style.
- **PostgreSQL** (MySQL 8 accepte si l'hebergement l'impose), `tenant_id` sur chaque table
  metier. **Etat actuel : SQLite**, voir la section « Base de donnees » plus bas.
- **PWA** pour le parcours invite et l'ecran de scan : service worker, file locale,
  synchronisation au retour du reseau.
- **Queues Laravel** (Redis) et `schedule:run` pour les taches planifiees : purges, envois
  programmes, rappels, expiration des reservations et des liens de liste d'attente.
- **Stockage** S3-compatible via `Storage`, URL signees expirantes. Aucun fichier sensible
  servi depuis `public/`.
- **Auth** Fortify ou Breeze adapte a Inertia, TOTP pour la 2FA, sessions en cookie httpOnly.
- **Envois** Mail Laravel (Resend ou SES) et WhatsApp Business API derriere une interface
  dediee, pour rester remplacable.
- **Exports** `maatwebsite/excel` pour le XLSX, rendu HTML vers PDF pour billets, recus et
  listes de controle.

---

## Composants d'interface : reutiliser, ne pas reecrire

Aucune primitive d'interface ne doit etre codee a la main.

- Base : **shadcn/ui** (Radix UI plus Tailwind), installe composant par composant avec son CLI.
  Couvre bouton, champ, zone de texte, select, case a cocher, interrupteur, onglets, dialogue,
  infobulle, menu deroulant, notification, tableau, badge, calendrier.
- Icones : **lucide-react**. Aucune icone dessinee a la main en SVG.
- Tableaux de donnees : **TanStack Table** (`@tanstack/react-table`) pour tout tableau du
  back-office : base d'inscrits, file de preuves, lignes de releve, factures, journal, equipe.
  Colonnes typees, tri, filtres, pagination et selection viennent de la bibliotheque, jamais
  d'une implementation maison. Le rendu utilise les primitives de table shadcn/ui. Au dela de
  quelques milliers de lignes, pagination et tri passent cote serveur, exposes par
  `spatie/laravel-query-builder`, et la virtualisation se fait avec `@tanstack/react-virtual`.
- Formulaires : le `useForm` **natif d'Inertia** (`@inertiajs/react`), avec les memes regles
  cote serveur dans les Form Requests. Decision actee a l'etape 4 (formulaire d'inscription) :
  tous les formulaires du projet (tenants, profils, organisation, unites) etaient deja ecrits
  ainsi avant que ce document n'impose React Hook Form et Zod, jamais installes ni utilises nulle
  part. Rester sur `useForm` evite un ecart de style avec l'existant plutot que d'introduire une
  seconde facon d'ecrire un formulaire dans la meme base de code.
- Graphiques : **Recharts**. Dates : **date-fns** en locale francaise.
- QR : generation cote serveur (**bwip-js** ou equivalent), lecture camera avec
  **@zxing/browser**.

Avant d'ecrire un composant, verifier qu'il n'existe pas deja dans la bibliotheque installee
ou dans `resources/js/components/ui`. Un composant maison ne se justifie que pour un element
propre au metier : carte d'invitation, plan de salle, viseur de scan, compte a rebours de
reservation.

---

## Respecter les bonnes pratiques de chaque outil

Une brique s'ecrit **comme son ecosysteme attend qu'elle s'ecrive**, pas comme on l'aurait
ecrite ailleurs. Avant d'ajouter du code sur une technologie du projet, verifier la maniere
recommandee par sa documentation officielle, et la suivre. Une solution maison qui contourne
le mecanisme prevu par l'outil est une dette, meme quand elle fonctionne.

Concretement :

- **Laravel.** Les conventions du framework font foi : nommage des tables et des cles, Eloquent
  plutot que du SQL a la main, Form Requests, Policies, Actions, Events, Jobs, Enums adosses
  aux colonnes. Ne pas reimplementer ce que le framework fournit deja (validation, hachage,
  signature d'URL, limitation de debit, planification, files).
- **Inertia.** Les controleurs rendent des pages, pas du JSON. Les props partagees passent par
  le middleware dedie. Pas d'API REST parallele pour le back-office.
- **React.** Composants fonctionnels, hooks, un composant par fichier, etat local minimal,
  pas de logique metier dans le rendu. Suivre les regles des hooks, sans les contourner.
- **TypeScript.** Types explicites sur les contrats serveur vers client. Pas de `any`, pas de
  `as` pour faire taire le compilateur : corriger le type.
- **Paquets tiers.** Utiliser les points d'extension prevus, pas la modification de leur
  comportement interne. Si un paquet publie une configuration, la configurer plutot que de le
  detourner.
- **Tests.** Les assertions et les fabriques du framework, pas des verifications maison sur les
  memes faits.

Quand une bonne pratique de l'outil entre en conflit avec une regle de ce fichier ou de
`SECURITY.md`, ce sont ces documents qui l'emportent, et la raison doit etre ecrite en
commentaire a l'endroit du compromis.

---

## Navigation : ne pas laisser un ecran orphelin

Un ecran de back-office nouvellement construit devient accessible **dans le meme changement**
qui l'introduit : lien dans la barre laterale quand c'est un espace global (tableau de bord,
equipe, reglages), ou lien contextuel depuis la ressource dont il depend (une ligne de la liste
des evenements renvoie vers les preuves, le plan de salle, le scan de cet evenement precis). Un
controleur, une route et une page sans lien pour y arriver obligent le proprietaire du projet a
connaitre l'URL par coeur pour verifier le travail : ce n'est jamais termine tant que ce lien
n'existe pas, meme si la fonctionnalite elle-meme fonctionne. Corollaire : en reprenant une
etape, verifier d'abord qu'aucun ecran deja construit n'est reste orphelin (c'etait le cas des
preuves et du plan de salle avant l'etape 7), plutot que de n'y penser que pour le nouvel ecran.

---

## Conventions de code

- **Respecter le code existant.** Lire les conventions du projet avant d'ajouter du code et
  s'y conformer, plutot que d'introduire un nouveau style.
- Nommage semantique et explicite, en anglais dans le code, en francais dans les textes
  affiches : `SeatHold`, `ProofSubmission`, `RegistrationPurgeService`, `remainingSeats`, et
  jamais `data`, `tmp`, `handleClick2`.
- Laravel : controleurs minces, **Form Requests** pour la validation, **Policies** pour les
  autorisations, **Actions** ou **Services** pour la logique metier, **Events** et
  **Listeners** pour les effets de bord, **Jobs** pour l'asynchrone, **Enums** PHP pour les
  statuts. Aucune requete Eloquent dans une vue ou un composant React.
- React : un composant par fichier, pages dans `resources/js/pages`, composants partages dans
  `resources/js/components`, hooks dans `resources/js/hooks`. Pas de logique metier dans les
  composants.
- Types partages : maintenir des types TypeScript pour les props Inertia, afin que le contrat
  serveur vers client soit verifie a la compilation.
- **Commentaires seulement quand ils sont necessaires** : regle metier non evidente, choix de
  verrouillage, contrainte reglementaire. Pas de paraphrase du code, pas de banniere
  decorative.
- Aucun **emoji** et aucun **tiret cadratin** dans le code, les commentaires, les messages de
  commit ou les textes affiches. Utiliser la virgule, le deux-points ou la parenthese.
- Formatage automatique : **Pint** pour PHP, **Prettier** et **ESLint** pour TypeScript, en
  hook de pre-commit.
- Tests : **Pest** pour le back, **Vitest** plus **Testing Library** pour le front, tests de
  bout en bout sur les parcours critiques. Les tests du front vivent a cote du fichier qu'ils
  verifient (`search.ts`, `search.test.ts`), importent depuis `vite-plus/test` et se lancent par
  `npm test` (`vp test run`, environnement `jsdom`, reglage dans `vite.config.ts`). Un composant
  qui lit ses textes par `useTranslation()` se teste avec ce hook simule : la traduction rend la
  cle, le test porte sur ce que le composant affiche.
- Tests de bout en bout : **Playwright** (choix du proprietaire du projet, 2026-10-02, pour le scan
  hors connexion et Safari d'iPhone), dossier `e2e/`, `npm run test:e2e`. Ils tournent contre une
  application lancee a part, sur ses propres bases et fichiers sous `storage/e2e`
  (`e2e/environment.ts`, environnement `e2e`), remise a zero et semee a chaque execution : jamais
  contre les donnees de developpement. Les elements se designent par leur `data-test`.

---

## Methode : le test avant le code

**Ecrire le test en premier**, des que le comportement est verifiable : regle metier, calcul,
autorisation, validation, transition de statut, endpoint. Le cycle est test rouge, puis code
minimal qui le fait passer, puis remaniement.

- Un test par regle, nomme en francais et lisible comme une phrase :
  `it('refuse une preuve deposee apres expiration du decompte')`.
- **Toutes les regles de `README.md` section 2 doivent avoir un test avant leur
  implementation.** Aucune exception sur la disponibilite des places, la priorite, le decompte
  de reservation, les purges et l'isolation des locataires.
- Tests de concurrence obligatoires sur la reservation : deux requetes simultanees sur la
  derniere place, une seule aboutit.
- Tests d'autorisation obligatoires sur chaque route du back-office : chaque role est teste
  autorise et refuse, et un locataire tiers recoit 404.
- Une correction de bogue commence par un test qui reproduit le bogue.
- Pas de test pour du code trivial sans logique : accesseur simple, mapping direct, composant
  purement presentationnel.

---

## Ordre de travail : toute l'interface avant le reste du serveur

Decision du proprietaire du projet (2026-09-21) : avant de poursuivre le travail serveur qui
reste, **terminer tous les ecrans de `README.md` section 4**. But : avoir un apercu global du
chantier, juger l'ensemble du produit a l'ecran avant d'investir dans ce qu'il reste a
construire cote serveur.

- Un ecran dont le serveur n'existe pas encore se construit quand meme : page React, types des
  props, textes fr et en, lien de navigation, et les etats complets (vide, chargement, erreur,
  permission refusee). Rien ne reste sans lien pour y arriver (voir « Navigation »).
- Ses donnees viennent d'un **jeu d'exemple**, jamais de logique metier ecrite a la hate pour
  faire illusion. Le jeu d'exemple se voit : bandeau « donnees d'exemple » sur la page et
  `isSample: true` dans les props. Aucun chiffre d'exemple ne se presente comme reel.
- Le controleur provisoire ne porte que le rendu : route, autorisation, props d'exemple. Chaque
  point provisoire est marque d'un commentaire `PROVISOIRE`, et le controleur est **remplace**, pas
  complete, quand le serveur arrive.
- Les regles de ce document valent pour l'interface comme pour le reste : textes traduits dans les
  deux langues, accessibilite, un composant par fichier, aucune logique metier dans un composant.
- Le travail serveur reprend ensuite ecran par ecran, en remplacant les donnees d'exemple, test
  avant code.

---

## Limitation de debit

Utiliser les limiteurs Laravel (`RateLimiter`, middleware `throttle`) partout ou l'abus est
possible, avec des limiteurs nommes et non le seul reglage par defaut.

| Point d'entree              | Limite indicative       | Cle                         |
| --------------------------- | ----------------------- | --------------------------- |
| Connexion                   | 5 tentatives par minute | email plus adresse IP       |
| Verification 2FA            | 5 tentatives par minute | session utilisateur         |
| Lien public d'inscription   | 20 requetes par minute  | adresse IP                  |
| Soumission de preuve        | 5 par heure             | inscription plus adresse IP |
| Lien de reprise             | 10 par heure            | jeton plus adresse IP       |
| Scan a l'entree             | 60 par minute           | utilisateur agent           |
| Exports et import de releve | 30 par heure            | utilisateur                 |

Verrouillage progressif apres echecs repetes sur la connexion et la 2FA. Chaque blocage est
journalise. Les reponses limitees renvoient un message utile, jamais un code technique brut.

---

## Base de donnees : ne rien changer sans demande explicite

- **Ne pas modifier la configuration de base de donnees existante** : connexion par defaut,
  hote, port, nom de base, identifiants, moteur, jeu de caracteres, `DB_*` du `.env`.
  Toute modification doit etre demandee explicitement par le proprietaire du projet.
- Si un besoin semble exiger un changement de configuration, le signaler et attendre l'accord
  au lieu de l'appliquer.
- Les migrations ajoutent des tables et des colonnes ; elles ne renomment ni ne suppriment une
  structure existante sans accord explicite.

### Etat actuel : SQLite

Le proprietaire du projet a decide de rester sur **SQLite**, la configuration par defaut du
starter. PostgreSQL reste l'objectif, il est reporte. Ne pas reproposer le changement a
chaque session, et ne pas y toucher sans nouvelle demande explicite.

Deux consequences a compenser dans le code, pas a ignorer :

- **`lockForUpdate()` est sans effet en SQLite.** La grammaire SQLite de Laravel compile la
  clause de verrouillage en chaine vide : l'appel passe sans erreur et sans protection. La
  garantie contre la survente ne peut donc pas reposer dessus. Elle repose sur une
  **contrainte d'unicite reelle en base** pour l'attribution de siege, plus un verrou
  atomique applicatif (`Cache::lock()`) par evenement autour du calcul et de la creation
  d'une reservation. Continuer d'ecrire `lockForUpdate()` : inoperant aujourd'hui, actif le
  jour du basculement.
- **La Row Level Security n'est plus le sujet.** L'isolation entre locataires repose desormais
  sur une base separee par organisation via `stancl/tenancy`, pas sur une politique RLS
  Postgres : elle fonctionne donc a l'identique des aujourd'hui, en SQLite (un fichier
  `.sqlite` par locataire), et sans rien a changer le jour ou le projet passe sur PostgreSQL
  (une base par locataire, au lieu d'un fichier). Voir la section « Multi-locataire ».

Les tests de concurrence sur la derniere place restent obligatoires, mais SQLite etant
mono-ecrivain, ils verifient la contrainte d'integrite, pas une vraie course. Le nom du test
doit le dire, et la grille de `SECURITY.md` sera rejouee sur PostgreSQL avant production.

Les migrations restent **portables** : aucune syntaxe propre a PostgreSQL, la RLS arrivera
dans une migration separee et tardive.

---

## Donnees de demonstration : factories et seeders

Chaque modele metier a une **factory** avec des donnees credibles en francais, via Faker en
locale `fr_FR`, et des etats nommes pour les cas metier : `held()`, `proofSubmitted()`,
`confirmed()`, `expired()`, `purged()`, `waitlisted()`.

Seeders a fournir :

- `DatabaseSeeder` orchestre l'ensemble et reste idempotent.
- `UnitSeeder` : les unites du locataire, `ELIAKIM`, `QODESH`, `SENTINELLES`, `ELISHAMA`,
  `CHOSEN`, `ETAT MAJOR`, `Aucune`.
- `PlanSeeder` : Essentiel, Association, Institution avec leurs quotas.
- `TenantSeeder` : une organisation de demonstration complete, marque et identite legale.
- `EventSeeder` : plusieurs evenements couvrant les statuts en cours, ouvert, brouillon,
  termine, dont un evenement complet pour eprouver la liste d'attente.
- `RegistrationSeeder` : inscriptions reparties sur tous les statuts, avec accompagnateurs et
  unites, tables attribuees pour les inscriptions confirmees.
- `PaymentProofSeeder` : preuves saines et preuves douteuses, reference dupliquee, precision
  laissee par l'invite, capture deja vue.
- `TeamSeeder` : un membre par role, Proprietaire, Tresorier, Hotesse, Lecture.

### Compte principal de developpement

```
email    : admin@convive.com
mot de passe : password
role     : Proprietaire
```

Ce compte est cree par le seeder, uniquement hors production. La 2FA est desactivee pour lui
en environnement local afin de ne pas bloquer le developpement, et son mot de passe n'est
jamais un identifiant valide en production.

---

## Paquets : verifier avant d'installer, et demander avant d'ajouter

Avant d'ajouter une dependance qui n'est pas deja actee dans ce document, **comparer les
alternatives serieuses du moment** : etat de maintenance (derniere version, reactivite aux
versions Laravel recentes), adoption reelle, qualite de la documentation, adequation precise
au besoin.

**Ne pas installer directement.** Presenter au proprietaire du projet le choix envisage, les
alternatives ecartees et pourquoi, avant d'executer `composer require` ou `npm install`. La
decision finale lui revient. Cette regle ne s'applique pas aux paquets deja actes dans ce
document (la table Spatie, `stancl/tenancy`, etc.) : ce sont des choix deja tranches, a
utiliser sans les reproposer a chaque session. Un changement de version majeure ou
l'apparition d'une alternative clairement superieure a un choix deja fait se signale plutot
que de rester tu, mais n'autorise pas non plus un remplacement unilateral.

## Paquets : privilegier Spatie

Avant d'ecrire une brique transverse, verifier qu'un paquet Spatie la couvre. Ils sont le
choix par defaut, pour la coherence et la maintenance.

| Besoin                                         | Paquet                                       |
| ---------------------------------------------- | -------------------------------------------- |
| Roles et permissions                           | `spatie/laravel-permission`                  |
| Journal d'audit                                | `spatie/laravel-activitylog`                 |
| Fichiers deposes, conversions, URL signees     | `spatie/laravel-medialibrary`                |
| Rendu PDF (billets, recus, listes de controle) | `spatie/laravel-pdf`                         |
| Reglages de locataire et d'evenement           | `spatie/laravel-settings`                    |
| Enums riches                                   | `spatie/laravel-data` et `spatie/enum`       |
| Objets de transfert vers Inertia               | `spatie/laravel-data`                        |
| Sauvegardes                                    | `spatie/laravel-backup`                      |
| Surveillance de l'etat de l'application        | `spatie/laravel-health`                      |
| Requetes filtrables et triables                | `spatie/laravel-query-builder`               |
| Etiquettes libres                              | `spatie/laravel-tags`                        |
| Traduction de contenu                          | `spatie/laravel-translatable`                |
| Webhooks entrants et sortants                  | `spatie/laravel-webhook-client` et `-server` |
| Sitemap et robots du site produit              | `spatie/laravel-sitemap`                     |

Un paquet hors Spatie ne se justifie que si aucun equivalent Spatie n'existe : Inertia,
maatwebsite/excel, bwip-js, les bibliotheques front, et **le multi-locataire**, couvert par
`stancl/tenancy` (voir section suivante). Spatie ne propose pas de solution a base separee par
locataire ; `stancl/tenancy` est le paquet Laravel de reference sur ce terrain precis, avec un
support mature du mode multi-base et des connecteurs SQLite comme PostgreSQL.

## README : signaler une incoherence, ne jamais la corriger seul

`README.md` est la specification fonctionnelle de reference. Si une incoherence y apparait,
qu'elle vienne d'une contradiction interne, d'un choix devenu obsolete ou d'un ecart avec ce
que le code fait reellement, **ne pas la corriger de sa propre initiative**. Signaler le
passage concerne, expliquer en quoi il ne tient pas la route, proposer une solution et la
raison de ce choix precis plutot qu'une alternative, puis attendre la validation du proprietaire
du projet avant de modifier le document. Le README decrit des decisions produit, pas des
details d'implementation : une correction unilaterale, meme bien intentionnee, risque de trancher
a la place du proprietaire.

---

## Multi-locataire : isolation stricte

Le cloisonnement des donnees est la garantie centrale du produit. **Decision du proprietaire
du projet : une base de donnees physiquement separee par locataire, via `stancl/tenancy`.**
Ce n'est plus un filtrage applicatif a esperer sans faille, c'est une frontiere que le moteur
de base de donnees lui-meme ne peut pas franchir : une requete qui oublierait un filtre ne
peut pas lire les donnees d'un autre locataire, parce qu'elle n'est tout simplement pas
connectee a sa base.

### Ce qui vit ou

**Base centrale** (une seule, partagee) : tout ce qui doit etre lisible ou modifiable **a
travers** les locataires.

- `tenants` (etendu depuis le modele de `stancl/tenancy`) et `domains` : le registre des
  organisations et leurs sous-domaines.
- `users`, `tenant_members`, `tenant_invitations` : un utilisateur appartient a plusieurs
  organisations, doit pouvoir lister les siennes et en changer sans re-authentification, ce
  qui exige une vue centrale.
- `tenant_brandings` (identite legale et marque) : lu par le lien public avant meme que la
  tenancy ne soit initialisee (logo, couleurs, nom affiche), et par le registre lui-meme au
  meme titre que `domains`. Reste central plutot que de forcer une bascule de connexion pour
  un simple affichage.
- Le journal d'audit **des actions centrales** (creation d'organisation, changement de plan,
  facturation).

**Base de chaque locataire** (une par organisation, creee et migree a l'ouverture de l'espace) :
tout le reste, ce qui n'a de sens qu'a l'interieur d'une organisation.

- `events`, `payment_accounts`, `units` et toutes les tables qui suivront (inscriptions,
  preuves, tables, billets, scans, messages).
- `profiles`, `model_has_profiles`, `profile_has_permissions` : `spatie/laravel-permission`
  **sans mode `teams`**. Chaque locataire ayant sa propre base, la colonne d'equipe qui servait
  a cloisonner plusieurs organisations dans une table partagee n'a plus de raison d'etre : la
  base est deja la frontiere. Un profil « Tresorier » de l'organisation A et un profil du meme
  nom dans l'organisation B ne partagent litteralement aucune ligne, dans aucune table.
- Le journal d'audit **des actions du locataire**.

Consequence directe : plus aucune de ces tables ne porte de colonne `tenant_id`. Ce n'est pas
une omission a corriger, c'est le but de l'exercice.

### Ce que fait `stancl/tenancy`

1. **Identification.** Le sous-domaine pour le lien public de l'evenement
   (`InitializeTenancyBySubdomain`), le segment `{tenant}` de l'URL pour le back-office
   authentifie (resolveur par chemin, en gardant l'ergonomie deja construite : un utilisateur
   reste sur le domaine principal et change d'espace sans changer d'adresse). Les deux
   strategies coexistent, `stancl/tenancy` le permet nativement.
2. **Bascule de connexion.** Des l'identification faite, `tenancy()->initialize($tenant)`
   change la connexion de base de donnees par defaut pour celle du locataire. Tout modele
   Eloquent sans mention explicite de connexion lit et ecrit alors dans la bonne base, sans
   scope global, sans `tenant_id` a poser a la creation.
3. **Verification a l'autorisation.** Une Policy n'a plus a comparer un `tenant_id` : une
   ressource resolue est necessairement celle du locataire courant, puisqu'aucune autre n'est
   accessible depuis cette connexion. Elle verifie seulement le role et la permission. Un objet
   qui n'existe pas dans la base courante renvoie **404**, jamais 403, comme avant.
4. **Bootstrappers.** Cache, sessions, files et stockage sont prefixes ou isoles par
   `stancl/tenancy` lui-meme (bootstrappers officiels), plutot que par un prefixage maison :
   moins de code, moins de points d'oubli.

### Ce qui reste a surveiller malgre la base separee

La separation physique retire la classe de bogue la plus grave (oubli de scope, injection SQL
qui remonterait une autre organisation), mais deplace le risque plutot que de l'annuler :

- **Tenancy non initialisee.** Un job, une commande Artisan ou une tache planifiee qui
  s'execute sans avoir appele `tenancy()->initialize()` lit la base centrale ou aucune base
  tenant : refus d'executer la logique metier plutot qu'une execution silencieuse sur la
  mauvaise base. Une tache qui doit balayer tous les locataires boucle sur eux et initialise
  la tenancy a chaque tour (`tenancy()->runForMultiple()` ou equivalent), jamais une requete
  large sur une base qui n'existerait pas de toute facon.
- **Fuite entre domaine central et domaine locataire.** Une route du back-office central
  (facturation, creation d'organisation) ne doit pas rester accessible une fois la tenancy
  initialisee, et inversement : `stancl/tenancy` fournit `PreventAccessFromCentralDomains` et
  son inverse, a appliquer sur les deux groupes de routes.
- **Base de donnees non migree pour un nouveau locataire.** La creation d'une organisation
  cree le fichier ou la base, puis y rejoue les migrations `database/migrations/tenant/`
  **dans la meme operation** : un locataire sans ses migrations est un locataire casse, pas un
  cas a rattraper plus tard.
- **Tests d'isolation toujours obligatoires**, mais leur nature change : il ne s'agit plus de
  verifier qu'un filtre a bien ete applique, mais qu'aucune route ne repond avec les donnees
  d'un autre locataire meme quand on force l'acces (parce que la base n'est litteralement pas
  celle qui est interrogee).

### Ce qui reste central malgre tout, et donc encore filtre a la main

Les tables centrales (`tenant_members`, `tenant_invitations`, `tenant_brandings`, `domains`)
ne beneficient pas de la separation physique, puisqu'elles servent precisement a raisonner **a
travers** les locataires. Elles gardent une colonne `tenant_id`, un scope explicite dans les
requetes qui les touchent, et les memes regles qu'avant : Policy qui verifie l'appartenance,
404 sur acces croise, test d'isolation par route.

### Etat de la migration

**Migration terminee.** `App\Models\Tenant` implemente `TenantWithDatabase` : chaque
organisation a sa propre base (`tenant{id}.sqlite` aujourd'hui). Le mecanisme provisoire ecrit
avant cette decision (`App\Concerns\BelongsToTenant`, `App\Support\TenantContext`,
`App\Http\Middleware\ResolveCurrentTenant`, scope global et `tenant_id` sur les tables metier) a
ete retire du code. Les mentions qui en subsistent ailleurs dans ce document decrivent cet ancien
etat et ne font plus foi : c'est cette section qui l'emporte.

Ce qui est en place :

- **Back-office** : `App\Http\Middleware\EnsureTenantMembership` resout l'organisation par le
  segment `{tenant}` de l'URL, verifie l'appartenance (404 sinon), puis initialise la tenancy.
  Elle passe avant `SubstituteBindings` (`bootstrap/app.php`), pour que `{event}`, `{unit}`,
  `{profile}`... se resolvent deja dans la base du locataire. Pas de `scopeBindings()` sur ces
  sous-modeles : ils vivent dans une autre base physique.
- **Liens publics** : `InitializeTenancyBySubdomain` et `PreventAccessFromCentralDomains`
  (`routes/public.php`), plus `App\Http\Middleware\EndTenancy` pour revenir a la base centrale en
  fin de requete.
- **Taches planifiees et commandes** : elles bouclent sur les organisations et posent le contexte
  a chaque tour (`$tenant->run()` ou son alias `asCurrent()`).
- **Nouvelle table metier** : directement dans `database/migrations/tenant/`, sans colonne
  `tenant_id`. **Deploiement** : `php artisan tenants:migrate` puis
  `php artisan tenants:sync-permissions`, sans quoi une organisation deja ouverte garde un schema
  ou un catalogue de permissions en retard.

### Nommage du locataire

Le locataire est le modele `Tenant`. Le starter Laravel livrait cette notion sous le nom
`Team` ; elle a ete renommee en `Tenant`. Les tables centrales `tenants`, `domains`,
`tenant_members` et `tenant_invitations` portent une colonne `tenant_id` ou equivalente,
puisqu'elles vivent dans la base partagee et raisonnent a travers les locataires. Les tables
qui vivent dans la base d'un locataire (`events`, `payment_accounts`, `units`, `profiles`, et
ce qui suivra) n'en portent pas : la base elle-meme est le `tenant_id`. Il ne doit plus rester
de `Team` ni de `team_id` dans le code.

Dans les textes affiches, le locataire s'appelle **organisation**. Le mot « tenant » est du
jargon technique : il n'apparait jamais a l'ecran, dans un email ou dans un message d'erreur.

Les permissions du domaine « Equipe et profils » s'appellent `team.view`, `team.invite` et
`team.remove` : ici, `team` designe l'**equipe** d'une organisation, ses membres. Ce n'est pas
un reste de l'ancien modele `Team`.

---

## Profils et permissions

Les roles ne sont pas figes dans le code. L'exploitant compose ses propres **profils** a partir
d'un catalogue de permissions, lui fige.

### Ce qui est donnee et ce qui est code

- Les **permissions** sont un catalogue ferme declare dans `App\Enums\TenantPermission`, groupe
  par `App\Enums\TenantPermissionDomain`. Chaque entree correspond a un point de controle reel.
  On en ajoute quand un ecran l'exige ; on n'en retire jamais sans migration. **Aucune
  permission n'est creee depuis l'interface** : le controle d'acces deviendrait falsifiable par
  saisie.
- Les **profils** sont des donnees propres a chaque locataire : le modele `App\Models\Profile`,
  qui etend le role de `spatie/laravel-permission` en mode `teams`, le team etant le locataire.
  Deux organisations peuvent avoir un profil du meme nom sans se toucher.

### Configuration Spatie

Le paquet est reconfigure pour parler le vocabulaire du produit : table `profiles`, pivots
`model_has_profiles` et `profile_has_permissions`, cle de pivot `profile_id`, modele de role
`App\Models\Profile`. **Sans mode `teams`** : chaque locataire ayant sa propre base de donnees
(voir « Multi-locataire »), la colonne d'equipe qui aurait servi a cloisonner plusieurs
organisations dans une table partagee n'a plus de role a jouer, la base est deja la frontiere.
`tenant_members`, dans la base centrale, ne porte que l'appartenance et le renvoi vers la base
du locataire ; le profil, lui, vit dans l'affectation Spatie **a l'interieur** de cette base,
pour qu'il n'y ait qu'une seule source de verite localement.

### Regles a ne pas contourner

- Quatre profils sont crees a l'ouverture d'un espace : **Proprietaire**, systeme, qui detient
  tout le catalogue et n'est ni modifiable ni supprimable ; puis Tresorier, Hotesse et Lecture,
  point de depart librement remaniable.
- Un locataire conserve toujours au moins un Proprietaire actif : le dernier ne peut pas perdre
  son profil.
- `profiles.manage` ne permet pas d'accorder une permission que l'acteur ne detient pas
  lui-meme, ni d'affecter un profil qui en detient plus que le sien. Sans cette double regle, la
  gestion des profils est un chemin d'elevation de privileges.
- Un membre ne modifie pas le profil qu'il porte, et ne s'affecte pas un autre profil.
- Un profil porte par des membres ne se supprime pas : il faut d'abord les reaffecter, et la
  confirmation indique combien ils sont.
- Une modification de profil prend effet immediatement : le cache de permissions est invalide.
- Toute creation, modification, suppression et affectation est **journalisee** avec l'acteur,
  l'avant et l'apres, via `spatie/laravel-activitylog`.

### Double authentification exigee par le profil

Un profil porte le drapeau `requires_two_factor`. Ses porteurs n'atteignent pas le back-office
de l'organisation tant qu'ils n'ont pas active la 2FA : `EnsureTwoFactorForProfile` les renvoie
vers l'ecran de securite avec un message qui dit pourquoi.

- **Proprietaire et Tresorier l'exigent des l'ouverture d'un espace.** Ce sont les deux profils
  qui touchent a l'argent : comptes de versement, validation de preuves, facturation.
- Le profil systeme l'exige **toujours**, drapeau ou pas : `Profile::demandsTwoFactor()` le force.
  Sans cela, un Proprietaire pourrait desactiver l'exigence sur lui-meme.
- Le controle vient **apres** `EnsureTenantMembership` : un locataire tiers a deja recu 404, il
  n'apprend rien de l'existence de l'organisation.
- **Changer d'organisation et quitter une organisation restent hors de sa portee.** Un membre
  bloque ne doit pas se retrouver enferme : ces deux routes sont volontairement placees avant le
  middleware dans `routes/settings.php`.
- `config('convive.two_factor.enforced')` desactive le controle **en local uniquement**, pour ne
  pas bloquer le compte de developpement decrit plus haut, dont la 2FA est volontairement
  absente. En test et en production il est actif : les tests du back-office agissent donc avec
  `User::factory()->withTwoFactor()`.

### Verifier une permission

Cote serveur : `$user->hasTenantPermission($tenant, TenantPermission::ProofsApprove)`. Les
affectations etant portees par le locataire, toute lecture passe par `withinTenant()`, qui
positionne puis restaure le contexte d'equipe du registrar. Une verification hors contexte de
locataire ne doit jamais repondre.

L'autorisation se declare dans les Policies et se joue **avant** la validation : elle vit dans
`authorize()` du Form Request, pas seulement dans le controleur. Sinon un membre non autorise
recoit un message d'erreur de validation qui le renseigne sur ce que le formulaire attend.

Cote client, le front recoit la liste des permissions effectives et `resources/js/lib/permissions.ts`
fournit `can()`. **Cela sert uniquement a masquer ce qui est interdit** : chaque route et chaque
action revalident cote serveur.

---

## Organisation : identite legale, marque et sous-domaine

Le formulaire d'organisation (`README.md` ecran 14) vit dans `tenant_brandings`, une table a
part : ces champs sont optionnels, volumineux, et lus seulement sur ce formulaire, les billets
et les recus. `tenants` reste ce qui est charge a chaque requete.

- **Trois permissions, trois formulaires.** `tenant.legal` pour l'identite legale,
  `tenant.branding` pour les couleurs, `tenant.domain` pour le sous-domaine. Ce ne sont pas
  trois noms pour la meme chose : un tresorier peut avoir a corriger un numero de contribuable
  sans toucher a la marque. Chaque formulaire porte son `authorize()`.
- **Rien n'est obligatoire a la saisie.** L'identite legale se remplit par morceaux.
  `Tenant::isReadyToPublish()` dit si elle suffit, et c'est cette methode qui gardera la
  publication d'un lien public a l'etape 3, pas la validation du formulaire.
- **`is_personal` est un espace d'essai.** On ne demande ni papiers ni sous-domaine a
  l'inscription : on les demande le jour ou l'organisation publie. Un espace personnel reste
  donc utilisable, simplement pas publiable.
- **Formats permissifs, volontairement.** Le RCCM et le numero de contribuable varient d'un
  pays a l'autre de l'espace OHADA. Les regles refusent les caracteres impossibles, pas les
  formats inconnus : un motif trop strict rejetterait des identifiants valides.
- **Formes juridiques : catalogue ferme** (`App\Enums\LegalForm`), comme les permissions. La
  forme juridique figure sur les recus, elle ne se saisit pas en texte libre.
- **Sous-domaine.** Etiquette DNS en minuscules, normalisee avant validation pour que
  l'unicite et la valeur stockee parlent de la meme forme. `App\Support\Subdomain::Reserved`
  garde les adresses que les utilisateurs associent au produit lui-meme. Il devra etre **fige
  des qu'un lien public a ete distribue** : cette regle arrive a l'etape 3, avec les
  evenements.
- **Les trois enregistrements sont journalises** avec l'avant et l'apres. L'identite legale
  est, avec le compte de versement, l'endroit ou une modification discrete detourne l'argent.
- Les couleurs de marque sont stockees en hexadecimal : c'est ce que produit un
  `input[type=color]` et ce que consomment les emails, ou les fonctions de couleur modernes ne
  sont pas fiables. Defauts bordeaux `#7b1e3a` et or `#c9a227`.

### Fichiers de marque

Logo, bandeau, cachet et signature passent par `spatie/laravel-medialibrary`, sur le disque
`tenant_media` declare dans `config/filesystems.php`.

- **Hors racine web.** Racine `storage/app/tenant-media`, jamais `public/`. Le disque est
  declare `serve => true` : Laravel enregistre une route signee et `temporaryUrl()` devient
  disponible sur un disque local. `TenantBranding::brandFileUrl()` rend une URL **signee et
  expirante** (30 minutes par defaut). Aucune autre facon d'atteindre ces fichiers.
- **Ranges par locataire** : `tenants/{tenant_id}/{collection}/{media_id}/`, via
  `App\Support\TenantMediaPathGenerator`. Le chemin par defaut du paquet ne porte que
  l'identifiant du media ; ranger par locataire rend le cloisonnement visible sur le disque.
- **Reencodage systematique avant stockage**, avec `spatie/image`. Ce n'est pas une
  optimisation : une photo de cachet porte souvent la position GPS et le modele d'appareil de
  qui l'a prise. Le fichier stocke est une image et rien d'autre.
- **Le SVG est refuse.** Il peut porter du script, et rien ne le justifie pour un logo. Types
  acceptes : JPEG, PNG, WebP. 5 Mo au maximum.
- **La validation inspecte le contenu**, pas le nom : `image` plus `dimensions`, qui force un
  `getimagesize()`. Un script renomme en `.png` est refuse a la validation, avant que le
  reencodage n'echoue.
- Le nom de collection arrive par l'URL : il vient de `App\Enums\BrandFile`, jamais d'une
  chaine libre, sinon on ecrit dans une collection arbitraire.
- Depot et retrait sont journalises.

Le jour du basculement vers S3, seule l'entree `tenant_media` de `config/filesystems.php`
change.

---

## Evenements

- **La capacite est derivee**, jamais saisie : la somme des places des tables du plan de salle
  (`seating_tables.capacity`). Une capacite stockee a part finirait par diverger du plan de salle,
  et c'est le plan de salle qui fait foi le jour J.
- **Les tables n'ont pas toutes le meme nombre de places** (decision du 2026-09-29). Le formulaire
  decrit la salle en groupes (« 3 tables de 12, 20 tables de 8 ») ; `SyncSeatingTables` cree les
  tables des l'enregistrement, numerotees groupe apres groupe, et le plan de salle ajuste une
  table precise. Jamais une table sous le nombre de personnes deja placees, jamais une table
  occupee supprimee, jamais une capacite totale sous les places deja prises d'un evenement publie.
- **`table_count` et `seats_per_table` n'existent plus** (retires le 2026-10-02, avec l'accord du
  proprietaire du projet). La capacite est la somme des places des tables, un evenement sans table
  n'a aucune place. En test, la fabrique d'evenement pose vingt tables de dix places ; la cle
  `tables` de `create()` decrit une autre salle (`[nombre, places]`) ou aucune (`null`, quand le
  test pose ses propres tables).
- **« Complet » n'est pas un statut stocke** mais un etat calcule. Un statut stocke devrait
  etre mis a jour par quelqu'un, et ce quelqu'un se tromperait au pire moment. Les statuts
  reellement stockes sont brouillon, ouvert, en cours, termine.
- **Publier est un acte separe de la modification.** `Event::isReadyToPublish()` en est le
  gardien : identite legale de l'organisation complete, sous-domaine, capacite non nulle, date,
  et **au moins un compte de versement visible rattache a l'evenement**. Un evenement qui
  n'offre aucun compte ne dit pas a l'invite ou verser ; un recu emis sans raison sociale n'a
  aucune valeur.
- **Le jeton du lien public** fait 32 octets d'aleatoire et **ne tourne jamais** : il est
  l'adresse de l'evenement pour tous ceux qui l'ont recue. Aucun identifiant sequentiel
  devinable dans une URL publique.
- **Publier fige le sous-domaine** de l'organisation : le changer casserait des adresses deja
  entre les mains des invites.
- **Un evenement publie ne se supprime pas**, il se cloture. Seul un brouillon se supprime.
- **La duplication ne reprend ni le jeton ni la date de publication** : c'est un autre
  evenement, il a sa propre adresse.
- Montants en **francs CFA sans decimale** : un entier est la representation exacte, pas une
  approximation.

Le back-office des evenements vit sous `{tenant}/events`, pas sous `settings` : un evenement
est le produit, pas un reglage.

### Lien public de l'evenement (README ecran 3)

Premiere surface non authentifiee du produit : visuel, date, heure, capacite, tarif, places
restantes, date limite, en lecture seule. Le formulaire d'inscription (ecran 4) et le paiement
(ecran 5) restent a construire ; le bouton « S'inscrire » n'est pour l'instant qu'un etat, pas
un lien.

- **Sous-domaine, pas chemin.** `Route::domain('{tenant_subdomain}.'.config('convive.public_domain'))`
  tient le role du `TenantFinder` decrit dans la section « Multi-locataire » : c'est
  `App\Http\Middleware\ResolveCurrentTenant` (globalement prepose au groupe `web`) qui lit
  `tenant_subdomain` et pose le contexte, avant que le scope de `BelongsToTenant` n'entre en
  jeu. Un sous-domaine inconnu ne resout aucun locataire : le controleur repond 404.
- **L'acces croise est impossible par construction, pas verifie a la main.** Le jeton d'un
  evenement n'est cherche qu'a l'interieur du locataire deja resolu par le sous-domaine : le
  jeton d'une organisation A presente sous le sous-domaine de B ne trouve rien. Teste
  explicitement (`tests/Feature/Public/EventTest.php`).
- **Jeton inconnu et evenement jamais publie recoivent la meme reponse 404** qu'un sous-domaine
  inconnu : rien ne doit permettre de deviner ce qui existe.
- **`config('convive.public_domain')` ne porte jamais le port.** `Route::domain()` compare au
  host du header `Host`, que Symfony fournit toujours sans port : y inclure `:8000` empeche
  toute correspondance. Le port vient separement de `app.url` au moment de composer une URL
  complete (`Event::publicUrl()`).
- **Piege verifie en le corrigeant une fois : les arguments de controleur sur une route a
  domaine se lient par position, pas par nom, des que la methode declare moins de parametres
  que domaine plus chemin.** Avec `{tenant_subdomain}` en tete du domaine et `{token}` sur le
  chemin, une methode `show(string $token)` recevait la valeur du sous-domaine, pas celle du
  jeton, silencieusement (aucune erreur, un simple 404 en aval). Le controleur lit desormais
  `$request->route('token')` explicitement plutot que de compter sur l'injection implicite.
  A refaire a l'identique pour toute future route sur ce groupe de domaine.
- **Limite de debit `public-link`** (20 requetes par minute par adresse IP, table de
  `CLAUDE.md`), enregistree dans `AppServiceProvider`, testee jusqu'au 429.
- **Couleurs de marque du locataire appliquees**, jamais celles du back-office : elles arrivent
  deja validees par expression reguliere hexadecimale stricte, elles entrent donc sans risque
  dans une variable CSS (`--brand-primary`, `--brand-secondary`).
- **Dates formatees avec `date-fns`**, dans la langue de l'interface et non celle de l'appareil
  du visiteur : premiere utilisation du paquet prescrit par la pile technique, jusque-la absent
  du projet. Les ecrans plus anciens qui affichent encore une date via `toLocaleString()` du
  navigateur (`events/index.tsx`, `events/form.tsx`, `payment-accounts.tsx`) n'ont pas ete
  repris : a corriger a l'occasion, pas en urgence.

**Non couvert ici**, et a construire ailleurs : les en-tetes de securite (CSP a nonces, HSTS,
`X-Frame-Options`, `Referrer-Policy`, voir SECURITY.md H7) sont un chantier applicatif entier,
pas une chose a ajouter au cas par cas sur une seule route.

### Annonce sur le site produit

Decision du proprietaire du projet (2026-09-22) : au-dela du lien direct que l'organisateur
distribue lui-meme, un evenement peut aussi apparaitre dans une vitrine publique sur le site
produit (README ecran 1, domaine central, aucun sous-domaine), pour toucher un public qui n'a
jamais recu le lien. **Ceci est la decision retenue, le code n'est pas encore ecrit.**

- **Opt-in, jamais automatique.** Publier un evenement ne l'annonce pas de lui-meme sur le site
  produit : c'est un second geste, volontaire, depuis le back-office de l'evenement (le meme
  ecran que la publication, ou un reglage a cote). Un evenement destine a un cercle ferme
  (inscription sur invitation directe uniquement) reste publiable sans jamais apparaitre dans
  la vitrine.
- **`announced_at`, pas un booleen.** Meme raison que `published_at` : savoir depuis quand
  l'evenement est annonce importe (tri par recence de la vitrine), et retirer l'annonce se lit
  comme le vider, pas comme le renverser. Colonne sur `events`, base du locataire.
- **Retirer l'annonce est toujours possible**, y compris apres publication et independamment de
  la cloture : l'organisateur garde la main sur sa visibilite a tout moment, pas seulement au
  moment de publier.
- **La vitrine ne lit pas les bases des locataires a chaque affichage.** Boucler sur chaque
  organisation et initialiser sa tenancy a chaque requete d'un visiteur anonyme ne passerait pas
  a l'echelle (CLAUDE.md, « Multi-locataire », le meme risque que pour une tache planifiee, ici
  sur le chemin critique d'une page publique). La vitrine lit une table centrale dediee, une
  ligne par evenement annonce, tenue a jour en ecriture par `SaveEvent` (et son retrait) chaque
  fois que `announced_at`, le nom, la date ou le visuel changent : le meme principe que
  `tenant_brandings`, une donnee lue a travers les locataires vit au centre, jamais reconstruite
  a la volee depuis N bases a chaque visite.
- **Le lien reste l'adresse complete avec jeton**, jamais un identifiant court ou sequentiel :
  la vitrine ne fait qu'exposer un lien que l'organisateur a deja choisi de rendre public, elle
  ne cree pas une seconde facon d'atteindre l'evenement. Aucun changement a la garantie
  existante (« Le jeton du lien public », plus haut) pour un evenement non annonce.
- **Vocabulaire.** « Vitrine » ou « evenements a la une » a l'ecran, jamais « annuaire » ni
  « repertoire » (jargon technique), et le nom de l'organisation apparait tel que
  `tenant_brandings.display_name` le donne, jamais le sous-domaine brut.
- **Permission dediee**, distincte de `events.update` : annoncer publiquement une organisation
  est une decision de visibilite, pas une simple modification de fiche.

---

## Comptes de versement

C'est la surface la plus attaquee du produit : changer discretement le numero affiche sur un
lien public detourne tout l'argent d'un evenement, et les invites deposent des preuves
parfaitement authentiques. Les controles sont ceux de `SECURITY.md` C1, aucun n'est optionnel.

- **Valeur vivante et valeur en attente.** Les colonnes `channel`, `account_number` et
  `holder_name` sont ce que voit l'invite. Les colonnes `pending_*` portent une modification
  demandee et pas encore active : c'est ce qui permet de **garder l'ancien numero affiche
  pendant le delai**.
- **Delai d'activation de 24 h** sur le canal, le numero et le titulaire. Le libelle, la
  consigne et l'activation prennent effet tout de suite : ils ne designent pas ou va l'argent.
- **Un compte tout juste cree n'a pas de valeur vivante** et n'apparait donc nulle part avant
  la fin du delai. Sans cette regle, il suffirait d'ajouter un compte plutot que d'en modifier
  un pour contourner le delai.
- **Un second Proprietaire peut lever le delai**, jamais celui qui a demande le changement.
  Sans cette seconde condition, un compte compromis se validerait lui-meme.
- **Re-authentification** (`RequirePassword`) sur toutes les routes.
- **Alerte immediate** a tous les porteurs de `tenant.payment_accounts`, avec l'ancien et le
  nouveau numero. C'est ce qui rend le delai utile : sans alerte, personne ne regarde pendant
  les vingt-quatre heures ou le changement peut encore etre annule.
- **Un changement reste signale sept jours** (`changedRecently()`).
- **Journal** avec l'avant et l'apres a chaque demande, validation et annulation.
- L'activation est appliquee par une **tache planifiee**, seul endroit qui bascule une valeur
  en attente vers la valeur vivante. Si la tache ne tourne pas, l'activation est en retard et
  l'ancien numero reste affiche : c'est le bon sens de defaillance pour de l'argent.
- Une tache qui doit balayer tous les locataires **boucle sur eux et pose le contexte**
  (`Tenant::asCurrent()`), plutot que de lever le scope global.

**Non encore fait** : le second facteur TOTP rejoue juste avant la modification (seul le mot de
passe est redemande), l'alerte WhatsApp, et le bandeau sur le tableau de bord.

### Mesure d'audience : pages commerciales seulement

Google Analytics (README, « Mesure d'audience ») ne mesure que l'accueil et la vitrine, apres
consentement. L'identifiant arrive en prop de page (`analyticsId`) de ces deux controleurs,
**jamais en prop partagee** : le back-office ne doit pas le recevoir. Une nouvelle page
commerciale le recoit a son tour et rend `SiteAnalytics` ; une page du back-office ou du parcours
invite, jamais (leurs adresses portent des organisations, des jetons et des signatures). Test :
`tests/Feature/AnalyticsTest.php`.

### Numeros de telephone : invites de tout pays, comptes de versement ivoiriens

Decisions du proprietaire du projet (2026-09-29) : d'abord Cote d'Ivoire seule, puis, le meme jour,
les invites de tout pays. Le telephone d'un invite ne sert qu'a le joindre (WhatsApp, code,
rappels) : le paiement passe par une preuve deposee, jamais par ce numero. Seuls les comptes de
versement restent ivoiriens. `App\Support\PhoneNumber` en est la seule source de verite, adossee a
`propaganistas/laravel-phone` (libphonenumber) pour les numeros etrangers.

- **Forme unique** E.164 (`+`, indicatif, numero), quelle que soit l'ecriture saisie (`07 07...`,
  `+225 07...`, `00225...`, `(+225)...`, `+33 6...`, `0033 6...`). Un numero sans indicatif est lu
  comme ivoirien : un numero etranger doit porter le sien. Le telephone d'un invite est enregistre
  sous cette forme (`PhoneNumber::normalize()` dans `prepareForValidation` des Form Requests
  publics, regle `GuestPhoneNumber`), et c'est elle qu'on envoie a WhatsApp. Sans elle, le meme
  telephone passait pour deux numeros et l'attente apres des reservations expirees se contournait
  en ajoutant ou retirant l'indicatif (SECURITY.md C3).
- **Saisie** : `PhoneField` (`react-phone-number-input`, liste des pays en shadcn Popover et
  Command, drapeaux SVG embarques, compatibles avec la CSP). Le pays preselectionne vient de
  `App\Support\VisitorCountry` : l'en-tete `CF-IPCountry` de Cloudflare, la Cote d'Ivoire a
  defaut. Un champ cache envoie la forme E.164 ; le serveur revalide quoi qu'il arrive.
- **Comparaison** toujours par `PhoneNumber::same()`, jamais sur la chaine brute : les lignes
  enregistrees avant cette regle gardent leur ecriture d'origine.
- **Comptes de versement** : ivoiriens seulement (`PhoneNumber::normalizeIvorian()`), et le
  prefixe doit correspondre au reseau du canal, declare sur
  `PaymentChannel::mobilePrefixes()` (Orange 07, MTN 05, Moov 01, Wave tout mobile). Un « 05 » sous
  Orange Money enverrait l'argent vers un numero MTN. Le numero est enregistre par paires
  (`+225 07 07 12 34 56`). Virement et especes gardent leur saisie libre.
- Ouvrir les comptes de versement a un autre pays est une decision produit, pas un ajout de
  prefixe : a redemander.

---

## Unites

Les unites (`ELIAKIM`, `QODESH`, `SENTINELLES`, `ELISHAMA`, `CHOSEN`, `ETAT MAJOR`, `Aucune`)
sont des **donnees de locataire**, pas des constantes du code : chaque organisation compose la
sienne. Elles sont creees a l'ouverture d'un espace, comme les profils, et librement
remaniables ensuite.

- `Aucune` est un **choix valide**, pas une absence de choix. Un champ vide bloque la
  validation, `Aucune` ne la bloque pas.
- Une organisation conserve **au moins une unite** : sans elle, le formulaire d'inscription ne
  peut plus etre rempli.
- Une unite retiree de la liste se **desactive** (`is_active`) plutot que de se supprimer, pour
  ne pas casser l'historique des inscriptions deja prises.
- Unicite composite `(tenant_id, name)` : deux organisations peuvent avoir la meme unite, une
  seule organisation ne peut pas l'avoir deux fois.
- Permission dediee : `tenant.units`.

---

## Envois programmes et rappels

Etape 8 de « Ordre de construction » (README 2.7) : la carte d'invitation, envoyee a l'echeance
programmee de l'evenement, et les rappels (J-7, J-2, J-1 aux inscriptions sans preuve, jour J
moins 3 h aux billets valides).

### Email de l'invite : facultatif, pas dans le formulaire d'origine

Le formulaire d'inscription (README ecran 4, etape 4) ne demandait a l'origine que nom,
telephone, unite : aucune adresse email. Or 2.7 exige un envoi « par email et WhatsApp », rendu
impossible sans adresse a fournir. Decision du proprietaire du projet (etape 8) : **un champ
email facultatif sur l'ecran 4** (`registrations.email`, colonne nullable).

- WhatsApp reste le canal systematique : le telephone est deja obligatoire, aucune inscription
  n'en est jamais depourvue.
- L'email s'ajoute quand l'invite l'a renseigne. Pas de canal manquant a gerer : `via()` sur
  chaque notification l'inclut ou non selon que la route `mail` est posee.
- Rien a rattraper sur les inscriptions existantes : colonne nullable, aucune migration de
  donnees.

### WhatsApp derriere une interface dediee

`App\Contracts\WhatsAppSender` (une methode, `send(string $to, string $message)`), liee a
`App\Support\LogWhatsAppSender` dans `AppServiceProvider`. Ce palliatif ecrit dans le journal
applicatif, comme `MAIL_MAILER=log` par defaut : aucun identifiant WhatsApp Business API n'est
disponible, et aucun paquet n'est installe pour une implementation qui n'appellerait personne.
Le jour ou l'organisation fournit ses identifiants, seule cette liaison change, jamais les
Actions ni les notifications qui s'adressent au contrat.

Un canal de notification `whatsapp` (`App\Notifications\Channels\WhatsAppChannel`), enregistre
par `Notification::extend()` dans `AppServiceProvider` : point d'extension prevu par Laravel,
pas un envoi ad hoc depuis les Actions. Chaque notification qui veut ce canal expose une methode
`toWhatsApp()`, au meme titre que `toMail()`.

Les inscriptions ne sont pas des utilisateurs authentifies : aucun modele `Notifiable`. Les
notifications sont routees a la demande, `Notification::route('whatsapp', $registration->phone)
->route('mail', $registration->email)->notify(...)` ; `route('mail', null)` laisse simplement le
canal mail hors de `via()`, sans condition a ecrire a l'appel.

### Lien de retour vers l'inscription, sans le jeton de reprise

La carte et les rappels sont envoyes par une tache planifiee, souvent des jours apres la
creation de l'inscription. Le jeton de reprise (README ecran 5, section « Securite ») ne peut
pas servir a construire ce lien : seule son empreinte SHA-256 est stockee, le jeton en clair
n'a jamais touche la base au-dela de l'URL remise a l'invite au moment de l'inscription. Un
processus planifie ne peut donc pas le reconstituer.

`Registration::notificationToken()` calcule un HMAC-SHA256 deterministe sur l'identifiant de
l'inscription et `APP_KEY` : recalculable a l'identique a tout moment, jamais stocke nulle
part. `Registration::signedResumeUrl()` compose l'URL complete
(`/e/{jeton evenement}/register/{id}/link?signature=...`), verifiee par
`RegistrationController::link()` avec `hash_equals` (comparaison a temps constant). Un lien
altere ou pour une autre inscription recoit le meme 404 qu'un jeton de reprise inconnu.

Construction manuelle de l'URL, comme `Event::publicUrl()`, pas `route()`/`url()` ni le
middleware `signed` de Laravel : ces deux mecanismes deriveraient l'hote du lien de la requete
HTTP en cours, qui n'existe pas depuis une tache planifiee. Le sous-domaine du locataire vient
de `Tenant::current()`, comme partout ailleurs ou une URL publique complete se construit.

### Idempotence : une colonne « envoye a » par envoi, jamais un statut

Chaque envoi marque un timestamp sur la ligne concernee (`registrations.card_sent_at`,
`registrations.proof_reminder_j7_sent_at`, `..._j2_sent_at`, `..._j1_sent_at`,
`tickets.reminder_sent_at`) plutot que de deduire l'etat d'une date de reference : une tache
planifiee rejouee toutes les cinq minutes ne doit renvoyer ni la carte ni un rappel deja parti.
Meme principe que `public_token` ou `held_until` ailleurs sur ces modeles.

La carte est envoyee a deux endroits distincts, tous deux gardes par `card_sent_at` : depuis
`ValidatePaymentProof`, immediatement, quand l'echeance programmee de l'evenement est deja
passee au moment de la validation (« une inscription validee apres l'echeance est envoyee a la
validation », README 2.7) ; depuis la tache planifiee, pour toutes les inscriptions confirmees
avant cette echeance.

**Non encore fait** : un vrai client WhatsApp Business API (le palliatif journalise seulement),
et le rendu HTML aux couleurs de marque du locataire pour les emails, qui utilisent pour
l'instant le gabarit texte par defaut des notifications Laravel.

---

## Sauvegardes

`spatie/laravel-backup`, lance par **`convive:backup`** et jamais par `backup:run` seul, qui
archiverait les fichiers sans aucune base.

- **Contenu d'une archive** : `backup-databases/` (la base centrale et celle de chaque
  organisation), `tenant-media/` (fichiers de marque) et `payment-proofs/` (preuves de paiement).
- **Les bases sont copiees par `VACUUM INTO`** (`App\Support\Backup\DatabaseSnapshots`), pas par
  l'export du paquet : celui-ci appelle le programme `sqlite3`, absent d'un serveur ou seul PHP
  est installe, et ne connait que des connexions declarees d'avance, pas une base par
  organisation. La copie est coherente meme pendant une ecriture. Les copies sont retirees du
  serveur des que l'archive est faite. Au passage a PostgreSQL, l'export du paquet reprend la main
  (`backup.source.databases`) ; la classe refuse d'ici la tout autre moteur.
- **Planification** (`routes/console.php`) : sauvegarde a 2 h 30, menage a 3 h 30, surveillance a
  6 h. Conservation : tout pendant 7 jours, puis une par jour, par semaine, par mois, **un an au
  plus** et aucune archive annuelle (`config/backup.php`). C'est le temps pendant lequel une
  organisation effacee peut encore figurer dans une archive : annonce a qui supprime la sienne,
  garde par `BackupRetentionTest`.
- **Destination** : le disque `backups` (`storage/app/backups`) par defaut, donc sur le serveur de
  l'application. Il protege d'une erreur de manipulation, pas de la perte du serveur : en
  production, `BACKUP_DISK` designe un stockage exterieur et `BACKUP_ARCHIVE_PASSWORD` chiffre
  l'archive. L'ecran de sante technique le rappelle tant que ce n'est pas fait.
- **Alertes** : seuls les echecs previennent, a l'adresse `CONVIVE_ALERT_EMAIL`. Sans elle, aucun
  courriel ne part et l'etat ne se lit que sur l'ecran de sante technique.
- **Restauration** : a la main, application arretee. Extraire l'archive, remettre `central.sqlite`
  et les `tenant{id}.sqlite` dans `database/`, `tenant-media/` et `payment-proofs/` dans
  `storage/app/`, puis `php artisan tenants:migrate`. Aucun bouton de restauration dans la
  console : ecraser toutes les bases ne se fait pas d'un clic.

---

## Surveillance : taches planifiees et files

`spatie/laravel-health`, controles declares dans `App\Providers\HealthServiceProvider`, lus par
l'ecran de sante technique de la console (README ecran 31).

- **Controles** : planificateur vivant, taches planifiees, traitement de la file, envois en echec,
  base centrale, Redis, espace disque. Le planificateur les joue chaque minute ; l'ecran les rejoue
  a l'affichage (un resultat garde en cache serait celui d'avant la panne).
- **Releve des taches** (`scheduled_task_runs`, base centrale) : tenu par
  `App\Support\Health\ScheduledTaskRecorder` a partir des evenements du planificateur de Laravel
  (debut, fin, echec, tache ecartee). « En retard » et « en echec » ne sont pas stockes, ils se
  lisent sur les dates (`ScheduledTaskRun::state()`), avec cinq minutes de marge. Le releve ne doit
  jamais empecher une tache de tourner : chaque ecriture est protegee par `rescue()`.
- **Une nouvelle tache planifiee porte une `description()`** : c'est son nom dans le releve. Sa
  traduction va dans `console.health.task_labels`, sous la cle `Str::slug(description, '_')`, dans
  les deux langues ; sans traduction, l'ecran affiche la description telle quelle.
- **Envois en echec** : lus sur `queue.failer`, relances (`queue:retry`) ou ecartes depuis la
  console, gestes journalises. `config/queue.php` range les echecs dans la base **centrale**, la
  seule a porter `failed_jobs`.
- **Alertes** : un controle en echec ecrit a `CONVIVE_ALERT_EMAIL`, une fois par heure au plus. La
  meme adresse recoit les echecs de sauvegarde. Sans elle, rien ne part.
- **Limite a connaitre** : un planificateur arrete ne peut pas envoyer sa propre alerte. Seul un
  service exterieur appele a chaque passage (`SCHEDULE_HEARTBEAT_URL`) previent d'un arret complet ;
  d'ici la, l'arret ne se voit que sur l'ecran.
- **Journal applicatif** (`storage/logs`) : un fichier par jour (`LOG_STACK=daily`), garde 14 jours
  (30 en production), niveau `warning` en production. Jamais `single`, fichier unique sans limite.
  Un 404 attendu (sous-domaine inconnu) n'y ecrit rien (`dontReport` dans `bootstrap/app.php`), et
  les tests n'y ecrivent pas (`LOG_CHANNEL=null`). Ce qui doit se voir va dans un journal de
  l'application (audit, journal central, ecran Securite), pas seulement dans ce fichier.
- **En production**, le serveur doit faire tourner `php artisan schedule:run` chaque minute (cron)
  et un processus `php artisan queue:work` surveille (Supervisor ou equivalent).

---

## Documents juridiques

Politique de confidentialite, conditions d'utilisation et mentions legales : `/confidentialite`,
`/conditions`, `/mentions-legales`, domaine central, lies depuis le pied du site, la creation de
compte et le formulaire d'inscription de l'invite.

- **Les textes sont dans `lang/{fr,en}/legal.php`**, lus cote serveur par
  `App\Support\LegalDocument` et passes en props : le groupe n'est pas dans
  `HandleInertiaRequests::TranslationGroups`, il alourdirait chaque page.
- **Ils decrivent ce que le code fait reellement.** Une duree ou une regle qui change dans le code
  (delai d'effacement, conservation des sauvegardes ou du journal, duree de session, prestataire,
  donnee collectee) se change dans les deux langues **dans le meme commit**, et
  `LegalDocument::Version` avance. `LegalPagesTest` accroche les durees annoncees aux constantes.
- **L'identite de l'editeur et de ses prestataires vient de `config('convive.legal')`**, jamais d'un
  texte en dur. Une valeur vide s'affiche « [a completer] », et `convive:production-check` refuse
  de partir en production tant qu'il en reste une ou que `CONVIVE_LEGAL_REVIEWED` n'est pas vrai.
- **Ces textes ne sont pas un avis juridique** : rediges a partir du fonctionnement du service, ils
  restent a faire relire par un juriste. Aucun bandeau ne le dit sur les pages (decision du
  proprietaire du projet, 2026-10-02) ; seule la verification de production le rappelle.
- **L'acceptation est gardee** : `users.terms_accepted_at` et `users.terms_version`, poses a la
  creation du compte, case obligatoire.
- **Roles** : l'organisation est responsable du traitement des donnees de ses invites, l'editeur
  est son sous-traitant ; l'editeur est responsable pour les comptes, la facturation et le site.

---

## Animation et site produit

Le site produit est la vitrine commerciale, il doit avoir le niveau de finition d'un site
d'editeur de logiciel. Le back-office reste sobre.

Outils :

- **Framer Motion** pour les transitions d'interface : entrees en cascade, changements d'etat,
  transitions de page Inertia, panneaux et dialogues. C'est le choix par defaut dans React.
- **GSAP** avec ScrollTrigger pour les sequences liees au defilement du site produit :
  reveler des sections, epingler un bloc, faire progresser une demonstration, compter des
  chiffres. GSAP n'est charge que sur les pages publiques.
- **Three.js** uniquement si une scene tridimensionnelle apporte quelque chose, par exemple un
  plan de salle en volume ou un billet qui pivote. Chargement paresseux, jamais sur le chemin
  critique, toujours avec une image de repli.

Principes :

- **Le mouvement sert la comprehension.** Il explique une hierarchie, une causalite, un
  changement d'etat. Rien qui decore sans expliquer.
- **Sobriete.** Durees de 0.2 a 0.6 s, courbes douces, deplacements de faible amplitude,
  opacite et translation plutot que rotation et rebond.
- **Une seule animation dominante par section.** Le regard n'a jamais deux choses a suivre.
- **Le produit comme heros.** Montrer les artefacts reels, billet, decompte de reservation,
  file de preuves, plan de salle, plutot que des illustrations abstraites.
- **Performance.** Animer uniquement `transform` et `opacity`, viser 60 images par seconde,
  respecter `prefers-reduced-motion` en desactivant tout mouvement non essentiel, ne jamais
  bloquer l'affichage du premier contenu.
- **Accessibilite.** Aucun contenu visible uniquement apres une animation. Le texte reste
  lisible et selectionnable sans mouvement.
- Reperes de composition : hero pleine largeur sur fond encre avec titre serre, chiffres cles
  en bandeau, grille de cartes asymetrique, frise d'etapes numerotees, tarifs avec un palier
  mis en avant, bande de conclusion.

---

## Securite : exigences non negociables

- **Isolation des locataires** : chaque requete metier filtree par `tenant_id`, via un scope
  global Eloquent plus une verification dans les Policies, et Row Level Security en base.
  Un acces croise renvoie 404, jamais 403, pour ne pas divulguer l'existence de la ressource.
- **Autorisation systematique** : `authorize()` ou middleware `can` sur chaque route du
  back-office. Aucune verification de role uniquement cote client.
- **Concurrence** : calcul des places et creation d'une reservation dans une transaction avec
  `lockForUpdate()` sur l'evenement, plus une contrainte d'unicite en base contre la double
  attribution d'un siege.
- **Idempotence** : cle d'idempotence sur la soumission de preuve et sur la validation, pour
  qu'un double clic ou un rejeu reseau ne cree pas deux enregistrements.
- **Fichiers deposes** : type MIME et extension verifies cote serveur, taille limitee a 5 Mo,
  images reencodees pour supprimer les metadonnees, stockage hors racine web, acces par URL
  signee expirante.
- **Liens de reprise et de liste d'attente** : jeton aleatoire de 32 octets minimum, stocke
  hache, a usage limite et expirant. Aucun identifiant sequentiel devinable dans une URL
  publique.
- **Limitation de debit** : connexion, 2FA, lien public d'inscription, soumission de preuve,
  scan. Verrouillage progressif apres echecs repetes.
- **Protection standard** : CSRF sur toutes les mutations, echappement par defaut, requetes
  preparees uniquement, en-tetes de securite (CSP stricte, HSTS, `X-Frame-Options`,
  `Referrer-Policy`), cookies `Secure` et `HttpOnly`.
- **Secrets** en variables d'environnement uniquement, jamais dans le depot, rotation possible
  sans redeploiement.
- **Journal d'audit inalterable** : ecriture seule, avec acteur, action, ressource, adresse IP
  et agent utilisateur, conservation 24 mois. Donnees personnelles minimisees, procedure de
  suppression sur demande prevue.
- **QR** : jeton signe par cle **asymetrique (Ed25519)**, lie a un locataire et a un evenement,
  invalide apres cloture de l'evenement. Signature asymetrique et non HMAC : le scan fonctionne
  hors ligne, l'appareil de l'agent ne doit jamais detenir de secret de signature, seulement la
  cle publique de verification (voir `SECURITY.md` C2).

Regle generale : **toutes les regles metier de `README.md` section 2 sont appliquees cote
serveur.** L'interface ne fait que les refleter ; elle ne les remplace jamais.

La modelisation de menaces detaillee, les controles obligatoires et la grille de test avant
mise en production sont dans **`SECURITY.md`**. Les points critiques a ne pas contourner :
signature **asymetrique** des jetons QR (l'appareil hors ligne ne detient jamais de secret),
re-authentification forte et delai d'activation sur tout changement de compte de versement,
verification du numero avant reservation pour empecher l'epuisement automatise des places,
et interdiction de tout moteur de gabarit evaluateur sur une chaine fournie par un locataire.

---

## Design et experience utilisateur

- Style epure, flat, anime sobrement. Fond `oklch(0.965 0.004 60)`, cartes blanches sans
  bordure ni ombre, accent indigo `oklch(0.52 0.13 262)`, encre `oklch(0.17 0.006 60)`.
- Les couleurs de **marque** du locataire s'appliquent uniquement au parcours invite, au billet
  et aux messages. Le back-office reste neutre.
- Typographie sans-serif systeme pour l'interface. Playfair Display et Cormorant Garamond
  reserves aux billets et aux liens d'invitation.
- Cibles tactiles au minimum 44 px, contraste du texte au moins 4.5:1, grilles en
  `minmax(0, 1fr)` pour eviter les debordements, transitions de 0.15 a 0.35 s.
- Francais par defaut, anglais complet sur le parcours invite, montants en francs CFA.

Principes a tenir :

- **Un ecran, une decision.** Le parcours invite est sequence en etapes numerotees qui
  indiquent ou se passe l'action, y compris quand elle a lieu hors de l'application.
- **Etats complets pour chaque vue** : chargement, vide, erreur, succes, permission refusee,
  hors ligne. Un ecran vide explique quoi faire.
- **Messages d'erreur utiles** : ce qui s'est passe et l'action suivante, jamais un code
  technique. Les erreurs de formulaire sont attachees au champ concerne.
- **Retour immediat** sur chaque action : etat de chargement sur les boutons, confirmation
  brieve, mise a jour optimiste seulement quand l'echec est reversible.
- **Rien de destructif sans confirmation** : purge manuelle, rejet de preuve, suppression d'un
  membre. La confirmation rappelle la consequence exacte.
- **Accessibilite** : navigation clavier complete, focus visible, roles ARIA fournis par les
  composants headless, aucune information portee par la couleur seule, respect de
  `prefers-reduced-motion`.
- **Mobile d'abord** sur le parcours invite et le scan, densite plus elevee sur le back-office.
  Aucune largeur fixe, grilles qui se replient.
- **Animation utile** : elle explique un changement d'etat, elle ne decore pas.
- **Pas de jargon** dans les textes affiches. L'invite ne lit ni statut technique ni nom de
  table de base de donnees.

---

## Internationalisation

L'application est multilingue. **Francais par defaut**, anglais complet sur le parcours
invite. Aucun texte affiche n'est ecrit en dur dans le code : ni en PHP, ni en TSX.

### Ou vivent les textes

| Fichier                                                                         | Contenu                                                      |
| ------------------------------------------------------------------------------- | ------------------------------------------------------------ |
| `lang/{fr,en}/common.php`                                                       | actions et etats partages : enregistrer, annuler, chargement |
| `lang/{fr,en}/navigation.php`                                                   | menus, fil d'ariane, libelles de navigation                  |
| `lang/{fr,en}/account.php`                                                      | connexion, inscription, 2FA, profil, securite, apparence     |
| `lang/{fr,en}/tenants.php`                                                      | organisations, membres, invitations                          |
| `lang/{fr,en}/organisation.php`                                                 | identite legale, marque, sous-domaine                        |
| `lang/{fr,en}/profiles.php`, `permissions.php`                                  | profils et catalogue de permissions                          |
| `lang/{fr,en}/legal.php`                                                        | documents juridiques, lus cote serveur seulement             |
| `lang/{fr,en}/{auth,validation,passwords,pagination,http-statuses,actions}.php` | traductions du framework, fournies par `laravel-lang/common` |

Les fichiers du framework sont **generes**, pas ecrits a la main : `php artisan lang:add <code>`
pour une nouvelle langue, `php artisan lang:update` pour les mettre a jour. Ne pas y ajouter de
cle applicative, elle serait ecrasee. Les groupes applicatifs portent des noms qui n'entrent
jamais en collision avec ceux du framework.

### Regles

- Les **cles sont en anglais**, en notation pointee et semantique : `tenants.flash.created`,
  `account.login.submit`. Les **valeurs** sont dans la langue concernee.
- Toute nouvelle cle est ajoutee **dans les deux langues** dans le meme changement. Une cle
  presente d'un seul cote est un bogue, pas un travail en cours.
- Cote serveur : `__('groupe.cle')`, avec des parametres nommes (`:name`), jamais de
  concatenation.
- Cote client : le hook `useTranslation()` fournit `t('groupe.cle', { name })`. Hors composant
  React, par exemple dans un `Page.layout`, utiliser `translate(translations, 'groupe.cle')`,
  les props partagees etant disponibles dans la forme fonction du layout.
- Une cle absente rend la cle elle-meme. C'est voulu : le trou se voit a l'ecran et en test.
- Seuls les groupes listes dans `HandleInertiaRequests::TranslationGroups` sont envoyes au
  navigateur. `validation.php` et consorts restent cote serveur : ils n'ont rien a faire dans
  la charge utile d'une page.
- Le mot **tenant** est du jargon : a l'ecran, c'est toujours **organisation**.

### Resolution de la langue

`App\Http\Middleware\SetLocale` tranche dans cet ordre :

1. le parametre `?lang=` s'il designe une langue servie, et il est memorise en session ;
2. la langue memorisee en session ;
3. l'en-tete `Accept-Language`, **uniquement pour un visiteur non authentifie** : le
   back-office reste en francais tant qu'un membre n'a pas choisi explicitement ;
4. le francais.

Le catalogue des langues servies est `App\Support\Locale::supported()`. Une langue absente de
ce catalogue est ignoree, y compris via `?lang=` et via le selecteur : le parametre n'est
jamais transmis tel quel a `App::setLocale()`.

Le composant `LocaleSwitcher` ecrit le choix en session via `PUT /locale` plutot que de
trainer un `?lang=` dans toutes les URL.

### Ce qui n'est pas couvert ici

La traduction du **contenu saisi par un locataire** (nom d'evenement, gabarit de message) est
un autre sujet : elle se fera avec `spatie/laravel-translatable`, sur les colonnes concernees.
Les fichiers de `lang/` ne servent qu'a l'interface.

---

## Ordre de construction

1. Tenants, utilisateurs, roles, authentification et 2FA.
2. Formulaire d'organisation : marque et identite legale.
3. Creation d'evenement et lien public.
4. Inscription, accompagnateurs, unites, calcul du montant.
5. **Reservation, decompte, disponibilite, priorite, purge** : noyau critique, couvert par des
   tests de concurrence.
6. Depot de preuve, file de verification, validation, attribution des tables.
7. Billet, jeton QR, scan, journal des passages.
8. Envois programmes et rappels.
9. Exports, rapports, rapprochement CSV.
10. Notifications, abonnement et facturation.

Tests indispensables avant de considerer une etape terminee :

- Deux inscriptions simultanees sur la derniere place : une seule aboutit.
- Envoi de preuve apres expiration du decompte : refuse.
- Reprise de lien quand l'evenement est complet : refusee, dossier purge.
- Purge a l'echeance et a l'epuisement : places rendues, journal ecrit.
- Scan d'un billet deja utilise : signale, forcage trace.
- Requete d'un locataire sur les donnees d'un autre : impossible.

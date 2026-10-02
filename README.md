# Convive — application web d'inscription événementielle (SaaS)

> Spécification fonctionnelle du produit : **ce qu'il faut construire**.
> Les règles de développement, la pile, les conventions, la sécurité et les principes UI/UX
> sont dans **`CLAUDE.md`**, lu automatiquement par Claude Code à chaque session.
> Maquette interactive de référence : `Convive.dc.html` (prototype HTML, données factices).

---

## 1. Le produit en une page

Convive est un SaaS multi-locataire (multi-tenant) qui permet à une organisation
(association, église, unité, entreprise) de gérer les inscriptions à un événement payant à
places limitées.

Quatre publics :

| Public                         | Accès                                                      | Ce qu'il fait                                                                                                                                                   |
| ------------------------------ | ---------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Invité**                     | lien public, sans compte                                   | s'inscrit, déclare ses accompagnateurs, transfère l'argent hors plateforme, dépose sa preuve de paiement, reçoit sa carte d'invitation                          |
| **Organisation**               | back-office authentifié                                    | crée les événements, vérifie les preuves, gère le plan de salle, exporte, consulte les rapports                                                                 |
| **Agent d'accueil**            | back-office, rôle restreint                                | scanne les QR des billets à l'entrée                                                                                                                            |
| **Éditeur** (l'équipe Convive) | console d'exploitation, comptes distincts, domaine central | suit les organisations et leur abonnement, applique plans et quotas, suspend ou réactive, surveille la santé technique, assure le support sur autorisation (§3) |

**Contrainte structurante : aucun encaissement dans l'application.**
L'invité verse sur un compte Mobile Money / bancaire appartenant à l'organisation, puis
choisit le compte versé (dont découle le canal) + référence de transaction + capture du reçu,
avec une précision facultative. L'organisation valide ou rejette.
Il n'y a **aucune** intégration de passerelle de paiement pour les participations.
(Seul l'abonnement SaaS de l'organisation est, lui, réellement prélevé.)

---

## 2. Règles métier — la partie à ne pas se tromper

Ces règles sont le cœur du produit. Elles doivent être appliquées **côté serveur**, jamais
seulement dans l'interface.

### 2.1 Cycle de vie d'une inscription

```
DRAFT ──▶ HELD ──▶ PROOF_SUBMITTED ──▶ CONFIRMED
  │         │              │
  │         │              └──▶ PROOF_REJECTED ──▶ (retour HELD)
  │         └──▶ EXPIRED (décompte écoulé)
  └──▶ DELETED (purge)

Tout état sauf DELETED ──▶ CANCELLED (annulation par l'organisation)
```

- `DRAFT` — formulaire rempli, aucune place consommée fermement.
- `HELD` — décompte de réservation en cours (**10 min par défaut, paramétrable par
  événement**). Les places sont bloquées pendant ce temps, y compris si le transfert est déjà
  validé sur le téléphone de l'invité.
- `PROOF_SUBMITTED` — preuve déposée, en file de vérification.
- `CONFIRMED` — preuve validée : la table est attribuée, le billet et le QR sont générés.
- `EXPIRED` — décompte écoulé sans preuve : les places retournent au stock. L'envoi de preuve
  doit être **refusé** dans cet état ; l'invité doit relancer une réservation.
- `DELETED` — purgée (voir 2.4).
- `CANCELLED` : annulée par l'organisation, à tout stade, avec un motif. C'est une décision de
  l'organisation, distincte de `EXPIRED` et de `PROOF_REJECTED` qui sont des échecs du parcours
  invité : elle n'est jamais purgée. La place et la table sont libérées aussitôt, le billet
  devient invalide. Le sort du paiement d'une inscription validée est traité en 2.11.

### 2.2 Priorité et disponibilité

- Les inscriptions `CONFIRMED` sont **prioritaires** sur toutes les autres.
- **Aucune** nouvelle inscription au-delà de la capacité de l'événement : le lien public
  affiche un état « complet » et propose la liste d'attente.
- **Toute** reprise d'inscription (lien de reprise) et **toute** relance de réservation doit
  d'abord revérifier le stock côté serveur, avant de redémarrer le décompte.
- Places disponibles = `capacité − places CONFIRMED − places HELD non expirées`.
  Ce calcul doit être fait sous verrou (transaction sérialisable ou `SELECT … FOR UPDATE`)
  pour éviter la survente en cas de clics simultanés.

### 2.3 Liste d'attente

Quand l'événement est complet, l'invité peut rejoindre une liste d'attente ordonnée.
Dès qu'une place se libère (purge, expiration, annulation), le premier de la liste reçoit un
lien **valable 6 h** pour finaliser. Passé ce délai, on passe au suivant.

### 2.4 Purge automatique

Deux déclencheurs, tous deux à implémenter comme tâches planifiées + hooks :

1. **À l'échéance paramétrée** (date + heure, par événement) : toutes les inscriptions non
   finalisées (`DRAFT`, `HELD`, `EXPIRED`, `PROOF_REJECTED`) sont supprimées et leurs places
   rendues au stock.
2. **À l'épuisement des places** : si la capacité est atteinte par les seules inscriptions
   `CONFIRMED`, les inscriptions non finalisées sont supprimées immédiatement.

Chaque purge écrit une entrée dans le journal (nombre de dossiers, places libérées).

### 2.5 Accompagnateurs et unités

- Jusqu'à **10 accompagnateurs** par inscription (plafond paramétrable par événement).
- Le participant **et chaque accompagnateur** doivent renseigner une **unité** obligatoire.
  Liste fermée : `ÉLIAKIM`, `QODESH`, `SENTINELLES`, `ELISHAMA`, `CHOSEN`, `ÉTAT MAJOR`,
  `Aucune`. La valeur `Aucune` est un choix valide ; un champ vide bloque la validation.
  Cette liste doit être **paramétrable par organisation** (ce sont des données de tenant, pas
  des constantes du code).
- Montant dû = `tarif_par_personne × (1 + nombre d'accompagnateurs)`.

### 2.6 Attribution des tables

Automatique, **à la validation de la preuve**, dans l'ordre de validation.
Options par événement :

- table(s) réservée(s) (ex. table 01 pour `ÉTAT MAJOR`) ;
- regroupement par unité ;
- règles de séparation entre unités ;
- un accompagnateur est **toujours** assis avec son invitant.
  Un placement manuel par l'organisateur doit rester possible et tracé.

### 2.7 Envoi des cartes d'invitation

**Programmé**, pas immédiat : une date/heure d'envoi par événement. À cette échéance, toutes
les inscriptions `CONFIRMED` reçoivent leur carte par email et WhatsApp. Une inscription
validée après l'échéance est envoyée à la validation.
Rappels automatiques : `J-7`, `J-2`, `J-1` aux inscriptions sans preuve, et `jour J − 3 h`
aux billets validés.

La carte envoyée à l'invité porte aussi le lien individuel du billet de chacun de ses
accompagnateurs (voir 2.8) : il peut tout recevoir et se charger de les leur transmettre.

**Envoi manuel**, depuis la base d'inscrits, quand l'envoi automatique n'a pas fonctionné ou
qu'un invité a perdu sa carte. Réservé à la permission « Envoyer des messages »
(`messages.send`), pour les seules inscriptions `CONFIRMED`. La base d'inscrits indique si la
carte est partie, et quand.

- **Invité principal** : envoyer ou renvoyer sa carte depuis l'application (WhatsApp, et email
  s'il en a donné un), même si elle est déjà partie ; copier le lien de sa carte ; ou ouvrir
  WhatsApp sur son numéro avec le message déjà rédigé, pour l'envoyer depuis son propre
  téléphone. Le message comprend les billets des accompagnateurs.
- **Accompagnateurs** : aucun numéro n'est recueilli pour eux. Pour chacun, copier le lien de
  son seul billet, ou ouvrir WhatsApp avec le message déjà rédigé, le destinataire étant choisi
  dans le téléphone de l'organisateur. Jamais la carte de l'invité principal, qui donne accès
  aux billets de tout le groupe.
- Le lien est une clé d'accès au billet : chaque envoi, copie de lien ou ouverture de WhatsApp
  est journalisé, par personne, avec son auteur. Un envoi manuel compte dans le quota
  d'envois du plan.

### 2.8 Authenticité des billets

- **Un billet par personne** (décision du 2026-09-27) : l'invité reçoit un billet à son nom et un
  billet nominatif par accompagnateur, chacun avec son propre QR. Chaque personne entre quand elle
  arrive, seule ou en groupe, sans forçage. L'invité transmet à chaque accompagnateur son billet
  (lien individuel, partageable par WhatsApp depuis sa page).
- **Billets en PDF**, pour l'entrée sans connexion stable : l'invité télécharge depuis sa page
  tous les billets du groupe, une page par personne ; un accompagnateur télécharge son seul
  billet depuis son lien individuel. Le QR imprimé est le même jeton signé que celui de la page,
  vérifié de la même façon au scan. Mêmes signatures que les liens, donc accessible à qui peut
  déjà voir le billet, et plus du tout une fois l'inscription annulée.
- Le QR encode un **jeton signé côté serveur** par signature **asymétrique (Ed25519)**, jamais
  un simple identifiant. Le scan étant hors ligne, l'appareil de l'agent ne détient que la clé
  publique de vérification ; un HMAC symétrique exposerait le secret sur chaque téléphone
  (voir `SECURITY.md` C2).
- Chaque billet ne s'utilise qu'une fois. Un billet scanné une seconde fois est signalé « déjà
  scanné » avec l'heure et l'agent du premier passage ; l'agent peut forcer l'entrée, ce qui est
  journalisé.
- Le scan fonctionne **hors ligne** (file locale, synchronisation au retour du réseau).
- Chaque scan (accepté, refusé, forcé) est journalisé : agent, heure, poste.

### 2.9 Détection des preuves douteuses

À la réception d'une preuve, calculer et afficher des signaux :

- empreinte perceptuelle de l'image déjà vue sur une autre inscription ;
- référence de transaction déjà utilisée ;
- référence absente du relevé importé ;
- montant du relevé ≠ montant dû ;
- précision laissée par l'invité (information à lire, pas une anomalie).

### 2.10 Rapprochement du relevé

Import CSV des relevés Mobile Money / bancaires (colonnes : date, référence, émetteur,
montant). Rapprochement automatique par référence puis par montant + nom approchant.
Quatre issues : rapprochée, montant divergent, nom approchant, sans inscription.

### 2.11 Paiement d'une inscription annulée

L'organisation peut annuler une inscription à tout stade, y compris `CONFIRMED`. L'application
ne rembourse jamais elle-même : elle ne déplace pas d'argent, elle garde la trace de ce qu'il
devient.

- **Sans paiement validé** (réservation, preuve en attente ou rejetée) : l'annulation ne porte
  que le motif, aucun paiement n'est à traiter.
- **Inscription validée** : l'annulation fixe le sort du paiement, parmi trois choix.
  - **À rembourser**, choix présélectionné : l'organisation doit la somme, qui reste visible
    tant qu'elle n'est pas remboursée.
  - **Remboursé** : moyen, date, référence de transaction si elle existe, et frais de
    transaction.
  - **Conservé** : motif obligatoire (annulation hors délai, don...).
- **Tout ou rien** : on rembourse l'intégralité du montant payé, **moins les frais de
  transaction du remboursement**, à la charge de l'invité. Les frais sont saisis tels que
  l'opérateur les a prélevés, jamais calculés par un taux. Ils ne peuvent pas atteindre le
  montant payé. Le remboursement partiel pour d'autres raisons (retenue, pénalité) n'est pas
  prévu à ce stade.
- **Marquer comme remboursé** : une annulation « À rembourser » passe à « Remboursé » quand
  l'argent est parti. C'est le seul changement possible après coup, et l'inscription reste
  annulée.
- **Permission dédiée** pour fixer ou modifier le sort d'un paiement, distincte de
  l'annulation.
- **Journal** : chaque choix, avec l'auteur, la date, le montant remboursé et les frais.
- **Totaux** de l'événement (rapport, tableau de bord) : encaissé (toutes les preuves
  validées, y compris celles des inscriptions annulées), remboursé, frais imputés, à
  rembourser, et net (encaissé moins remboursé). Tant qu'il reste des sommes à rembourser, le
  tableau de bord le signale.
- **L'invité est prévenu** de l'annulation (WhatsApp, et email s'il en a donné un) : le motif,
  et le sort de son paiement (remboursement en cours, remboursé le … par … avec le montant
  net, ou non remboursable avec le motif).

---

## 3. Modèle SaaS

- **Tenant = organisation.** Isolation stricte des données par **base de données séparée par
  locataire**, via `stancl/tenancy` : chaque organisation a sa propre base (un fichier
  `.sqlite` aujourd'hui, une base PostgreSQL demain, sans rien a changer au mecanisme). Une
  base centrale partagee ne garde que ce qui traverse les organisations : le registre des
  organisations, les utilisateurs, les appartenances et invitations, l'identite legale.
  Le modèle s'appelle `Tenant` dans le code ; à l'écran, il s'appelle toujours
  « organisation ». Les détails sont dans `CLAUDE.md`, section « Multi-locataire ».
- Un utilisateur peut appartenir à plusieurs organisations ; sélecteur d'espace dans l'en-tête.
- **Informations d'organisation** (le « tenancier ») : logo carré, bandeau des liens publics,
  cachet, signature du responsable, couleurs de marque, raison sociale, nom affiché, nom du
  responsable signataire, forme juridique, numéro RCCM/registre, numéro de contribuable,
  adresse du siège, email, téléphone, sous-domaine.
  Le cachet et la signature sont apposés sur les billets, reçus et exports PDF.
- **Plans** : Essentiel (1 événement actif, 200 inscrits, 2 membres), Association (5 / 1 000 /
  10, + rapprochement + rapports), Institution (illimité, domaine propre, SSO).
  Les quotas doivent être **appliqués**, pas seulement affichés.
- **Facturation** : moyen de paiement enregistré, prélèvement mensuel, relance J+3 en cas
  d'échec, suspension de l'espace à J+10 d'impayé, historique de factures.

### Profils et permissions : entierement dynamiques

Les roles ne sont pas figes dans le code. L'exploitant cree ses propres **profils** et y
attache les permissions qu'il veut ; tout utilisateur portant ce profil herite de ces
permissions, immediatement.

**Ce que l'exploitant peut faire** (ecran Profils, section Profil & equipe) :

- creer un profil, le nommer, le decrire ;
- cocher les permissions parmi le catalogue de l'application, groupees par domaine ;
- dupliquer un profil existant pour en deriver une variante ;
- renommer un profil, modifier ses permissions, le desactiver ;
- affecter un profil a un membre, en changer a tout moment ;
- voir, pour chaque profil, le nombre de membres concernes avant d'enregistrer une
  modification.

**Regles a respecter :**

- Les profils sont des donnees, propres a chaque locataire. Deux organisations peuvent avoir
  des profils differents portant le meme nom.
- Les **permissions**, elles, sont un catalogue fixe declare dans le code (une constante ou un
  enum), car chacune correspond a un point de controle reel. L'exploitant compose avec ce
  catalogue, il n'en invente pas.
- Un profil systeme **Proprietaire** existe par defaut, detient toutes les permissions et
  n'est ni modifiable ni supprimable. Un locataire garde toujours au moins un Proprietaire
  actif : la derniere affectation ne peut pas etre retiree.
- Trois profils sont pre-crees a l'ouverture d'un espace, comme point de depart modifiable :
  Tresorier, Hotesse, Lecture.
- Un profil affecte a des membres ne peut pas etre supprime : il faut d'abord reaffecter ces
  membres, et la confirmation indique combien ils sont.
- Une modification de profil prend effet immediatement : les caches de permissions sont
  invalides pour tous les membres concernes, et leur session est reevaluee a la requete
  suivante.
- Toute creation, modification, suppression de profil et toute affectation sont **journalisees**
  avec l'acteur, l'avant et l'apres.
- Un utilisateur peut appartenir a plusieurs organisations avec un profil different dans
  chacune. Ses permissions sont toujours celles du locataire courant.
- Aucune verification de permission uniquement cote client. Le front recoit la liste des
  permissions effectives pour masquer ce qui est interdit, mais chaque route et chaque action
  revalide cote serveur.

**Implementation** : `spatie/laravel-permission` en mode `teams` (le team etant le locataire),
les profils etant ses roles et le catalogue ses permissions. La table pivot porte le
`tenant_id`, de sorte qu'un profil ne fuit jamais d'un locataire a l'autre.

**Catalogue de permissions** (a completer au fil des ecrans, jamais reduit sans migration) :

| Domaine             | Permissions                                                                                 |
| ------------------- | ------------------------------------------------------------------------------------------- |
| Evenements          | `events.view`, `events.create`, `events.update`, `events.duplicate`, `events.close`         |
| Inscriptions        | `registrations.view`, `registrations.export`, `registrations.purge`, `registrations.cancel` |
| Preuves             | `proofs.view`, `proofs.approve`, `proofs.reject`                                            |
| Rapprochement       | `reconciliation.import`, `reconciliation.resolve`                                           |
| Plan de salle       | `seating.view`, `seating.assign`, `seating.constraints`                                     |
| Controle a l'entree | `scan.perform`, `scan.force`, `scan.log.view`                                               |
| Envois              | `messages.schedule`, `messages.send`, `messages.templates`                                  |
| Rapports            | `reports.view`, `reports.export`                                                            |
| Espace et marque    | `tenant.branding`, `tenant.legal`, `tenant.domain`                                          |
| Equipe et profils   | `team.view`, `team.invite`, `team.remove`, `profiles.manage`                                |
| Abonnement          | `billing.view`, `billing.manage`                                                            |
| Journal             | `audit.view`                                                                                |

### Profils pre-crees a l'ouverture d'un espace

Point de depart modifiable, pas une contrainte.

| Action                           | Proprietaire | Tresorier | Hotesse | Lecture |
| -------------------------------- | :----------: | :-------: | :-----: | :-----: |
| Créer / modifier un événement    |      ✓       |           |         |         |
| Valider une preuve de paiement   |      ✓       |     ✓     |         |         |
| Voir la base d'inscrits complète |      ✓       |     ✓     |         |    ✓    |
| Exporter Excel / PDF             |      ✓       |     ✓     |         |         |
| Scanner à l'entrée               |      ✓       |           |    ✓    |         |
| Modifier le plan de salle        |      ✓       |           |         |         |
| Gérer l'abonnement et la marque  |      ✓       |           |         |         |

2FA obligatoire pour Propriétaire et Trésorier, et **appliquée**. Chaque profil porte le
drapeau `requires_two_factor` ; ses porteurs n'atteignent pas le back-office de l'organisation
tant qu'ils n'ont pas activé la double authentification. Le profil système l'exige toujours,
drapeau ou pas. Changer d'organisation et quitter une organisation restent possibles, pour ne
pas enfermer un membre bloqué. Le contrôle est désactivé en local seulement, afin de ne pas
bloquer le compte de développement.

**État actuel.** Le moteur est en place et testé : catalogue de permissions figé, profils par
locataire via `spatie/laravel-permission` en mode `teams`, profils de départ créés à
l'ouverture d'un espace, garde-fous contre l'élévation de privilèges, journalisation via
`spatie/laravel-activitylog`, et écran de gestion des profils. Les règles précises sont dans
`CLAUDE.md`, section « Profils et permissions ».

### Console d'exploitation (l'éditeur)

Décision du propriétaire du projet (2026-09-28). L'éditeur gère le SaaS depuis une console qui
lui est propre, séparée du back-office des organisations. Elle administre **des organisations
clientes, pas leurs événements**.

**Accès**

- **Comptes éditeur distincts des comptes d'organisation.** Un compte éditeur n'ouvre aucun
  espace d'organisation ; un compte d'organisation n'ouvre pas la console.
- **2FA obligatoire pour tout compte éditeur, sans exception de profil ni d'environnement**, y
  compris en local : le seeder fournit un secret TOTP de développement connu.
- **Domaine dédié** sur le domaine central (`admin.<domaine>`). Ses routes sont inaccessibles
  depuis un sous-domaine d'organisation, et inversement.
- **Profils éditeur** sur un catalogue de permissions fermé, distinct de celui des
  organisations : Fondateur (tout), Support (lecture des organisations, accès de support),
  Comptabilité (facturation, plans, suspensions).

**Ce que l'éditeur voit : des métadonnées, jamais le contenu**

- Par défaut, pour chaque organisation : nom affiché, plan, état de l'abonnement, date
  d'ouverture, quotas consommés (événements actifs, inscrits, membres), dernière activité.
- **Ni les inscrits, ni les preuves, ni les montants collectés, ni les numéros de versement.**
  L'isolation des données est la promesse centrale du produit, elle vaut aussi face à
  l'éditeur.
- Les compteurs vivent dans une table centrale tenue à jour à l'écriture par l'organisation.
  La console ne parcourt jamais les bases des organisations à chaque affichage (même principe
  que la vitrine).

**Accès de support**

- L'éditeur ne lit le contenu d'une organisation que si **un Propriétaire de cette
  organisation lui a ouvert un accès de support** depuis son propre back-office (écran 25).
- Accès limité à **24 heures au plus**, révocable à tout moment, en **lecture seule**,
  nominatif (un compte éditeur précis).
- Tant qu'il est ouvert, l'organisation voit un bandeau permanent.
- Chaque page consultée est journalisée **dans le journal de l'organisation**, qu'elle peut
  relire, et dans le journal central.

**Actions sur une organisation**

- **Changer de plan.** Une montée prend effet immédiatement. Une descente prend effet à la fin
  de la période payée, et elle est refusée tant que la consommation dépasse les quotas du plan
  visé ; le message dit lesquels.
- **Offrir ou prolonger une période d'essai**, avec une date de fin.
- **Suspendre et réactiver.** Suspension automatique à J+10 d'impayé (voir Facturation plus
  haut), ou manuelle avec un motif obligatoire. Une organisation suspendue a son back-office en
  lecture seule (exports toujours possibles) et ses liens publics fermés aux nouvelles
  inscriptions, avec un message neutre. **Les billets déjà émis restent valides au scan** : un
  impayé de l'organisation ne refoule pas ses invités à l'entrée.
- **Supprimer une organisation**, uniquement à sa demande écrite. Suppression annulable pendant
  **30 jours**, puis effacement de sa base et de ses fichiers. Le journal central garde la trace
  de l'opération, sans les données.
- **Retirer une annonce de la vitrine** jugée abusive, avec un motif transmis à l'organisation.

**Journal central**

Toute action de la console est journalisée : acteur, organisation concernée, avant et après,
adresse IP. Conservation 24 mois, comme le journal des organisations.

---

## 4. Écrans à livrer

### Public

1. **Site produit** — hero, bénéfices, quatre étapes, tarifs, appel à l'action.
2. **Connexion** — identifiants → code 2FA à 6 chiffres → espace ; création d'un espace
   (essai 14 jours) qui redirige vers le formulaire d'organisation.

### Invité (PWA, bilingue FR/EN, mobile d'abord)

3. **Lien d'inscription** — visuel, date, heure, capacité, tarif, places restantes (si
   l'organisateur choisit de les afficher, masquées par défaut ; « Complet » toujours signalé),
   date limite.
4. **Fiche** — nom, téléphone, email (facultatif), unité, compteur d'accompagnateurs (0–10) avec
   nom + unité chacun, total recalculé en direct. L'email facultatif : WhatsApp (le téléphone
   est déjà recueilli) reste le canal systématique de la carte d'invitation (2.7), l'email s'y
   ajoute quand l'invité l'a renseigné.
5. **Paiement en trois étapes** — (1) comptes de versement avec bouton copier et consigne de
   mettre son nom en motif du transfert ; (2) compte versé, référence, capture du reçu,
   précision facultative ;
   (3) envoi avant la fin du décompte. Décompte visible, alerte sous 2 min, blocage de l'envoi
   à expiration, bouton « vérifier les places et relancer ».
6. **Preuve reçue** — en attente de vérification.
7. **Billet** — un billet par personne du groupe (invité et chaque accompagnateur) : nom, unité,
   numéro de table, QR ; bouton d'envoi par WhatsApp du billet de chaque accompagnateur ; mention
   d'envoi programmé.
8. **Inscription enregistrée sans preuve** — lien de reprise (copier / se l'envoyer), échéance.
9. **Reprendre mon inscription** — récapitulatif, s'il reste assez de places pour le groupe
   (avec le nombre si l'organisateur l'affiche), vérification avant de continuer.
10. **Complet** — état complet + liste d'attente (position, lien de 6 h).
11. **Inscription supprimée** — après purge ou épuisement des places.

### Back-office

12. **Mes événements** — cartes avec statut, remplissage, montant collecté, preuves en attente ;
    créer, dupliquer, partir d'un modèle.
13. **Nouvel événement** — assistant 3 étapes : identité (nom, sous-titre, date, heure, lieu,
    visuel, couleurs) / places & tarif (groupes de tables, par exemple « 3 tables de 12 » et
    « 20 tables de 8 », tarif, plafond d'accompagnateurs, comptes de versement) / échéances
    (date limite, purge, envoi programmé, durée de réservation). La capacité est la somme des
    places de toutes les tables (décision du 2026-09-29 : les tables n'ont pas toutes le même
    nombre de places).
14. **Espace & marque** — le formulaire d'organisation décrit en §3.
15. **Gabarit du billet** — modèles, éléments activables (logo, cachet, signature, liste des
    accompagnateurs), aperçu, impression des listes de contrôle par table.
16. **Abonnement** — consommation des quotas, plans, moyen de paiement, recouvrement, factures.
17. **Tableau de bord** — inscrits, preuves validées, preuves à vérifier, sans preuve, places
    restantes, inscriptions par jour, preuves par canal, occupation des tables, activité récente.
18. **Preuves à vérifier** — file avec reçu, montant dû, canal, référence, précision de
    l'invité, unités, signaux
    d'anomalie, valider / rejeter / ouvrir le reçu.
19. **Rapprochement** — import CSV, statistiques, table des lignes avec action par ligne.
20. **Base d'inscrits** — recherche, filtres (tous / validées / à vérifier / sans preuve /
    annulés), table, pagination, personnes entrées par inscription, exports Excel / PDF / CSV /
    listes de contrôle, états vides.
21. **Plan de salle** — grille de tables, panneau de table avec occupants, regroupement par
    unité, contraintes, déplacement manuel, capacité ajustable table par table (jamais sous le
    nombre de personnes déjà placées).
22. **Rapports post-événement** — présence et absents comptés par personne, recettes, durée
    moyenne de contrôle, présence et recettes par unité, export PDF.
23. **Historique des actions** — date, action, ce qui s'est passé, par qui, depuis quelle
    adresse ; conservation 24 mois, sans modification ni effacement possibles.
24. **Réglages événement** — identité visuelle, places totales, échéances, rappels, règles
    (envoi programmé, attribution automatique, inscription sans preuve, lisibilité de la preuve,
    purge à l'épuisement, réservation temporaire).
25. **Profil & équipe** — profil, 2FA, matrice de permissions, préférences de notification par
    type d'alerte (appli / email / les deux), membres et invitations, **accès du support** :
    ouvrir un accès à l'équipe Convive (compte concerné, durée de 24 h au plus), le révoquer,
    relire ce qui a été consulté.

### Agent d'accueil (mobile)

26. **Scan** — viseur, compteur de personnes entrées sur les attendues, trois résultats : valide
    (nom de la personne, unité, table, « accompagnateur de … » le cas échéant), déjà scanné
    (heure et agent du premier passage, forçage possible), refusé ; hors ligne ; derniers passages.

### Console d'exploitation (éditeur)

27. **Organisations** : liste avec recherche et filtres (plan, essai, impayé, suspendue),
    quotas consommés, dernière activité.
28. **Fiche organisation** : identité, plan, historique d'abonnement, factures, consommation,
    accès de support en cours, journal des actions de l'éditeur ; changer de plan, prolonger
    l'essai, suspendre ou réactiver, programmer la suppression.
29. **Recouvrement** : impayés, relances envoyées, suspensions à venir (J+10), paiements en
    échec.
30. **Plans** : prix et quotas d'Essentiel, Association, Institution. Une modification ne
    touche pas les abonnements en cours avant leur renouvellement.
31. **Santé technique** : organisations dont la base est absente ou pas à jour des migrations,
    tâches planifiées en retard, files bloquées, sauvegardes.
32. **Vitrine** : annonces publiées, retrait avec motif.
33. **Journal central** : actions de la console et opérations sur les organisations.
34. **Équipe éditeur** : comptes éditeur, profils, 2FA, invitations.

---

## 5. Notifications

Événements notifiables : preuve reçue, réservation expirée, preuve rejetée, places épuisées,
purge effectuée, invitation d'équipe en attente, billet refusé à l'entrée, inscription annulée.
Canaux configurables par type : in-app, email, ou les deux. Cloche avec compteur de non-lus,
clic → écran concerné, « tout marquer comme lu ».

---

## 6. Pile technique imposée

- **Laravel 13** avec **React intégré via Inertia.js** et TypeScript. Pas d'API REST
  séparée pour le back-office : les contrôleurs Laravel rendent des pages Inertia.
- **Vite** pour le bundling, **Tailwind CSS** pour le style.
- **PostgreSQL** (MySQL 8 accepté si l'hébergement l'impose) pour la base de chaque
  locataire, une base physiquement séparée par organisation via `stancl/tenancy` (pas de
  `tenant_id` sur les tables métier : la base elle-même est la frontière). **Le projet tourne
  aujourd'hui sur SQLite**, par décision du propriétaire : chaque locataire a son propre
  fichier `.sqlite`, le verrouillage de ligne reste indisponible et compensé par des
  contraintes d'unicité en base et des tests d'isolation systématiques. Les modalités exactes
  sont dans `CLAUDE.md`, sections « Base de données » et « Multi-locataire ».
- **PWA** pour le parcours invite et l'ecran de scan : service worker, file d'attente locale,
  synchronisation au retour du reseau.
- **Files et jobs** : queues Laravel (Redis) plus `schedule:run` pour les taches planifiees
  (purges, envois programmes, rappels, expiration des reservations et des liens de liste
  d'attente).
- **Stockage** : disque S3-compatible via `Storage`, URL signees temporaires pour les recus,
  logos, cachets et signatures. Aucun fichier sensible servi depuis `public/`.
- **Auth** : Laravel Fortify ou Breeze adapte a Inertia, TOTP pour la 2FA, sessions chiffrees
  en cookie httpOnly, `SameSite=Lax`.
- **Envois** : Mail Laravel (Resend ou SES) et WhatsApp Business API encapsulee dans un
  service dedie avec interface, pour rester remplacable.
- **QR** : jeton signe par cle asymetrique Ed25519 (cle privee serveur, cle publique embarquee
  sur l'appareil de scan pour la verification hors ligne), contenant `tenant_id`, `event_id`,
  `registration_id`, `nonce`, `not_after` et une version de cle pour la rotation.
- **Exports** : `maatwebsite/excel` pour le XLSX, moteur de rendu HTML vers PDF pour les
  billets, recus et listes de controle.

### Composants d'interface : reutiliser, ne pas reecrire

Aucun composant de base ne doit etre code a la main. Installer une bibliotheque headless
accessible et composer avec elle.

- Base recommandee : **shadcn/ui** (Radix UI plus Tailwind), installe composant par composant
  avec son CLI. Couvre bouton, champ, zone de texte, select, case a cocher, interrupteur,
  onglets, dialogue, infobulle, menu deroulant, notification, tableau, badge, calendrier.
- Icones : **lucide-react**. Aucun emoji, aucune icone dessinee a la main en SVG.
- Tableaux de donnees : **TanStack Table** pour le tri, la pagination et le filtrage.
- Formulaires : **React Hook Form** plus **Zod**, avec les memes regles de validation cote
  serveur dans les Form Requests Laravel.
- Graphiques : **Recharts**.
- QR : **bwip-js** ou equivalent cote serveur pour la generation, **@zxing/browser** pour la
  lecture camera.
- Dates : **date-fns** avec la locale francaise.

Regle : avant d'ecrire un composant, verifier qu'il n'existe pas deja dans la bibliotheque
installee ou dans le dossier `resources/js/components/ui`. Un composant maison ne se justifie
que pour un element propre au metier (carte d'invitation, plan de salle, viseur de scan,
compte a rebours de reservation).

### Conventions de code

- **Respecter le code existant.** Lire les conventions du projet avant d'ajouter du code, et
  s'y conformer plutot que d'introduire un nouveau style.
- Nommage semantique et explicite, en anglais dans le code, en francais dans les textes
  affiches. `SeatHold`, `ProofSubmission`, `RegistrationPurgeService`, `remainingSeats` et non
  `data`, `tmp`, `handleClick2`.
- Structure Laravel : controleurs minces, **Form Requests** pour la validation, **Policies**
  pour les autorisations, **Actions** ou **Services** pour la logique metier, **Events** et
  **Listeners** pour les effets de bord, **Jobs** pour l'asynchrone, **Enums** PHP pour les
  statuts. Aucune requete Eloquent dans une vue ou un composant React.
- Structure React : un composant par fichier, des pages dans `resources/js/pages`, des
  composants partages dans `resources/js/components`, des hooks dans `resources/js/hooks`.
  Pas de logique metier dans les composants.
- Types partages : generer ou maintenir des types TypeScript decrivant les props Inertia, pour
  que le contrat serveur vers client soit verifie a la compilation.
- **Commentaires seulement quand ils sont necessaires** : une regle metier non evidente, un
  choix de verrouillage, une contrainte reglementaire. Pas de commentaire qui paraphrase le
  code, pas de banniere decorative.
- Aucun **emoji** et aucun **tiret cadratin** dans le code, les commentaires, les messages de
  commit ou les textes affiches. Utiliser la virgule, le deux-points ou la parenthese.
- Formatage automatique : **Pint** pour PHP, **Prettier** et **ESLint** pour TypeScript, en
  hook de pre-commit.
- Tests : **Pest** pour le back, **Vitest** plus **Testing Library** pour le front, tests de
  bout en bout sur les parcours critiques.

### Securite : exigences non negociables

- **Isolation des locataires** : chaque requete metier est filtree par `tenant_id`, via un
  scope global Eloquent plus une verification dans les Policies. Row Level Security au niveau
  de la base en complement. Toute tentative d'acces croise renvoie 404, jamais 403, pour ne
  pas divulguer l'existence de la ressource.
- **Autorisation systematique** : `authorize()` ou middleware `can` sur chaque route du
  back-office. Aucune verification de role uniquement cote client.
- **Concurrence** : le calcul des places disponibles et la creation d'une reservation se font
  dans une transaction avec `lockForUpdate()` sur l'evenement. Une contrainte d'unicite en
  base empeche la double attribution d'un siege.
- **Idempotence** : cle d'idempotence sur la soumission de preuve et sur la validation, pour
  qu'un double clic ou un rejeu reseau ne cree pas deux enregistrements.
- **Fichiers deposes** : type MIME et extension verifies cote serveur, taille limitee a 5 Mo,
  images reencodees pour supprimer les metadonnees, stockage hors racine web, acces par URL
  signee expirante. Antivirus si l'hebergement le permet.
- **Liens de reprise et de liste d'attente** : jeton aleatoire de 32 octets minimum, stocke
  hache, a usage limite et expirant. Aucun identifiant sequentiel devinable dans une URL
  publique.
- **Limitation de debit** : sur la connexion, la 2FA, le lien public d'inscription, la
  soumission de preuve et le scan. Verrouillage progressif apres echecs repetes.
- **Protection standard** : CSRF actif sur toutes les mutations, echappement par defaut,
  requetes preparees uniquement, en-tetes de securite (CSP stricte, HSTS, `X-Frame-Options`,
  `Referrer-Policy`), cookies `Secure` et `HttpOnly`.
- **Secrets** : uniquement en variables d'environnement, jamais dans le depot. Rotation
  possible sans redeploiement du code.
- **Journal d'audit inalterable** : ecriture seule, avec acteur, action, ressource, adresse IP
  et agent utilisateur. Conservation 24 mois. Les donnees personnelles sont minimisees et une
  procedure de suppression sur demande est prevue.
- **Signature du QR** : asymetrique (Ed25519), verifiable hors ligne sans secret sur l'appareil,
  jeton lie a un evenement et a un locataire, invalide apres cloture de l'evenement.

### Mesure d'audience (Google Analytics)

Décision du propriétaire du projet (2026-09-29) : Google Analytics 4 mesure la fréquentation du
site commercial. Le script officiel `gtag.js` suffit, sans paquet supplémentaire.

- **Pages mesurées : l'accueil et la vitrine des évènements à la une, rien d'autre.** Le
  back-office désigne des organisations dans ses adresses, le parcours invité y porte des jetons
  de reprise et des signatures : aucune de ces adresses ne doit partir chez un tiers. En quittant
  une page commerciale, l'envoi est coupé par le drapeau officiel de Google
  (`window['ga-disable-<identifiant>']`).
- **Consentement préalable.** Un bandeau demande l'accord du visiteur ; rien n'est chargé ni
  déposé chez Google avant « Accepter ». Un refus efface les cookies `_ga` éventuels. Le choix est
  mémorisé dans le navigateur du visiteur et se révise à tout moment depuis le lien « Mesure
  d'audience » du pied de page.
- **Données minimales.** Une page vue ne porte que l'origine et le chemin, jamais les paramètres
  de l'adresse. Signaux Google et personnalisation publicitaire désactivés, stockage publicitaire
  refusé (mode consentement de Google).
- **Configuration.** Variable d'environnement `GOOGLE_ANALYTICS_ID` (identifiant de mesure
  `G-XXXXXXXXXX`). Vide en local et en test : rien n'est chargé, et la politique de sécurité du
  contenu (CSP) reste fermée à Google. Renseignée, la CSP autorise les domaines de Google
  (`www.googletagmanager.com`, `*.google-analytics.com`, `*.analytics.google.com`).
- **Réglage à faire dans Google Analytics.** Dans le flux de données, désactiver « Changements de
  page basés sur les événements de l'historique du navigateur » (mesure améliorée) : les pages
  vues sont envoyées par l'application elle-même, page commerciale par page commerciale.
- **Mentions légales.** Le traitement doit figurer dans la politique de confidentialité du site
  (finalité, durée de conservation réglée dans Google Analytics, transfert hors de Côte d'Ivoire),
  conformément à la loi ivoirienne n° 2013-450 sur les données personnelles et au RGPD pour les
  visiteurs européens.

### Entités principales

```
Tenant, TenantBranding, Plan, Subscription, Invoice
User, TenantMembership (role), Session
Event, EventSettings, PaymentAccount, Unit
Registration, Companion, SeatHold, Payment Proof, WaitlistEntry
Table, SeatAssignment, SeatingConstraint
Ticket (jeton QR), ScanEvent
StatementImport, StatementLine, ReconciliationMatch
Notification, NotificationPreference, AuditLog
PlatformUser, PlatformProfile, PlatformAuditLog
TenantUsage (compteurs centraux), SupportAccessGrant, TenantSuspension
```

---

## 7. Design et experience utilisateur

- Style : épuré, flat, animé sobrement. Fond `oklch(0.965 0.004 60)`, cartes blanches sans
  bordure ni ombre, accent indigo `oklch(0.52 0.13 262)`, encre `oklch(0.17 0.006 60)`.
- Les couleurs de **marque** (bordeaux/or par défaut) s'appliquent uniquement au parcours
  invité, au billet et aux messages ; le back-office reste neutre.
- Typographie : sans-serif système pour l'interface ; Playfair Display et Cormorant Garamond
  réservés aux billets et aux liens d'invitation.
- Cibles tactiles ≥ 44 px, contraste texte ≥ 4.5:1, grilles `minmax(0, 1fr)` pour éviter les
  débordements, transitions courtes (0.15–0.35 s).
- Francais par defaut, anglais complet sur le parcours invite, montants en francs CFA.
  Le socle multilingue est en place : fichiers `lang/fr` et `lang/en`, résolution de la langue
  par paramètre d'URL, session ou en-tête du navigateur, et sélecteur de langue. Aucun texte
  affiché n'est écrit en dur. Les règles sont dans `CLAUDE.md`, section
  « Internationalisation ».

### Principes UI/UX a tenir

- **Un ecran, une decision.** Le parcours invite est sequence en etapes numerotees qui
  indiquent ou se passe l'action, y compris quand elle a lieu hors de l'application.
- **Etats complets pour chaque vue** : chargement, vide, erreur, succes, permission refusee,
  hors ligne. Un ecran vide explique quoi faire, il ne se contente pas d'etre vide.
- **Messages d'erreur utiles** : dire ce qui s'est passe et l'action suivante, jamais un code
  technique. Les erreurs de formulaire sont attachees au champ concerne.
- **Retour immediat** sur chaque action : etat de chargement sur les boutons, confirmation
  brieve, mise a jour optimiste seulement quand l'echec est reversible.
- **Rien de destructif sans confirmation** : purge manuelle, rejet de preuve, suppression d'un
  membre. La confirmation rappelle la consequence exacte.
- **Accessibilite** : navigation clavier complete, focus visible, roles ARIA fournis par les
  composants headless, contraste verifie, aucune information portee par la couleur seule,
  respect de `prefers-reduced-motion`.
- **Mobile d'abord** sur le parcours invite et le scan, densite plus elevee sur le back-office.
  Aucune largeur fixe, grilles qui se replient.
- **Animation utile** : elle explique un changement d'etat ou une transition, elle ne decore
  pas. Duree courte, jamais bloquante.
- **Pas de jargon** dans les textes affiches. L'invite ne lit ni statut technique ni nom de
  table de base de donnees.

---

## 8. Ordre de construction conseillé

**Migration d'architecture en cours, avant de poursuivre a l'etape 4.** Le multi-locataire
bascule d'un filtrage `tenant_id` dans une base partagee vers une base de donnees separee par
locataire (`stancl/tenancy`, voir `CLAUDE.md`). Chaque etape suivante ajoute des tables
propres a un locataire : plus on avance sur l'ancien mecanisme, plus la migration devient
lourde a rejouer. Elle est faite avant de reprendre la construction du produit.

1. Tenants, utilisateurs, rôles, authentification + 2FA. **Fait.**
2. Formulaire d'organisation (marque et identité légale). **Fait.**
3. Création d'événement et lien public. **Fait.** Isolation par locataire, unités, comptes
   de versement, modèle d'événement, assistant et page publique (écran 3, lecture seule).
4. Inscription, accompagnateurs, unités, calcul du montant.
5. **Réservation, décompte, disponibilité, priorité, purge** — le noyau critique, à couvrir
   par des tests de concurrence.
6. Dépôt de preuve, file de vérification, validation, attribution des tables.
7. Billet, jeton QR, scan, journal des passages.
8. Envois programmés et rappels.
9. Exports, rapports, rapprochement CSV.
10. Notifications, abonnement et facturation. Avec elle, les trois morceaux de la console
    d'exploitation dont elle dépend : catalogue des plans (écran 30), compteurs de quotas
    (`TenantUsage`) et suspension.
11. Console d'exploitation : le reste des écrans 27 à 34, et l'accès du support (écran 25).

### Tests indispensables

- Deux inscriptions simultanées sur la dernière place : une seule doit aboutir.
- Envoi de preuve après expiration du décompte : refusé.
- Reprise de lien quand l'événement est complet : refusée, dossier purgé.
- Purge à l'échéance et à l'épuisement : places rendues, journal écrit.
- Scan d'un billet déjà utilisé : signalé, forçage tracé.
- Requête d'un tenant sur les données d'un autre : impossible.
- Un compte éditeur sans accès de support ouvert ne lit aucune donnée d'organisation : 404.
- Un accès de support expiré ou révoqué ne donne plus rien ; chaque consultation faite pendant
  l'accès figure au journal de l'organisation.
- Un compte d'organisation n'atteint aucune route de la console, et inversement.
- Une organisation suspendue ne prend plus d'inscription, ses billets déjà émis passent au scan.
- Une descente de plan au-delà des quotas consommés est refusée.

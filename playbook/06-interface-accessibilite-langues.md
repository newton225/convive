# 06. Interface, accessibilité et langues

Comment un écran se construit pour être clair, accessible, traduisible et cohérent avec les autres.

## 1. Composants : réutiliser, ne pas réécrire

- **UI-01.** **Aucune primitive d'interface n'est codée à la main** (bouton, champ, liste déroulante, case à
  cocher, interrupteur, onglets, dialogue, infobulle, menu, notification, tableau, badge, calendrier). On installe
  une bibliothèque **headless et accessible** et on compose avec elle.
- **UI-02.** Avant d'écrire un composant, vérifier qu'il n'existe pas déjà dans la bibliothèque installée ou dans
  le dossier des composants du projet. Un composant maison se justifie seulement pour un élément **propre au
  métier**.
- **UI-03.** **Icônes** d'une bibliothèque unique, jamais dessinées à la main ; aucun emoji dans l'interface.
- **UI-04.** **Tableaux de données** : une bibliothèque de tableau pour le tri, le filtre, la pagination et la
  sélection, jamais une implémentation maison. Le rendu utilise les primitives de table du projet.
- **UI-05.** **Graphiques**, **dates**, **lecture de codes** : une bibliothèque chacun, choisie une fois, déclarée
  dans le fichier d'instructions du projet.
- **UI-06.** **Formulaires** : une seule manière dans tout le projet. Les règles sont celles du serveur ; le
  client ne les redéfinit pas. Si un formulaire existant utilise l'outil natif du cadre, on ne ré-introduit pas
  une seconde bibliothèque de formulaires.
- **UI-07.** **Validation en temps réel** : à la sortie d'un champ, le serveur rejoue les règles de ce champ seul
  (une seule définition des règles). **Jamais sur une route limitée ou à effet de bord** (inscription publique,
  connexion) : chaque requête de validation compterait dans le limiteur.
- **UI-08.** **Le message d'un champ disparaît dès qu'on le retouche.** Les erreurs de formulaire sont attachées
  au champ concerné, pas affichées en bloc en haut.

## 2. États complets

Chaque vue prévoit ses états, tous :

| État | Ce qu'il faut |
| --- | --- |
| Chargement | Indicateur sur le bouton ou la zone, jamais un écran figé |
| Vide | Explique **quoi faire**, pas seulement « aucun résultat » ; différencie « rien encore » de « rien avec ce filtre » |
| Erreur | Dit ce qui s'est passé et l'action suivante |
| Succès | Confirmation brève (notification), pas une page entière |
| Permission refusée | Explique la raison, sans révéler ce qu'on n'a pas le droit de savoir |
| Hors ligne | Dit ce qui reste possible et ce qui sera synchronisé |

- **UI-10.** **Retour immédiat** sur chaque action : état de chargement du bouton, confirmation, mise à jour
  optimiste **seulement** quand l'échec est réversible.
- **UI-11.** **Une réponse du serveur donne un retour** : succès en notification, échec sous le champ ou en
  notification. Un geste silencieux est un bogue.
- **UI-12.** Un écran **sans la permission requise** n'apparaît pas dans la navigation ; la route, elle, refuse.

## 3. Confirmation et gestes risqués

- **UI-20.** **Rien d'irréversible sans confirmation** : suppression, purge, rejet, envoi en masse, validation
  d'un paiement, duplication, clôture. Le mot « irréversible » n'est pas le seul critère : **toute action qui ne
  s'annule pas facilement** demande la sienne.
- **UI-21.** La confirmation **nomme la conséquence exacte** et la chose touchée (« Une copie de "X" sera créée en
  brouillon... ») ; le résumé permet de vérifier qu'on agit sur la bonne ligne.
- **UI-22.** Le **focus s'ouvre sur « Annuler »**, jamais sur l'action. Le bouton d'action est inactif pendant
  l'envoi (double clic).
- **UI-23.** Une confirmation s'ouvre depuis un menu **non modal** quand elle est lancée depuis ce menu (sinon
  certains composants laissent la page inerte après la fermeture du menu).
- **UI-24.** Une action destructrice est **visuellement distincte** (rouge, séparée) et jamais placée à côté d'une
  action courante sans séparation.

## 4. Navigation

- **UI-30.** **Aucun écran orphelin** : un écran construit est accessible **dans le même changement** (lien de
  barre latérale pour un espace global, lien contextuel depuis la ressource dont il dépend). En reprenant une
  étape, vérifier d'abord qu'aucun écran déjà construit n'est resté sans lien.
- **UI-31.** Les **destinations d'une ressource** se rangent par moment d'usage (suivi, jour J, fiche) derrière un
  seul bouton, pas en dix liens par carte.
- **UI-32.** Le bouton **« retour »** du navigateur ne ramène pas à un formulaire déjà envoyé (connexion,
  inscription : vider l'historique de navigation après l'action).
- **UI-33.** **Un écran, une décision** : le parcours long est séquencé en **étapes numérotées** qui disent où se
  passe l'action, y compris quand elle a lieu hors de l'application.
- **UI-34.** Des **visites guidées** accompagnent les écrans complexes : une visite par écran, lancée à la
  première ouverture, rejouable par un bouton, suivie **en base** (par membre, quel que soit l'appareil), avec un
  repère par étape ; une étape dont le repère est absent (menu replié, bouton réservé à un autre profil) est
  **sautée**. Les textes d'une visite décrivent ce que fait le produit.

## 5. Textes et ton

- **UI-40.** **Pas de jargon.** L'utilisateur ne lit ni statut technique, ni nom de table, ni mot de
  l'architecture interne. (Dans un produit multi-clients, le mot technique « tenant » devient « organisation ».)
- **UI-41.** **Messages d'erreur utiles** : ce qui s'est passé, ce qu'on peut faire, jamais un code.
- **UI-42.** **Pas de redondance** : un badge « Invité » à côté du texte « L'invité » n'apprend rien ; n'afficher
  l'auteur nommé que quand il y a quelqu'un à nommer.
- **UI-43.** Une **information critique** (« pas encore envoyée », « refusé ») se distingue par plus que sa place :
  la couleur d'état **et** le texte, jamais la couleur seule.
- **UI-44.** Les **champs obligatoires** sont signalés (astérisque rouge plus mention pour lecteur d'écran, légende
  en tête d'un formulaire de plusieurs champs) et **déduits de la validation serveur**, jamais devinés. Un champ
  exigé seulement à la publication porte sa propre marque « pour publier » ; la page nomme ce qui manque encore.
- **UI-45.** **Saisie qui reconnaît sa fin** : un code à N chiffres s'envoie au dernier chiffre (le collage ne
  garde que les chiffres) ; ne pas l'appliquer à un formulaire de plusieurs champs.
- **UI-46.** **Listes triées, filtrées, paginées** : en-têtes de tri clairs, filtre ramenant à la première page,
  paramètres dans l'adresse pour que le retour arrière garde l'état.

## 6. Accessibilité

- **UI-50.** **Navigation clavier complète**, focus visible, rôles ARIA fournis par les composants headless.
- **UI-51.** **Contraste du texte** au moins 4,5:1, cibles tactiles au moins 44 px.
- **UI-52.** **Aucune information portée par la couleur seule.**
- **UI-53.** **`prefers-reduced-motion`** respecté : tout mouvement non essentiel se désactive.
- **UI-54.** **Aucun contenu visible seulement après une animation** ; le texte reste lisible et sélectionnable
  sans mouvement.
- **UI-55.** Les champs ont des **libellés** (visibles ou réservés aux lecteurs d'écran), les images utiles un
  texte alternatif, les titres une hiérarchie.
- **UI-56.** Un **composant tiers** (défi anti-robot, carte) suit le thème de la page, s'adapte à la largeur du
  formulaire et ne s'affiche qu'en cas de besoin (affichage sur doute).

## 7. Mise en page et design

- **UI-60.** **Mobile d'abord** pour le parcours des utilisateurs finaux et les écrans de terrain ; densité plus
  élevée pour le back-office. Aucune largeur fixe ; grilles qui se replient ; grilles en `minmax(0, 1fr)` pour
  éviter les débordements.
- **UI-61.** **Jetons de design** (couleurs, espacements, rayons) déclarés en variables ; **thème clair et
  sombre** pour chaque couleur ; un fond défini explicitement.
- **UI-62.** Couleurs de **marque d'un client** : appliquées seulement au parcours de ses utilisateurs, à ses
  documents et à ses messages ; le back-office reste neutre.
- **UI-63.** **Typographie** : sans-serif système pour l'interface, polices d'accent réservées aux documents et
  aux invitations.
- **UI-64.** **Animation sobre** : durées de 0,2 à 0,6 s, courbes douces, faible amplitude, opacité et translation
  plutôt que rotation et rebond ; **une seule animation dominante par section** ; seulement `transform` et
  `opacity`. Le mouvement **explique** un changement d'état, il ne décore pas.
- **UI-65.** **Le produit comme héros** sur un site commercial : montrer les artefacts réels (billet, file de
  preuves) plutôt que des illustrations abstraites.
- **UI-66.** **Données d'exemple visibles** : un écran bâti avant son serveur porte un bandeau et un indicateur ;
  aucun chiffre d'exemple ne se présente comme réel.

## 8. Internationalisation

- **I18N-01.** **Aucun texte affiché n'est écrit en dur**, ni côté serveur ni côté client.
- **I18N-02.** Textes dans des fichiers par **groupe thématique** (actions communes, navigation, compte,
  organisation, permissions, messages juridiques...). Les fichiers du framework sont **générés**, on n'y ajoute
  pas de clé applicative (elle serait écrasée).
- **I18N-03.** **Clés en anglais**, en notation pointée sémantique (`group.subgroup.key`) ; valeurs dans la langue
  concernée.
- **I18N-04.** **Toute nouvelle clé s'ajoute dans toutes les langues dans le même changement.** Une clé présente
  d'un seul côté est un bogue.
- **I18N-05.** Paramètres **nommés** (`:name`), jamais de concaténation. Pluriels par les règles du framework.
- **I18N-06.** Une clé absente **rend la clé elle-même** : le trou se voit à l'écran et en test.
- **I18N-07.** Seuls les groupes utiles sont **envoyés au navigateur** ; les messages de validation restent
  côté serveur.
- **I18N-08.** **Résolution de la langue**, dans cet ordre : paramètre d'adresse (mémorisé en session), langue de
  session, en-tête du navigateur **pour un visiteur non authentifié seulement**, langue par défaut. Le code de
  langue est **confronté à un catalogue fermé** avant d'être utilisé (voir SEC-45).
- **I18N-09.** Un sélecteur de langue écrit le choix en **session** plutôt que de traîner un paramètre dans
  chaque adresse.
- **I18N-10.** **Dates et nombres** formatés dans la langue **choisie dans l'interface**, pas celle de l'appareil ;
  montants dans la devise du produit, sans décimale quand la devise n'en a pas.
- **I18N-11.** Le **contenu saisi par un client** (nom d'événement, gabarit) se traduit par un mécanisme
  distinct de l'interface (colonnes traduisibles), pas par les fichiers de langue.
- **I18N-12.** Un test vérifie que **chaque langue a les mêmes clés** que la langue de référence.

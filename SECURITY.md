# Revue de securite — Convive

Revue conduite sur les specifications (`README.md`, `CLAUDE.md`) et le prototype
`Convive.dc.html`. Ce document est une modelisation de menaces, a traiter comme une liste
d'exigences pendant le developpement, puis comme une grille de test avant mise en production.

Au moment de la redaction, aucun code metier n'existait. Le socle est aujourd'hui en place
(locataires, utilisateurs, invitations, authentification et 2FA) ; le reste des controles
decrits ici est a implementer au fur et a mesure des etapes de construction.

Le projet tourne sur **SQLite** par decision du proprietaire. Deux controles cites plus bas
sont donc indisponibles et compenses autrement : la Row Level Security (C4) et le
verrouillage de ligne `SELECT ... FOR UPDATE` (H4). Voir `CLAUDE.md`, section
« Base de donnees ».

Chaque entree suit le meme format : l'attaque, son impact, le controle obligatoire.
Les severites sont evaluees dans le contexte du produit : argent detourne, places vendues
deux fois, donnees d'un locataire lues par un autre.

Note sur le prototype : c'est une maquette a donnees factices. Le decompte de reservation, le
QR et la disponibilite y sont simules cote client. Aucun de ces mecanismes ne doit etre porte
tel quel : ils appartiennent au serveur.

---

## Severite critique

### C1. Detournement des comptes de versement

**Attaque.** Le lien public affiche les numeros Mobile Money de l'organisation. Un membre a
faibles privileges, un compte compromis, ou une session volee modifie ce numero. Les invites
versent sur le compte de l'attaquant et deposent des preuves authentiques. Personne ne s'en
apercoit avant le rapprochement.

**Impact.** Perte financiere directe et totale sur la periode, sans trace de fraude cote
plateforme.

**Controles.**

- Permission dediee `tenant.payment_accounts`, jamais incluse dans un profil pre-cree autre
  que Proprietaire.
- Re-authentification forte (mot de passe plus code TOTP) immediatement avant la modification,
  independamment de la session en cours.
- Notification immediate au Proprietaire et a tous les Tresoriers, par email et WhatsApp, avec
  l'ancien et le nouveau numero.
- Tant qu'aucun evenement n'a jamais ete publie, creation et modification du canal, du numero
  et du titulaire prennent effet apres un apercu et une confirmation explicite : aucun invite ne
  peut encore voir ces coordonnees ni y verser.
- Des la premiere publication (`tenants.first_published_at`, pose a la publication et jamais
  retire), le delai d'activation de 24 h
  s'applique a toute creation ou modification du canal, du numero et du titulaire. L'ancien
  numero reste affiche pendant ce delai. Le delai reste actif apres la cloture de l'evenement.
  Un second Proprietaire peut toujours l'ecourter, jamais la personne qui a demande le changement.
- Entree d'audit non modifiable, et bandeau sur le tableau de bord pendant sept jours apres
  tout changement.
- Aucun numero de versement modifiable par import, par API ou par un webhook.

**Etat.** La regle d'activation immediate avant la premiere publication, avec apercu et
confirmation (`confirmed` exige par `SavePaymentAccount`), est implementee le 2026-10-07 ; la
confirmation de la premiere publication annonce le delai qui commence. Permission dediee,
delai de 24 h, validation anticipee par un second Proprietaire qui ne peut pas etre le demandeur,
re-authentification par mot de passe, alerte email a tous les porteurs de la permission avec
l'ancien et le nouveau numero, signalement pendant sept jours, journal avec l'avant et l'apres :
en place et couverts par des tests. Restent aussi a faire : le second facteur TOTP rejoue juste
avant la modification, l'alerte WhatsApp, et le bandeau sur le tableau de bord.

### C2. Contrefacon de billets par extraction de la cle sur un appareil hors ligne

**Attaque.** Le scan doit fonctionner hors ligne. Si la verification repose sur HMAC, chaque
telephone d'agent embarque le secret de signature. Un appareil perdu, un agent malveillant ou
un stockage local extrait donne la capacite de **forger un billet valide** pour n'importe
quelle table.

**Impact.** Faux billets indetectables, salle en surcapacite, credibilite du controle ruinee.

**Controles.**

- Signature **asymetrique** (Ed25519). Le serveur signe avec la cle privee, l'appareil ne
  detient que la cle publique. La verification hors ligne devient possible sans secret.
- Cle privee dans un KMS ou un HSM, jamais dans le depot ni dans une variable d'environnement
  lisible par le front.
- Jeton portant `tenant_id`, `event_id`, `registration_id`, `nonce`, `not_after`, et une
  version de cle pour permettre la rotation.
- Rotation de cle par evenement. Tous les jetons expirent a la cloture.
- Dedoublonnage local sur l'appareil pendant la session hors ligne, plus dedoublonnage serveur
  a la synchronisation, avec rapport des conflits detectes apres coup.
- Liste de revocation signee, telechargee a chaque reprise de reseau, pour les billets annules
  ou rembourses.

### C3. Epuisement des places par reservations automatisees

**Attaque.** Le lien public est anonyme et une reservation bloque des places pendant 10 min.
Un script cree des reservations en boucle, avec des numeros differents, et maintient les 250
places bloquees en permanence. Aucun invite legitime ne peut plus s'inscrire.

**Impact.** Deni de service sur la fonction centrale du produit, evenement sabotable par un
concurrent ou un plaisantin, sans aucune compromission technique.

**Controles.**

- Verification du numero de telephone par code a usage unique **avant** de passer en
  reservation. Une reservation ne se cree pas sur un numero non verifie.
- Une seule reservation active par numero de telephone et par evenement.
- Plafond de reservations simultanees par adresse IP et par sous-reseau `/24`.
- Backoff exponentiel apres reservations expirees repetees sur le meme numero ou la meme IP.
- Captcha ou preuve de travail au dela d'un seuil de creation par minute sur l'evenement.
- Tableau de bord organisateur exposant le taux de reservations expirees, pour rendre l'attaque
  visible.
- Les limiteurs comptent l'IP reelle : `TrustProxies` configure avec les proxys connus
  uniquement, jamais `*`, sinon `X-Forwarded-For` est falsifiable et toute limitation par IP
  devient decorative.

**Etat.** Seule la limite generale du lien public (20 requetes par minute par IP, limiteur nomme
`public-link`) est en place, sur la page de lecture seule de l'evenement. Aucune reservation
n'existe encore : la verification du numero de telephone, le plafond par numero, le backoff et
le captcha restent entierement a construire avec l'ecran 4.

### C4. Contournement du cloisonnement des locataires

**Decision d'architecture.** Le cloisonnement repose desormais sur une **base de donnees
separee par locataire** (`stancl/tenancy`), pas sur un scope applicatif filtrant `tenant_id`
dans une base partagee. Cette section decrit le modele de menace **cible** ; le code n'est pas
encore migre, voir `CLAUDE.md` section « Multi-locataire » pour l'etat exact.

**Attaque.** Avec une base separee, l'essentiel des vecteurs traditionnels (oubli de scope,
`withoutGlobalScopes()` laisse en debug, requete SQL brute qui ignore le filtre) disparait :
il n'y a plus de filtre a oublier, la table d'un autre locataire n'existe simplement pas sur
la connexion active. Le risque se deplace vers l'**etablissement de cette connexion** :
tenancy jamais initialisee (job, commande Artisan, tache planifiee qui s'execute sans
locataire, lisant alors la base centrale ou aucune base), mauvais locataire initialise (bogue
dans le resolveur d'identification, sous-domaine ou segment de chemin mal interprete), route
du domaine central restee accessible une fois la tenancy en cours (ou l'inverse), base d'un
nouveau locataire creee sans que ses migrations n'aient ete rejouees.

**Impact.** Lecture ou modification des donnees d'une autre organisation, ou execution d'une
action metier sur une base vide ou fausse. Sur un SaaS, defaut fatal.

**Controles.**

- **Aucune logique metier ne s'execute sans tenancy initialisee.** Un job, une commande
  Artisan ou une tache planifiee sans locataire resolu refuse de s'executer plutot que de lire
  silencieusement la base centrale. Une tache qui balaie tous les locataires boucle sur eux et
  initialise la tenancy a chaque tour, jamais une requete large.
- `PreventAccessFromCentralDomains` (et son inverse) sur les deux groupes de routes : le
  domaine central et le domaine d'un locataire ne se traversent pas.
- **Deux strategies d'identification, chacune testee independamment** : sous-domaine pour le
  lien public de l'evenement, segment de chemin pour le back-office authentifie. Un jeton ou
  un identifiant d'une organisation A presente sous l'identification d'une organisation B doit
  echouer, dans les deux sens.
- Creation d'une base de locataire et execution de ses migrations dans **la meme operation
  transactionnelle au sens applicatif** : un locataire sans ses tables est un locataire cree a
  moitie, pas un etat a rattraper par un correctif ulterieur.
- Les tables **centrales** (`tenant_members`, `tenant_invitations`, `tenant_brandings`,
  `domains`) ne beneficient pas de la separation physique : elles gardent un scope explicite
  par `tenant_id`, une verification dans les Policies, et le test « acces croise attendu 404 »
  sur chacune de leurs routes, exactement comme avant.
- Test automatise sur **chaque** route du back-office et du parcours public : requete d'un
  locataire tiers, reponse attendue 404, verifiee **sans** pouvoir forcer un scope puisqu'il
  n'y en a plus a forcer : le test verifie que la donnee n'existe pas sur la connexion, pas
  qu'un filtre a bien ete applique.

**Etat.** Le lien public de l'evenement (README ecran 3) est batti aujourd'hui sur le
mecanisme provisoire (base partagee, scope `tenant_id`) decrit dans `CLAUDE.md` : `Route::domain()`
resout le locataire par sous-domaine, le jeton d'un evenement n'est cherche que dans le
locataire deja resolu, un jeton d'une organisation A presente sous le sous-domaine de B ne
trouve rien, verifie par test. Cette garantie est **portee a l'identique** apres la migration
vers `stancl/tenancy` : c'est le meme scenario de test, rejoue contre la nouvelle mecanique.

### C5. Injection de gabarit dans les messages et les billets

### C5. Injection de gabarit dans les messages et les billets

**Attaque.** L'exploitant edite des gabarits de message et de billet avec des variables. Si le
rendu passe par `Blade::render()`, `eval`, ou tout moteur evaluant des expressions sur une
chaine fournie par l'utilisateur, on obtient une **execution de code arbitraire** cote serveur
depuis le back-office d'un locataire.

**Impact.** Compromission du serveur et donc de tous les locataires, depuis un compte client
legitime.

**Controles.**

- Substitution de variables par liste blanche stricte (`{{ nom }}`, `{{ table }}`), sur une
  table de correspondance explicite. Aucun moteur de gabarit evaluateur sur une chaine
  utilisateur.
- Echappement contextuel : HTML pour l'email, texte brut pour WhatsApp.
- Aucun HTML libre accepte dans les gabarits ; si du formatage est necessaire, sous-ensemble
  balise par balise, assaini serveur.
- Les valeurs inconnues rendent une chaine vide, jamais le nom de la variable, jamais une
  erreur exposant la pile.

---

## Severite haute

### H1. Depot de preuve comme vecteur d'execution et de fuite

**Attaque.** L'invite depose une capture. Vecteurs : SVG contenant du script, servi en
`image/svg+xml` sur le domaine de l'application donc XSS avec session admin ; PDF avec
JavaScript ou action d'ouverture de fichier ; image piegee visant la chaine de traitement
(Ghostscript via ImageMagick reste une source classique d'execution) ; polyglotte HTML plus
image ; archive ou image a decompression explosive ; MIME declare honnete mais contenu tout
autre.

**Controles.**

- Types acceptes par liste blanche stricte, valides sur les **octets** du fichier, pas sur
  l'extension ni sur l'en-tete `Content-Type` fourni.
- SVG refuse sans exception.
- Reencodage systematique des images en JPEG ou PNG, ce qui detruit metadonnees, scripts et
  charges utiles, et normalise les dimensions.
- Delegue Ghostscript desactive dans la configuration ImageMagick, ou traitement via une
  bibliotheque qui ne l'utilise pas. Limites de memoire et de pixels imposees.
- PDF : soit refuse au profit d'une capture d'ecran, soit converti en image dans un processus
  isole, sans reseau, avec temps et memoire plafonnes.
- Servi depuis un domaine distinct sans cookie, en `Content-Disposition: attachment`, avec
  `X-Content-Type-Options: nosniff` et une CSP `sandbox`.
- Taille limitee a 5 Mo, cadence limitee par inscription et par IP.
- Analyse antivirus si l'hebergement le permet, avant mise a disposition.

### H2. Fuite de reçus par URL signee

**Attaque.** Les reçus contiennent des donnees financieres personnelles. Une URL signee copiee
dans un ticket de support, un partage WhatsApp, ou transmise via l'en-tete `Referer` d'une page
tierce, reste utilisable pendant toute sa duree de validite.

**Controles.**

- Duree de validite de quelques minutes, pas d'heures.
- URL liee a la session ou a usage unique par jeton, avec revocation a la premiere utilisation
  pour les documents sensibles.
- `Referrer-Policy: no-referrer` et `Cache-Control: no-store` sur les routes de media.
- Aucun identifiant metier lisible dans le chemin, aucune donnee personnelle dans l'URL.
- Chaque acces a un reçu est journalise avec l'acteur.

### H3. Enumeration et moisson de donnees personnelles

**Attaque.** Le lien de reprise, le formulaire public et la liste d'attente permettent de
tester des numeros de telephone et des references. Reponses differenciees ou temps de reponse
differents suffisent a savoir qui est inscrit. Les references sequentielles du type
`SP-2026-0041` sont devinables et invitent a l'IDOR.

**Controles.**

- Jeton de reprise aleatoire de 32 octets au minimum, **stocke hache**, compare en temps
  constant, a usage limite et expirant. Jamais de reference metier comme identifiant public.
- References affichees non sequentielles, ou decouplees de l'identifiant technique.
- Reponses et temps de reponse uniformes, que l'inscription existe ou non.
- Limitation de debit par jeton, par IP et par numero, avec journalisation des blocages.
- Les liens de liste d'attente expirent en 6 h et sont a usage unique.

### H4. Double validation et doubles attributions

**Attaque.** Deux tresoriers valident la meme preuve au meme instant ; deux requetes reclament
la meme place de liste d'attente ; deux validations concurrentes attribuent le meme siege. Le
verrou est pose sur l'evenement pour les places, mais rarement sur les autres transitions.

**Controles.**

- Toute transition de statut passe par une transaction avec `lockForUpdate()` sur la ressource
  concernee, ou un verrou consultatif PostgreSQL par inscription. **Sans effet en SQLite** :
  Laravel y compile la clause de verrouillage en chaine vide. Compenser par un verrou atomique
  applicatif (`Cache::lock()`) par evenement, et garder `lockForUpdate()` pour le jour du
  basculement.
- Contrainte d'unicite en base sur `(event_id, table_id, seat_index)` et sur l'attribution par
  inscription. La base doit refuser ce que le code aurait laisse passer. **C'est la garantie
  principale tant que le verrouillage de ligne n'existe pas**, pas une ceinture de securite
  secondaire.
- Cle d'idempotence obligatoire sur la soumission de preuve, la validation, le rejet, la
  reclamation d'une place de liste d'attente et le scan.
- Transitions exprimees comme machine a etats explicite : une transition depuis un statut
  inattendu echoue, elle ne se contente pas d'ecraser.

### H5. Abus des envois comme relais de spam

**Attaque.** L'espace permet d'envoyer emails et WhatsApp. Un locataire importe une liste de
numeros achetes et se sert de la reputation d'envoi de la plateforme. Ou modifie un gabarit
pour y placer un lien d'hameconnage sous couvert d'une invitation.

**Controles.**

- Envoi possible uniquement vers des coordonnees attachees a une inscription du locataire
  courant, jamais vers une liste libre.
- Quotas d'envoi par plan, appliques cote serveur, avec compteur visible.
- Detection des pics anormaux et suspension automatique avec alerte.
- Liens sortants restreints au domaine de l'application ; aucun lien arbitraire injectable
  depuis un gabarit.
- Domaines d'envoi separes par usage, SPF, DKIM et DMARC configures, surveillance des plaintes.

### H6. Escalade de privileges par les profils dynamiques

**Attaque.** Les profils sont modifiables par l'exploitant. Un membre disposant de
`profiles.manage` s'ajoute `tenant.payment_accounts` ou `billing.manage`, ou modifie le profil
qu'il porte lui-meme. Variante : affectation d'un profil d'un autre locataire par manipulation
de l'identifiant.

**Controles.**

- `profiles.manage` ne permet pas d'attribuer une permission que l'acteur ne detient pas
  lui-meme.
- **Ni d'affecter a un membre un profil qui detient plus que le sien.** Affecter un profil
  revient a en accorder les permissions : sans cette regle, un porteur de `profiles.manage`
  promeut un complice Proprietaire, qui le promeut en retour. Le controle sur la composition
  d'un profil ne suffit pas, il faut le meme sur l'affectation.
- Un utilisateur ne peut pas modifier le profil qu'il porte, ni s'affecter un autre profil.
- L'autorisation est verifiee **avant** la validation, dans `authorize()` du Form Request : un
  refus doit rester un refus, pas un message d'erreur qui renseigne sur les profils existants.
- Le profil Proprietaire est systeme : ni modifiable, ni supprimable, et un locataire conserve
  toujours au moins un Proprietaire actif.
- Les permissions forment un **catalogue fixe declare dans le code**. Aucune permission creee
  depuis l'interface, sinon le controle d'acces devient falsifiable par saisie.
- `spatie/laravel-permission` en mode `teams`, le team etant le locataire, pivot portant le
  `tenant_id` : un profil ne traverse pas les locataires.
- Invalidation immediate du cache de permissions des membres concernes, reevaluation a la
  requete suivante.
- Journal avec l'avant et l'apres a chaque creation, modification et affectation.
- Un profil peut **exiger la double authentification** de ses porteurs (`requires_two_factor`).
  Proprietaire et Tresorier l'exigent des l'ouverture d'un espace, et le profil systeme l'exige
  toujours, drapeau ou pas : sans cela un Proprietaire leverait l'exigence sur lui-meme. Le
  back-office de l'organisation reste ferme tant que la 2FA n'est pas activee.
- Le blocage ne doit pas enfermer : **changer d'organisation et quitter une organisation restent
  accessibles**. Un membre a qui l'on vient d'affecter un profil exigeant la 2FA doit pouvoir
  sortir de l'espace sans passer par un administrateur.
- **Etat.** Le controle est desactive en environnement local, pour ne pas bloquer le compte de
  developpement dont la 2FA est volontairement absente. Verifier que
  `CONVIVE_ENFORCE_TWO_FACTOR` n'est pas positionne a `false` en production : la grille de test
  avant mise en production doit le confirmer.

### H7. Politique de securite du contenu affaiblie par Inertia

**Attaque.** Inertia passe son etat initial dans un attribut de la page. Une CSP en
`unsafe-inline` pour accommoder ce fonctionnement rend toute XSS immediatement exploitable.
Les branding tenant (couleurs, textes) constituent des points d'injection si un jour rendus en
HTML brut.

**Controles.**

- CSP a nonces, `unsafe-inline` interdit, `default-src 'self'`, `object-src 'none'`,
  `base-uri 'self'`, `frame-ancestors 'none'`.
- `dangerouslySetInnerHTML` interdit en revue de code, sans exception negociee.
- Couleurs de marque validees par expression reguliere stricte avant d'entrer dans une variable
  CSS. Aucune valeur libre injectee dans un attribut `style`.
- HSTS, `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`,
  `Permissions-Policy` restrictive.

---

### H8. Identite legale et sous-domaine detournes

**Attaque.** L'identite legale est ce qui figure sur les recus : raison sociale, RCCM, numero
de contribuable, nom du signataire. Un membre qui la modifie discretement fait emettre des
recus au nom d'une autre entite. Variante : accaparement d'un sous-domaine que les invites
associent au produit (`www`, `admin`, `billing`) ou a une organisation connue.

**Controles.**

- Trois permissions distinctes, `tenant.legal`, `tenant.branding` et `tenant.domain`, chacune
  verifiee dans `authorize()` de son Form Request, avant validation.
- Un acces croise renvoie **404** sur les quatre routes du formulaire, jamais 403.
- **Journal avec l'avant et l'apres** a chaque enregistrement, acteur compris. C'est le seul
  moyen de reconstituer sous quelle identite un recu a ete emis a une date donnee.
- Sous-domaine restreint aux etiquettes DNS en minuscules, normalise avant validation pour que
  l'unicite porte sur la forme stockee, et refuse s'il figure dans la liste reservee de
  `App\Support\Subdomain`.
- Formes juridiques issues d'un **catalogue ferme**, jamais du texte libre : elles apparaissent
  sur des documents a valeur probante.
- **Fichiers de marque** (logo, bandeau, cachet, signature) : stockes hors racine web sur le
  disque `tenant_media`, ranges par locataire, atteignables uniquement par **URL signee
  expirante**. Types limites a JPEG, PNG et WebP, 5 Mo au maximum. **SVG refuse** : il peut
  porter du script (voir H1). La validation inspecte le contenu (`image` plus `dimensions`,
  qui force un `getimagesize()`), pas le nom du fichier. **Reencodage systematique avant
  stockage** : une photo de cachet porte souvent la position GPS de qui l'a prise. Le nom de
  collection vient d'un catalogue ferme, jamais de l'URL telle quelle.
- Le sous-domaine est **fige des qu'un lien public a ete distribue** : le changer casserait des
  adresses deja entre les mains des invites.
- **A faire :** exiger une re-authentification forte sur la modification de l'identite legale,
  au meme titre que sur le compte de versement (voir C1 et M5).

## Severite moyenne

### M1. Fuite de donnees par les props Inertia

Transmettre un modele complet vers le front expose des colonnes non prevues : hachages,
jetons, notes internes, coordonnees d'autres inscrits. Le front n'a pas a filtrer ce que le
serveur n'aurait pas du envoyer.

**Controles.** Objets de transfert explicites via `spatie/laravel-data`, champ par champ.
Aucun `$model->toArray()` en props. Revue systematique du contenu des props sur les ecrans
sensibles : preuves, equipe, abonnement, journal.

### M2. Injection de formules dans les exports

Une valeur commencant par `=`, `+`, `-`, `@`, une tabulation ou un retour chariot est
interpretee comme formule par Excel. Un invite saisit un nom malveillant, l'organisateur ouvre
l'export, la formule s'execute sur son poste.

**Controles.** Prefixage par apostrophe ou refus des caracteres de tete a l'export. Test
automatise avec un jeu de valeurs hostiles.

### M3. Exfiltration par export legitime

Un profil Lecture exporte la base complete, ou un ancien membre le fait avant son depart.
L'action est autorisee, ce n'est pas une intrusion, mais c'est une fuite.

**Controles.** Permission d'export distincte de la permission de consultation. Limitation a 30
exports par heure et par utilisateur par defaut, reglable par l'editeur depuis l'ecran Securite
de la console (jamais retirable, chaque reglage journalise) : 5 bloquait un tresorier qui exporte
plusieurs evenements le meme jour. Journalisation avec le nombre de lignes et les filtres
appliques. Notification au Proprietaire au dela d'un seuil. Filigrane portant l'identite du
demandeur et l'horodatage sur les exports PDF.

### M4. Requetes cote serveur non maitrisees

Import d'un logo par URL, cible de webhook sortant, ou rendu HTML vers PDF par navigateur sans
tete : autant de moyens de faire emettre au serveur une requete vers `169.254.169.254`,
`localhost` ou un service interne, y compris via `file://` pour lire un fichier local.

**Controles.** Schemas limites a `https`, resolution DNS controlee avec refus des plages
privees et de bouclage, protection contre le rebinding par verification apres resolution,
JavaScript desactive et acces reseau bloque dans le moteur de rendu PDF, absence totale de
recuperation de ressource distante lors du rendu.

### M5. Robustesse de la double authentification

TOTP rejouable dans sa fenetre, codes de secours stockes en clair, absence de re-authentifi
cation pour les operations sensibles, session non regeneree apres elevation.

**Controles.** Memorisation du dernier pas TOTP consomme pour bloquer le rejeu. Codes de
secours haches, a usage unique, denombres. Verrouillage progressif apres echecs. Re-authentifi
cation exigee pour les comptes de versement, les profils, l'abonnement et la suppression d'un
membre. Regeneration de session a la connexion, a l'elevation et au changement d'espace.

### M6. Journal d'audit alterable

Un journal modifiable ne prouve rien. Si l'application dispose de `UPDATE` et `DELETE` sur la
table d'audit, un attaquant ayant obtenu une execution SQL efface ses traces.

**Controles.** Revocation des droits `UPDATE` et `DELETE` sur la table d'audit pour
l'utilisateur applicatif. Chainage par empreinte de l'entree precedente pour rendre une
suppression detectable. Export periodique vers un stockage en ecriture seule. Conservation 24
mois, purge automatisee et tracee ensuite.

### M7. Hygiene des traces et de la telemetrie

Codes a usage unique, jetons de reprise, references de transaction completes et images de reçus
n'ont rien a faire dans les journaux applicatifs, ni chez un service de suivi d'erreurs.

**Controles.** Liste de champs masques etendue aux jetons, codes, numeros de telephone et
references. Aucune capture de corps de requete sur les routes de depot de preuve.
Anonymisation des donnees envoyees au suivi d'erreurs.

### M8. Donnees residuelles dans la PWA

Le billet est consultable hors ligne, la file de scan contient des noms et des numeros de
table. Un telephone partage ou perdu expose ces donnees apres deconnexion.

**Controles.** Aucun jeton d'authentification en `localStorage`. Effacement de la file locale
et des caches a la deconnexion et a la cloture de l'evenement. Contenu hors ligne reduit au
strict necessaire. Verrouillage de l'ecran de scan par code apres inactivite.

---

## Severite basse, a ne pas negliger

- **Affectation de masse.** `$fillable` explicite sur chaque modele, `tenant_id`, statuts et
  montants jamais remplissables depuis une requete. Interdiction de `fill($request->all())`.
- **Choix de langue.** Le code de langue vient de l'exterieur, par `?lang=`, par le selecteur
  ou par `Accept-Language`. Il ne doit jamais atteindre `App::setLocale()` ni un chemin de
  fichier sans avoir ete confronte au catalogue `App\Support\Locale::supported()` : une valeur
  libre ouvre la porte a la traversee de repertoire dans le chargeur de traductions. Une
  langue inconnue est ignoree en silence, elle n'est pas memorisee en session, et elle ne
  provoque pas d'erreur revelant l'arborescence.
- **CSRF sur les formulaires publics.** Le parcours invite poste des donnees : jeton CSRF
  obligatoire, plus champ piege pour les robots. `SameSite=Lax` ne suffit pas seul.
- **Deconnexion et sessions.** Invalidation de toutes les sessions au changement de mot de
  passe, liste des appareils connectes, expiration absolue en plus de l'inactivite.
- **Dependances.** `composer audit` et `npm audit` bloquants en integration continue, mises a
  jour de securite suivies, versions epinglees, verrous commites.
- **Sauvegardes.** Chiffrees, avec des identifiants distincts de ceux de l'application,
  restauration testee periodiquement, et test de restauration documente.
- **Erreurs.** `APP_DEBUG=false` en production verifie par un test de deploiement. Aucune pile
  d'appel, aucun nom de table dans une reponse. Pages d'erreur redigees pour l'utilisateur.
- **Uniformite du refus.** 404 partout ou 403 revelerait l'existence d'une ressource d'un autre
  locataire, y compris sur les redirections et les messages de validation.

---

## Grille de test avant mise en production

A executer et a consigner. Un point non verifie est un point suppose faux.

**Cloisonnement.** Pour chaque route du back-office, requete d'un locataire tiers, attendu 404.
Requete forcee sans scope, attendu vide grace a la RLS. Job execute sans locataire, attendu
echec. Canal de diffusion souscrit depuis un autre locataire, attendu refus. Jeton d'un lien
public presente sous le sous-domaine d'une autre organisation, attendu 404.

**Places et concurrence.** Deux requetes simultanees sur la derniere place, une seule aboutit.
Cent requetes en parallele, aucune survente. Preuve envoyee apres expiration du decompte,
refusee. Reprise de lien sur evenement complet, refusee et dossier purge. Purge a l'echeance et
a l'epuisement, places rendues et journal ecrit.

**Billets.** Jeton modifie, refuse. Jeton d'un autre evenement, refuse. Jeton d'un autre
locataire, refuse. Billet scanne deux fois, signale, forcage trace. Appareil hors ligne
compromis, incapable de forger un jeton. Billet revoque, refuse apres synchronisation.

**Depots de fichiers.** SVG avec script, refuse. PDF avec JavaScript, refuse ou neutralise.
Polyglotte image et HTML, refuse. Image a decompression explosive, refusee. Extension
falsifiee, refusee. Metadonnees presentes apres reencodage, attendu absentes.

**Argent.** Modification d'un compte de versement sans re-authentification, refusee. Avant
toute premiere publication, compte cree ou modifie actif apres confirmation. Apres publication,
un nouveau compte reste invisible et une modification conserve l'ancien numero jusqu'a la fin
du delai. Validation du delai par le demandeur lui-meme, refusee. Validation par un
non-Proprietaire, refusee. Sans la permission dediee, refusee. Notification et delai
d'activation effectifs. Double validation d'une preuve, une seule prise en compte.

**Profils.** Attribution d'une permission non detenue, refusee. Modification de son propre
profil, refusee. Suppression du dernier Proprietaire, refusee. Profil d'un autre locataire
affecte, refuse. Modification de profil, effet immediat verifie. Porteur d'un profil exigeant
la 2FA sans 2FA activee, back-office ferme, et `CONVIVE_ENFORCE_TWO_FACTOR` confirme actif en
production.

**Organisation.** Modification de l'identite legale sans `tenant.legal`, refusee. Des couleurs
sans `tenant.branding`, refusee. Du sous-domaine sans `tenant.domain`, refusee. Sous-domaine
reserve ou deja pris, refuse. Chaque enregistrement journalise avec l'avant et l'apres. SVG
depose comme logo, refuse. Script renomme en `.png`, refuse. Fichier de plus de 5 Mo, refuse.
Metadonnees presentes dans un fichier de marque stocke, attendu absentes. Fichier atteignable
sans URL signee, attendu impossible.

**Limitation de debit.** Chaque limiteur du tableau de `CLAUDE.md` verifie, y compris avec un
`X-Forwarded-For` falsifie. Reservations automatisees en boucle, bloquees avant epuisement des
places.

**Exposition.** Props Inertia inspectees sur les ecrans sensibles. Export avec valeurs
hostiles, formules neutralisees. URL signee reutilisee apres expiration, refusee. En-tetes de
securite presents. CSP sans `unsafe-inline`.

---

## Ce qui reste a decider

Deux points demandent un arbitrage du proprietaire du projet avant developpement.

1. **Verification du numero de telephone par code avant reservation.** C'est la meilleure
   protection contre l'epuisement automatise des places, mais cela ajoute une etape au parcours
   invite et un cout d'envoi. A trancher explicitement.
2. **Acceptation des PDF comme preuve.** Les refuser au profit des captures d'ecran supprime
   une classe entiere de vulnerabilites, au prix d'un inconvenient pour les virements
   bancaires.

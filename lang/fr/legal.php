<?php

/*
 * Les documents juridiques du site (confidentialité, conditions, mentions légales). Lus côté serveur
 * par `App\Support\LegalDocument`, jamais envoyés avec les traductions partagées.
 *
 * Chaque phrase décrit ce que l'application fait réellement : une durée ou une règle qui change dans
 * le code se change ici dans le même commit, et `LegalDocument::Version` avance.
 *
 * Les jetons `:editor`, `:contact_email`... sont remplis depuis `config('convive.legal')`.
 */

return [
    'updated' => 'Dernière mise à jour : :date',
    'contents' => 'Sommaire',
    'placeholder' => '[à compléter]',

    'nav' => [
        'title' => 'Informations légales',
        'privacy' => 'Confidentialité',
        'terms' => 'Conditions d’utilisation',
        'notice' => 'Mentions légales',
    ],

    'documents' => [

        'privacy' => [
            'title' => 'Politique de confidentialité',
            'summary' => 'Quelles données :app traite, pourquoi, pendant combien de temps, qui y a accès, et comment vous pouvez les faire corriger ou effacer.',
            'sections' => [
                [
                    'title' => 'Qui est responsable de vos données',
                    'paragraphs' => [
                        ':app est un service édité par :editor (:editor_address). Il permet à des organisations, associations, églises, entreprises, de gérer les inscriptions à leurs événements.',
                        'Deux situations sont à distinguer, parce que le responsable de vos données n’est pas le même.',
                    ],
                    'items' => [
                        'Vous avez un compte :app (vous êtes membre d’une organisation) : :editor est responsable du traitement des données de votre compte, de la facturation et du site.',
                        'Vous êtes invité à un événement : l’organisation qui vous invite est responsable de vos données. :editor les traite pour son compte, comme sous-traitant, selon ses instructions. Pour toute demande, adressez-vous d’abord à cette organisation.',
                    ],
                ],
                [
                    'title' => 'Les données que nous traitons',
                    'paragraphs' => [
                        'Nous ne demandons que ce qui sert au fonctionnement du service.',
                    ],
                    'items' => [
                        'Compte : nom, adresse email, numéro de téléphone, mot de passe (conservé sous forme hachée, jamais en clair), clé de la double authentification, et la liste de vos appareils connectés (adresse IP, navigateur, date de dernière activité).',
                        'Organisation : nom, identité légale (raison sociale, forme juridique, numéro RCCM, numéro de contribuable, adresse), logo, bandeau, cachet et signature, comptes de versement (canal, numéro, titulaire), membres et leurs profils.',
                        'Invités : nom, numéro de téléphone, adresse email si elle est fournie, unité, nom et unité des accompagnateurs, montant dû, et la preuve de paiement déposée (capture, référence de transaction, canal, précision libre).',
                        'Billets et entrée : le billet émis, son code QR, et les passages enregistrés à l’entrée (date, heure, agent).',
                        'Abonnement : le plan, les factures et l’état des paiements. Le paiement par carte est traité par Stripe : :app ne reçoit ni ne conserve votre numéro de carte.',
                        'Journal : chaque action sensible est enregistrée avec son auteur, la date, l’adresse IP et le navigateur utilisé.',
                        'Mesure d’audience : uniquement sur la page d’accueil et la page des événements à la une, et seulement si vous l’acceptez.',
                    ],
                ],
                [
                    'title' => 'Pourquoi nous les traitons',
                    'items' => [
                        'Fournir le service : créer un compte, gérer les événements, les inscriptions, les preuves de paiement, les billets et le contrôle à l’entrée. C’est l’exécution du contrat qui nous lie à l’organisation.',
                        'Envoyer les messages liés à une inscription : carte d’invitation, rappels, billet. Ils partent par WhatsApp et, si une adresse a été fournie, par email. Le code de vérification du téléphone, quand l’organisation l’exige, part par SMS.',
                        'Protéger le service et l’argent des organisations : double authentification, journal des actions, limitation des tentatives, détection des preuves douteuses. C’est notre intérêt légitime et celui des organisations.',
                        'Facturer l’abonnement et tenir notre comptabilité : c’est une obligation légale.',
                        'Mesurer la fréquentation du site commercial : uniquement avec votre accord.',
                    ],
                    'paragraphs_after' => [
                        'Nous ne vendons aucune donnée, nous ne faisons aucune publicité ciblée, et aucune décision n’est prise à votre égard de façon entièrement automatisée : une preuve de paiement est toujours validée ou rejetée par une personne de l’organisation.',
                    ],
                ],
                [
                    'title' => 'Qui a accès à vos données',
                    'items' => [
                        'Les membres de l’organisation, selon leur profil : un agent d’accueil ne voit pas ce que voit un trésorier.',
                        'L’équipe de :editor n’a pas accès au contenu d’une organisation. Elle n’y entre que si un Propriétaire lui ouvre un accès de support : nominatif, en lecture seule, limité à 24 heures, révocable à tout moment, et dont chaque page consultée est inscrite au journal de l’organisation.',
                        'Notre hébergeur : :host.',
                        'Stripe, pour le paiement de l’abonnement.',
                        'Notre prestataire d’envoi d’emails (:mail_provider), WhatsApp (Meta) et Orange Côte d’Ivoire (SMS du code de vérification), pour acheminer les messages.',
                        'Cloudflare, pour la vérification anti-robot du formulaire d’inscription : elle reçoit l’adresse IP et des informations techniques sur le navigateur, jamais le contenu du formulaire.',
                        'Google Analytics, seulement si vous avez accepté la mesure d’audience.',
                        'Les autorités, lorsque la loi nous y oblige.',
                    ],
                    'paragraphs_after' => [
                        'Les données d’une organisation sont rangées dans une base qui lui est propre, physiquement séparée de celle des autres organisations.',
                    ],
                ],
                [
                    'title' => 'Transferts hors de Côte d’Ivoire',
                    'paragraphs' => [
                        'Certains de ces prestataires traitent des données en dehors de la Côte d’Ivoire. Ces transferts sont limités à ce qui est nécessaire au service et encadrés par les garanties prévues par la loi ivoirienne n° 2013-450 relative à la protection des données à caractère personnel et, pour les personnes situées dans l’Union européenne, par le Règlement général sur la protection des données (RGPD).',
                    ],
                ],
                [
                    'title' => 'Combien de temps nous les gardons',
                    'items' => [
                        'Compte : jusqu’à ce que vous le supprimiez.',
                        'Organisation et tout son contenu (événements, invités, preuves, billets) : tant que l’organisation existe. C’est elle qui décide de la durée de conservation des données de ses invités.',
                        'Inscriptions non finalisées (sans preuve validée) : supprimées à l’échéance fixée par l’organisateur, avec la capture de la preuve éventuellement déposée.',
                        'Organisation supprimée : invisible immédiatement, puis effacée définitivement 30 jours plus tard.',
                        'Événement supprimé : effacé définitivement 30 jours plus tard.',
                        'Journal des actions : 24 mois.',
                        'Session de connexion : 12 heures au plus, puis il faut se reconnecter.',
                        'Copies de sauvegarde : un an au plus.',
                        'Factures et pièces comptables : pendant la durée exigée par la réglementation comptable.',
                    ],
                ],
                [
                    'title' => 'Suppression et effacement',
                    'paragraphs' => [
                        'Seul un Propriétaire peut supprimer une organisation. Elle disparaît alors tout de suite pour tous ses membres, et ses liens publics cessent de fonctionner.',
                        'Pendant 30 jours, rien n’est encore effacé : l’équipe de :editor peut restaurer l’organisation à la demande d’un Propriétaire, avec ses membres et ses événements. Tous les Propriétaires sont prévenus par email de la suppression et de la date de l’effacement.',
                        'Au bout de 30 jours, la base de l’organisation, ses fichiers et les preuves de paiement de ses invités sont effacés de nos systèmes en service. Cet effacement est définitif.',
                        'Des copies peuvent subsister jusqu’à un an dans nos sauvegardes. Elles ne servent qu’à rétablir le service après une panne, ne sont pas consultées autrement, et disparaissent d’elles-mêmes à l’expiration de ce délai.',
                        'Quand vous supprimez votre compte, votre espace personnel suit le même circuit. Vous ne pouvez pas supprimer votre compte tant que vous êtes le dernier Propriétaire d’une organisation : transmettez-la ou supprimez-la d’abord.',
                    ],
                ],
                [
                    'title' => 'Comment nous les protégeons',
                    'items' => [
                        'Connexion chiffrée (HTTPS) sur tout le service.',
                        'Double authentification obligatoire pour les profils qui touchent à l’argent.',
                        'Une base de données séparée par organisation.',
                        'Les images déposées sont réenregistrées pour en retirer les informations cachées (position GPS, modèle d’appareil).',
                        'Les fichiers ne sont accessibles que par des liens signés qui expirent.',
                        'Tout changement de compte de versement est différé de 24 heures et signalé à tous les responsables.',
                        'Chaque action sensible est inscrite dans un journal que personne ne peut modifier.',
                    ],
                    'paragraphs_after' => [
                        'Aucun système n’est infaillible. Si une violation de données vous concernant survenait, nous en informerions les organisations concernées et l’autorité de protection des données dans les délais prévus par la loi.',
                    ],
                ],
                [
                    'title' => 'Vos droits',
                    'paragraphs' => [
                        'Vous pouvez demander à accéder à vos données, à les faire corriger ou effacer, vous opposer à leur traitement pour un motif légitime, et retirer à tout moment un consentement que vous avez donné.',
                    ],
                    'items' => [
                        'Vous avez un compte : la plupart de ces actions se font depuis vos réglages. Pour le reste, écrivez à :privacy_email.',
                        'Vous êtes invité : adressez-vous à l’organisation qui vous a invité. Si vous nous écrivez, nous lui transmettons votre demande.',
                    ],
                    'paragraphs_after' => [
                        'Nous répondons dans un délai d’un mois. Si la réponse ne vous satisfait pas, vous pouvez saisir l’Autorité de Régulation des Télécommunications/TIC de Côte d’Ivoire (ARTCI), autorité de protection des données, ou l’autorité de protection des données de votre pays de résidence.',
                    ],
                ],
                [
                    'title' => 'Cookies et stockage dans votre navigateur',
                    'items' => [
                        'Cookie de session et cookie de protection des formulaires : indispensables pour vous connecter et sécuriser vos actions. Ils ne servent à rien d’autre.',
                        'Préférences d’affichage (thème clair ou sombre, menu replié, langue) : enregistrées dans votre navigateur pour votre confort.',
                        'Contrôle à l’entrée : les passages scannés hors connexion sont gardés sur l’appareil de l’agent jusqu’à leur envoi, puis effacés.',
                        'Mesure d’audience (Google Analytics) : aucun cookie n’est déposé avant votre accord. Vous pouvez changer d’avis à tout moment depuis le lien « Mesure d’audience » en bas du site. Elle ne concerne que la page d’accueil et la page des événements à la une.',
                    ],
                ],
                [
                    'title' => 'Mineurs',
                    'paragraphs' => [
                        'La création d’un compte est réservée aux personnes majeures. Quand un événement accueille des mineurs, c’est à l’organisation de recueillir l’accord de leurs parents ou tuteurs.',
                    ],
                ],
                [
                    'title' => 'Modification de cette politique',
                    'paragraphs' => [
                        'Cette politique évolue avec le service. La date de dernière mise à jour figure en haut de page. Un changement important est annoncé aux organisations par email avant de prendre effet.',
                    ],
                ],
                [
                    'title' => 'Nous contacter',
                    'paragraphs' => [
                        'Pour toute question sur vos données : :privacy_email. Par courrier : :editor, :editor_address.',
                    ],
                ],
            ],
        ],

        'terms' => [
            'title' => 'Conditions générales d’utilisation',
            'summary' => 'Les règles qui s’appliquent entre :editor et les organisations qui utilisent :app.',
            'sections' => [
                [
                    'title' => 'Objet et acceptation',
                    'paragraphs' => [
                        'Ces conditions régissent l’utilisation de :app, service édité par :editor. En créant un compte, vous les acceptez, ainsi que la politique de confidentialité. Si vous agissez pour une organisation, vous déclarez avoir le pouvoir de l’engager.',
                    ],
                ],
                [
                    'title' => 'Définitions',
                    'items' => [
                        'Organisation : l’association, l’église, l’entreprise ou la personne qui ouvre un espace sur :app pour gérer ses événements.',
                        'Propriétaire : le membre qui détient tous les droits sur l’organisation.',
                        'Membre : toute personne ajoutée à une organisation, avec le profil que le Propriétaire lui donne.',
                        'Invité : la personne qui s’inscrit à un événement par le lien public.',
                    ],
                ],
                [
                    'title' => 'Compte et sécurité',
                    'items' => [
                        'Les informations fournies à l’inscription doivent être exactes et tenues à jour.',
                        'Votre mot de passe et votre second facteur d’authentification sont personnels. Vous êtes responsable de ce qui est fait depuis votre compte.',
                        'La double authentification est exigée pour les profils qui touchent à l’argent (comptes de versement, validation des preuves, facturation).',
                        'Prévenez-nous sans délai si vous soupçonnez un accès non autorisé.',
                    ],
                ],
                [
                    'title' => 'Le service',
                    'paragraphs' => [
                        ':app permet de publier un événement, de recevoir des inscriptions, de vérifier des preuves de paiement, d’attribuer des tables, d’émettre des billets et de contrôler les entrées.',
                        'Nous faisons notre possible pour que le service soit disponible en permanence, sans pouvoir le garantir : il peut être interrompu pour maintenance ou pour une cause extérieure. Nous faisons évoluer le service ; une fonction peut être modifiée ou retirée.',
                    ],
                ],
                [
                    'title' => 'L’argent des invités ne passe pas par :app',
                    'paragraphs' => [
                        'Les invités versent leur participation directement sur les comptes de versement de l’organisation (mobile money, virement, espèces). :editor ne reçoit, ne détient et ne reverse aucune de ces sommes.',
                        'La preuve déposée par un invité est une déclaration : c’est à l’organisation de vérifier, sur son propre compte, que l’argent est bien arrivé avant de valider. Les signaux affichés (référence déjà vue, capture déjà déposée) sont une aide, pas une garantie.',
                        'L’organisation est seule responsable des comptes de versement qu’elle affiche, des sommes qu’elle encaisse, des remboursements qu’elle doit à ses invités et du déroulement de son événement.',
                    ],
                ],
                [
                    'title' => 'Plans, essai et paiement de l’abonnement',
                    'items' => [
                        'Les plans, leurs limites et leurs prix sont indiqués sur le site et dans l’écran d’abonnement. Un plan gratuit existe ; les plans payants sont facturés par mois.',
                        'Une période d’essai peut être proposée. Ses conditions sont indiquées dans l’application.',
                        'Le paiement se fait en ligne, par carte, par l’intermédiaire de Stripe. L’abonnement se renouvelle chaque mois jusqu’à sa résiliation, possible à tout moment : elle prend effet à la fin de la période déjà payée.',
                        'En cas d’impayé, une relance est envoyée au bout de 3 jours. Au bout de 10 jours, l’organisation est suspendue : son espace passe en lecture seule et ses liens publics n’acceptent plus d’inscription. Les billets déjà émis restent valables à l’entrée.',
                        'Un prix ou une limite peut changer. Les organisations abonnées en sont prévenues à l’avance ; le changement ne s’applique qu’à la période suivante.',
                        'Sauf disposition légale contraire, une période commencée n’est pas remboursée.',
                    ],
                ],
                [
                    'title' => 'Ce que l’organisation s’engage à respecter',
                    'items' => [
                        'N’utiliser le service que pour des événements licites, et ne rien publier d’illégal, de trompeur ou qui porte atteinte aux droits d’autrui.',
                        'Ne publier un événement qu’après avoir renseigné son identité légale exacte.',
                        'Informer ses invités de l’usage fait de leurs données, n’en collecter que ce qui est nécessaire, et répondre à leurs demandes.',
                        'Accomplir les formalités qui lui incombent auprès de l’autorité de protection des données.',
                        'Ne pas tenter de contourner les limites du service, d’accéder aux données d’une autre organisation, ni de perturber son fonctionnement.',
                    ],
                ],
                [
                    'title' => 'Données des invités : qui fait quoi',
                    'paragraphs' => [
                        'L’organisation est responsable du traitement des données de ses invités. :editor agit comme sous-traitant : il ne traite ces données que pour fournir le service, selon les instructions de l’organisation données par l’usage de l’application.',
                    ],
                    'items' => [
                        ':editor s’engage à garder ces données confidentielles et à ne pas les utiliser pour son propre compte.',
                        'Son personnel n’y accède que par un accès de support ouvert par un Propriétaire, en lecture seule et journalisé.',
                        'Il met en place les mesures de sécurité décrites dans la politique de confidentialité, et prévient l’organisation sans délai en cas de violation de données la concernant.',
                        'Il recourt aux prestataires listés dans la politique de confidentialité, et reste responsable de leur intervention.',
                        'À la fin de la relation, les données sont effacées selon les règles de la section « Suppression ».',
                    ],
                ],
                [
                    'title' => 'Événements annoncés sur le site',
                    'paragraphs' => [
                        'Une organisation peut choisir d’annoncer un événement sur la page publique des événements à la une. C’est un choix volontaire, qu’elle peut retirer à tout moment. :editor peut retirer une annonce contraire à ces conditions ; le motif est transmis à l’organisation.',
                    ],
                ],
                [
                    'title' => 'Propriété intellectuelle',
                    'paragraphs' => [
                        'Le service, son code, sa marque et son apparence appartiennent à :editor. L’organisation reçoit un droit d’usage, non exclusif et non cessible, pour la durée de son utilisation du service.',
                        'L’organisation reste propriétaire de ses contenus (textes, logos, visuels, données). Elle nous autorise à les héberger et à les afficher dans la seule mesure nécessaire au service, et garantit qu’elle a le droit de les utiliser.',
                    ],
                ],
                [
                    'title' => 'Suspension, résiliation et suppression',
                    'paragraphs' => [
                        'Un Propriétaire peut supprimer son organisation à tout moment depuis ses réglages. :editor peut suspendre ou fermer une organisation en cas d’impayé, de manquement grave à ces conditions ou sur demande d’une autorité ; le motif lui est communiqué.',
                        'Une organisation supprimée n’est plus accessible à ses membres. Elle reste restaurable pendant 30 jours, à la demande d’un Propriétaire, puis ses données sont effacées définitivement. Des copies peuvent subsister jusqu’à un an dans les sauvegardes, qui ne servent qu’à rétablir le service après une panne.',
                        'Il appartient à l’organisation d’exporter ses données avant la suppression : listes d’inscrits, rapports, reçus.',
                    ],
                ],
                [
                    'title' => 'Responsabilité',
                    'paragraphs' => [
                        ':editor est tenu d’apporter au service le soin d’un professionnel diligent. Il ne répond pas des dommages indirects (perte de recettes, de clientèle, d’image), ni des conséquences d’un mauvais usage du service, d’une information inexacte fournie par l’organisation, ou d’un paiement entre un invité et l’organisation.',
                        'Dans la limite permise par la loi, sa responsabilité est plafonnée au montant payé par l’organisation au cours des douze mois précédant le fait générateur.',
                        'Aucune des parties ne répond d’un manquement dû à un cas de force majeure : panne de réseau ou d’électricité, défaillance d’un opérateur, décision d’une autorité.',
                    ],
                ],
                [
                    'title' => 'Modification des conditions',
                    'paragraphs' => [
                        'Ces conditions peuvent évoluer. Les organisations sont prévenues par email d’un changement important au moins 30 jours avant son entrée en vigueur. Continuer à utiliser le service après cette date vaut acceptation ; à défaut, l’organisation peut résilier et supprimer son espace.',
                    ],
                ],
                [
                    'title' => 'Droit applicable et litiges',
                    'paragraphs' => [
                        'Ces conditions sont soumises au droit ivoirien et aux Actes uniformes de l’OHADA. En cas de différend, les parties cherchent d’abord une solution amiable. À défaut d’accord dans un délai de 30 jours, le litige est porté devant les tribunaux compétents d’Abidjan, sous réserve des règles protectrices dont bénéficie un consommateur.',
                    ],
                ],
                [
                    'title' => 'Nous contacter',
                    'paragraphs' => [
                        ':editor, :editor_address. Email : :contact_email.',
                    ],
                ],
            ],
        ],

        'notice' => [
            'title' => 'Mentions légales',
            'summary' => 'Qui édite et qui héberge :app.',
            'sections' => [
                [
                    'title' => 'Éditeur',
                    'items' => [
                        'Dénomination : :editor',
                        'Forme juridique : :legal_form',
                        'Capital social : :share_capital',
                        'Registre du commerce (RCCM) : :registration_number',
                        'Numéro de compte contribuable : :tax_number',
                        'Siège social : :editor_address',
                        'Email : :contact_email',
                        'Directeur de la publication : :publication_director',
                    ],
                ],
                [
                    'title' => 'Hébergeur',
                    'paragraphs' => [
                        ':host, :host_address.',
                    ],
                ],
                [
                    'title' => 'Contenus publiés par les organisations',
                    'paragraphs' => [
                        'Les pages d’événement sont rédigées par les organisations, qui en sont responsables. Pour signaler un contenu illicite, écrivez à :contact_email en précisant l’adresse de la page et le motif : il est examiné et, s’il y a lieu, retiré.',
                    ],
                ],
                [
                    'title' => 'Propriété intellectuelle',
                    'paragraphs' => [
                        'La marque :app, le site et le logiciel sont la propriété de :editor. Toute reproduction sans autorisation est interdite.',
                    ],
                ],
                [
                    'title' => 'Données personnelles',
                    'paragraphs' => [
                        'Le traitement des données personnelles est décrit dans la politique de confidentialité. Contact : :privacy_email.',
                    ],
                ],
            ],
        ],
    ],
];

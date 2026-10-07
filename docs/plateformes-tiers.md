# Plateformes tierces : liens d'accès

Les services extérieurs dont Convive dépend, et où les gérer. **Aucun secret ici** : mots de passe,
jetons et clés restent dans `.env` (jamais dans le dépôt). Ce document ne donne que les adresses
et des identifiants qui ne permettent rien seuls.

État au 2026-10-07.

## WhatsApp : Meta (en service)

Envoi des cartes, rappels et alertes par l'API Cloud de WhatsApp, en direct (`WHATSAPP_DRIVER=meta`).

| Quoi | Lien |
| --- | --- |
| Portefeuille d'entreprise « Dev Ultra App » (paramètres, utilisateurs système, jetons) | https://business.facebook.com/settings |
| Gestionnaire WhatsApp (numéros, nom d'affichage, qualité) | https://business.facebook.com/wa/manage/home/ |
| Modèles de messages (statut d'approbation) | https://business.facebook.com/wa/manage/message-templates/ |
| Application Meta « Convive » (configuration de l'API, webhook) | https://developers.facebook.com/apps/ |
| Documentation de l'API Cloud | https://developers.facebook.com/docs/whatsapp/cloud-api |

- Compte WhatsApp Business (WABA) : `1108085104900352`.
- Numéro d'envoi actuel : numéro de test de Meta (`WHATSAPP_META_PHONE_NUMBER_ID` dans `.env`).
- Jeton : utilisateur système `convive-serveur`, sans expiration.
- Les six modèles utilitaires sont approuvés ; leurs textes sont dans `docs/whatsapp-modeles.md`.
- Reste à faire chez Meta : numéro dédié, nom d'affichage « Convive », moyen de paiement.

## WhatsApp : Twilio (en réserve)

Premier connecteur branché, gardé en secours (`WHATSAPP_DRIVER=twilio`).

| Quoi | Lien |
| --- | --- |
| Console Twilio (journaux des messages, bac à sable WhatsApp) | https://console.twilio.com/ |

## SMS : Orange Côte d'Ivoire (en attente)

Connecteur écrit pour le code de vérification du téléphone (`SMS_DRIVER=orange`) ; compte Orange
pas encore créé, code par SMS mis de côté.

| Quoi | Lien |
| --- | --- |
| Orange Developer (compte, applications, identifiants) | https://developer.orange.com/ |
| API SMS Côte d'Ivoire et tarifs | https://developer.orange.com/apis/sms-ci |

## Courriels : OVH E-mail Pro (en service)

Envoi par le SMTP d'OVH, adresse `support@devultraapp.com` (`MAIL_*` dans `.env`).

| Quoi | Lien |
| --- | --- |
| Espace client OVH (domaine devultraapp.com, DNS, E-mail Pro) | https://www.ovh.com/manager/ |

Resend a été retenu pour plus tard, quand l'hébergement sera choisi : https://resend.com/

## Hébergement : Contabo (en service)

Le VPS qui sert devultraapp.com.

| Quoi | Lien |
| --- | --- |
| Panneau client Contabo | https://my.contabo.com/ |
| Site produit | https://devultraapp.com |

## Protection anti-robot : Cloudflare Turnstile

Le contrôle du formulaire d'inscription des invités (`TURNSTILE_*` dans `.env`).

| Quoi | Lien |
| --- | --- |
| Tableau de bord Cloudflare (rubrique Turnstile) | https://dash.cloudflare.com/ |

## Code source : GitHub

| Quoi | Lien |
| --- | --- |
| Dépôt | https://github.com/newton225/convive |

## Pas encore branchés

- **Mesure d'audience** : Google Analytics, https://analytics.google.com/ (`GOOGLE_ANALYTICS_ID`
  vide).
- **Surveillance extérieure du planificateur** : `SCHEDULE_HEARTBEAT_URL` vide, service à choisir.
- **Stockage S3** : `AWS_*` vides, les fichiers restent sur le disque du serveur.
- **Paiement des abonnements** : Stripe indisponible en Côte d'Ivoire, paiement à la main retenu.

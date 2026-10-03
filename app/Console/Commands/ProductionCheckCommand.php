<?php

namespace App\Console\Commands;

use App\Support\BotCheck;
use App\Support\LegalDocument;
use App\Support\Release;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Verification de la configuration avant une mise en production (SECURITY.md, « Erreurs » et
 * grille de test : « Un point non verifie est un point suppose faux »). A jouer a chaque
 * deploiement : un echec arrete le deploiement plutot que de laisser partir un reglage de
 * developpement.
 */
#[Signature('convive:production-check')]
#[Description('Refuse une configuration de production dangereuse (debug, cookies, proxys, emails, 2FA)')]
class ProductionCheckCommand extends Command
{
    public function handle(): int
    {
        $failures = array_keys(array_filter([
            'APP_DEBUG doit valoir false : une erreur afficherait la pile d\'appel et les noms de tables.' => (bool) config('app.debug'),
            'APP_ENV doit valoir production.' => config('app.env') !== 'production',
            'APP_KEY est vide : sessions, chiffrement et liens signes ne tiennent pas.' => blank(config('app.key')),
            'SESSION_SECURE_COOKIE doit valoir true : le cookie de session ne doit jamais circuler en clair.' => config('session.secure') !== true,
            'Le cookie de session doit rester HttpOnly.' => config('session.http_only') !== true,
            'TRUSTED_PROXIES ne doit jamais contenir * : X-Forwarded-For deviendrait falsifiable (SECURITY.md C3).' => str_contains((string) config('convive.security.trusted_proxies'), '*'),
            'MAIL_MAILER ne doit pas ecrire les emails dans le journal ni les jeter.' => in_array(config('mail.default'), ['log', 'array'], true),
            'LOG_LEVEL ne doit pas valoir debug : le journal porterait des details internes et grossirait vite.' => config('logging.channels.'.config('logging.default').'.level') === 'debug' || config('logging.channels.daily.level') === 'debug',
            'LOG_STACK ne doit pas contenir single : un fichier de journal unique grossit jusqu\'a remplir le disque. Utilisez daily.' => in_array('single', (array) config('logging.channels.stack.channels'), true),
            'CONVIVE_ENFORCE_TWO_FACTOR doit rester actif en production.' => config('convive.two_factor.enforced') !== true,
            'Les pages juridiques sont incompletes : renseignez l\'identite de l\'editeur ('.implode(', ', LegalDocument::missingIdentity()).').' => LegalDocument::missingIdentity() !== [],
            'La livraison n\'est pas estampillee : jouez php artisan convive:release apres chaque mise a jour du code, pour que la version affichee dise ce qui tourne.' => Release::current() === null,
            'TURNSTILE_SITE_KEY et TURNSTILE_SECRET_KEY doivent porter les vraies cles Cloudflare : vides, la protection anti-robot ne s\'applique nulle part ; cles d\'essai (1x..., 2x..., 3x...), elle laisse passer les robots et affiche un bandeau de test aux invites.' => BotCheck::siteKey() === null || BotCheck::usesTestKeys(),
            'CONVIVE_LEGAL_REVIEWED doit valoir true : les pages juridiques n\'ont pas ete validees par un juriste.' => ! LegalDocument::isReviewed(),
        ]));

        if ($failures === []) {
            $this->components->info('Configuration de production verifiee.');

            return self::SUCCESS;
        }

        foreach ($failures as $failure) {
            $this->components->error($failure);
        }

        return self::FAILURE;
    }
}

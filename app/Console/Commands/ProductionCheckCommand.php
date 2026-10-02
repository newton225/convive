<?php

namespace App\Console\Commands;

use App\Support\LegalDocument;
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
            'CONVIVE_ENFORCE_TWO_FACTOR doit rester actif en production.' => config('convive.two_factor.enforced') !== true,
            'Les pages juridiques sont incompletes : renseignez l\'identite de l\'editeur ('.implode(', ', LegalDocument::missingIdentity()).').' => LegalDocument::missingIdentity() !== [],
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

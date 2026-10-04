<?php

namespace App\Support;

use App\Notifications\Console\TenantCreationFailed;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Un espace qui n'a pas pu etre ouvert (incident du 2026-10-04) : le detail technique va au journal
 * applicatif, et l'equipe est prevenue a `CONVIVE_ALERT_EMAIL`. La personne, elle, lit un message
 * d'excuse sur le formulaire, jamais une page d'erreur.
 */
final class TenantCreationFailure
{
    public static function report(Throwable $exception, string $organisation, string $email): void
    {
        report($exception);

        $alertEmail = config('convive.alert_email');

        if (is_string($alertEmail) && $alertEmail !== '') {
            // L'alerte ne doit jamais aggraver l'echec qu'elle signale.
            rescue(fn () => Notification::route('mail', $alertEmail)->notifyNow(new TenantCreationFailed($organisation, $email)));
        }
    }
}

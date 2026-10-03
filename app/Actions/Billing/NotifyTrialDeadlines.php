<?php

namespace App\Actions\Billing;

use App\Actions\Notifications\SendAlert;
use App\Enums\NotificationType;
use App\Models\Tenant;
use App\Settings\TrialSettings;
use Stancl\Tenancy\Exceptions\TenantDatabaseDoesNotExistException;

/**
 * Prevenir les organisations dont l'essai se termine (README section 3) : sept jours avant, la
 * veille, puis une fois l'essai echu. A l'echeance rien n'est supprime : l'organisation retombe
 * sur le plan par defaut (`Tenant::plan()`), et ces alertes sont ce qui lui evite de le decouvrir
 * en voyant une publication refusee.
 *
 * Jouee chaque jour par le planificateur. Une organisation abonnee n'est pas concernee, son
 * abonnement l'emporte sur l'essai ; un essai sans date de fin non plus, ni l'espace personnel de
 * chaque compte, qui ne publie rien.
 */
class NotifyTrialDeadlines
{
    /**
     * A partir de combien de jours avant la fin part le premier rappel.
     */
    public const FirstNoticeDays = 7;

    public function __construct(private SendAlert $alerts, private TrialSettings $settings)
    {
        //
    }

    public function handle(): void
    {
        // Essai ferme depuis la console : plus personne n'est a l'essai, rien a annoncer.
        if (! $this->settings->enabled) {
            return;
        }

        Tenant::query()
            ->whereNotNull('trial_ends_at')
            // L'espace personnel ne publie rien : la fin de son essai ne change rien a ce qu'il permet.
            ->where('is_personal', false)
            ->whereNull('trial_ended_notified_at')
            ->doesntHave('subscription')
            ->each(function (Tenant $tenant) {
                $endsAt = $tenant->trial_ends_at;

                if ($endsAt === null) {
                    return;
                }

                if ($endsAt->isPast()) {
                    $this->notify($tenant, NotificationType::TrialEnded, ['plan' => $tenant->plan()->name], 'trial_ended_notified_at');

                    return;
                }

                $daysLeft = $tenant->trialDaysLeft();

                if ($daysLeft === null) {
                    return;
                }

                if ($daysLeft <= 1 && $tenant->trial_last_day_notified_at === null) {
                    // Un essai raccourci a moins d'un jour ne recoit pas, en plus, le rappel de la semaine.
                    $tenant->forceFill(['trial_ending_notified_at' => $tenant->trial_ending_notified_at ?? now()]);
                    $this->notify($tenant, NotificationType::TrialEnding, ['count' => $daysLeft], 'trial_last_day_notified_at');

                    return;
                }

                if ($daysLeft <= self::FirstNoticeDays && $tenant->trial_ending_notified_at === null) {
                    $this->notify($tenant, NotificationType::TrialEnding, ['count' => $daysLeft], 'trial_ending_notified_at');
                }
            });
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function notify(Tenant $tenant, NotificationType $type, array $params, string $column): void
    {
        try {
            // L'alerte s'adresse a l'equipe de l'organisation : elle part sous sa tenancy.
            $tenant->run(fn () => $this->alerts->toTenantMembers(
                $type,
                $params,
                route('tenants.billing.show', $tenant, absolute: false),
            ));
        } catch (TenantDatabaseDoesNotExistException) {
            // Voir `SyncTenantPermissionsCommand` : sans `end()`, la tenancy reste a moitie posee.
            // Une organisation sans base est signalee par la sante technique ; on ne marque rien,
            // le rappel repartira une fois la base reparee.
            tenancy()->end();

            return;
        }

        $tenant->forceFill([$column => now()])->save();
    }
}

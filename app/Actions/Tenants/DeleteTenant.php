<?php

namespace App\Actions\Tenants;

use App\Actions\Console\ManageOrganisation;
use App\Contracts\SubscriptionBillingGateway;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Tenants\TenantDeletionScheduled;
use App\Support\Console\ConsoleJournal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * La suppression d'une organisation par son Proprietaire (README section 3). Elle disparait tout de
 * suite pour ses membres, mais rien n'est efface : l'effacement reel est programme a trente jours,
 * le meme circuit que la suppression demandee a l'equipe Convive (`tenants:erase-scheduled`). D'ici
 * la, l'equipe Convive peut la restaurer (`ManageOrganisation::cancelDeletion()`).
 *
 * Sans cette echeance, une organisation « supprimee » gardait sa base et ses fichiers sur le
 * serveur indefiniment, sans que personne puisse ni les lire ni les effacer.
 */
class DeleteTenant
{
    public function handle(Tenant $tenant, User $actor): void
    {
        // Releves avant de retirer les appartenances : ce sont eux qu'on previent.
        $owners = $tenant->owners();
        $eraseAt = now()->addDays(ManageOrganisation::DeletionDelayDays);

        DB::transaction(function () use ($tenant, $actor, $eraseAt) {
            User::where('current_tenant_id', $tenant->id)
                ->where('id', '!=', $actor->id)
                ->each(fn (User $affectedUser) => $affectedUser->switchTenant($affectedUser->personalTenant()));

            $tenant->invitations()->delete();
            // Les appartenances centrales partent : plus personne n'atteint l'organisation. Les
            // profils, eux, restent dans sa base, et c'est d'eux qu'une restauration repart.
            $tenant->memberships()->delete();

            $tenant->forceFill(['deletion_scheduled_at' => $eraseAt])->save();
            $tenant->delete();
        });

        // La requete tourne dans le contexte de l'organisation qu'on vient de supprimer. On en sort
        // avant d'ecrire aux Proprietaires : un message mis en file dans ce contexte porterait
        // l'identifiant d'une organisation que la file ne saurait plus ouvrir, et ne partirait jamais.
        tenancy()->end();

        ConsoleJournal::record('tenant_deleted_by_owner', $actor, $tenant, [
            'erase_at' => $eraseAt->toISOString(),
            'subscription_cancelled' => $this->cancelSubscription($tenant),
        ]);

        Notification::send($owners, new TenantDeletionScheduled($tenant->name, $actor->name, $eraseAt->toISOString()));
    }

    /**
     * Stop the paid subscription at the end of the period already paid : une organisation
     * supprimee ne doit pas continuer d'etre prelevee. Un echec ici n'empeche pas la suppression ;
     * il est note au journal central, ou l'equipe Convive le voit.
     */
    private function cancelSubscription(Tenant $tenant): bool
    {
        $subscription = $tenant->subscription;

        if ($subscription?->stripe_subscription_id === null) {
            return false;
        }

        try {
            app(SubscriptionBillingGateway::class)->cancel($subscription);

            return true;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }
}

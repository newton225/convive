<?php

use App\Actions\Audit\PurgeAuditLog;
use App\Actions\Billing\ProcessOverdueSubscriptions;
use App\Actions\Registrations\ExpireHolds;
use App\Actions\Registrations\PurgeRegistrations;
use App\Actions\Tenants\SavePaymentAccount;
use App\Actions\Tickets\SendInvitationCard;
use App\Actions\Tickets\SendProofReminder;
use App\Actions\Tickets\SendTicketReminder;
use App\Actions\Waitlist\PromoteNextWaitlistEntry;
use App\Enums\RegistrationStatus;
use App\Enums\ReminderCheckpoint;
use App\Enums\WaitlistStatus;
use App\Models\Event;
use App\Models\PaymentAccount;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\Ticket;
use App\Models\WaitlistEntry;
use App\Support\AuditChain;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    TenantInvitation::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->delete();
})->daily()->description('Delete expired tenant invitations');

/*
 * Applique les changements de compte de versement dont le delai est ecoule.
 *
 * C'est le seul endroit qui bascule une valeur en attente vers la valeur vivante. Si la tache
 * ne tourne pas, l'activation est en retard et l'ancien numero reste affiche : c'est le bon
 * sens de defaillance pour de l'argent.
 */
Schedule::call(function (SavePaymentAccount $save) {
    // On boucle sur les locataires et on pose le contexte, plutot que de lever le scope
    // global : une tache planifiee n'a pas de raison d'etre le seul endroit du code qui sait
    // lire les donnees de tout le monde d'un coup.
    Tenant::query()->each(fn (Tenant $tenant) => $tenant->asCurrent(
        fn () => PaymentAccount::query()
            ->whereNotNull('pending_activates_at')
            ->where('pending_activates_at', '<=', now())
            ->each(fn (PaymentAccount $account) => $save->apply($account)),
    ));
})->everyFiveMinutes()->description('Activate due payment account changes');

/*
 * Marque `Expired` toute reservation dont le decompte est ecoule (README 2.1 et 2.2) : la
 * disponibilite en tient deja compte a la lecture (`Registration::scopeOccupyingSeats`), mais le
 * statut stocke doit refleter la realite pour l'affichage et les rapports, sans attendre que
 * l'invite revienne sur sa page.
 *
 * Boucle evenement par evenement, plutot qu'une seule mise a jour groupee, pour pouvoir avancer
 * la liste d'attente (README 2.3) des qu'une place se libere ainsi.
 */
Schedule::call(function (ExpireHolds $expire) {
    Tenant::query()->each(fn (Tenant $tenant) => $tenant->asCurrent(
        fn () => Event::query()->each(fn (Event $event) => $expire->handle($event)),
    ));
})->everyMinute()->description('Mark expired holds as such and advance the waitlist');

/*
 * Meme logique pour la liste d'attente elle-meme (README 2.3) : passe le delai de six heures
 * sans reponse, on invite le suivant.
 */
Schedule::call(function (PromoteNextWaitlistEntry $promote) {
    Tenant::query()->each(fn (Tenant $tenant) => $tenant->asCurrent(function () use ($promote) {
        Event::query()->each(function (Event $event) use ($promote) {
            $expired = WaitlistEntry::query()
                ->where('event_id', $event->id)
                ->where('status', WaitlistStatus::Invited)
                ->where('expires_at', '<=', now())
                ->update(['status' => WaitlistStatus::Expired]);

            if ($expired > 0) {
                $promote->handle($event);
            }
        });
    }));
})->everyMinute()->description('Expire unanswered waitlist invites and advance the queue');

/*
 * Purge automatique (README 2.4), premier declencheur : a l'echeance planifiee de l'evenement,
 * toutes les inscriptions non finalisees sont supprimees et leurs places rendues au stock.
 */
Schedule::call(function (PurgeRegistrations $purge) {
    Tenant::query()->each(fn (Tenant $tenant) => $tenant->asCurrent(
        fn () => Event::query()
            ->whereNotNull('purge_at')
            ->where('purge_at', '<=', now())
            ->each(fn (Event $event) => $purge->handle($event)),
    ));
})->everyFiveMinutes()->description('Purge non-finalized registrations at their event deadline');

/*
 * Purge automatique (README 2.4), second declencheur : la capacite est atteinte par les seules
 * inscriptions confirmees, sans attendre l'echeance. `Confirmed` n'est pas encore atteignable
 * par le code (etape 6, validation de la preuve) : ce declencheur reste en place, pret des que
 * la transition existera.
 */
Schedule::call(function (PurgeRegistrations $purge) {
    Tenant::query()->each(fn (Tenant $tenant) => $tenant->asCurrent(
        fn () => Event::query()->where('rule_purge_on_exhaustion', true)->each(function (Event $event) use ($purge) {
            if ($event->capacity() > 0 && $event->confirmedSeats() >= $event->capacity()) {
                $purge->handle($event);
            }
        }),
    ));
})->everyMinute()->description('Purge non-finalized registrations once capacity is exhausted by confirmed seats alone');

/*
 * Envoi programme de la carte d'invitation (README 2.7), etape 8 de « Ordre de construction ».
 * A l'echeance de l'evenement, toutes les inscriptions confirmees avant cette echeance (celles
 * validees apres sont deja servies par `ValidatePaymentProof`) recoivent leur carte.
 */
Schedule::call(function (SendInvitationCard $send) {
    Tenant::query()->each(fn (Tenant $tenant) => $tenant->asCurrent(
        fn () => Event::query()
            ->where('rule_scheduled_send', true)
            ->whereNotNull('invitations_send_at')
            ->where('invitations_send_at', '<=', now())
            ->each(fn (Event $event) => Registration::query()
                ->where('event_id', $event->id)
                ->where('status', RegistrationStatus::Confirmed)
                ->whereNull('card_sent_at')
                ->each(fn (Registration $registration) => $send->handle($registration))),
    ));
})->everyFiveMinutes()->description('Send the invitation card once an event reaches its scheduled send date');

/*
 * Rappels J-7, J-2 et J-1 aux inscriptions sans preuve encore validee (README 2.7), etape 8.
 * Trois blocs distincts plutot qu'une boucle sur les echeances : meme choix editorial que les
 * declencheurs de purge ci-dessus, chacun reste lisible et testable seul.
 */
Schedule::call(function (SendProofReminder $send) {
    $checkpoint = ReminderCheckpoint::SevenDaysBefore;

    Tenant::query()->each(fn (Tenant $tenant) => $tenant->asCurrent(
        fn () => Event::query()
            ->where('reminder_j7_enabled', true)
            ->whereNotNull('starts_at')
            ->whereBetween('starts_at', [now(), now()->addDays($checkpoint->value)])
            ->each(fn (Event $event) => Registration::query()
                ->where('event_id', $event->id)
                ->whereIn('status', [RegistrationStatus::Held, RegistrationStatus::ProofRejected])
                ->whereNull($checkpoint->column())
                ->each(fn (Registration $registration) => $send->handle($registration, $checkpoint))),
    ));
})->everyFiveMinutes()->description('Send the J-7 missing-proof reminder');

Schedule::call(function (SendProofReminder $send) {
    $checkpoint = ReminderCheckpoint::TwoDaysBefore;

    Tenant::query()->each(fn (Tenant $tenant) => $tenant->asCurrent(
        fn () => Event::query()
            ->where('reminder_j2_enabled', true)
            ->whereNotNull('starts_at')
            ->whereBetween('starts_at', [now(), now()->addDays($checkpoint->value)])
            ->each(fn (Event $event) => Registration::query()
                ->where('event_id', $event->id)
                ->whereIn('status', [RegistrationStatus::Held, RegistrationStatus::ProofRejected])
                ->whereNull($checkpoint->column())
                ->each(fn (Registration $registration) => $send->handle($registration, $checkpoint))),
    ));
})->everyFiveMinutes()->description('Send the J-2 missing-proof reminder');

Schedule::call(function (SendProofReminder $send) {
    $checkpoint = ReminderCheckpoint::OneDayBefore;

    Tenant::query()->each(fn (Tenant $tenant) => $tenant->asCurrent(
        fn () => Event::query()
            ->where('reminder_j1_enabled', true)
            ->whereNotNull('starts_at')
            ->whereBetween('starts_at', [now(), now()->addDays($checkpoint->value)])
            ->each(fn (Event $event) => Registration::query()
                ->where('event_id', $event->id)
                ->whereIn('status', [RegistrationStatus::Held, RegistrationStatus::ProofRejected])
                ->whereNull($checkpoint->column())
                ->each(fn (Registration $registration) => $send->handle($registration, $checkpoint))),
    ));
})->everyFiveMinutes()->description('Send the J-1 missing-proof reminder');

/*
 * Rappel jour J moins 3 heures aux billets valides (README 2.7), etape 8. Plus frequent que les
 * rappels de preuve : une fenetre de trois heures tolere moins de retard qu'une fenetre de
 * plusieurs jours avant de declencher une premiere verification.
 */
Schedule::call(function (SendTicketReminder $send) {
    Tenant::query()->each(fn (Tenant $tenant) => $tenant->asCurrent(
        fn () => Event::query()
            ->where('reminder_day_of_enabled', true)
            ->whereNotNull('starts_at')
            ->whereBetween('starts_at', [now(), now()->addHours(3)])
            ->each(fn (Event $event) => Ticket::query()
                ->whereHas('registration', fn ($query) => $query->where('event_id', $event->id))
                ->whereNull('reminder_sent_at')
                ->each(fn (Ticket $ticket) => $send->handle($ticket))),
    ));
})->everyMinute()->description('Send the J-3h reminder to valid tickets');

/*
 * Relance a J+3 et suspension a J+10 des abonnements impayes (README section 3, « Facturation »).
 * Une seule passe par jour suffit : les etapes se comptent en jours, et l'action est idempotente.
 */
Schedule::call(fn (ProcessOverdueSubscriptions $process) => $process->handle())
    ->daily()
    ->description('Remind and suspend subscriptions left unpaid');

/*
 * Conservation du journal d'audit a 24 mois (CLAUDE.md, « Securite » : « ecriture seule...
 * conservation 24 mois »), centrale et par locataire (voir CLAUDE.md, « Multi-locataire » : les
 * deux journaux vivent dans des bases distinctes). Chaque purge laisse une entree et l'ancre de la
 * chaine d'empreintes (SECURITY.md M6).
 */
Schedule::call(fn (PurgeAuditLog $purge) => $purge->handle())
    ->daily()
    ->description('Purge the central audit log entries older than 24 months');

Schedule::call(function (PurgeAuditLog $purge) {
    Tenant::query()->each(fn (Tenant $tenant) => $tenant->asCurrent(fn () => $purge->handle()));
})->daily()->description('Purge each tenant\'s audit log entries older than 24 months');

/*
 * Verification quotidienne de la chaine d'empreintes du journal (SECURITY.md M6). Une rupture est
 * signalee au niveau critique : une entree modifiee ou supprimee hors de la purge tracee.
 */
Schedule::call(function () {
    $report = function (string $scope): void {
        $brokenId = AuditChain::firstBrokenEntry();

        if ($brokenId !== null) {
            Log::critical('Chaine du journal d\'audit rompue', ['scope' => $scope, 'entry_id' => $brokenId]);
        }
    };

    $report('central');
    Tenant::query()->each(fn (Tenant $tenant) => $tenant->asCurrent(fn () => $report('tenant:'.$tenant->id)));
})->daily()->description('Verify the audit log hash chains');

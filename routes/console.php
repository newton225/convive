<?php

use App\Actions\Audit\PurgeAuditLog;
use App\Actions\Billing\NotifyTrialDeadlines;
use App\Actions\Billing\ProcessOverdueSubscriptions;
use App\Actions\Events\PurgeDeletedEvents;
use App\Actions\Notifications\NotifyUpcomingPurge;
use App\Actions\Registrations\ExpireHolds;
use App\Actions\Registrations\PurgeRegistrations;
use App\Actions\Tenants\ManageSupportAccess;
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
use App\Support\Console\MessageJournal;
use App\Support\Console\SecurityJournal;
use App\Support\Console\TenantUsageRecorder;
use Illuminate\Support\Facades\Schedule;
use Spatie\Health\Commands\DispatchQueueCheckJobsCommand;
use Spatie\Health\Commands\RunHealthChecksCommand;
use Spatie\Health\Commands\ScheduleCheckHeartbeatCommand;
use Spatie\WebhookClient\Models\WebhookCall;

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
 * Alerte d'anticipation (prototype Convive.dc.html) : la purge approche, il reste un jour pour
 * relancer les invites sans preuve.
 */
Schedule::call(function (NotifyUpcomingPurge $notify) {
    Tenant::query()->each(fn (Tenant $tenant) => $tenant->asCurrent(
        fn () => Event::query()
            ->whereNull('purge_notice_sent_at')
            ->whereNotNull('purge_at')
            ->whereBetween('purge_at', [now(), now()->addHours(NotifyUpcomingPurge::NoticeHours)])
            ->each(fn (Event $event) => $notify->handle($event)),
    ));
})->hourly()->description('Warn the team a day before an automatic purge');

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
 * inscriptions confirmees, sans attendre l'echeance.
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
 * plusieurs jours avant de declencher une premiere verification. Le seul billet de l'invite : ses
 * accompagnateurs ont chacun le leur (README 2.8), mais le message part au meme telephone, un seul
 * suffit.
 */
Schedule::call(function (SendTicketReminder $send) {
    Tenant::query()->each(fn (Tenant $tenant) => $tenant->asCurrent(
        fn () => Event::query()
            ->where('reminder_day_of_enabled', true)
            ->whereNotNull('starts_at')
            ->whereBetween('starts_at', [now(), now()->addHours(3)])
            ->each(fn (Event $event) => Ticket::query()
                ->whereHas('registration', fn ($query) => $query->where('event_id', $event->id))
                ->where('holder_position', Ticket::GuestPosition)
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
 * Fin de la periode d'essai (README section 3) : rappel sept jours avant, la veille, puis le jour
 * ou l'essai a pris fin. Une passe par jour, le matin ; chaque rappel ne part qu'une fois.
 */
Schedule::call(fn (NotifyTrialDeadlines $notify) => $notify->handle())
    ->dailyAt('08:00')
    ->description('Warn organisations whose trial is ending');

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
 * signalee au niveau critique : une entree modifiee ou supprimee hors de la purge tracee. Le
 * resultat est garde pour l'ecran Securite de la console et le controle de sante, qui previent par
 * courriel. Les faits de securite de plus de 90 jours partent au meme passage.
 */
Schedule::call(function () {
    SecurityJournal::checkAuditChain();
    Tenant::query()->each(fn (Tenant $tenant) => $tenant->asCurrent(fn () => SecurityJournal::checkAuditChain($tenant)));
    SecurityJournal::purge();
    // Le releve des envois ne se garde que trente jours.
    MessageJournal::purge();
})->daily()->description('Verify the audit log hash chains');

/*
 * Filet de securite du catalogue de permissions (CLAUDE.md, « Profils et permissions ») : la
 * commande se joue a chaque deploiement, apres `tenants:migrate` ; ce passage quotidien rattrape
 * un deploiement ou elle aurait ete oubliee.
 */
Schedule::command('tenants:sync-permissions')
    ->daily()
    ->description('Bring every organisation up to the permission catalogue');

/*
 * Previent les Proprietaires quand un acces de support arrive a echeance (README section 3). Un
 * acces expire sans que personne n'agisse : il faut une tache pour le dire. Idempotente, par
 * `ended_notified_at`.
 */
Schedule::call(fn (ManageSupportAccess $manage) => $manage->announceExpired())
    ->everyFiveMinutes()
    ->description('Tell owners about support accesses that reached their term');

/*
 * Releve la consommation de chaque organisation et la range dans la base centrale (README
 * section 3) : la console lit ces compteurs, jamais les bases des organisations a chaque affichage.
 */
Schedule::call(fn () => TenantUsageRecorder::refreshAll())
    ->everyFifteenMinutes()
    ->description('Refresh the usage counters the console reads');

/*
 * Sauvegarde quotidienne (`config/backup.php`) : les bases de toutes les organisations et leurs
 * fichiers, la nuit, quand l'application est la moins sollicitee. `convive:backup` et non
 * `backup:run`, qui archiverait les fichiers sans les bases. Le menage applique ensuite la duree de
 * conservation, et la surveillance alerte si la derniere archive est trop ancienne.
 */
Schedule::command('convive:backup')
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->description('Back up every database and the uploaded files');

Schedule::command('backup:clean')
    ->dailyAt('03:30')
    ->description('Remove backups past their retention period');

Schedule::command('backup:monitor')
    ->dailyAt('06:00')
    ->description('Alert when the newest backup is too old');

/*
 * Surveillance (`App\Providers\HealthServiceProvider`). Deux temoins partent chaque minute : l'un
 * dit que le planificateur tourne, l'autre traverse la file pour dire qu'elle est traitee. Les
 * controles sont ensuite joues, et un echec previent par courriel (`config/health.php`).
 */
Schedule::command(ScheduleCheckHeartbeatCommand::class)
    ->everyMinute()
    ->description('Record that the scheduler is alive');

Schedule::command(DispatchQueueCheckJobsCommand::class)
    ->everyMinute()
    ->description('Send a witness job through the queue');

Schedule::command(RunHealthChecksCommand::class)
    ->everyMinute()
    ->description('Run the health checks and alert on failure');

/*
 * Efface les organisations dont la suppression programmee est arrivee a echeance (README
 * section 3) : trente jours apres la demande, sauf annulation depuis la console. `--force` : sans
 * lui la commande ne fait que lister. L'effacement ne se rattrape pas, chaque borne est testee
 * (`EraseScheduledTenantsTest`).
 */
Schedule::command('tenants:erase-scheduled --force')
    ->dailyAt('04:30')
    ->description('Erase organisations whose scheduled deletion is due');

/*
 * Efface pour de bon les evenements supprimes depuis plus de trente jours (des brouillons : un
 * evenement publie se cloture). Sans cette tache, ils resteraient dans la corbeille indefiniment,
 * avec leur visuel.
 */
Schedule::call(function (PurgeDeletedEvents $purge) {
    Tenant::query()->each(fn (Tenant $tenant) => $tenant->asCurrent(fn () => $purge->handle()));
})->daily()->description('Erase events deleted more than thirty days ago');

/*
 * Efface les appels de webhook (Stripe, WhatsApp) gardes depuis plus de trente jours
 * (`config/webhook-client.php`). Un message WhatsApp traite est deja efface aussitot ; il ne reste
 * ici que les appels dont le traitement a echoue, et ceux de Stripe.
 */
Schedule::command('model:prune', ['--model' => [WebhookCall::class]])
    ->daily()
    ->description('Delete webhook calls older than thirty days');

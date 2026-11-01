<?php

namespace App\Jobs;

use App\Enums\RegistrationStatus;
use App\Models\Registration;
use App\Notifications\Registrations\TableChanged;
use App\Support\GuestMessageQuota;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/**
 * Previent l'invite d'un changement de table (decision du 2026-10-31), un peu apres le deplacement et non a
 * chaque geste : un organisateur qui repose un invite trois fois en deux minutes ne lui envoie qu'un message,
 * avec la table finale. Le premier deplacement note la table que l'invite connaissait (`from`) et programme
 * ce travail ; les suivants, tant qu'il attend, ne font rien d'autre que changer l'attribution.
 *
 * Rien ne part si l'invite est revenu a la table qu'il connaissait, s'il n'a plus de table, si sa carte n'est pas
 * encore partie (elle portera la bonne table), s'il n'est plus confirme ou si l'evenement est fini.
 */
class NotifyTableChange implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Minutes d'attente avant l'envoi : le temps d'un reglage du plan de salle.
     */
    public const DelayMinutes = 10;

    public function __construct(public Registration $registration)
    {
        //
    }

    /**
     * La cle qui garde la table connue de l'invite tant que le message attend. Le cache est isole par
     * organisation (bootstrapper de `stancl/tenancy`).
     */
    public static function cacheKey(int $registrationId): string
    {
        return "table-change:{$registrationId}";
    }

    /**
     * Note a table change and schedule the message, unless one is already waiting. `$from` est le numero de
     * table avant ce deplacement (null : sans table), gardé seulement par le premier deplacement.
     */
    public static function schedule(Registration $registration, ?int $from): void
    {
        if ($registration->status !== RegistrationStatus::Confirmed || $registration->card_sent_at === null) {
            return;
        }

        $key = self::cacheKey($registration->id);

        if (Cache::has($key)) {
            return;
        }

        Cache::put($key, ['from' => $from], now()->addHour());

        self::dispatch($registration)->delay(now()->addMinutes(self::DelayMinutes));
    }

    public function handle(): void
    {
        $state = Cache::pull(self::cacheKey($this->registration->id));

        if (! is_array($state)) {
            return;
        }

        $registration = Registration::with('event', 'tableAssignment.seatingTable')->find($this->registration->id);

        if ($registration === null
            || $registration->status !== RegistrationStatus::Confirmed
            || $registration->card_sent_at === null
            || $registration->event->hasEnded()) {
            return;
        }

        $current = $registration->tableAssignment?->seatingTable->number;

        if ($current === null || $current === $state['from']) {
            return;
        }

        $link = $registration->signedResumeUrl();

        if ($link === null || ! GuestMessageQuota::allows()) {
            return;
        }

        Notification::route('whatsapp', $registration->phone)
            ->route('mail', $registration->email)
            ->notify(new TableChanged($registration, $current, $link));

        GuestMessageQuota::record();

        activity()
            ->performedOn($registration)
            ->event('updated')
            ->withProperties(['from' => $state['from'], 'to' => $current, 'channels' => $registration->cardChannels()])
            ->log('registrations.table_change_notified');
    }
}

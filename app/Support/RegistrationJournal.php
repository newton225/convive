<?php

namespace App\Support;

use App\Enums\RegistrationEventActor;
use App\Enums\RegistrationEventType;
use App\Models\Registration;
use App\Models\RegistrationEvent;
use App\Models\WaitlistEntry;

/**
 * Ecrit l'historique d'un dossier (`registration_events`). Une ecriture qui echoue ne doit jamais
 * faire echouer le geste de l'invite : `rescue()` la rapporte et laisse continuer.
 *
 * L'adresse IP n'est relevee que pour un fait de l'invite, et seulement quand l'appel vient bien de
 * son navigateur (`$withIp` faux pour un appel de WhatsApp, dont l'adresse est celle de Meta). Les
 * proprietes ne portent que des identifiants et des nombres, jamais un nom ni un telephone.
 */
class RegistrationJournal
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public static function record(
        RegistrationEventType $type,
        Registration|WaitlistEntry $subject,
        RegistrationEventActor $actor,
        array $properties = [],
        bool $withIp = true,
    ): void {
        rescue(fn () => RegistrationEvent::create([
            'event_id' => $subject->event_id,
            'registration_id' => $subject instanceof Registration ? $subject->id : null,
            'waitlist_entry_id' => $subject instanceof WaitlistEntry ? $subject->id : null,
            'type' => $type,
            'actor' => $actor,
            'ip_address' => $actor === RegistrationEventActor::Guest && $withIp ? request()->ip() : null,
            'properties' => $properties === [] ? null : $properties,
        ]));
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    public static function guest(RegistrationEventType $type, Registration|WaitlistEntry $subject, array $properties = [], bool $withIp = true): void
    {
        self::record($type, $subject, RegistrationEventActor::Guest, $properties, $withIp);
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    public static function system(RegistrationEventType $type, Registration|WaitlistEntry $subject, array $properties = []): void
    {
        self::record($type, $subject, RegistrationEventActor::System, $properties);
    }
}

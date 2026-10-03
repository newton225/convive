<?php

namespace App\Notifications\Channels;

use App\Contracts\WhatsAppSender;
use App\Support\WhatsApp\WhatsAppTemplate;
use Illuminate\Notifications\Notification;

/**
 * Canal de notification personnalise pour WhatsApp, enregistre dans `AppServiceProvider`. Suit
 * le point d'extension prevu par Laravel (`Notification::extend()`) plutot qu'un envoi ad hoc
 * depuis les Actions (CLAUDE.md, « Respecter les bonnes pratiques de chaque outil »).
 */
class WhatsAppChannel
{
    public function __construct(private readonly WhatsAppSender $sender)
    {
        //
    }

    /**
     * `$notifiable` n'est pas type : ni `object` (les methodes de routage ne sont pas garanties
     * par une interface commune), ni un modele precis (une notification WhatsApp n'est routee
     * que via `Notification::route()`, jamais attachee a un modele Eloquent). Meme choix que les
     * canaux natifs de Laravel (`Illuminate\Notifications\Channels\MailChannel::send()`).
     */
    public function send(mixed $notifiable, Notification $notification): void
    {
        $to = $notifiable->routeNotificationFor('whatsapp', $notification);

        if ($to === null || ! method_exists($notification, 'toWhatsApp')) {
            return;
        }

        // Le modele qui correspond a ce message, quand la notification en declare un : c'est lui
        // que WhatsApp exige hors des 24 heures qui suivent un message de la personne.
        $template = method_exists($notification, 'whatsAppTemplate') ? $notification->whatsAppTemplate($notifiable) : null;

        $this->sender->send($to, $notification->toWhatsApp($notifiable), $template instanceof WhatsAppTemplate ? $template : null);
    }
}

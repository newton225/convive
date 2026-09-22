<?php

namespace App\Notifications\Channels;

use App\Contracts\WhatsAppSender;
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

        $this->sender->send($to, $notification->toWhatsApp($notifiable));
    }
}

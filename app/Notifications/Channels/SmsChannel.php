<?php

namespace App\Notifications\Channels;

use App\Contracts\SmsSender;
use Illuminate\Notifications\Notification;

/**
 * Canal de notification SMS, enregistre dans `AppServiceProvider` comme `WhatsAppChannel`. Une
 * notification qui le demande expose `toSms()`.
 */
class SmsChannel
{
    public function __construct(private readonly SmsSender $sender)
    {
        //
    }

    /**
     * `$notifiable` n'est pas type, pour la meme raison que dans `WhatsAppChannel::send()`.
     */
    public function send(mixed $notifiable, Notification $notification): void
    {
        $to = $notifiable->routeNotificationFor('sms', $notification);

        if ($to === null || ! method_exists($notification, 'toSms')) {
            return;
        }

        $this->sender->send($to, $notification->toSms($notifiable));
    }
}

<?php

namespace App\Support\Console;

use App\Contracts\SmsSender;
use App\Contracts\WhatsAppSender;
use App\Models\MessageLog;
use App\Models\Tenant;
use App\Support\ListPage;
use Illuminate\Http\Request;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Lang;

/**
 * Le releve des envois pour la console (README section 3) : chaque courriel, chaque message
 * WhatsApp et chaque SMS parti y laisse son canal, son type et son destinataire masque. Il repond a deux
 * questions que rien ne permettait de poser : des messages partent-ils, et partent-ils vraiment
 * (un canal encore simule est signale comme tel).
 *
 * Un envoi qui echoue ne passe pas ici : il devient un travail de file en echec, liste par l'ecran
 * de sante technique.
 */
class MessageJournal
{
    /**
     * Les canaux releves : ceux qui sortent de l'application. Les alertes internes (cloche) n'en
     * font pas partie.
     */
    private const Channels = ['mail', 'whatsapp', 'sms'];

    /**
     * Record a notification that just left through one of the outgoing channels.
     */
    public static function sent(NotificationSent $event): void
    {
        if (! in_array($event->channel, self::Channels, true)) {
            return;
        }

        // Un releve qui echoue ne doit jamais faire echouer l'envoi qu'il accompagne.
        rescue(function () use ($event) {
            $recipient = self::recipientOf($event);

            // Sans adresse ni numero, le canal n'a rien envoye : Laravel annonce quand meme la
            // notification comme partie.
            if ($recipient === null) {
                return;
            }

            MessageLog::create([
                'channel' => $event->channel,
                'type' => class_basename($event->notification),
                'tenant_id' => tenant('id'),
                'recipient' => $recipient,
                'simulated' => self::isSimulated($event->channel),
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Determine whether the channel really delivers, or only writes to the log.
     */
    public static function isSimulated(string $channel): bool
    {
        return match ($channel) {
            'whatsapp' => ! app(WhatsAppSender::class)->delivers(),
            'sms' => ! app(SmsSender::class)->delivers(),
            'mail' => in_array(config('mail.default'), ['log', 'array'], true),
            default => false,
        };
    }

    /**
     * Get what the messages screen shows.
     *
     * Les envois sont pagines par le serveur (TODO du 2026-10-07, point 11) : plus de fenetre des
     * derniers, tous ceux encore conserves restent atteignables.
     *
     * @return array{channels: array<int, array{channel: string, simulated: bool, lastDay: int, lastWeek: int}>, types: array<int, array{type: string, label: string, count: int}>, messages: array<int, array{id: int, at: string, channel: string, type: string, organisation: string|null, recipient: string|null, simulated: bool}>, messagesMeta: array{currentPage: int, lastPage: int, total: int}}
     */
    public static function overview(Request $request): array
    {
        $week = MessageLog::where('created_at', '>=', now()->subDays(7))->get(['channel', 'type', 'created_at']);
        $day = now()->subDay();

        $page = ListPage::of(MessageLog::query()->latest('created_at')->latest('id'), $request);
        $latest = $page->getCollection();
        $organisations = Tenant::withTrashed()->whereKey($latest->pluck('tenant_id')->filter()->unique())->pluck('name', 'id');

        return [
            'channels' => array_map(fn (string $channel) => [
                'channel' => $channel,
                'simulated' => self::isSimulated($channel),
                'lastDay' => $week->where('channel', $channel)->where('created_at', '>=', $day)->count(),
                'lastWeek' => $week->where('channel', $channel)->count(),
            ], self::Channels),
            'types' => $week
                ->countBy('type')
                ->sortDesc()
                ->map(fn (int $count, string $type) => ['type' => $type, 'label' => self::label($type), 'count' => $count])
                ->values()
                ->all(),
            'messages' => $latest
                ->map(fn (MessageLog $message) => [
                    'id' => $message->id,
                    'at' => $message->created_at->toISOString(),
                    'channel' => $message->channel,
                    'type' => self::label($message->type),
                    'organisation' => $organisations[$message->tenant_id] ?? null,
                    'recipient' => $message->recipient,
                    'simulated' => $message->simulated,
                ])
                ->values()
                ->all(),
            'messagesMeta' => ListPage::meta($page),
        ];
    }

    /**
     * Forget the messages past their retention.
     */
    public static function purge(): int
    {
        return MessageLog::where('created_at', '<', now()->subDays(MessageLog::RetentionDays))->delete();
    }

    /**
     * Mask an address or a number : assez pour distinguer deux envois, pas pour identifier
     *
     * quelqu'un. `a***@exemple.ci`, `+225******56`.
     */
    public static function mask(string $recipient): string
    {
        if (str_contains($recipient, '@')) {
            [$local, $domain] = explode('@', $recipient, 2);

            return mb_substr($local, 0, 1).'***@'.$domain;
        }

        $length = mb_strlen($recipient);

        return $length <= 6
            ? str_repeat('*', $length)
            : mb_substr($recipient, 0, 4).str_repeat('*', $length - 6).mb_substr($recipient, -2);
    }

    private static function recipientOf(NotificationSent $event): ?string
    {
        $route = $event->notifiable->routeNotificationFor($event->channel, $event->notification);

        // Une adresse peut etre donnee avec un nom (`[adresse => nom]`).
        if (is_array($route)) {
            $route = array_is_list($route) ? ($route[0] ?? null) : array_key_first($route);
        }

        return is_string($route) && $route !== '' ? self::mask($route) : null;
    }

    /**
     * Le type est le nom de la notification dans le code ; l'ecran en donne une traduction, et
     * garde le nom tel quel pour un type qui n'en a pas encore.
     */
    private static function label(string $type): string
    {
        $key = "console.messages.types.{$type}";

        return Lang::has($key) ? __($key) : $type;
    }
}

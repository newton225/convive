<?php

namespace App\Actions\Events;

use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Image\Image;

/**
 * Opere sur l'evenement de l'organisation dont la base est active au moment de l'appel : la
 * tenancy est deja initialisee par le moment ou cette action s'execute (voir CLAUDE.md,
 * « Multi-locataire »), aucun `Tenant` n'a besoin d'etre passe ici.
 */
class SaveEvent
{
    /**
     * Create or update an event and journal the change.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, int>  $paymentAccountIds
     */
    public function handle(?Event $event, array $attributes, array $paymentAccountIds): Event
    {
        return DB::transaction(function () use ($event, $attributes, $paymentAccountIds) {
            $creating = $event === null;

            $event ??= new Event;

            $before = $creating ? null : $this->snapshot($event);

            $event->fill($attributes);
            $event->save();

            $event->paymentAccounts()->sync($paymentAccountIds);
            $event->load('paymentAccounts');

            activity()
                ->performedOn($event)
                ->event($creating ? 'created' : 'updated')
                ->withProperties(array_filter([
                    'old' => $before,
                    'attributes' => $this->snapshot($event),
                ]))
                ->log($creating ? 'event.created' : 'event.updated');

            return $event;
        });
    }

    /**
     * Hand out the public link of the event.
     *
     * Publier fige le sous-domaine de l'organisation : des invites vont detenir cette adresse.
     */
    public function publish(Event $event): Event
    {
        return DB::transaction(function () use ($event) {
            $event->ensurePublicToken();

            $event->status = EventStatus::Open;
            $event->published_at ??= now();
            $event->save();

            activity()
                ->performedOn($event)
                ->event('updated')
                ->withProperties([
                    'attributes' => ['status' => $event->status->value, 'published_at' => $event->published_at->toISOString()],
                ])
                ->log('event.published');

            return $event;
        });
    }

    /**
     * Close the event.
     */
    public function close(Event $event): Event
    {
        return DB::transaction(function () use ($event) {
            $before = $event->status;

            $event->status = EventStatus::Closed;
            $event->save();

            activity()
                ->performedOn($event)
                ->event('updated')
                ->withProperties([
                    'old' => ['status' => $before->value],
                    'attributes' => ['status' => $event->status->value],
                ])
                ->log('event.closed');

            return $event;
        });
    }

    /**
     * Duplicate the event as a fresh draft.
     *
     * La copie ne reprend ni le jeton public ni la date de publication : c'est un autre
     * evenement, il a sa propre adresse.
     */
    public function duplicate(Event $event): Event
    {
        return DB::transaction(function () use ($event) {
            $copy = $event->replicate([
                'public_token', 'published_at', 'created_at', 'updated_at', 'deleted_at',
            ]);

            $copy->name = __('events.duplicate_name', ['name' => $event->name]);
            $copy->status = EventStatus::Draft;
            $copy->save();

            $copy->paymentAccounts()->sync($event->paymentAccounts()->pluck('payment_accounts.id')->all());

            activity()
                ->performedOn($copy)
                ->event('created')
                ->withProperties([
                    'attributes' => $this->snapshot($copy),
                    'duplicated_from' => $event->id,
                ])
                ->log('event.duplicated');

            return $copy;
        });
    }

    /**
     * Update the reminders and rules of the event (README ecran 24).
     *
     * @param  array<string, bool>  $settings
     */
    public function settings(Event $event, array $settings): Event
    {
        return DB::transaction(function () use ($event, $settings) {
            $before = $this->settingsSnapshot($event);

            $event->fill($settings);
            $event->save();

            activity()
                ->performedOn($event)
                ->event('updated')
                ->withProperties([
                    'old' => $before,
                    'attributes' => $this->settingsSnapshot($event),
                ])
                ->log('event.settings_updated');

            return $event;
        });
    }

    /**
     * Store the event's visual, replacing whatever was there before (README ecran 13).
     *
     * Reencodage systematique, meme raison que les fichiers de marque de l'organisation
     * (CLAUDE.md, « Fichiers de marque ») : le fichier stocke est une image et rien d'autre.
     */
    public function visual(Event $event, UploadedFile $upload): Event
    {
        $event->addMedia($this->reencode($upload))
            ->usingFileName(Str::uuid()->toString().'.'.$this->extension($upload))
            ->usingName(Event::VisualCollection)
            ->toMediaCollection(Event::VisualCollection);

        activity()
            ->performedOn($event)
            ->event('updated')
            ->log('event.visual_updated');

        return $event;
    }

    /**
     * Remove the event's visual.
     */
    public function removeVisual(Event $event): Event
    {
        $event->clearMediaCollection(Event::VisualCollection);

        activity()
            ->performedOn($event)
            ->event('updated')
            ->log('event.visual_deleted');

        return $event;
    }

    private function reencode(UploadedFile $upload): string
    {
        $destination = tempnam(sys_get_temp_dir(), 'event').'.'.$this->extension($upload);

        Image::load($upload->getRealPath())->save($destination);

        return $destination;
    }

    private function extension(UploadedFile $upload): string
    {
        return match ($upload->getMimeType()) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Event $event): array
    {
        return [
            'name' => $event->name,
            'starts_at' => $event->starts_at?->toISOString(),
            'table_count' => $event->table_count,
            'seats_per_table' => $event->seats_per_table,
            'price_per_person' => $event->price_per_person,
            'companion_limit' => $event->companion_limit,
            'hold_duration_minutes' => $event->hold_duration_minutes,
            'payment_accounts' => $event->paymentAccounts->pluck('id')->all(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function settingsSnapshot(Event $event): array
    {
        return [
            'reminder_j7_enabled' => $event->reminder_j7_enabled,
            'reminder_j2_enabled' => $event->reminder_j2_enabled,
            'reminder_j1_enabled' => $event->reminder_j1_enabled,
            'reminder_day_of_enabled' => $event->reminder_day_of_enabled,
            'rule_scheduled_send' => $event->rule_scheduled_send,
            'rule_auto_seating' => $event->rule_auto_seating,
            'rule_allow_without_proof' => $event->rule_allow_without_proof,
            'rule_proof_legibility' => $event->rule_proof_legibility,
            'rule_purge_on_exhaustion' => $event->rule_purge_on_exhaustion,
            'rule_temporary_hold' => $event->rule_temporary_hold,
        ];
    }
}

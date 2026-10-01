<?php

namespace App\Actions\Events;

use App\Actions\Seating\SyncSeatingTables;
use App\Enums\BrandFile;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\SeatingTable;
use App\Models\ShowcaseEvent;
use App\Models\Tenant;
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
     * @param  array<int, int>|null  $tablePlan  capacite de chaque table par numero, `null` pour
     *                                           laisser le plan de salle tel quel
     */
    public function handle(?Event $event, array $attributes, array $paymentAccountIds, ?array $tablePlan = null): Event
    {
        return DB::transaction(function () use ($event, $attributes, $paymentAccountIds, $tablePlan) {
            $creating = $event === null;

            $event ??= new Event;

            $before = $creating ? null : $this->snapshot($event);

            $event->fill($attributes);
            $event->save();

            $event->paymentAccounts()->sync($paymentAccountIds);
            $event->load('paymentAccounts');

            // Les tables existent des l'enregistrement : c'est leur capacite, table par table, qui
            // fait celle de l'evenement (decision du 2026-09-29). Les conflits avec des invites
            // deja places ont ete refuses par `SaveEventRequest`.
            if ($tablePlan !== null) {
                app(SyncSeatingTables::class)->handle($event, $tablePlan);
            }

            activity()
                ->performedOn($event)
                ->event($creating ? 'created' : 'updated')
                ->withProperties(array_filter([
                    'old' => $before,
                    'attributes' => $this->snapshot($event),
                ]))
                ->log($creating ? 'event.created' : 'event.updated');

            // La vitrine ne relit jamais la base du locataire (CLAUDE.md, « Annonce sur le
            // site produit ») : sa copie centrale doit rester a jour par elle-meme des que le
            // nom ou la date changent sur un evenement deja annonce.
            if ($event->isAnnounced()) {
                $this->syncShowcase($event);
            }

            return $event;
        });
    }

    /**
     * Announce the event on the product site's showcase (opt-in, CLAUDE.md « Annonce sur le
     * site produit ») : un second geste, volontaire, jamais automatique a la publication.
     */
    public function announce(Event $event): Event
    {
        return DB::transaction(function () use ($event) {
            $event->announced_at = now();
            $event->save();

            $this->syncShowcase($event);

            activity()
                ->performedOn($event)
                ->event('updated')
                ->withProperties(['attributes' => ['announced_at' => $event->announced_at->toISOString()]])
                ->log('event.announced');

            return $event;
        });
    }

    /**
     * Withdraw the event from the showcase.
     *
     * Toujours possible, y compris apres publication et independamment de la cloture :
     * l'organisateur garde la main sur sa visibilite a tout moment.
     */
    public function withdraw(Event $event): Event
    {
        return DB::transaction(function () use ($event) {
            $event->announced_at = null;
            $event->save();

            ShowcaseEvent::where('tenant_id', Tenant::current()?->id)
                ->where('event_id', $event->id)
                ->delete();

            activity()
                ->performedOn($event)
                ->event('updated')
                ->log('event.announcement_withdrawn');

            return $event;
        });
    }

    /**
     * Keep the central showcase row in sync with this event's current display data.
     */
    private function syncShowcase(Event $event): void
    {
        $tenant = Tenant::current();

        if ($tenant === null || $event->announced_at === null) {
            return;
        }

        ShowcaseEvent::updateOrCreate(
            ['tenant_id' => $tenant->id, 'event_id' => $event->id],
            [
                'name' => $event->name,
                // `branding` existe forcement ici : `announce()` exige un evenement deja
                // publie, ce qui exige lui-meme `Tenant::isReadyToPublish()`, qui n'est vrai
                // que si `branding` existe et est complete.
                'organisation_name' => $tenant->branding->display_name ?? $tenant->name,
                'starts_at' => $event->starts_at,
                'public_url' => $event->publicUrl(),
                'announced_at' => $event->announced_at,
                'visual_path' => $event->getFirstMedia(Event::VisualCollection)?->getPathRelativeToRoot(),
            ],
        );
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
            // Rien de ce qui appartient a la vie de l'original : ni son adresse publique, ni son
            // annonce en vitrine, ni sa cle de signature des billets (SECURITY.md C2), ni ses
            // alertes deja envoyees (la copie doit pouvoir prevenir a son tour).
            $copy = $event->replicate([
                'public_token', 'published_at', 'announced_at', 'created_at', 'updated_at', 'deleted_at',
                'qr_public_key', 'qr_secret_key',
                'seats_low_alerted_at', 'purge_notice_sent_at',
            ]);

            $copy->name = __('events.duplicate_name', ['name' => $event->name]);
            $copy->status = EventStatus::Draft;
            $copy->qr_key_version = 1;
            $copy->save();

            $copy->paymentAccounts()->sync($event->paymentAccounts()->pluck('payment_accounts.id')->all());

            // Le plan de salle fait partie de la configuration : memes tables, memes capacites,
            // memes reservations d'unite. Jamais les invites places, qui sont ceux de l'original.
            SeatingTable::where('event_id', $event->id)->orderBy('number')->get()
                ->each(fn (SeatingTable $table) => SeatingTable::create([
                    'event_id' => $copy->id,
                    'number' => $table->number,
                    'capacity' => $table->capacity,
                    'reserved_unit_id' => $table->reserved_unit_id,
                ]));

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

        if ($event->isAnnounced()) {
            $this->syncShowcase($event->refresh());
        }

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

        if ($event->isAnnounced()) {
            $this->syncShowcase($event->refresh());
        }

        return $event;
    }

    /**
     * Save the event's own ticket template (README ecran 15). Desactive, il rend la main a celui
     * de l'organisation sans effacer ce qui avait ete regle.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function ticketTemplate(Event $event, array $attributes): Event
    {
        return DB::transaction(function () use ($event, $attributes) {
            $before = $this->ticketTemplateSnapshot($event);

            $event->fill($attributes);
            $event->save();

            activity()
                ->performedOn($event)
                ->event('updated')
                ->withProperties([
                    'old' => $before,
                    'attributes' => $this->ticketTemplateSnapshot($event),
                ])
                ->log('event.ticket_template_updated');

            return $event;
        });
    }

    /**
     * Store one of the event's own ticket backgrounds, replacing whatever was there before.
     * `$crop` is the area the operator framed, in pixels of the upright image.
     *
     * @param  array{x: int, y: int, width: int, height: int}|null  $crop
     */
    public function ticketBackground(Event $event, BrandFile $file, UploadedFile $upload, ?array $crop = null): Event
    {
        $event->addMedia($this->reencode($upload, $crop))
            ->usingFileName(Str::uuid()->toString().'.'.$this->extension($upload))
            ->usingName($file->value)
            ->toMediaCollection($file->value);

        activity()
            ->performedOn($event)
            ->event('updated')
            ->withProperties(['file' => $file->value])
            ->log('event.ticket_background_updated');

        return $event;
    }

    /**
     * Remove one of the event's own ticket backgrounds : the organisation's applies again.
     */
    public function removeTicketBackground(Event $event, BrandFile $file): Event
    {
        $event->clearMediaCollection($file->value);

        activity()
            ->performedOn($event)
            ->event('updated')
            ->withProperties(['file' => $file->value])
            ->log('event.ticket_background_deleted');

        return $event;
    }

    /**
     * @param  array{x: int, y: int, width: int, height: int}|null  $crop
     */
    private function reencode(UploadedFile $upload, ?array $crop = null): string
    {
        $destination = tempnam(sys_get_temp_dir(), 'event').'.'.$this->extension($upload);

        $image = Image::load($upload->getRealPath());

        if ($crop !== null) {
            // Redressee d'abord : la zone a ete choisie sur l'image telle que le navigateur
            // l'affiche (voir `SaveTenantBrandFile::reencode()`).
            $image->orientation()->manualCrop($crop['width'], $crop['height'], $crop['x'], $crop['y']);
        }

        $image->save($destination);

        return $destination;
    }

    /**
     * @return array<string, mixed>
     */
    private function ticketTemplateSnapshot(Event $event): array
    {
        return [
            'ticket_template_enabled' => $event->ticket_template_enabled,
            'ticket_model' => $event->ticket_model?->value,
            'ticket_element_logo' => $event->ticket_element_logo,
            'ticket_element_stamp' => $event->ticket_element_stamp,
            'ticket_element_signature' => $event->ticket_element_signature,
            'ticket_element_companions' => $event->ticket_element_companions,
        ];
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

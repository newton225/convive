<?php

namespace App\Http\Controllers\Events;

use App\Actions\Events\SaveEvent;
use App\Enums\BrandFile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Events\SaveEventTicketBackgroundRequest;
use App\Http\Requests\Events\SaveEventTicketTemplateRequest;
use App\Models\Event;
use App\Models\Tenant;
use App\Models\TenantBranding;
use App\Support\TicketQrCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Le gabarit du billet propre a un evenement (README ecran 15). Active, il l'emporte sur celui de
 * l'organisation (`TicketTemplateController`) ; sinon c'est ce dernier qui s'applique, et l'ecran
 * le montre tel quel. Logo, cachet et signature restent ceux de l'organisation : seuls le modele,
 * les elements affiches et les fonds se reglent par evenement.
 */
class EventTicketTemplateController extends Controller
{
    /**
     * Display the ticket template editor of the given event.
     */
    public function edit(Request $request, Tenant $tenant, Event $event): Response
    {
        Gate::authorize('update', [$event, $tenant]);

        $branding = $tenant->brandingOrCreate();

        return Inertia::render('events/ticket-template', [
            'tenant' => ['slug' => $tenant->slug, 'name' => $tenant->name],
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'startsAt' => $event->starts_at?->toISOString(),
                'venue' => $event->venue,
            ],
            'permissions' => $request->user()->toTenantPermissions($tenant),
            'enabled' => $event->ticket_template_enabled,
            // Ce que l'evenement a regle pour lui, ou a defaut le gabarit de l'organisation : le
            // point de depart quand on active le sien.
            'model' => ($event->ticket_model ?? $branding->ticket_model)->value,
            'elements' => $this->elementsOf($event->ticket_model !== null ? $event : $branding),
            // Le gabarit de l'organisation, celui qui s'applique tant que l'evenement n'a pas
            // active le sien.
            'organisation' => [
                'model' => $branding->ticket_model->value,
                'elements' => $this->elementsOf($branding),
                'backgroundUrl' => $branding->ticketBackgroundUrl(),
                'bodyBackgroundUrl' => $branding->ticketBodyBackgroundUrl(),
            ],
            'brand' => [
                'displayName' => $branding->display_name ?? $tenant->name,
                'colors' => $event->colors(),
                'logoUrl' => $branding->brandFileUrl(BrandFile::Logo),
                'stampUrl' => $branding->brandFileUrl(BrandFile::Stamp),
                'signatureUrl' => $branding->brandFileUrl(BrandFile::Signature),
                // Les fonds propres a l'evenement ; nuls, ceux de l'organisation s'appliquent.
                'backgroundUrl' => $event->ownTicketBackgroundUrl(BrandFile::TicketBackground),
                'bodyBackgroundUrl' => $event->ownTicketBackgroundUrl(BrandFile::TicketBodyBackground),
                'representative' => $branding->representative_name,
            ],
            'backgroundFile' => BrandFile::TicketBackground->toOption(),
            'bodyBackgroundFile' => BrandFile::TicketBodyBackground->toOption(),
            'sampleQrImage' => TicketQrCode::sample(),
        ]);
    }

    /**
     * Update the ticket template of the given event.
     */
    public function update(SaveEventTicketTemplateRequest $request, Tenant $tenant, Event $event, SaveEvent $save): RedirectResponse
    {
        $save->ticketTemplate($event, $request->boolean('ticket_template_enabled')
            ? [
                'ticket_template_enabled' => true,
                'ticket_model' => $request->validated('ticket_model'),
                'ticket_element_logo' => $request->boolean('ticket_element_logo'),
                'ticket_element_stamp' => $request->boolean('ticket_element_stamp'),
                'ticket_element_signature' => $request->boolean('ticket_element_signature'),
                'ticket_element_companions' => $request->boolean('ticket_element_companions'),
            ]
            : ['ticket_template_enabled' => false]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ticket_template.flash.updated')]);

        return to_route('tenants.events.ticket-template.edit', [$tenant, $event]);
    }

    /**
     * Store one of the event's own ticket backgrounds.
     */
    public function storeFile(SaveEventTicketBackgroundRequest $request, Tenant $tenant, Event $event, string $file, SaveEvent $save): RedirectResponse
    {
        $save->ticketBackground($event, $this->background($file), $request->file('file'), $request->crop());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('organisation.flash.file_updated')]);

        return to_route('tenants.events.ticket-template.edit', [$tenant, $event]);
    }

    /**
     * Remove one of the event's own ticket backgrounds.
     */
    public function destroyFile(Tenant $tenant, Event $event, string $file, SaveEvent $save): RedirectResponse
    {
        Gate::authorize('update', [$event, $tenant]);

        $save->removeTicketBackground($event, $this->background($file));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('organisation.flash.file_deleted')]);

        return to_route('tenants.events.ticket-template.edit', [$tenant, $event]);
    }

    /**
     * @return array{logo: bool, stamp: bool, signature: bool, companions: bool}
     */
    private function elementsOf(Event|TenantBranding $source): array
    {
        return [
            'logo' => $source->ticket_element_logo,
            'stamp' => $source->ticket_element_stamp,
            'signature' => $source->ticket_element_signature,
            'companions' => $source->ticket_element_companions,
        ];
    }

    /**
     * Le nom arrive par l'URL : il vient du catalogue, et seuls les fonds du billet se deposent
     * sur un evenement.
     */
    private function background(string $file): BrandFile
    {
        $background = BrandFile::tryFrom($file);

        abort_unless($background !== null && in_array($background, BrandFile::ticketBackgrounds(), true), 404);

        return $background;
    }
}

<?php

namespace App\Http\Controllers\Tenants;

use App\Actions\Tenants\SaveTenantOrganisation;
use App\Enums\BrandFile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenants\SaveTicketTemplateRequest;
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
 * Le gabarit du billet (README ecran 15) : modeles, elements activables, apercu, impression des
 * listes de controle par table. Modele et elements vivent sur `tenant_brandings`, un reglage
 * d'organisation (CLAUDE.md, « Organisation »), pas d'evenement.
 */
class TicketTemplateController extends Controller
{
    /**
     * Display the ticket template editor with a live preview.
     */
    public function edit(Request $request, Tenant $tenant): Response
    {
        Gate::authorize('updateBrand', $tenant);

        $branding = $tenant->brandingOrCreate();

        return Inertia::render('tenants/ticket-template', [
            'tenant' => ['slug' => $tenant->slug, 'name' => $tenant->name],
            'permissions' => $request->user()->toTenantPermissions($tenant),
            'brand' => $this->brand($tenant, $branding),
            // Les fonds du billet se deposent ici, par la meme route et les memes controles que les
            // fichiers de marque de l'organisation.
            'backgroundFile' => $this->fileOption(BrandFile::TicketBackground),
            'bodyBackgroundFile' => $this->fileOption(BrandFile::TicketBodyBackground),
            // Le QR de l'apercu, rendu comme celui d'un vrai billet et aussi dense, mais non signe :
            // un scan le refuse.
            'sampleQrImage' => TicketQrCode::dataUri(str_repeat('convive-apercu-', 17)),
            'model' => $branding->ticket_model->value,
            'elements' => [
                'logo' => $branding->ticket_element_logo,
                'stamp' => $branding->ticket_element_stamp,
                'signature' => $branding->ticket_element_signature,
                'companions' => $branding->ticket_element_companions,
            ],
            'events' => Event::query()
                ->latest('starts_at')
                ->limit(8)
                ->get()
                ->map(fn (Event $event) => [
                    'id' => $event->id,
                    'name' => $event->name,
                    'startsAt' => $event->starts_at?->toISOString(),
                    'venue' => $event->venue,
                ])
                ->all(),
        ]);
    }

    /**
     * Update the ticket template.
     */
    public function update(SaveTicketTemplateRequest $request, Tenant $tenant, SaveTenantOrganisation $save): RedirectResponse
    {
        $save->ticketTemplate($tenant, [
            'ticket_model' => $request->validated('ticket_model'),
            'ticket_element_logo' => $request->boolean('ticket_element_logo'),
            'ticket_element_stamp' => $request->boolean('ticket_element_stamp'),
            'ticket_element_signature' => $request->boolean('ticket_element_signature'),
            'ticket_element_companions' => $request->boolean('ticket_element_companions'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ticket_template.flash.updated')]);

        return to_route('tenants.ticket-template.edit', $tenant);
    }

    /**
     * @return array{value: string, label: string, hint: string, crop: array{width: int, height: int}|null}
     */
    private function fileOption(BrandFile $file): array
    {
        return [
            'value' => $file->value,
            'label' => $file->label(),
            'hint' => $file->hint(),
            // Les proportions de la partie du talon couverte, que le rognage impose avant l'envoi.
            'crop' => $file->crop(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function brand(Tenant $tenant, TenantBranding $branding): array
    {
        return [
            'displayName' => $branding->display_name ?? $tenant->name,
            'colors' => $branding->colors(),
            'logoUrl' => $branding->brandFileUrl(BrandFile::Logo),
            'stampUrl' => $branding->brandFileUrl(BrandFile::Stamp),
            'signatureUrl' => $branding->brandFileUrl(BrandFile::Signature),
            'backgroundUrl' => $branding->ticketBackgroundUrl(),
            'bodyBackgroundUrl' => $branding->ticketBodyBackgroundUrl(),
            'representative' => $branding->representative_name,
        ];
    }
}

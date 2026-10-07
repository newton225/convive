<?php

namespace App\Http\Controllers\Public;

use App\Enums\BrandFile;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Tenant;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Le lien d'inscription (README ecran 3), en lecture seule : visuel, date, heure, capacite,
 * tarif, places restantes, date limite. Le formulaire d'inscription lui-meme est l'etape 4.
 *
 * Aucune authentification ici : c'est la premiere surface non authentifiee du produit. Le
 * cloisonnement repose sur la base separee du locataire (voir CLAUDE.md, « Multi-locataire »),
 * initialisee par `InitializeTenancyBySubdomain` (stancl/tenancy) avant que ce controleur ne
 * s'execute.
 */
class EventController extends Controller
{
    /**
     * Show the public landing page of an event (README ecran 3) : visuel, date, heure,
     * capacite, tarif, places restantes, date limite. Le paiement (comptes de versement) vit
     * dans le parcours d'inscription, ecran 5, pas ici.
     */
    public function show(string $token): Response
    {
        $tenant = Tenant::current();

        // La tenancy est deja initialisee a ce point (InitializeTenancyBySubdomain a deja
        // repondu 404 sinon), mais un locataire sans sous-domaine encore assigne ne devrait
        // jamais s'y trouver non plus : verification defensive.
        abort_if(! $tenant, 404);

        $event = Event::where('public_token', $token)->first();

        // Un evenement non publie n'a rien a montrer, mais le distinguer d'un jeton inexistant
        // permettrait d'enumerer les evenements : 404 dans tous les cas.
        abort_if(! $event || ! $event->isPublished(), 404);

        return Inertia::render('public/event', [
            'token' => $token,
            'event' => [
                'name' => $event->name,
                'subtitle' => $event->subtitle,
                'startsAt' => $event->starts_at?->toISOString(),
                'venue' => $event->venue,
                'venueAddress' => $event->venue_address,
                'venueMapUrl' => $event->venue_map_url,
                'capacity' => $event->capacity(),
                'showRemainingSeats' => $event->rule_show_remaining_seats,
                'remainingSeats' => $event->publicRemainingSeats(),
                'isFull' => $event->isFull(),
                'pricePerPerson' => $event->price_per_person,
                'priceCategories' => $event->priceCategories()->get()->map(fn ($category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'price' => $category->price,
                    'quota' => $category->quota,
                    'remainingQuota' => $category->remaining(),
                ])->all(),
                'companionLimit' => $event->companion_limit,
                'registrationDeadline' => $event->registration_deadline?->toISOString(),
                'registrationDeadlineHasPassed' => $event->registrationDeadlineHasPassed(),
                'acceptsRegistrations' => $event->acceptsRegistrations(),
                // Les couleurs de l'evenement priment sur celles de l'organisation (README
                // ecran 13) : `Event::colors()` retombe deja sur celles du locataire quand
                // l'evenement n'en definit pas.
                'colors' => $event->colors(),
                'visualUrl' => $event->visualUrl(),
            ],
            'tenant' => [
                'name' => $tenant->name,
                // `->` et non `?->` : a l'interieur d'un `??`, PHP suppress deja l'erreur d'acces
                // sur null pour toute la chaine de proprietes, comme pour un tableau ou une
                // variable indefinie. Le nullsafe serait redondant ici (il resterait requis
                // pour un appel de methode, que `??` ne protege pas de la meme facon).
                'displayName' => $tenant->branding->display_name ?? $tenant->name,
                'colors' => $tenant->brandingOrCreate()->colors(),
                'logoUrl' => $tenant->branding?->brandFileUrl(BrandFile::Logo),
                'bannerUrl' => $tenant->branding?->brandFileUrl(BrandFile::Banner),
            ],
        ]);
    }
}

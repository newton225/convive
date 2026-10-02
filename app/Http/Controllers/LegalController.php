<?php

namespace App\Http\Controllers;

use App\Support\LegalDocument;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Les pages juridiques du site (politique de confidentialite, conditions d'utilisation, mentions
 * legales), sur le domaine central, ouvertes a tous. Le document a rendre vient de la route
 * (`routes/web.php`), jamais d'un parametre libre.
 *
 * Non mesurees par Google Analytics : seules l'accueil et la vitrine le sont (README, « Mesure
 * d'audience »).
 */
class LegalController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $document = (string) $request->route()?->defaults['document'];

        abort_unless(array_key_exists($document, LegalDocument::Slugs), 404);

        return Inertia::render('legal', [
            'document' => LegalDocument::get($document),
            'version' => LegalDocument::Version,
            'labels' => [
                'updated' => __('legal.updated'),
                'contents' => __('legal.contents'),
                'nav' => __('legal.nav'),
            ],
        ]);
    }
}

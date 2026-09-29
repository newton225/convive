<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-tetes de securite (SECURITY.md H7) : CSP a nonces sans `unsafe-inline`, plus HSTS,
 * X-Content-Type-Options, X-Frame-Options, Referrer-Policy et une Permissions-Policy
 * restrictive.
 *
 * Le nonce vient de `Vite::useCspNonce()`, point d'extension officiel du framework pour ce cas
 * precis (CLAUDE.md, « Respecter les bonnes pratiques de chaque outil ») : chaque balise
 * script/style/preload posee par `@vite(...)` le porte automatiquement. Genere une seule fois
 * ici, avant que la vue ne s'affiche ; tout le reste (props partagees Inertia, `app.blade.php`)
 * lit `Vite::cspNonce()`, jamais `useCspNonce()` a nouveau, qui en tirerait un second different.
 */
class SetSecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        // Des valeurs par defaut, jamais un ecrasement : une route qui pose une politique plus
        // stricte la garde. Le telechargement d'un recu (SECURITY.md H2) envoie `no-referrer` et
        // une CSP `sandbox` ; les remplacer ici laissait fuir son URL signee dans le Referer.
        $defaults = [
            'Content-Security-Policy' => $this->csp($nonce),
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => $this->permissionsPolicy(),
        ];

        foreach ($defaults as $header => $value) {
            if (! $response->headers->has($header)) {
                $response->headers->set($header, $value);
            }
        }

        // HSTS n'a de sens que sur une reponse deja servie en HTTPS : l'emettre sur du HTTP nu
        // n'a aucun effet (les navigateurs l'ignorent par specification), mais l'omettre en local
        // evite un en-tete trompeur dans les outils de developpement.
        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    /**
     * Build the Content-Security-Policy header value.
     */
    private function csp(string $nonce): string
    {
        // Le serveur de developpement Vite sert le JS, le CSS et les polices depuis une autre
        // origine en local (voir vite.config.ts, `server.host`) : `:*` couvre le port qu'il
        // choisit (il derive du port par defaut quand celui-ci est deja pris) sans avoir a le
        // figer ni a le deviner ici. Absent en production, ou tout vient du meme domaine.
        $devOrigin = app()->environment('local')
            ? 'http://127.0.0.1:* ws://127.0.0.1:*'
            : '';

        // Google Analytics (README, « Mesure d'audience ») : ses domaines n'entrent dans la CSP
        // que si un identifiant est configure. Le script ne se charge qu'apres consentement et
        // sur les pages commerciales ; la CSP, elle, ne peut pas distinguer les pages d'une meme
        // application a une seule page, elle s'ouvre donc pour tout le document.
        $analytics = config('services.google_analytics.measurement_id')
            ? [
                'script' => 'https://www.googletagmanager.com',
                'connect' => 'https://*.google-analytics.com https://*.analytics.google.com https://*.googletagmanager.com',
                'img' => 'https://*.google-analytics.com https://*.googletagmanager.com',
            ]
            : ['script' => '', 'connect' => '', 'img' => ''];

        $directives = [
            "default-src 'self'",
            trim("script-src 'self' 'nonce-{$nonce}' {$analytics['script']} {$devOrigin}"),
            trim("style-src 'self' 'nonce-{$nonce}' {$devOrigin}"),
            trim("font-src 'self' {$devOrigin}"),
            trim("img-src 'self' data: {$analytics['img']}"),
            trim("connect-src 'self' {$analytics['connect']} {$devOrigin}"),
            "worker-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ];

        // Les sources facultatives absentes laissent des espaces doubles : on les resserre.
        return implode('; ', array_map(fn (string $directive) => (string) preg_replace('/\s+/', ' ', $directive), $directives));
    }

    /**
     * Build the Permissions-Policy header value.
     *
     * La camera reste ouverte, meme origine seulement : l'ecran de scan (README ecran 26) en a
     * besoin pour lire un billet. Tout le reste, sans usage dans le produit, est ferme.
     */
    private function permissionsPolicy(): string
    {
        return implode(', ', [
            'camera=(self)',
            'microphone=()',
            'geolocation=()',
            'payment=()',
            'usb=()',
        ]);
    }
}

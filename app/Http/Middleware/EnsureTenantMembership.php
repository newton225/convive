<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resout le locataire du back-office par son slug (segment `{tenant}` ou `{current_tenant}`
 * de l'URL), verifie l'appartenance, puis initialise la tenancy : c'est ce dernier appel qui
 * bascule la connexion de base de donnees par defaut vers celle du locataire (voir CLAUDE.md,
 * « Multi-locataire »). Prioritaire, prepose avant `SubstituteBindings` dans `bootstrap/app.php`,
 * pour que les sous-modeles ({event}, {unit}, {profile}...) se resolvent deja dans la bonne
 * base.
 */
class EnsureTenantMembership
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        [$user, $tenant] = [$request->user(), $this->tenant($request)];

        // Un locataire tiers ne doit pas apprendre que la ressource existe : 404, jamais 403.
        abort_if(! $user || ! $tenant || ! $user->belongsToTenant($tenant), 404);

        tenancy()->initialize($tenant);

        if ($request->route('current_tenant') && ! $user->isCurrentTenant($tenant)) {
            $user->switchTenant($tenant);
        }

        // `finally` : une exception levee plus loin dans la pile (controleur, validation...) ne
        // doit pas laisser `database.default` bascule sur le locataire. Sans cette garantie, un
        // seul test en echec suffit a corrompre la connexion centrale pour tous ceux qui suivent
        // dans le meme processus (voir CLAUDE.md, « Multi-locataire »).
        try {
            return $next($request);
        } finally {
            tenancy()->end();
        }
    }

    /**
     * Get the tenant associated with the request.
     */
    protected function tenant(Request $request): ?Tenant
    {
        $tenant = $request->route('current_tenant') ?? $request->route('tenant');

        if (is_string($tenant)) {
            $tenant = Tenant::where('slug', $tenant)->first();
        }

        return $tenant instanceof Tenant ? $tenant : null;
    }
}

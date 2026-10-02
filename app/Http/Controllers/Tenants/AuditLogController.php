<?php

namespace App\Http\Controllers\Tenants;

use App\Enums\AuditLogType;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AuditTrail;
use App\Support\Search\UnaccentedSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * La journalisation (README ecran 23) : date, type, message, acteur, adresse IP ; conservation 24
 * mois, en ecriture seule (CLAUDE.md, « Securite »).
 *
 * `activity_log` vit dans la base du locataire (voir CLAUDE.md, « Multi-locataire ») : la
 * connexion active au moment de cette requete est deja celle de l'organisation, aucun filtre
 * `tenant_id` a poser.
 */
class AuditLogController extends Controller
{
    /**
     * Display the paginated, filterable, searchable audit log of the organisation.
     */
    public function index(Request $request, Tenant $tenant): Response
    {
        Gate::authorize('view', [AuditTrail::class, $tenant]);

        $activities = $this->filteredQuery($request)
            ->with('causer')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('tenants/audit', [
            'tenant' => ['slug' => $tenant->slug, 'name' => $tenant->name],
            'entries' => $activities->getCollection()->map(fn (Activity $activity) => $this->entry($activity)),
            'meta' => [
                'currentPage' => $activities->currentPage(),
                'lastPage' => $activities->lastPage(),
                'total' => $activities->total(),
            ],
            'filters' => [
                'search' => $request->string('filter.search')->toString() ?: null,
                'type' => $request->string('filter.type')->toString() ?: null,
                'sort' => $request->string('sort')->toString() ?: null,
            ],
            'types' => AuditLogType::values(),
        ]);
    }

    /**
     * @return QueryBuilder<Activity>
     */
    private function filteredQuery(Request $request): QueryBuilder
    {
        return QueryBuilder::for(Activity::class)
            ->allowedFilters(
                AllowedFilter::callback('search', function (Builder $query, string $value) {
                    // `whereHasMorph` composerait une sous-requete correlee sur `users`, une
                    // table qui n'existe pas dans la base du locataire (CLAUDE.md,
                    // « Multi-locataire ») : `User` vit dans la base centrale, une connexion
                    // separee. On resout d'abord les identifiants cote central, puis on filtre
                    // sur `causer_id` cote locataire, jamais de jointure entre les deux bases.
                    $causerIds = User::query()
                        ->tap(fn (Builder $users) => UnaccentedSearch::apply($users, ['name'], $value))
                        ->pluck('id');

                    $query->where(function (Builder $query) use ($value, $causerIds) {
                        $query->where(fn (Builder $description) => UnaccentedSearch::apply($description, ['description'], $value))
                            ->orWhere(function (Builder $query) use ($causerIds) {
                                $query->where('causer_type', User::class)
                                    ->whereIn('causer_id', $causerIds);
                            });
                    });
                }),
                AllowedFilter::exact('type', 'description'),
            )
            ->defaultSort('-created_at')
            ->allowedSorts('created_at');
    }

    /**
     * @return array<string, mixed>
     */
    private function entry(Activity $activity): array
    {
        return [
            'id' => $activity->id,
            'type' => $activity->description,
            'actor' => $this->actorLabel($activity),
            'subject' => $this->subjectLabel($activity),
            'ip' => $activity->properties->get('ip'),
            'at' => $activity->created_at?->toISOString(),
        ];
    }

    /**
     * Le nom de l'acteur, ou une mention de tache planifiee quand l'action n'a pas de causeur
     * (les purges automatiques, par exemple).
     */
    private function actorLabel(Activity $activity): string
    {
        $causer = $activity->causer;

        return $causer instanceof User ? $causer->name : __('audit.system_actor');
    }

    /**
     * Best-effort human label for the entry's subject : the model's own name when it has one,
     * else whatever name the journalised properties kept (a deleted event, a replaced brand
     * file collection). `null` when nothing usable is found : the message template for that
     * type does not need a subject either.
     */
    private function subjectLabel(Activity $activity): ?string
    {
        $subject = $activity->subject;

        if ($subject instanceof Model) {
            foreach (['name', 'label', 'registration.name'] as $path) {
                $value = data_get($subject, $path);

                if (is_string($value) && $value !== '') {
                    return $value;
                }
            }
        }

        foreach (['attributes.name', 'old.name', 'attributes.collection', 'old.collection', 'attributes.filename', 'old.filename'] as $path) {
            $value = data_get($activity->properties, $path);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}

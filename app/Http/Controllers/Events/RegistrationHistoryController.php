<?php

namespace App\Http\Controllers\Events;

use App\Enums\AuditLogType;
use App\Enums\ClaimCategory;
use App\Enums\PaymentChannel;
use App\Enums\RegistrationEventType;
use App\Enums\TenantPermission;
use App\Http\Controllers\Controller;
use App\Models\AuditEntry;
use App\Models\Event;
use App\Models\Registration;
use App\Models\RegistrationEvent;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * L'historique d'un dossier : les faits de l'invite et du systeme (`registration_events`) et les
 * gestes de l'equipe (journal d'audit), dans l'ordre. C'est ce qui permet de dire, devant un litige,
 * ce qui s'est passe et qui en etait l'auteur.
 *
 * Une liste courte par nature (un dossier ne depasse pas quelques dizaines de lignes) : sans
 * pagination. L'adresse IP n'est montree qu'aux porteurs de `audit.view`, comme dans le journal.
 */
class RegistrationHistoryController extends Controller
{
    private const Limit = 200;

    public function show(Request $request, Tenant $tenant, Event $event, Registration $registration): Response
    {
        Gate::authorize('view', [$registration, $tenant]);

        abort_unless($registration->event_id === $event->id, 404);

        $withIp = $request->user()->hasTenantPermission($tenant, TenantPermission::AuditView);

        $waitlistEntryIds = RegistrationEvent::query()
            ->where('registration_id', $registration->id)
            ->where('type', RegistrationEventType::WaitlistConverted)
            ->get()
            ->map(fn (RegistrationEvent $fact) => $fact->properties['waitlist_entry_id'] ?? null)
            ->filter();

        $facts = RegistrationEvent::query()
            ->where(fn ($query) => $query
                ->where('registration_id', $registration->id)
                ->orWhereIn('waitlist_entry_id', $waitlistEntryIds))
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(self::Limit)
            ->get()
            ->map(fn (RegistrationEvent $fact) => [
                'key' => 'fact-'.$fact->id,
                'kind' => $fact->actor->value,
                'label' => $fact->type->label(),
                'detail' => $this->detail($fact),
                'actor' => $fact->actor->label(),
                'ip' => $withIp ? $fact->ip_address : null,
                'at' => $fact->created_at?->toISOString(),
            ]);

        $gestures = AuditEntry::query()
            ->with('causer')
            ->where('subject_type', Registration::class)
            ->where('subject_id', $registration->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(self::Limit)
            ->get()
            ->map(fn (AuditEntry $entry) => [
                'key' => 'audit-'.$entry->id,
                'kind' => 'team',
                'label' => AuditLogType::tryFrom((string) $entry->description) !== null
                    ? __('audit.types.'.$entry->description)
                    : (string) $entry->description,
                'detail' => null,
                'actor' => $entry->causer instanceof User ? $entry->causer->name : __('audit.system_actor'),
                'ip' => $withIp ? $entry->properties->get('ip') : null,
                'at' => $entry->created_at?->toISOString(),
            ]);

        /** @var Collection<int, array<string, mixed>> $timeline */
        $timeline = $facts->concat($gestures)->sortBy('at')->values();

        return Inertia::render('events/registration-history', [
            'tenant' => ['slug' => $tenant->slug],
            'event' => ['id' => $event->id, 'name' => $event->name],
            'registration' => [
                'id' => $registration->id,
                'reference' => $registration->reference,
                'name' => $registration->name,
                'statusLabel' => $registration->status->label(),
            ],
            'timeline' => $timeline,
            'showsIp' => $withIp,
        ]);
    }

    /**
     * Une precision lisible tiree des proprietes du fait, ou null. Jamais un nom ni un telephone.
     */
    private function detail(RegistrationEvent $fact): ?string
    {
        $properties = $fact->properties ?? [];

        return match ($fact->type) {
            RegistrationEventType::Held => $this->text('registrations.history.details.held', [
                'count' => $properties['party_size'] ?? 1,
                'kind' => match (true) {
                    (bool) ($properties['free'] ?? false) => $this->text('registrations.history.details.held_free'),
                    (bool) ($properties['renewed'] ?? false) => $this->text('registrations.history.details.held_renewed'),
                    default => $this->text('registrations.history.details.held_new'),
                },
            ]),
            RegistrationEventType::ProofSubmitted => isset($properties['channel'])
                ? PaymentChannel::tryFrom($properties['channel'])?->label()
                : null,
            RegistrationEventType::TableAssigned => isset($properties['table_number'])
                ? $this->text('registrations.history.details.table', ['number' => $properties['table_number']])
                : null,
            RegistrationEventType::TicketsIssued => $this->text('registrations.history.details.tickets', ['count' => $properties['count'] ?? 0]),
            RegistrationEventType::ClaimSubmitted => isset($properties['category'])
                ? ClaimCategory::tryFrom($properties['category'])?->label()
                : null,
            RegistrationEventType::PhoneVerified => match ($properties['method'] ?? null) {
                'sms' => $this->text('registrations.history.details.phone_sms'),
                'whatsapp' => $this->text('registrations.history.details.phone_whatsapp'),
                default => null,
            },
            RegistrationEventType::WaitlistJoined => $this->text('registrations.history.details.held', [
                'count' => $properties['party_size'] ?? 1,
                'kind' => $this->text('registrations.history.details.waitlist'),
            ]),
            default => null,
        };
    }

    /**
     * Un texte de l'historique : `__()` peut rendre un groupe entier pour une cle incomplete, jamais ici.
     *
     * @param  array<string, mixed>  $replace
     */
    private function text(string $key, array $replace = []): string
    {
        $text = __($key, $replace);

        return is_string($text) ? $text : $key;
    }
}

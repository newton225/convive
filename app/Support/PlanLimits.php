<?php

namespace App\Support;

use App\Enums\EventStatus;
use App\Enums\PlanFeature;
use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\MessageUsage;
use App\Models\Registration;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;

/**
 * Les quotas et les options du plan d'une organisation (README section 3), appliques cote serveur :
 * « les quotas doivent etre appliques, pas seulement affiches ».
 *
 * Ce que chaque plafond compte :
 * - Evenements actifs : publies et pas encore clotures (`Open`, `Ongoing`). Un brouillon ou un
 *   evenement clos ne consomme rien.
 * - Inscrits : les personnes (`party_size`) des dossiers qui occupent une place ou attendent une
 *   verification, sur les evenements actifs. Un dossier expire, rejete, annule ou brouillon ne
 *   compte pas.
 * - Membres : les membres de l'equipe plus les invitations encore en attente, sans quoi on
 *   depasserait le plafond en invitant tout le monde avant que quiconque accepte.
 *
 * Un plafond `null` est illimite. Tout se coupe avec `convive.billing.enforce_plan_limits`.
 * Les comptes en base d'une organisation se lisent sous sa tenancy (`Tenant::run()`).
 */
class PlanLimits
{
    private function __construct(private readonly Tenant $tenant) {}

    public static function for(Tenant $tenant): self
    {
        return new self($tenant);
    }

    public static function enforced(): bool
    {
        return (bool) config('convive.billing.enforce_plan_limits');
    }

    /**
     * Determine whether one more event may be published.
     */
    public function canPublishEvent(): bool
    {
        return $this->hasRoom($this->tenant->plan()->max_active_events, $this->activeEvents(), 1);
    }

    /**
     * Determine whether the given number of guests may still be registered.
     */
    public function canRegister(int $people): bool
    {
        return $this->hasRoom($this->tenant->plan()->max_registrations, $this->registrations(), $people);
    }

    /**
     * Determine whether one more member, or one more invitation, fits in the team.
     */
    public function canAddMember(): bool
    {
        return $this->hasRoom($this->tenant->plan()->max_members, $this->members(), 1);
    }

    /**
     * Determine whether the plan opens the given feature.
     */
    public function allows(PlanFeature $feature): bool
    {
        return ! self::enforced() || $this->tenant->plan()->allows($feature);
    }

    public function activeEvents(): int
    {
        return $this->tenant->run(fn () => Event::query()
            ->whereIn('status', $this->activeStatuses())
            ->count());
    }

    public function registrations(): int
    {
        return (int) $this->tenant->run(fn () => Registration::query()
            ->whereHas('event', fn (Builder $event) => $event->whereIn('status', $this->activeStatuses()))
            ->where(fn (Builder $query) => $query
                ->where('status', RegistrationStatus::ProofSubmitted)
                ->orWhere(fn (Builder $seated) => $seated->occupyingSeats()))
            ->sum('party_size'));
    }

    public function members(): int
    {
        return $this->tenant->members()->count()
            + $this->tenant->invitations()->whereNull('accepted_at')->count();
    }

    /**
     * Get how many messages were sent to guests this month (cards and reminders).
     */
    public function messagesThisMonth(): int
    {
        return (int) $this->tenant->run(fn () => MessageUsage::where('month', now()->format('Y-m'))->value('count'));
    }

    /**
     * Determine whether one more guest message fits in this month's quota.
     */
    public function canSendMessage(): bool
    {
        return $this->hasRoom($this->tenant->plan()->max_messages_per_month, $this->messagesThisMonth(), 1);
    }

    /**
     * Get the plan's ceilings next to what is used, for the subscription screen.
     *
     * @return array<string, array{used: int, max: int|null}>
     */
    public function usage(): array
    {
        $plan = $this->tenant->plan();

        return [
            'events' => ['used' => $this->activeEvents(), 'max' => $plan->max_active_events],
            'registrations' => ['used' => $this->registrations(), 'max' => $plan->max_registrations],
            'members' => ['used' => $this->members(), 'max' => $plan->max_members],
            'messages' => ['used' => $this->messagesThisMonth(), 'max' => $plan->max_messages_per_month],
        ];
    }

    private function hasRoom(?int $max, int $used, int $adding): bool
    {
        return ! self::enforced() || $max === null || $used + $adding <= $max;
    }

    /**
     * @return array<int, EventStatus>
     */
    private function activeStatuses(): array
    {
        return [EventStatus::Open, EventStatus::Ongoing];
    }
}

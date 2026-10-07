<?php

namespace App\Support\Console;

use App\Models\AuditChainCheck;
use App\Models\SecurityEvent;
use App\Models\Tenant;
use App\Support\AuditChain;
use App\Support\ListPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Les faits de securite que la console montre (README section 3, CLAUDE.md « Limitation de debit » :
 * « chaque blocage est journalise ») : limites de debit atteintes, connexions verrouillees, et
 * integrite de chaque journal d'audit. Ils n'etaient ecrits qu'au journal du serveur, que personne
 * ne lit.
 */
class SecurityJournal
{
    /**
     * Record that a request hit a rate limit. Une meme adresse qui insiste sur la meme route
     * n'ecrit qu'une ligne par minute : celui qui martele une limite ne doit pas, en plus, remplir
     * la table qui le signale.
     */
    public static function rateLimited(Request $request): void
    {
        // La connexion est limitee par le meme mecanisme que le reste : c'est ici qu'un
        // verrouillage se reconnait, et l'adresse visee compte plus que le nom de la route.
        if ($request->route()?->getName() === 'login.store') {
            self::lockout($request);

            return;
        }

        self::record(SecurityEvent::RateLimited, $request, $request->route()?->getName() ?? $request->path());
    }

    /**
     * Record that a sign-in was locked after repeated failures, with the address aimed at.
     */
    public static function lockout(Request $request): void
    {
        $email = $request->input('email');

        self::record(SecurityEvent::LoginLockout, $request, is_string($email) ? mb_strtolower($email) : null);
    }

    /**
     * Verify one audit log (the current organisation's, or the central one outside any) and keep
     * the result. Une rupture reste aussi ecrite au niveau critique du journal du serveur.
     */
    public static function checkAuditChain(?Tenant $tenant = null): AuditChainCheck
    {
        $brokenId = AuditChain::firstBrokenEntry();
        $scope = $tenant === null ? AuditChainCheck::Central : "tenant:{$tenant->id}";

        if ($brokenId !== null) {
            Log::critical('Chaine du journal d\'audit rompue', ['scope' => $scope, 'entry_id' => $brokenId]);
        }

        return AuditChainCheck::updateOrCreate(['scope' => $scope], [
            'tenant_id' => $tenant?->id,
            'organisation' => $tenant?->name,
            'checked_at' => now(),
            'broken_entry_id' => $brokenId,
        ]);
    }

    /**
     * Get what the security screen shows.
     *
     * Les faits sont pagines par le serveur (TODO du 2026-10-07, point 11) : plus de fenetre des
     * derniers, tous restent atteignables.
     *
     * @return array{chains: array{checkedAt: string|null, count: int, broken: array<int, array{scope: string, organisation: string|null, entryId: int, checkedAt: string}>}, counts: array{rateLimited: int, lockouts: int}, events: array<int, array{id: int, at: string, type: string, subject: string|null, ip: string|null}>, eventsMeta: array{currentPage: int, lastPage: int, total: int}}
     */
    public static function overview(Request $request): array
    {
        $checks = AuditChainCheck::query()->get();
        $since = now()->subDay();
        $events = ListPage::of(SecurityEvent::query()->latest('created_at')->latest('id'), $request);

        return [
            'chains' => [
                // La plus ancienne des verifications : c'est elle qui dit si la tache tourne.
                'checkedAt' => $checks->min('checked_at')?->toISOString(),
                'count' => $checks->count(),
                'broken' => $checks
                    ->filter(fn (AuditChainCheck $check) => $check->isBroken())
                    ->map(fn (AuditChainCheck $check) => [
                        'scope' => $check->scope,
                        'organisation' => $check->organisation,
                        'entryId' => (int) $check->broken_entry_id,
                        'checkedAt' => $check->checked_at->toISOString(),
                    ])
                    ->values()
                    ->all(),
            ],
            'counts' => [
                'rateLimited' => SecurityEvent::where('type', SecurityEvent::RateLimited)->where('created_at', '>=', $since)->count(),
                'lockouts' => SecurityEvent::where('type', SecurityEvent::LoginLockout)->where('created_at', '>=', $since)->count(),
            ],
            'events' => $events->getCollection()
                ->map(fn (SecurityEvent $event) => [
                    'id' => $event->id,
                    'at' => $event->created_at->toISOString(),
                    'type' => $event->type,
                    'subject' => $event->subject,
                    'ip' => $event->ip,
                ])
                ->values()
                ->all(),
            'eventsMeta' => ListPage::meta($events),
        ];
    }

    /**
     * Count the audit logs whose chain is broken, for the health check.
     */
    public static function brokenChains(): int
    {
        return AuditChainCheck::whereNotNull('broken_entry_id')->count();
    }

    /**
     * Forget the events past their retention.
     */
    public static function purge(): int
    {
        return SecurityEvent::where('created_at', '<', now()->subDays(SecurityEvent::RetentionDays))->delete();
    }

    private static function record(string $type, Request $request, ?string $subject): void
    {
        // Une ecriture de journal ne doit jamais faire echouer la reponse qu'elle accompagne.
        rescue(function () use ($type, $request, $subject) {
            if (! Cache::add('security:'.sha1($type.'|'.$request->ip().'|'.$subject), true, 60)) {
                return;
            }

            SecurityEvent::create([
                'type' => $type,
                'subject' => $subject === null ? null : mb_substr($subject, 0, 255),
                'tenant_id' => tenant('id'),
                'user_id' => $request->user()?->id,
                'ip' => $request->ip(),
                'created_at' => now(),
            ]);
        });
    }
}

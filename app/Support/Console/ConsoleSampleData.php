<?php

namespace App\Support\Console;

use Illuminate\Support\Carbon;

/**
 * PROVISOIRE : jeu d'exemple de la console d'exploitation (README section 3, ecrans 27 a 34),
 * construite avant son serveur (CLAUDE.md, « Ordre de travail »). Aucune de ces valeurs ne vient
 * de la base : chaque page l'annonce par `isSample` et le bandeau « donnees d'exemple ».
 *
 * Les dates sont relatives au moment de l'affichage, pour que l'exemple reste vraisemblable
 * (un impaye « depuis 6 jours » reste a 6 jours). Supprime, pas complete, quand le serveur arrive.
 */
class ConsoleSampleData
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function organisations(): array
    {
        return [
            self::summary('eglise-bethel', 'Église Béthel Cocody', 'association', 'active', openedDaysAgo: 214, activeDaysAgo: 0, usage: [3, 5, 642, 1000, 7, 10]),
            self::summary('anciens-lycee-classique', 'Anciens du Lycée Classique', 'association', 'past_due', openedDaysAgo: 380, activeDaysAgo: 2, usage: [2, 5, 418, 1000, 4, 10], pastDueDaysAgo: 6),
            self::summary('chorale-sainte-cecile', 'Chorale Sainte-Cécile', 'essential', 'active', openedDaysAgo: 45, activeDaysAgo: 1, usage: [1, 1, 187, 200, 2, 2]),
            self::summary('rotary-plateau', 'Rotary Club Abidjan Plateau', 'institution', 'active', openedDaysAgo: 512, activeDaysAgo: 0, usage: [8, null, 2310, null, 14, null]),
            self::summary('ja-yopougon', 'Jeunesse Adventiste de Yopougon', 'association', 'trial', openedDaysAgo: 9, activeDaysAgo: 0, usage: [1, 5, 36, 1000, 3, 10], trialEndsInDays: 5),
            self::summary('cabinet-kouassi', 'Cabinet Kouassi et Associés', 'association', 'suspended', openedDaysAgo: 290, activeDaysAgo: 18, usage: [0, 5, 120, 1000, 3, 10], pastDueDaysAgo: 14, suspendedDaysAgo: 4),
            self::summary('fondation-espoir', 'Fondation Espoir Bouaké', 'essential', 'deletion_scheduled', openedDaysAgo: 160, activeDaysAgo: 22, usage: [0, 1, 0, 200, 1, 2], deletionInDays: 23),
            self::summary('amicale-infirmiers', 'Amicale des Infirmiers de Treichville', 'association', 'past_due', openedDaysAgo: 98, activeDaysAgo: 3, usage: [1, 5, 254, 1000, 5, 10], pastDueDaysAgo: 2),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function organisation(string $slug): ?array
    {
        $summary = collect(self::organisations())->firstWhere('slug', $slug);

        if ($summary === null) {
            return null;
        }

        $monthly = $summary['plan'] === 'association' ? 45000 : 0;

        return [
            ...$summary,
            'legalName' => $summary['name'],
            'contactEmail' => "contact@{$slug}.ci",
            'subdomain' => $slug,
            'history' => [
                ['at' => $summary['openedAt'], 'type' => 'opened', 'detail' => 'Essentiel'],
                ['at' => $summary['planChangedAt'], 'type' => 'plan_changed', 'detail' => $summary['planName']],
            ],
            'invoices' => $monthly === 0 ? [] : [
                ['number' => 'CNV-2026-0'.(410 + strlen($slug)), 'amount' => $monthly, 'currency' => 'XOF', 'status' => $summary['status'] === 'past_due' || $summary['status'] === 'suspended' ? 'open' : 'paid', 'issuedAt' => self::daysAgo(8)],
                ['number' => 'CNV-2026-0'.(380 + strlen($slug)), 'amount' => $monthly, 'currency' => 'XOF', 'status' => 'paid', 'issuedAt' => self::daysAgo(38)],
                ['number' => 'CNV-2026-0'.(350 + strlen($slug)), 'amount' => $monthly, 'currency' => 'XOF', 'status' => 'paid', 'issuedAt' => self::daysAgo(68)],
            ],
            'supportAccess' => $slug === 'eglise-bethel' ? [
                'operator' => 'Awa Traoré',
                'grantedBy' => 'Pasteur Yao Kouamé',
                'expiresAt' => self::hoursFromNow(17),
            ] : null,
            'consoleActions' => $slug === 'cabinet-kouassi' ? [
                ['at' => self::daysAgo(4), 'actor' => null, 'type' => 'tenant_suspended'],
            ] : [],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function unpaid(): array
    {
        return collect(self::organisations())
            ->filter(fn (array $organisation) => in_array($organisation['status'], ['past_due', 'suspended'], true))
            ->map(fn (array $organisation) => [
                'slug' => $organisation['slug'],
                'name' => $organisation['name'],
                'planName' => $organisation['planName'],
                'amount' => 45000,
                'currency' => 'XOF',
                'status' => $organisation['status'],
                'pastDueSince' => $organisation['pastDueSince'],
                'remindersSent' => $organisation['pastDueDays'] >= 3 ? 1 : 0,
                'suspendsAt' => $organisation['status'] === 'suspended' ? null : self::daysFromNow(10 - $organisation['pastDueDays']),
                'failureReason' => $organisation['slug'] === 'anciens-lycee-classique' ? 'insufficient_funds' : 'card_expired',
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function failedPayments(): array
    {
        return [
            ['name' => 'Anciens du Lycée Classique', 'slug' => 'anciens-lycee-classique', 'at' => self::daysAgo(3), 'amount' => 45000, 'currency' => 'XOF', 'reason' => 'insufficient_funds'],
            ['name' => 'Anciens du Lycée Classique', 'slug' => 'anciens-lycee-classique', 'at' => self::daysAgo(6), 'amount' => 45000, 'currency' => 'XOF', 'reason' => 'insufficient_funds'],
            ['name' => 'Amicale des Infirmiers de Treichville', 'slug' => 'amicale-infirmiers', 'at' => self::daysAgo(2), 'amount' => 45000, 'currency' => 'XOF', 'reason' => 'card_expired'],
            ['name' => 'Cabinet Kouassi et Associés', 'slug' => 'cabinet-kouassi', 'at' => self::daysAgo(14), 'amount' => 45000, 'currency' => 'XOF', 'reason' => 'card_expired'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function health(): array
    {
        return [
            'databases' => [
                ['slug' => 'ja-yopougon', 'name' => 'Jeunesse Adventiste de Yopougon', 'issue' => 'pending_migrations', 'pendingMigrations' => 2],
                ['slug' => 'fondation-espoir', 'name' => 'Fondation Espoir Bouaké', 'issue' => 'missing_database', 'pendingMigrations' => null],
            ],
            'tasks' => [
                ['key' => 'payment_accounts_activation', 'lastRunAt' => self::minutesAgo(4), 'everyMinutes' => 5, 'late' => false],
                ['key' => 'registrations_purge', 'lastRunAt' => self::minutesAgo(3), 'everyMinutes' => 5, 'late' => false],
                ['key' => 'scheduled_cards', 'lastRunAt' => self::minutesAgo(2), 'everyMinutes' => 5, 'late' => false],
                ['key' => 'proof_reminders', 'lastRunAt' => self::minutesAgo(95), 'everyMinutes' => 60, 'late' => true],
            ],
            'queues' => [
                ['name' => 'default', 'pending' => 3, 'failed' => 0, 'oldestAt' => self::minutesAgo(1)],
                ['name' => 'notifications', 'pending' => 41, 'failed' => 2, 'oldestAt' => self::minutesAgo(12)],
            ],
            'backup' => ['lastAt' => self::hoursAgo(7), 'sizeMb' => 184, 'healthy' => true],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function announcements(): array
    {
        return [
            ['id' => 1, 'eventName' => 'Dîner de gala annuel', 'organisationName' => 'Rotary Club Abidjan Plateau', 'announcedAt' => self::daysAgo(2), 'startsAt' => self::daysFromNow(24), 'publicUrl' => 'https://rotary-plateau.convive.ci/e/'.str_repeat('a3f9', 16)],
            ['id' => 2, 'eventName' => 'Concert de Noël', 'organisationName' => 'Chorale Sainte-Cécile', 'announcedAt' => self::daysAgo(5), 'startsAt' => self::daysFromNow(80), 'publicUrl' => 'https://chorale-sainte-cecile.convive.ci/e/'.str_repeat('7c2e', 16)],
            ['id' => 3, 'eventName' => 'Retraite spirituelle', 'organisationName' => 'Église Béthel Cocody', 'announcedAt' => self::daysAgo(11), 'startsAt' => self::daysFromNow(17), 'publicUrl' => 'https://eglise-bethel.convive.ci/e/'.str_repeat('b18d', 16)],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function withdrawnAnnouncements(): array
    {
        return [
            ['id' => 4, 'eventName' => 'Soirée gains garantis', 'organisationName' => 'Fondation Espoir Bouaké', 'withdrawnAt' => self::daysAgo(20), 'actor' => 'Awa Traoré', 'reason' => 'Promesse de gains financiers, contraire aux conditions d’utilisation.'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function auditEntries(): array
    {
        return [
            ['id' => 9, 'at' => self::hoursAgo(2), 'actor' => 'Awa Traoré', 'type' => 'support_access_used', 'organisation' => 'Église Béthel Cocody', 'ip' => '102.67.12.40'],
            ['id' => 8, 'at' => self::daysAgo(1), 'actor' => 'Koffi N’Guessan', 'type' => 'trial_extended', 'organisation' => 'Jeunesse Adventiste de Yopougon', 'ip' => '102.67.12.41'],
            ['id' => 7, 'at' => self::daysAgo(4), 'actor' => null, 'type' => 'tenant_suspended', 'organisation' => 'Cabinet Kouassi et Associés', 'ip' => null],
            ['id' => 6, 'at' => self::daysAgo(7), 'actor' => 'Koffi N’Guessan', 'type' => 'deletion_scheduled', 'organisation' => 'Fondation Espoir Bouaké', 'ip' => '102.67.12.41'],
            ['id' => 5, 'at' => self::daysAgo(12), 'actor' => 'Mariam Diabaté', 'type' => 'plan_changed', 'organisation' => 'Rotary Club Abidjan Plateau', 'ip' => '41.207.3.18'],
            ['id' => 4, 'at' => self::daysAgo(20), 'actor' => 'Awa Traoré', 'type' => 'announcement_withdrawn', 'organisation' => 'Fondation Espoir Bouaké', 'ip' => '102.67.12.40'],
            ['id' => 3, 'at' => self::daysAgo(31), 'actor' => 'Koffi N’Guessan', 'type' => 'operator_invited', 'organisation' => null, 'ip' => '102.67.12.41'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function team(): array
    {
        return [
            'operators' => [
                ['id' => 1, 'name' => 'Koffi N’Guessan', 'email' => 'koffi@convive.com', 'profile' => 'founder', 'twoFactor' => true, 'lastLoginAt' => self::hoursAgo(1)],
                ['id' => 2, 'name' => 'Awa Traoré', 'email' => 'awa@convive.com', 'profile' => 'support', 'twoFactor' => true, 'lastLoginAt' => self::hoursAgo(3)],
                ['id' => 3, 'name' => 'Mariam Diabaté', 'email' => 'mariam@convive.com', 'profile' => 'accounting', 'twoFactor' => true, 'lastLoginAt' => self::daysAgo(2)],
            ],
            'invitations' => [
                ['email' => 'serge@convive.com', 'profile' => 'support', 'sentAt' => self::daysAgo(1)],
            ],
        ];
    }

    /**
     * @param  array{0: int, 1: int|null, 2: int, 3: int|null, 4: int, 5: int|null}  $usage
     * @return array<string, mixed>
     */
    private static function summary(
        string $slug,
        string $name,
        string $plan,
        string $status,
        int $openedDaysAgo,
        int $activeDaysAgo,
        array $usage,
        ?int $pastDueDaysAgo = null,
        ?int $suspendedDaysAgo = null,
        ?int $trialEndsInDays = null,
        ?int $deletionInDays = null,
    ): array {
        return [
            'slug' => $slug,
            'name' => $name,
            'plan' => $plan,
            'planName' => ['essential' => 'Essentiel', 'association' => 'Association', 'institution' => 'Institution'][$plan],
            'status' => $status,
            'openedAt' => self::daysAgo($openedDaysAgo),
            'planChangedAt' => self::daysAgo(max(0, $openedDaysAgo - 30)),
            'lastActivityAt' => self::hoursAgo($activeDaysAgo * 24 + 2),
            'usage' => [
                'activeEvents' => ['used' => $usage[0], 'max' => $usage[1]],
                'registrations' => ['used' => $usage[2], 'max' => $usage[3]],
                'members' => ['used' => $usage[4], 'max' => $usage[5]],
            ],
            'pastDueDays' => $pastDueDaysAgo ?? 0,
            'pastDueSince' => $pastDueDaysAgo === null ? null : self::daysAgo($pastDueDaysAgo),
            'suspendedAt' => $suspendedDaysAgo === null ? null : self::daysAgo($suspendedDaysAgo),
            'trialEndsAt' => $trialEndsInDays === null ? null : self::daysFromNow($trialEndsInDays),
            'deletionAt' => $deletionInDays === null ? null : self::daysFromNow($deletionInDays),
        ];
    }

    private static function daysAgo(int $days): string
    {
        return Carbon::now()->subDays($days)->toISOString();
    }

    private static function daysFromNow(int $days): string
    {
        return Carbon::now()->addDays($days)->toISOString();
    }

    private static function hoursAgo(int $hours): string
    {
        return Carbon::now()->subHours($hours)->toISOString();
    }

    private static function hoursFromNow(int $hours): string
    {
        return Carbon::now()->addHours($hours)->toISOString();
    }

    private static function minutesAgo(int $minutes): string
    {
        return Carbon::now()->subMinutes($minutes)->toISOString();
    }
}

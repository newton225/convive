<?php

namespace App\Support\Health;

use Illuminate\Queue\Failed\CountableFailedJobProvider;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Throwable;

/**
 * Les files vues par l'ecran de sante technique (README ecran 31) : ce qui attend d'etre traite, et
 * ce qui a echoue pour de bon. Lu sur le mecanisme de Laravel (`queue.failer`), quel que soit le
 * moteur de file.
 */
class QueueOverview
{
    /**
     * Nombre de travaux en echec detailles a l'ecran ; le compte, lui, porte sur tous.
     */
    private const FailedShown = 20;

    /**
     * Count the jobs waiting on the default queue, or null when the queue does not answer.
     */
    public static function pending(): ?int
    {
        try {
            return Queue::size();
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    public static function failedCount(): int
    {
        $failer = self::failer();

        return $failer instanceof CountableFailedJobProvider
            ? $failer->count()
            : count($failer->all());
    }

    /**
     * Get the most recent failed jobs, with what failed and why, in one line each.
     *
     * @return array<int, array{id: string, job: string, queue: string, failedAt: string, error: string}>
     */
    public static function failed(): array
    {
        return collect(self::failer()->all())
            ->take(self::FailedShown)
            // Le mecanisme de Laravel rend des objets sans type : chaque champ est lu comme texte.
            ->map(fn (object $job) => get_object_vars($job))
            ->map(fn (array $job) => [
                'id' => self::text($job, 'id'),
                'job' => self::jobName(self::text($job, 'payload')),
                'queue' => self::text($job, 'queue'),
                'failedAt' => Carbon::parse(self::text($job, 'failed_at'))->toISOString(),
                // La premiere ligne de l'erreur : la pile d'appel reste au journal du serveur.
                'error' => Str::limit(Str::before(self::text($job, 'exception'), "\n"), 300),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $job
     */
    private static function text(array $job, string $field): string
    {
        $value = $job[$field] ?? '';

        return is_scalar($value) ? (string) $value : '';
    }

    private static function failer(): FailedJobProviderInterface
    {
        return app('queue.failer');
    }

    /**
     * Get what the job was, without its namespace. Une notification en file est emballee dans un
     * travail generique de Laravel : c'est le nom de la notification qui dit ce qui n'est pas parti.
     */
    private static function jobName(string $payload): string
    {
        $decoded = json_decode($payload, true);
        $name = is_array($decoded) ? (string) ($decoded['displayName'] ?? '') : '';
        $command = is_array($decoded) ? (string) ($decoded['data']['command'] ?? '') : '';

        if (preg_match('/"(App\\\\Notifications\\\\[^"]+)"/', $command, $matches) === 1) {
            $name = $matches[1];
        }

        return $name === '' ? '?' : class_basename($name);
    }
}

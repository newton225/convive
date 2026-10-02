import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vite-plus/test';
import { HealthChecksCard } from '@/components/console/health-checks-card';
import type { ConsoleHealthCheck } from '@/types';

// Le composant lit ses textes par `useTranslation()`, qui vient des props partagées d'Inertia.
// Ici la traduction rend la clé : le test porte sur ce que la carte affiche, pas sur les textes.
vi.mock('@/hooks/use-translation', () => ({
    useTranslation: () => ({ t: (key: string) => key, locale: 'fr' }),
}));

const check = (
    name: string,
    status: ConsoleHealthCheck['status'],
): ConsoleHealthCheck => ({ name, status, summary: '' });

describe('HealthChecksCard', () => {
    it('dit qu’aucun contrôle n’a pu être joué quand la liste est vide', () => {
        render(<HealthChecksCard checks={[]} />);

        expect(screen.getByText('console.health.checks_empty')).toBeTruthy();
    });

    it('montre le planificateur avant les autres, quel que soit l’ordre reçu', () => {
        render(
            <HealthChecksCard
                checks={[
                    check('disk', 'ok'),
                    check('queue', 'ok'),
                    check('schedule', 'failed'),
                ]}
            />,
        );

        const labels = screen
            .getAllByRole('listitem')
            .map((item) => item.getAttribute('data-test'));

        expect(labels).toEqual([
            'console-health-check-schedule',
            'console-health-check-queue',
            'console-health-check-disk',
        ]);
    });

    it('écrit l’état en toutes lettres et explique un échec', () => {
        render(<HealthChecksCard checks={[check('schedule', 'failed')]} />);

        expect(
            screen.getByText('console.health.check_status.failed'),
        ).toBeTruthy();
        expect(
            screen.getByText('console.health.checks.schedule.failed'),
        ).toBeTruthy();
    });

    it('n’explique pas un contrôle qui n’a pas été joué', () => {
        render(<HealthChecksCard checks={[check('disk', 'skipped')]} />);

        expect(
            screen.getByText('console.health.check_status.skipped'),
        ).toBeTruthy();
        expect(
            screen.queryByText('console.health.checks.disk.skipped'),
        ).toBeNull();
    });
});

import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vite-plus/test';
import InputError from '@/components/input-error';

const listeners: Array<() => void> = [];

// Le routeur d'Inertia : `start` marque le debut d'un nouvel envoi, que le composant ecoute.
vi.mock('@inertiajs/react', () => ({
    router: {
        on: (_event: string, callback: () => void) => {
            listeners.push(callback);

            return () => undefined;
        },
    },
}));

describe('InputError', () => {
    it('affiche le message', () => {
        render(
            <div>
                <input name="ends_at" aria-label="fin" />
                <InputError message="La date de fin doit etre apres le debut." />
            </div>,
        );

        expect(screen.getByText(/date de fin/)).toBeTruthy();
    });

    it('disparait quand on retouche le champ', () => {
        render(
            <div>
                <input name="ends_at" aria-label="fin" />
                <InputError message="La date de fin doit etre apres le debut." />
            </div>,
        );

        fireEvent.input(screen.getByLabelText('fin'), {
            target: { value: '2026-10-10T20:00' },
        });

        expect(screen.queryByText(/date de fin/)).toBeNull();
    });

    it('revient quand le serveur renvoie une erreur apres un nouvel envoi', () => {
        const { rerender } = render(
            <div>
                <input name="ends_at" aria-label="fin" />
                <InputError message="Erreur A" />
            </div>,
        );

        fireEvent.input(screen.getByLabelText('fin'), {
            target: { value: 'x' },
        });
        expect(screen.queryByText('Erreur A')).toBeNull();

        // Un nouvel envoi demarre, puis le serveur renvoie une autre erreur.
        listeners.forEach((listener) => listener());
        rerender(
            <div>
                <input name="ends_at" aria-label="fin" />
                <InputError message="Erreur B" />
            </div>,
        );

        expect(screen.getByText('Erreur B')).toBeTruthy();
    });
});

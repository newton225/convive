import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vite-plus/test';
import { SubdomainField } from '@/components/subdomain-field';

vi.mock('@inertiajs/react', () => ({
    router: { on: () => () => undefined },
}));

// La traduction rend la cle, sauf l'exemple grise, dont le test lit la valeur.
vi.mock('@/hooks/use-translation', () => ({
    useTranslation: () => ({
        t: (key: string) =>
            key === 'organisation.placeholders.subdomain'
                ? 'mon-association'
                : key,
        locale: 'fr',
    }),
}));

vi.mock('@/components/label-with-help', () => ({
    LabelWithHelp: ({ label }: { label: string }) => <label>{label}</label>,
}));

// Le projet designe ses elements par `data-test`, pas `data-testid` (CLAUDE.md).
const input = () =>
    document.querySelector<HTMLInputElement>(
        '[data-test="organisation-subdomain"]',
    )!;
const address = () =>
    document.querySelector('[data-test="organisation-subdomain-address"]')!;

describe('SubdomainField', () => {
    it('passe la saisie en minuscules', () => {
        render(
            <SubdomainField defaultValue="" publicDomain="app.convive.test" />,
        );

        fireEvent.change(input(), { target: { value: 'MonAsso' } });

        expect(input().value).toBe('monasso');
    });

    it('montre l adresse obtenue au fil de la saisie', () => {
        render(
            <SubdomainField defaultValue="" publicDomain="app.convive.test" />,
        );

        fireEvent.change(input(), { target: { value: 'Pilote' } });

        expect(address().textContent).toBe('pilote.app.convive.test');
    });

    it('montre un exemple tant que le champ est vide', () => {
        render(
            <SubdomainField defaultValue="" publicDomain="app.convive.test" />,
        );

        expect(address().textContent).toBe('mon-association.app.convive.test');
        expect(input().placeholder).toBe('mon-association');
    });

    it('revient a l exemple quand on efface la saisie', () => {
        render(
            <SubdomainField
                defaultValue="pilote"
                publicDomain="app.convive.test"
            />,
        );

        fireEvent.change(input(), { target: { value: '' } });

        expect(address().textContent).toBe('mon-association.app.convive.test');
    });

    it('reprend la valeur deja enregistree', () => {
        render(
            <SubdomainField
                defaultValue="pilote"
                publicDomain="app.convive.test"
            />,
        );

        expect(input().value).toBe('pilote');
        expect(address().textContent).toBe('pilote.app.convive.test');
    });

    it('affiche l erreur renvoyee par le serveur', () => {
        render(
            <SubdomainField
                defaultValue="www"
                publicDomain="app.convive.test"
                error="Ce sous-domaine est reserve."
            />,
        );

        expect(screen.getByText('Ce sous-domaine est reserve.')).toBeTruthy();
    });
});

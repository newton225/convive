import { render } from '@testing-library/react';
import { describe, expect, it, vi } from 'vite-plus/test';
import { RegistrationDetails } from '@/components/registrations/registration-details';
import type { RegistrationRow } from '@/types';

// La traduction rend la cle : le test porte sur ce que le composant choisit d'afficher.
vi.mock('@/hooks/use-translation', () => ({
    useTranslation: () => ({
        t: (key: string) => key,
        locale: 'fr',
    }),
}));

const registration = (people: RegistrationRow['people']): RegistrationRow =>
    ({ people }) as RegistrationRow;

const texts = (selector: string) =>
    Array.from(document.querySelectorAll(selector)).map(
        (element) => element.textContent,
    );

describe('RegistrationDetails', () => {
    it('liste l invite puis ses accompagnateurs', () => {
        render(
            <RegistrationDetails
                registration={registration([
                    {
                        name: 'Aya Kouassi',
                        unit: 'ELIAKIM',
                        isGuest: true,
                        priceCategory: { name: 'VIP', price: 15000 },
                        entered: true,
                    },
                    {
                        name: 'Kofi Kouassi',
                        unit: 'QODESH',
                        isGuest: false,
                        priceCategory: { name: 'Enfant', price: 0 },
                        entered: false,
                    },
                ])}
            />,
        );

        const people = document.querySelectorAll(
            '[data-test="registration-person"]',
        );

        expect(people).toHaveLength(2);
        expect(people[0].textContent).toContain('Aya Kouassi');
        expect(people[0].textContent).toContain('registrations.details.guest');
        expect(people[1].textContent).toContain('Kofi Kouassi');
        expect(people[1].textContent).toContain(
            'registrations.details.companion',
        );
    });

    it('dit gratuit pour un tarif a 0 et donne le prix sinon', () => {
        render(
            <RegistrationDetails
                registration={registration([
                    {
                        name: 'Aya',
                        unit: 'U',
                        isGuest: true,
                        priceCategory: { name: 'VIP', price: 15000 },
                        entered: null,
                    },
                    {
                        name: 'Kofi',
                        unit: 'U',
                        isGuest: false,
                        priceCategory: { name: 'Enfant', price: 0 },
                        entered: null,
                    },
                ])}
            />,
        );

        const tariffs = texts('[data-test="registration-person-tariff"]');

        expect(tariffs[0]).toMatch(/^VIP · 15\D000 F CFA$/);
        expect(tariffs[1]).toBe('Enfant · guest.registration.summary.free');
    });

    it('distingue entre, pas encore entre et billet pas encore emis', () => {
        render(
            <RegistrationDetails
                registration={registration(
                    [true, false, null].map((entered, index) => ({
                        name: `Personne ${index}`,
                        unit: 'U',
                        isGuest: index === 0,
                        priceCategory: null,
                        entered,
                    })),
                )}
            />,
        );

        expect(texts('[data-test="registration-person-entry"]')).toEqual([
            'registrations.details.entered',
            'registrations.details.not_entered',
            'registrations.details.no_ticket',
        ]);
        expect(texts('[data-test="registration-person-tariff"]')).toEqual([
            'registrations.details.no_tariff',
            'registrations.details.no_tariff',
            'registrations.details.no_tariff',
        ]);
    });
});

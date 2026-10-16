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

// L'animation d'ouverture n'est pas ce que ces tests verifient.
vi.mock('framer-motion', () => ({
    motion: {
        div: ({
            children,
            className,
            ...props
        }: {
            children: React.ReactNode;
            className?: string;
        } & Record<string, unknown>) => (
            <div
                className={className}
                data-test={props['data-test'] as string | undefined}
            >
                {children}
            </div>
        ),
    },
    useReducedMotion: () => false,
}));

const base: RegistrationRow = {
    id: 1,
    reference: 'OSP-2026-0001',
    name: 'Aya Kouassi',
    phone: '+2250707000000',
    unit: 'ELIAKIM',
    partySize: 2,
    amountDue: 15000,
    status: 'confirmed',
    statusLabel: 'Validée',
    purgeable: false,
    holdActive: false,
    tableNumber: 1,
    cancellationReason: null,
    channelLabel: 'Wave',
    receiptUrl: null,
    proofReference: 'TX-12345',
    proofSubmittedAt: '2026-10-14T10:00:00Z',
    enteredCount: 0,
    enteredAt: null,
    cardSentAt: null,
    people: [],
    card: null,
};

const registration = (
    overrides: Partial<RegistrationRow>,
): RegistrationRow => ({
    ...base,
    ...overrides,
});

const texts = (selector: string) =>
    Array.from(document.querySelectorAll(selector)).map(
        (element) => element.textContent,
    );

describe('RegistrationDetails', () => {
    it('liste l invite puis ses accompagnateurs', () => {
        render(
            <RegistrationDetails
                registration={registration({
                    people: [
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
                    ],
                })}
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
                registration={registration({
                    people: [
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
                    ],
                })}
            />,
        );

        const tariffs = texts('[data-test="registration-person-tariff"]');

        expect(tariffs[0]).toMatch(/^VIP · 15\D000 F CFA$/);
        expect(tariffs[1]).toBe('Enfant · guest.registration.summary.free');
    });

    it('distingue entre, pas encore entre et billet pas encore emis', () => {
        render(
            <RegistrationDetails
                registration={registration({
                    people: [true, false, null].map((entered, index) => ({
                        name: `Personne ${index}`,
                        unit: 'U',
                        isGuest: index === 0,
                        priceCategory: null,
                        entered,
                    })),
                })}
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

    it('montre le paiement : canal, reference et motif d annulation', () => {
        render(
            <RegistrationDetails
                registration={registration({
                    cancellationReason: 'Doublon',
                })}
            />,
        );

        const payment =
            document.querySelector('[data-test="registration-payment"]')
                ?.textContent ?? '';

        expect(payment).toContain('Wave');
        expect(payment).toContain('TX-12345');
        expect(payment).toContain('Doublon');
    });

    it('ne montre pas de motif d annulation quand il n y en a pas', () => {
        render(<RegistrationDetails registration={registration({})} />);

        expect(
            document.querySelector('[data-test="registration-cancellation"]'),
        ).toBeNull();
    });

    it('dit que la carte n est pas partie et que personne n est entre', () => {
        render(<RegistrationDetails registration={registration({})} />);

        const entry =
            document.querySelector('[data-test="registration-entry"]')
                ?.textContent ?? '';

        expect(entry).toContain('registrations.card.not_sent');
        expect(entry).toContain('registrations.entry.not_yet');
    });
});

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
    rejection: null,
    channelLabel: 'Wave',
    receiptUrl: null,
    proofReference: 'TX-12345',
    proofSubmittedAt: '2026-10-14T10:00:00Z',
    enteredCount: 0,
    enteredAt: null,
    cardSentAt: null,
    ticketDownloads: 0,
    ticketDownloadedAt: null,
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
    const guest = {
        name: 'Aya Kouassi',
        unit: 'ELIAKIM',
        isGuest: true,
        priceCategory: { name: 'VIP', price: 15000 },
        entered: true,
    };
    const companion = {
        name: 'Kofi Kouassi',
        unit: 'QODESH',
        isGuest: false,
        priceCategory: { name: 'Enfant', price: 0 },
        entered: false,
    };

    it('ne liste que les accompagnateurs, pas l invite deja sur la ligne', () => {
        render(
            <RegistrationDetails
                registration={registration({ people: [guest, companion] })}
            />,
        );

        const people = document.querySelectorAll(
            '[data-test="registration-person"]',
        );

        expect(people).toHaveLength(1);
        expect(people[0].textContent).toContain('Kofi Kouassi');
        expect(people[0].textContent).not.toContain('Aya Kouassi');
    });

    it('dit qu il n y a aucun accompagnateur quand l invite est seul', () => {
        render(
            <RegistrationDetails
                registration={registration({ people: [guest] })}
            />,
        );

        expect(
            document.querySelectorAll('[data-test="registration-person"]'),
        ).toHaveLength(0);
        expect(
            document.querySelector('[data-test="registration-no-companions"]')
                ?.textContent,
        ).toBe('registrations.details.no_companions');
    });

    it('dit gratuit pour un tarif a 0 et donne le prix sinon', () => {
        render(
            <RegistrationDetails
                registration={registration({
                    people: [
                        guest,
                        companion,
                        {
                            ...companion,
                            name: 'Awa',
                            priceCategory: { name: 'VIP', price: 15000 },
                        },
                    ],
                })}
            />,
        );

        const tariffs = texts('[data-test="registration-person-tariff"]');

        expect(tariffs[0]).toBe('Enfant · guest.registration.summary.free');
        expect(tariffs[1]).toMatch(/^VIP · 15\D000 F CFA$/);
    });

    it('distingue entre, pas encore entre et billet pas encore emis', () => {
        render(
            <RegistrationDetails
                registration={registration({
                    people: [guest, true, false, null].map((entered, index) =>
                        entered === guest
                            ? guest
                            : {
                                  name: `Personne ${index}`,
                                  unit: 'U',
                                  isGuest: false,
                                  priceCategory: null,
                                  entered: entered as boolean | null,
                              },
                    ),
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

    it('donne le motif du rejet de la preuve avec sa precision', () => {
        render(
            <RegistrationDetails
                registration={registration({
                    status: 'proof_rejected',
                    rejection: {
                        reason: 'Paiement fractionne',
                        note: 'Deux virements',
                    },
                })}
            />,
        );

        const rejection =
            document.querySelector('[data-test="registration-rejection"]')
                ?.textContent ?? '';

        expect(rejection).toContain('Paiement fractionne');
        expect(rejection).toContain('Deux virements');
    });

    it('ne montre aucun motif de rejet quand la preuve n est pas rejetee', () => {
        render(<RegistrationDetails registration={registration({})} />);

        expect(
            document.querySelector('[data-test="registration-rejection"]'),
        ).toBeNull();
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

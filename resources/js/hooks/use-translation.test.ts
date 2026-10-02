import { describe, expect, it } from 'vite-plus/test';
import { translate } from '@/hooks/use-translation';

const translations = {
    common: {
        actions: { save: 'Enregistrer' },
    },
    tenants: {
        flash: { created: 'Organisation :name créée.' },
    },
    console: {
        health: {
            pending:
                '{0} Aucun envoi en attente.|{1} 1 envoi en attente.|[2,*] :count envois en attente.',
            migrations: '{1} 1 migration|[2,*] :count migrations',
        },
    },
    guest: {
        seats: 'place restante|places restantes',
    },
    audit: {
        types: {
            'organisation.legal_updated': 'Identité légale',
            'event.created': 'Événement créé',
        },
    },
};

describe('translate', () => {
    it('rend le texte de la clé pointée', () => {
        expect(translate(translations, 'common.actions.save')).toBe(
            'Enregistrer',
        );
    });

    it('rend la clé elle-même quand elle est absente, pour que le trou se voie', () => {
        expect(translate(translations, 'common.actions.inconnue')).toBe(
            'common.actions.inconnue',
        );
    });

    it('rend la clé quand elle désigne un groupe et non un texte', () => {
        expect(translate(translations, 'common.actions')).toBe(
            'common.actions',
        );
    });

    it('trouve un texte rangé sous une clé qui contient elle-même un point', () => {
        // Le journal range ses textes sous le nom de l'action.
        expect(
            translate(translations, 'audit.types.organisation.legal_updated'),
        ).toBe('Identité légale');
        expect(translate(translations, 'audit.types.event.created')).toBe(
            'Événement créé',
        );
        expect(translate(translations, 'audit.types.event.inconnue')).toBe(
            'audit.types.event.inconnue',
        );
    });

    it('remplace les paramètres nommés', () => {
        expect(
            translate(translations, 'tenants.flash.created', {
                name: 'Chorale Sainte-Cécile',
            }),
        ).toBe('Organisation Chorale Sainte-Cécile créée.');
    });

    it('choisit la forme exacte pour zéro et pour un', () => {
        expect(
            translate(translations, 'console.health.pending', { count: 0 }),
        ).toBe('Aucun envoi en attente.');
        expect(
            translate(translations, 'console.health.pending', { count: 1 }),
        ).toBe('1 envoi en attente.');
    });

    it('choisit la forme de la plage et y écrit le nombre', () => {
        expect(
            translate(translations, 'console.health.pending', { count: 7 }),
        ).toBe('7 envois en attente.');
    });

    it('choisit entre singulier et pluriel sans bornes écrites', () => {
        expect(translate(translations, 'guest.seats', { count: 1 })).toBe(
            'place restante',
        );
        expect(translate(translations, 'guest.seats', { count: 3 })).toBe(
            'places restantes',
        );
    });

    it('retombe sur la dernière forme quand aucune borne ne correspond', () => {
        // Zéro n'est couvert ni par `{1}` ni par `[2,*]`.
        expect(
            translate(translations, 'console.health.migrations', { count: 0 }),
        ).toBe('0 migrations');
    });
});

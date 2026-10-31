import { formatDateTime } from '@/lib/format-date';
import type { LocaleCode } from '@/types';
import type { TranslationReplacements } from '@/types/i18n';

type Translate = (
    key: string,
    replacements?: TranslationReplacements,
) => string;

type CardFacts = {
    cardSentAt: string | null;
    cardFailedAt: string | null;
    cardChannels: string[];
};

export type CardStatus = {
    state: 'failed' | 'sent' | 'not_sent';
    label: string;
};

/**
 * Ou en est la carte d'invitation d'un dossier (decision du 2026-10-31) : refusee par le service d'envoi,
 * partie (avec les canaux), ou pas encore envoyee. La date d'envoi dit seulement que le message est parti
 * en file : un refus ulterieur du service l'emporte.
 */
export function cardStatus(
    registration: CardFacts,
    t: Translate,
    locale: LocaleCode,
): CardStatus {
    if (registration.cardFailedAt) {
        return {
            state: 'failed',
            label: t('registrations.card.failed', {
                date: formatDateTime(registration.cardFailedAt, locale),
            }),
        };
    }

    if (registration.cardSentAt) {
        return {
            state: 'sent',
            label: t('registrations.card.sent_via', {
                date: formatDateTime(registration.cardSentAt, locale),
                channels: registration.cardChannels
                    .map((channel) =>
                        t(`registrations.card.channels.${channel}`),
                    )
                    .join(' + '),
            }),
        };
    }

    return { state: 'not_sent', label: t('registrations.card.not_sent') };
}

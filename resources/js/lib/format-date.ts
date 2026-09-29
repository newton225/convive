import { format, formatDistanceToNow, type Locale } from 'date-fns';
import { enUS, fr } from 'date-fns/locale';
import type { LocaleCode } from '@/types';

/**
 * Locale date-fns pour la langue de l'interface. C'est elle qui pilote les dates, jamais
 * `toLocaleString()` du navigateur : la langue affichee doit suivre le choix explicite de
 * l'utilisateur (CLAUDE.md, section internationalisation), pas les reglages de son appareil.
 */
const locales: Record<LocaleCode, Locale> = { fr, en: enUS };

export function formatDateTime(value: string, locale: LocaleCode): string {
    return format(new Date(value), 'PPPp', { locale: locales[locale] });
}

export function formatDate(value: string, locale: LocaleCode): string {
    return format(new Date(value), 'PPP', { locale: locales[locale] });
}

// « il y a 38 minutes » : l'anciennete d'un depot se lit mieux ainsi qu'en date absolue.
export function formatRelative(value: string, locale: LocaleCode): string {
    return formatDistanceToNow(new Date(value), {
        addSuffix: true,
        locale: locales[locale],
    });
}

// Les morceaux d'une date pour une vignette de calendrier : « 14 », « nov. », « samedi », « 19:00 ».
export function formatDateParts(
    value: string,
    locale: LocaleCode,
): { day: string; month: string; weekday: string; time: string } {
    const date = new Date(value);
    const options = { locale: locales[locale] };

    return {
        day: format(date, 'd', options),
        month: format(date, 'MMM', options),
        weekday: format(date, 'EEEE', options),
        time: format(date, 'p', options),
    };
}

export type LocaleCode = 'fr' | 'en';

export type SupportedLocales = Record<LocaleCode, string>;

export type Translations = Record<string, unknown>;

export type TranslationReplacements = Record<string, string | number>;

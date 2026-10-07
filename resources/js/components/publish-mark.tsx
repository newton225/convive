import { useTranslation } from '@/hooks/use-translation';

/**
 * La marque d'un champ exige pour publier, mais pas pour enregistrer (identite legale, decision du
 * 2026-10-07) : distincte de l'asterisque, qui dit qu'un envoi sera refuse sans le champ.
 */
export function PublishMark() {
    const { t } = useTranslation();

    return (
        <span className="text-muted-foreground ml-1.5 text-xs font-normal">
            ({t('common.for_publishing')})
        </span>
    );
}

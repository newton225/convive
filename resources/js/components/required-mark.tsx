import { useTranslation } from '@/hooks/use-translation';

/**
 * L'asterisque d'un champ obligatoire. Le signe est cache aux lecteurs d'ecran, qui lisent le mot
 * « obligatoire » a sa place : l'information ne passe pas par la couleur seule.
 */
export function RequiredMark() {
    const { t } = useTranslation();

    return (
        <>
            <span aria-hidden="true" className="text-destructive ml-0.5">
                *
            </span>
            <span className="sr-only"> ({t('common.required')})</span>
        </>
    );
}

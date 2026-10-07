import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

type Props = {
    className?: string;
};

/**
 * La legende des asterisques, en tete d'un formulaire qui a au moins un champ obligatoire.
 */
export function RequiredFieldsNote({ className }: Props) {
    const { t } = useTranslation();

    return (
        <p
            className={cn('text-muted-foreground text-xs', className)}
            data-test="required-fields-note"
        >
            {t('common.required_note')}
        </p>
    );
}

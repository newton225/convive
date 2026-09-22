import { Check, Copy } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

type Props = {
    value: string;
    label?: string;
    variant?: React.ComponentProps<typeof Button>['variant'];
    size?: React.ComponentProps<typeof Button>['size'];
    className?: string;
    testId?: string;
};

/**
 * Un bouton « copier » reutilisable : icone qui devient une coche le temps de confirmer, jamais
 * la seule couleur pour porter l'etat (le libelle change aussi). Le presse-papiers peut etre
 * refuse par le navigateur (contexte non securise, permission bloquee) : la valeur reste alors
 * affichee et selectionnable a la main, copier n'est jamais le seul moyen de la recuperer.
 */
export function CopyButton({
    value,
    label,
    variant = 'outline',
    size = 'sm',
    className,
    testId,
}: Props) {
    const { t } = useTranslation();
    const [copied, setCopied] = useState(false);

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(value);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch {
            // Presse-papiers indisponible : rien a faire, la valeur reste lisible a l'ecran.
        }
    };

    return (
        <Button
            type="button"
            variant={variant}
            size={size}
            className={cn('min-h-11', className)}
            onClick={() => void copy()}
            data-test={testId ?? 'copy-button'}
        >
            {copied ? <Check /> : <Copy />}
            {copied
                ? t('common.actions.copied')
                : (label ?? t('common.actions.copy'))}
        </Button>
    );
}

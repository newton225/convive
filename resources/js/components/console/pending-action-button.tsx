import type { LucideIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useTranslation } from '@/hooks/use-translation';

type Props = {
    label: string;
    icon?: LucideIcon;
    variant?: 'default' | 'outline' | 'destructive' | 'secondary';
};

/**
 * Une action de la console dont le serveur n'existe pas encore (CLAUDE.md, « Ordre de travail ») :
 * visible pour juger l'ecran, desactivee pour ne rien promettre, et l'infobulle dit pourquoi. Le
 * bouton desactive ne recoit pas le survol : l'enveloppe le recoit a sa place.
 */
export function PendingActionButton({
    label,
    icon: Icon,
    variant = 'outline',
}: Props) {
    const { t } = useTranslation();

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <span tabIndex={0} className="inline-flex">
                    <Button variant={variant} size="sm" disabled>
                        {Icon && <Icon />}
                        {label}
                    </Button>
                </span>
            </TooltipTrigger>
            <TooltipContent>{t('console.pending_action')}</TooltipContent>
        </Tooltip>
    );
}

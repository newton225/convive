import { CircleHelp } from 'lucide-react';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { useTranslation } from '@/hooks/use-translation';

type Props = {
    // Ce que la bulle explique (le libelle du champ ou de la section) : nom accessible du bouton.
    subject: string;
    children: React.ReactNode;
};

/**
 * Un bouton « ? » a cote d'un libelle qui ne s'explique pas de lui-meme (purge, reservation,
 * delai d'activation...). Une bulle au clic plutot qu'au survol : elle s'ouvre aussi au toucher
 * sur telephone et au clavier, et se ferme avec Echap. Le bouton reste petit a l'oeil, sa zone
 * tactile est elargie a 44 px (CLAUDE.md, « Design et experience utilisateur »).
 */
export function HelpTip({ subject, children }: Props) {
    const { t } = useTranslation();

    return (
        <Popover>
            <PopoverTrigger
                type="button"
                aria-label={t('common.help.about', { subject })}
                className="text-muted-foreground hover:text-foreground focus-visible:ring-ring relative inline-flex size-5 shrink-0 items-center justify-center rounded-full outline-none after:absolute after:-inset-3 focus-visible:ring-2"
            >
                <CircleHelp className="size-4" />
            </PopoverTrigger>
            <PopoverContent
                side="top"
                className="w-auto max-w-xs text-sm leading-relaxed"
            >
                {children}
            </PopoverContent>
        </Popover>
    );
}

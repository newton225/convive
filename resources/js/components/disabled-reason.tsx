import type { ReactNode } from 'react';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';

/**
 * Dit pourquoi un bouton grise ne reagit pas. Un bouton desactive ne recoit ni survol ni focus :
 * c'est l'enveloppe qui porte l'infobulle, et elle se lit aussi au clavier. Sans raison, le bouton
 * reste tel quel.
 */
export function DisabledReason({
    reason,
    children,
}: {
    reason: string | null;
    children: ReactNode;
}) {
    if (reason === null) {
        return <>{children}</>;
    }

    return (
        <TooltipProvider>
            <Tooltip>
                <TooltipTrigger asChild>
                    <span
                        tabIndex={0}
                        className="inline-flex"
                        data-test="disabled-reason"
                    >
                        {children}
                    </span>
                </TooltipTrigger>
                <TooltipContent className="max-w-xs">{reason}</TooltipContent>
            </Tooltip>
        </TooltipProvider>
    );
}

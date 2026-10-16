import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { RegistrationShowStatus } from '@/types';

/**
 * Une couleur par etat du dossier, pour le reperer d'un coup d'oeil dans la base d'inscrits : vert
 * confirme, bleu a verifier, ambre en attente de paiement, rouge preuve rejetee (le seul qui demande
 * une action de l'invite), gris pour ce qui est clos (annule, expire) ou pas commence (brouillon). Le
 * libelle reste ecrit : la couleur ne porte jamais seule l'information.
 */
const tones: Record<RegistrationShowStatus, string> = {
    confirmed:
        'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-300',
    proof_submitted:
        'bg-sky-100 text-sky-800 dark:bg-sky-500/20 dark:text-sky-300',
    held: 'bg-amber-100 text-amber-900 dark:bg-amber-500/20 dark:text-amber-300',
    proof_rejected:
        'bg-red-100 text-red-800 dark:bg-red-500/25 dark:text-red-300',
    cancelled:
        'bg-zinc-200 text-zinc-700 dark:bg-zinc-500/25 dark:text-zinc-300',
    expired: 'bg-zinc-100 text-zinc-600 dark:bg-zinc-500/15 dark:text-zinc-400',
    draft: 'bg-zinc-100 text-zinc-600 dark:bg-zinc-500/15 dark:text-zinc-400',
};

export function RegistrationStatusBadge({
    status,
    label,
}: {
    status: RegistrationShowStatus;
    label: string;
}) {
    return (
        <Badge
            variant="secondary"
            className={cn('border-transparent', tones[status])}
            data-status={status}
        >
            {label}
        </Badge>
    );
}

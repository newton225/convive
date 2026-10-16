/**
 * Les couleurs des badges d'etat, une par sens : vert ce qui est acquis, bleu ce qui est en cours de
 * traitement, ambre ce qui attend, rouge ce qui demande une action ou a echoue, gris ce qui est clos
 * ou pas commence, indigo une mention neutre (annonce). Le libelle reste toujours ecrit : la couleur
 * ne porte jamais seule l'information.
 */
export type StatusTone =
    | 'success'
    | 'info'
    | 'warning'
    | 'danger'
    | 'neutral'
    | 'muted'
    | 'accent';

export const statusToneClasses: Record<StatusTone, string> = {
    success:
        'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-300',
    info: 'bg-sky-100 text-sky-800 dark:bg-sky-500/20 dark:text-sky-300',
    warning:
        'bg-amber-100 text-amber-900 dark:bg-amber-500/20 dark:text-amber-300',
    danger: 'bg-red-100 text-red-800 dark:bg-red-500/25 dark:text-red-300',
    neutral: 'bg-zinc-200 text-zinc-700 dark:bg-zinc-500/25 dark:text-zinc-300',
    muted: 'bg-zinc-100 text-zinc-600 dark:bg-zinc-500/15 dark:text-zinc-400',
    accent: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-500/20 dark:text-indigo-300',
};

export const eventStatusTones: Record<string, StatusTone> = {
    draft: 'muted',
    open: 'success',
    ongoing: 'info',
    closed: 'neutral',
};

export const subscriptionStatusTones: Record<string, StatusTone> = {
    active: 'success',
    past_due: 'warning',
    suspended: 'danger',
    canceled: 'neutral',
};

export const invoiceStatusTones: Record<string, StatusTone> = {
    paid: 'success',
    open: 'info',
    failed: 'danger',
    void: 'neutral',
};

export const registrationStatusTones: Record<string, StatusTone> = {
    confirmed: 'success',
    proof_submitted: 'info',
    held: 'warning',
    proof_rejected: 'danger',
    cancelled: 'neutral',
    expired: 'muted',
    draft: 'muted',
};

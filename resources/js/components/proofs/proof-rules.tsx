import { ShieldAlert } from 'lucide-react';
import { useTranslation } from '@/hooks/use-translation';

/**
 * Ce que le tresorier controle avant de valider ou de rejeter une preuve : la capture doit etre
 * l'originale, le montant complet et paye en une fois, frais de transaction compris. Ce sont les
 * regles annoncees a l'invite sur son formulaire (`guest.proof.help`) : l'application ne les verifie
 * pas seule, c'est ici que le tresorier les applique.
 */
export function ProofRules() {
    const { t } = useTranslation();

    return (
        <div
            className="bg-muted/60 space-y-2 rounded-md p-3 text-sm"
            data-test="proof-rules"
        >
            <p className="flex items-center gap-2 font-medium">
                <ShieldAlert className="size-4 shrink-0" />
                {t('proofs.rules.title')}
            </p>
            <ul className="text-muted-foreground list-disc space-y-1 pl-6">
                <li>{t('proofs.rules.original')}</li>
                <li>{t('proofs.rules.complete')}</li>
            </ul>
        </div>
    );
}

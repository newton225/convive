import { Download } from 'lucide-react';
import { SubmitButton } from '@/components/submit-button';
import { useFileDownload } from '@/hooks/use-file-download';
import { useTranslation } from '@/hooks/use-translation';

type Props = {
    url: string;
    // Plusieurs billets (tout le groupe) ou un seul : le libelle le dit.
    all?: boolean;
};

/**
 * Telecharger ses billets en PDF (README 2.8) : a garder sur le telephone, il s'ouvre meme sans
 * connexion au moment du controle a l'entree. Le serveur compose le document a la demande, ce qui
 * prend quelques secondes : le bouton passe en attente (roue, libelle « Preparation ») et ne se
 * laisse pas cliquer deux fois, voir `useFileDownload`. Pas une visite Inertia, qui ne sait pas
 * afficher un fichier.
 */
export function TicketPdfDownload({ url, all = false }: Props) {
    const { t } = useTranslation();
    const { downloading, download } = useFileDownload();

    return (
        <div className="space-y-1.5 text-center" data-test="ticket-pdf">
            <SubmitButton
                type="button"
                variant="outline"
                className="min-h-11 w-full"
                processing={downloading}
                onClick={() => void download(url)}
            >
                {downloading ? null : <Download />}
                {t(
                    downloading
                        ? 'guest.ticket_pdf.preparing'
                        : all
                          ? 'guest.ticket_pdf.download_all'
                          : 'guest.ticket_pdf.download',
                )}
            </SubmitButton>
            <p className="text-muted-foreground text-xs">
                {t('guest.ticket_pdf.download_hint')}
            </p>
        </div>
    );
}

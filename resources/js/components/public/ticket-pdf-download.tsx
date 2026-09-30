import { Download } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';

type Props = {
    url: string;
    // Plusieurs billets (tout le groupe) ou un seul : le libelle le dit.
    all?: boolean;
};

/**
 * Telecharger ses billets en PDF (README 2.8) : a garder sur le telephone, il s'ouvre meme sans
 * connexion au moment du controle a l'entree. Un simple lien de telechargement, pas une visite
 * Inertia, qui ne sait pas afficher un fichier.
 */
export function TicketPdfDownload({ url, all = false }: Props) {
    const { t } = useTranslation();

    return (
        <div className="space-y-1.5 text-center" data-test="ticket-pdf">
            <Button asChild variant="outline" className="min-h-11 w-full">
                <a href={url} download>
                    <Download />
                    {t(
                        all
                            ? 'guest.ticket_pdf.download_all'
                            : 'guest.ticket_pdf.download',
                    )}
                </a>
            </Button>
            <p className="text-muted-foreground text-xs">
                {t('guest.ticket_pdf.download_hint')}
            </p>
        </div>
    );
}

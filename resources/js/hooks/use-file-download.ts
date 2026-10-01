import { router } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import { useTranslation } from '@/hooks/use-translation';

// Le nom propose par le serveur (`Content-Disposition`), dans sa forme encodee si elle existe.
function filenameFrom(header: string | null): string | null {
    if (header === null) {
        return null;
    }

    const encoded = /filename\*=UTF-8''([^;]+)/i.exec(header);

    if (encoded) {
        try {
            return decodeURIComponent(encoded[1]);
        } catch {
            // Encodage invalide : on se rabat sur la forme simple.
        }
    }

    return /filename="?([^";]+)"?/i.exec(header)?.[1] ?? null;
}

/**
 * Telecharger un fichier produit a la demande (export, liste de controle) en gardant la main sur
 * l'attente : un lien direct ne dit rien pendant que le serveur compose le document, et laisse
 * cliquer une seconde fois (CLAUDE.md, « Retour immediat sur chaque action »). Le fichier est lu
 * puis remis au navigateur sous le nom que le serveur lui donne.
 *
 * Une redirection n'est pas suivie : le serveur renvoie ainsi vers la page avec son explication
 * (limite d'exports atteinte, session expiree). On recharge la page, qui l'affiche.
 */
export function useFileDownload(): {
    downloading: boolean;
    download: (url: string) => Promise<void>;
} {
    const { t } = useTranslation();
    const [downloading, setDownloading] = useState(false);

    const download = async (url: string) => {
        setDownloading(true);

        try {
            const response = await fetch(url, {
                credentials: 'same-origin',
                redirect: 'manual',
            });

            if (response.type === 'opaqueredirect') {
                router.reload();

                return;
            }

            const filename = filenameFrom(
                response.headers.get('Content-Disposition'),
            );

            if (!response.ok || filename === null) {
                toast.error(t('common.feedback.unexpected'));

                return;
            }

            const objectUrl = URL.createObjectURL(await response.blob());
            const link = document.createElement('a');
            link.href = objectUrl;
            link.download = filename;
            document.body.append(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(objectUrl);
        } catch {
            toast.error(t('common.feedback.network_error'));
        } finally {
            setDownloading(false);
        }
    };

    return { downloading, download };
}

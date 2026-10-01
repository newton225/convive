import { SubmitButton } from '@/components/submit-button';
import type { Button } from '@/components/ui/button';
import { useFileDownload } from '@/hooks/use-file-download';

type Props = Omit<
    React.ComponentProps<typeof Button>,
    'onClick' | 'type' | 'disabled'
> & {
    href: string;
};

/**
 * Bouton de telechargement d'un fichier produit a la demande : roue d'attente et bouton inactif
 * tant que le serveur compose le document (voir `useFileDownload`).
 */
export function DownloadButton({ href, children, ...props }: Props) {
    const { downloading, download } = useFileDownload();

    return (
        <SubmitButton
            type="button"
            processing={downloading}
            onClick={() => void download(href)}
            {...props}
        >
            {children}
        </SubmitButton>
    );
}

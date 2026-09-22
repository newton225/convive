import { MessageCircle } from 'lucide-react';
import { CopyButton } from '@/components/copy-button';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import { formatDateTime } from '@/lib/format-date';

type Props = {
    deadline?: string | null;
};

/**
 * README ecran 8 : le lien de reprise. Une inscription enregistree sans preuve se reprend par ce
 * lien, depuis n'importe quel appareil, jusqu'a l'echeance : l'invite peut le copier ou se
 * l'envoyer sur WhatsApp (le canal systematique du produit). C'est l'adresse de la page en cours,
 * qui porte deja le jeton de reprise : rien de plus a fabriquer ni a exposer.
 */
export function ResumeLinkCard({ deadline = null }: Props) {
    const { t, locale } = useTranslation();

    const link = typeof window === 'undefined' ? '' : window.location.href;

    return (
        <Card data-test="resume-link-card">
            <CardContent className="space-y-3 pt-6">
                <div className="space-y-1">
                    <p className="font-medium">
                        {t('guest.resume_link.title')}
                    </p>
                    <p className="text-muted-foreground text-sm">
                        {t('guest.resume_link.description')}
                    </p>
                    {deadline ? (
                        <p className="text-sm">
                            {t('guest.resume_link.deadline', {
                                date: formatDateTime(deadline, locale),
                            })}
                        </p>
                    ) : null}
                </div>

                <p className="bg-muted rounded-md p-2 font-mono text-xs break-all select-all">
                    {link}
                </p>

                <div className="flex flex-wrap gap-2">
                    <CopyButton
                        value={link}
                        label={t('guest.resume_link.copy')}
                        testId="resume-link-copy"
                    />
                    <Button variant="outline" className="min-h-11" asChild>
                        <a
                            href={`https://wa.me/?text=${encodeURIComponent(link)}`}
                            target="_blank"
                            rel="noopener noreferrer"
                            data-test="resume-link-share"
                        >
                            <MessageCircle />
                            {t('guest.resume_link.share')}
                        </a>
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}

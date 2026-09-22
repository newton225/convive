import { Download } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useInstallPrompt } from '@/hooks/use-install-prompt';
import { useTranslation } from '@/hooks/use-translation';

/**
 * L'invitation a installer Convive sur l'ecran d'accueil : utile surtout a l'agent d'accueil (le
 * scan doit rester disponible avec un reseau faible) et a l'invite qui garde son billet. N'apparait
 * que si le navigateur sait installer l'application, et jamais deux fois dans la meme session.
 */
export function InstallPrompt() {
    const { t } = useTranslation();
    const { canInstall, install, dismiss } = useInstallPrompt();

    if (!canInstall) {
        return null;
    }

    return (
        <div
            className="bg-card flex flex-wrap items-center justify-between gap-3 rounded-xl p-4"
            data-test="install-prompt"
        >
            <div className="flex items-start gap-3 text-sm">
                <Download className="mt-0.5 size-4 shrink-0" />
                <div>
                    <p className="font-medium">{t('common.install.title')}</p>
                    <p className="text-muted-foreground">
                        {t('common.install.description')}
                    </p>
                </div>
            </div>
            <div className="flex gap-2">
                <Button size="sm" variant="ghost" onClick={dismiss}>
                    {t('common.install.dismiss')}
                </Button>
                <Button size="sm" onClick={() => void install()}>
                    {t('common.install.action')}
                </Button>
            </div>
        </div>
    );
}

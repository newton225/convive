import { Link } from '@inertiajs/react';
import { ArrowLeft, CircleCheck, ShieldAlert } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import type { TwoFactorDetour } from '@/types';

type Props = {
    detour: TwoFactorDetour;
    twoFactorEnabled: boolean;
    // Descend jusqu'a la zone de la double authentification et l'encadre de nouveau.
    onShowZone: () => void;
};

/**
 * En tete de l'ecran de securite quand on y a ete envoye pour activer la double authentification :
 * pourquoi, quoi faire, puis le chemin du retour une fois que c'est fait.
 */
export function TwoFactorDetourNotice({
    detour,
    twoFactorEnabled,
    onShowZone,
}: Props) {
    const { t } = useTranslation();
    const reason = `account.two_factor_detour.reasons.${detour.reason}`;

    if (twoFactorEnabled) {
        return (
            <Alert data-test="two-factor-detour-done">
                <CircleCheck />
                <AlertTitle>{t('account.two_factor_detour.done')}</AlertTitle>
                <AlertDescription>
                    <Button asChild size="sm" className="mt-2">
                        <Link href={detour.returnUrl}>
                            <ArrowLeft />
                            {t(`${reason}.back`)}
                        </Link>
                    </Button>
                </AlertDescription>
            </Alert>
        );
    }

    return (
        <Alert data-test="two-factor-detour">
            <ShieldAlert />
            <AlertTitle>{t(`${reason}.title`)}</AlertTitle>
            <AlertDescription>
                <p>{t(`${reason}.why`)}</p>
                <ol className="list-decimal space-y-1 pl-5">
                    <li>
                        {t('account.two_factor_detour.steps.enable')}{' '}
                        <button
                            type="button"
                            onClick={onShowZone}
                            className="font-medium underline underline-offset-4"
                            data-test="two-factor-detour-show-zone"
                        >
                            {t('account.two_factor_detour.show_zone')}
                        </button>
                    </li>
                    <li>{t('account.two_factor_detour.steps.scan')}</li>
                    <li>{t('account.two_factor_detour.steps.back')}</li>
                </ol>
            </AlertDescription>
        </Alert>
    );
}

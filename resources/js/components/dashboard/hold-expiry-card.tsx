import { TimerOff } from 'lucide-react';
import { HelpTip } from '@/components/help-tip';
import { Card, CardContent } from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import type { DashboardHoldExpiry } from '@/types';

type Props = {
    expiry: DashboardHoldExpiry;
};

/**
 * Part des reservations expirees sans preuve (SECURITY.md C3). Un taux qui grimpe d'un coup est ce
 * a quoi ressemble, vu de l'organisateur, un blocage automatise des places : la carte le rend
 * visible, le chiffre est ecrit, jamais porte par une couleur.
 */
export function HoldExpiryCard({ expiry }: Props) {
    const { t } = useTranslation();

    return (
        <Card data-test="dashboard-hold-expiry">
            <CardContent className="flex items-start gap-3">
                <TimerOff className="text-muted-foreground mt-1 size-4 shrink-0" />
                <div className="min-w-0 flex-1 space-y-1 text-sm">
                    <p className="font-medium">
                        {expiry.rate === null
                            ? t('dashboard.hold_expiry.none')
                            : t('dashboard.hold_expiry.rate', {
                                  rate: String(expiry.rate),
                                  lapsed: String(expiry.lapsed),
                                  holds: String(expiry.holds),
                              })}
                    </p>
                    <p className="text-muted-foreground">
                        {t('dashboard.hold_expiry.help')}
                    </p>
                </div>
                <HelpTip subject={t('dashboard.hold_expiry.title')}>
                    {t('dashboard.help.hold_expiry')}
                </HelpTip>
            </CardContent>
        </Card>
    );
}

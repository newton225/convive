import { Head, router } from '@inertiajs/react';
import { OfflineBanner } from '@/components/offline-banner';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { formatCountdown, useCountdown } from '@/hooks/use-countdown';
import { useTranslation } from '@/hooks/use-translation';
import { finalize } from '@/routes/public/waitlist';
import type { WaitlistEntryShow } from '@/types';

type Props = {
    event: { name: string };
    entry: WaitlistEntryShow;
    token: string;
    resume: string;
};

/**
 * README ecran 10 : position dans la file en attendant, lien de six heures des qu'invite
 * (2.3). Le decompte reutilise le meme mecanisme que la reservation (voir
 * `resources/js/hooks/use-countdown.ts`).
 */
export default function PublicWaitlistShow({
    event,
    entry,
    token,
    resume,
}: Props) {
    const { t } = useTranslation();
    const { remainingSeconds, hasExpired } = useCountdown(
        entry.status === 'invited' ? entry.expiresAt : null,
    );

    return (
        <div className="bg-background flex min-h-screen flex-col items-center p-4">
            <Head title={t('guest.waitlist.title')} />
            <OfflineBanner />

            <main className="w-full max-w-lg space-y-6 pt-12">
                <div className="space-y-1 text-center">
                    <h1 className="text-2xl font-semibold">{event.name}</h1>
                    <p className="text-muted-foreground text-sm">
                        {entry.name}
                    </p>
                </div>

                <Card>
                    <CardContent className="space-y-4 pt-6 text-center">
                        {entry.status === 'waiting' ? (
                            <>
                                <p className="text-muted-foreground text-sm">
                                    {t('guest.waitlist.show.position_label')}
                                </p>
                                <p
                                    className="text-3xl font-semibold"
                                    data-test="waitlist-position"
                                >
                                    {entry.position}
                                </p>
                            </>
                        ) : null}

                        {entry.status === 'invited' && !hasExpired ? (
                            <>
                                <p className="font-medium">
                                    {t('guest.waitlist.show.invited')}
                                </p>
                                <p
                                    className="text-2xl font-semibold tabular-nums"
                                    data-test="waitlist-countdown"
                                >
                                    {formatCountdown(remainingSeconds)}
                                </p>
                                <Button
                                    data-test="waitlist-finalize"
                                    onClick={() =>
                                        router.post(
                                            finalize([token, resume]).url,
                                        )
                                    }
                                >
                                    {t('guest.waitlist.show.finalize')}
                                </Button>
                            </>
                        ) : null}

                        {entry.status === 'expired' ||
                        (entry.status === 'invited' && hasExpired) ? (
                            <p className="font-medium">
                                {t('guest.waitlist.show.expired')}
                            </p>
                        ) : null}

                        {entry.status === 'converted' ? (
                            <p className="font-medium">
                                {t('guest.waitlist.show.converted')}
                            </p>
                        ) : null}
                    </CardContent>
                </Card>
            </main>
        </div>
    );
}

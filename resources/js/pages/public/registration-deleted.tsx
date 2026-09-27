import { Head, Link } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { BrandColorStyle } from '@/components/brand-color-style';
import LocaleSwitcher from '@/components/locale-switcher';
import { OfflineBanner } from '@/components/offline-banner';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import { show } from '@/routes/public/events';
import { create } from '@/routes/public/registrations';
import { create as createWaitlistEntry } from '@/routes/public/waitlist';

type Props = {
    token: string;
    event: { name: string; acceptsRegistrations: boolean; isFull: boolean };
    tenant: {
        name: string;
        displayName: string;
        colors: { primary: string; secondary: string };
        logoUrl: string | null;
    };
};

/**
 * README ecran 11 : « Inscription supprimee », apres une purge a l'echeance ou a l'epuisement des
 * places (README 2.4). Deliberement generique : elle ne dit rien d'une inscription precise. Elle
 * propose la suite logique : s'inscrire de nouveau si l'evenement accepte encore du monde, la liste
 * d'attente s'il est complet, sinon revenir a la page de l'evenement. Mobile d'abord, aux couleurs
 * de l'organisation (les couleurs arrivent validees cote serveur, elles entrent sans risque dans
 * une variable CSS).
 */
export default function RegistrationDeleted({ token, event, tenant }: Props) {
    const { t } = useTranslation();

    return (
        <div className="bg-background flex min-h-screen flex-col">
            <BrandColorStyle colors={tenant.colors} />
            <Head title={t('guest.deleted.title')} />
            <OfflineBanner />

            <header className="flex items-center justify-between p-4">
                <div className="flex items-center gap-2">
                    {tenant.logoUrl ? (
                        <img
                            src={tenant.logoUrl}
                            alt={tenant.displayName}
                            className="h-8 w-8 rounded object-contain"
                        />
                    ) : null}
                    <span className="text-sm font-medium">
                        {tenant.displayName}
                    </span>
                </div>
                <LocaleSwitcher />
            </header>

            <main className="mx-auto w-full max-w-lg flex-1 space-y-6 px-4 pt-8 pb-12">
                <Card>
                    <CardContent className="space-y-4 pt-6 text-center">
                        <Trash2 className="text-muted-foreground mx-auto size-8" />
                        <h1
                            className="text-xl font-semibold"
                            data-test="registration-deleted"
                        >
                            {t('guest.deleted.title')}
                        </h1>
                        <p className="text-muted-foreground text-sm">
                            {t('guest.deleted.description')}
                        </p>
                        <p className="text-sm font-medium">{event.name}</p>

                        <div className="flex flex-col gap-2">
                            {event.acceptsRegistrations ? (
                                <Button
                                    className="min-h-11 bg-[color:var(--brand-primary)] text-white hover:bg-[color:var(--brand-primary)]/90"
                                    asChild
                                >
                                    <Link
                                        href={create(token)}
                                        data-test="deleted-register"
                                    >
                                        {t('guest.deleted.register')}
                                    </Link>
                                </Button>
                            ) : event.isFull ? (
                                <Button className="min-h-11" asChild>
                                    <Link
                                        href={createWaitlistEntry(token)}
                                        data-test="deleted-waitlist"
                                    >
                                        {t('guest.deleted.waitlist')}
                                    </Link>
                                </Button>
                            ) : null}
                            <Button
                                variant="ghost"
                                className="min-h-11"
                                asChild
                            >
                                <Link
                                    href={show(token)}
                                    data-test="deleted-back"
                                >
                                    {t('guest.deleted.back')}
                                </Link>
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </main>
        </div>
    );
}

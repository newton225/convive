import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, MailCheck } from 'lucide-react';
import { useState } from 'react';
import { ConfirmActionDialog } from '@/components/confirm-action-dialog';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { translate, useTranslation } from '@/hooks/use-translation';
import { login, logout } from '@/routes';
import { accept, decline, forget } from '@/routes/invitations';
import { index as tenantsIndex } from '@/routes/tenants';
import type {
    InvitationHomeItem,
    InvitationMismatch,
    Translations,
} from '@/types';

type Props = {
    invitations: InvitationHomeItem[];
    mismatch: InvitationMismatch | null;
    hasOrganisation: boolean;
    dashboardUrl: string | null;
};

/**
 * L'accueil des invitations (TODO du 2026-10-07) : la personne accepte ou refuse explicitement,
 * rien n'est rattache sans son clic. Une invitation suivie depuis un courriel mais adressee a une
 * autre adresse est expliquee, avec ce qu'il reste a faire, plutot que d'aboutir a une page vide.
 */
export default function Invitations({
    invitations,
    mismatch,
    hasOrganisation,
    dashboardUrl,
}: Props) {
    const { t } = useTranslation();
    const [declining, setDeclining] = useState<InvitationHomeItem | null>(null);
    const [processing, setProcessing] = useState<string | null>(null);

    const switchAccount = (invitation: InvitationMismatch) =>
        router.post(
            logout().url,
            {},
            {
                onSuccess: () =>
                    router.visit(
                        login({ query: { invitation: invitation.code } }).url,
                    ),
            },
        );

    return (
        <>
            <Head title={t('tenants.invitations_home.head')} />

            <div className="flex flex-col gap-6">
                {mismatch ? (
                    <Alert data-test="invitation-mismatch">
                        <AlertTriangle />
                        <AlertTitle>
                            {t('tenants.invitations_home.mismatch.title')}
                        </AlertTitle>
                        <AlertDescription className="space-y-3">
                            <p>
                                {t('tenants.invitations_home.mismatch.body', {
                                    tenant: mismatch.tenantName,
                                    invited: mismatch.invitedEmail,
                                    account: mismatch.accountEmail,
                                })}
                            </p>
                            <p>
                                {t(
                                    'tenants.invitations_home.mismatch.no_account',
                                    {
                                        invited: mismatch.invitedEmail,
                                        account: mismatch.accountEmail,
                                    },
                                )}
                            </p>
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    size="sm"
                                    data-test="invitation-mismatch-switch"
                                    onClick={() => switchAccount(mismatch)}
                                >
                                    {t(
                                        'tenants.invitations_home.mismatch.switch',
                                        { invited: mismatch.invitedEmail },
                                    )}
                                </Button>
                                <Button
                                    size="sm"
                                    variant="secondary"
                                    data-test="invitation-mismatch-dismiss"
                                    onClick={() => router.delete(forget().url)}
                                >
                                    {t(
                                        'tenants.invitations_home.mismatch.dismiss',
                                    )}
                                </Button>
                            </div>
                        </AlertDescription>
                    </Alert>
                ) : null}

                {invitations.length === 0 ? (
                    <p
                        className="text-muted-foreground text-center text-sm"
                        data-test="invitations-empty"
                    >
                        {t('tenants.invitations_home.empty')}
                    </p>
                ) : (
                    <ul className="space-y-3">
                        {invitations.map((invitation) => (
                            <li
                                key={invitation.code}
                                className="space-y-3 rounded-lg border p-4"
                                data-test="invitation-item"
                            >
                                <p className="flex items-start gap-2 text-sm">
                                    <MailCheck className="text-primary mt-0.5 size-4 shrink-0" />
                                    {invitation.inviterName
                                        ? t('tenants.invitations_home.item', {
                                              inviter: invitation.inviterName,
                                              tenant: invitation.tenantName,
                                              profile: invitation.profileName,
                                          })
                                        : t(
                                              'tenants.invitations_home.item_without_inviter',
                                              {
                                                  tenant: invitation.tenantName,
                                                  profile:
                                                      invitation.profileName,
                                              },
                                          )}
                                </p>
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        size="sm"
                                        data-test="invitation-accept"
                                        disabled={processing !== null}
                                        onClick={() =>
                                            router.post(
                                                accept(invitation.code).url,
                                                {},
                                                {
                                                    onStart: () =>
                                                        setProcessing(
                                                            invitation.code,
                                                        ),
                                                    onFinish: () =>
                                                        setProcessing(null),
                                                },
                                            )
                                        }
                                    >
                                        {t('tenants.invitations_home.accept')}
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="secondary"
                                        data-test="invitation-decline"
                                        disabled={processing !== null}
                                        onClick={() => setDeclining(invitation)}
                                    >
                                        {t('tenants.invitations_home.decline')}
                                    </Button>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}

                {hasOrganisation && dashboardUrl ? (
                    <Button variant="outline" asChild>
                        <Link href={dashboardUrl}>
                            {t('tenants.invitations_home.to_dashboard')}
                        </Link>
                    </Button>
                ) : (
                    <div className="space-y-3 text-center">
                        <p className="text-muted-foreground text-sm">
                            {t('tenants.invitations_home.no_organisation')}
                        </p>
                        <Button variant="outline" asChild>
                            <Link
                                href={tenantsIndex()}
                                data-test="invitations-create-organisation"
                            >
                                {t(
                                    'tenants.invitations_home.create_organisation',
                                )}
                            </Link>
                        </Button>
                    </div>
                )}
            </div>

            <ConfirmActionDialog
                open={declining !== null}
                onOpenChange={(open) => !open && setDeclining(null)}
                title={t('tenants.invitations_home.confirm_decline.title')}
                description={t(
                    'tenants.invitations_home.confirm_decline.description',
                    { tenant: declining?.tenantName ?? '' },
                )}
                confirmLabel={t('tenants.invitations_home.decline')}
                destructive
                testId="invitation-decline-confirm"
                onConfirm={() => {
                    if (declining) {
                        router.delete(decline(declining.code).url, {
                            onFinish: () => setDeclining(null),
                        });
                    }
                }}
            />
        </>
    );
}

Invitations.layout = ({ translations }: { translations: Translations }) => ({
    title: translate(translations, 'tenants.invitations_home.title'),
    description: translate(
        translations,
        'tenants.invitations_home.description',
    ),
});

import { InfoIcon } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import type { TenantInvitationContext } from '@/types';
import { useTranslation } from '@/hooks/use-translation';

type Props = {
    invitation: TenantInvitationContext;
    action: 'login' | 'register';
};

export default function TenantInvitationAlert({ invitation, action }: Props) {
    const { t } = useTranslation();
    return (
        <Alert
            data-test="tenant-invitation-alert"
            className="border-primary/20 bg-primary/5 text-foreground [&>svg]:text-primary"
        >
            <InfoIcon />
            <AlertDescription className="text-foreground">
                {t(`tenants.invitation_alert.${action}`, {
                    name: invitation.tenantName,
                })}
            </AlertDescription>
        </Alert>
    );
}

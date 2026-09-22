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
            className="border-blue-200 bg-blue-50 text-blue-900 dark:border-blue-900/50 dark:bg-blue-950/50 dark:text-blue-100 [&>svg]:text-blue-600 dark:[&>svg]:text-blue-400"
        >
            <InfoIcon />
            <AlertDescription className="text-blue-900 dark:text-blue-100">
                {t(`tenants.invitation_alert.${action}`, {
                    name: invitation.tenantName,
                })}
            </AlertDescription>
        </Alert>
    );
}

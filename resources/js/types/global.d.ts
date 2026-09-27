import type { Auth } from '@/types/auth';
import type { LocaleCode, SupportedLocales, Translations } from '@/types/i18n';
import type { NotificationsSummary } from '@/types/notifications';
import type { CurrentPlan, TenantPermissions } from '@/types/tenants';
import type { Tenant } from '@/types/tenants';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            cspNonce: string;
            locale: LocaleCode;
            supportedLocales: SupportedLocales;
            translations: Translations;
            currentTenant: Tenant | null;
            tenants: Tenant[];
            notifications: NotificationsSummary | null;
            tenantPermissions: TenantPermissions | null;
            currentPlan: CurrentPlan | null;
            [key: string]: unknown;
        };
    }
}

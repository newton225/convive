import type { Auth } from '@/types/auth';
import type { LocaleCode, SupportedLocales, Translations } from '@/types/i18n';
import type { NotificationsSummary } from '@/types/notifications';
import type { SupportAccessNotice } from '@/types/support-access';
import type { FlashToast } from '@/types/ui';
import type { CurrentPlan, TenantPermissions } from '@/types/tenants';
import type { Tenant } from '@/types/tenants';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        flashDataType: {
            toast?: FlashToast;
            // Le refus du numero vient d'une reservation avec decompte : le lien peut etre renvoye.
            recoverable?: boolean;
        };
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
            completedTours: string[];
            canAccessConsole: boolean;
            consoleAreas: string[];
            appVersion: string | null;
            // Le numero seul, sur les pages de connexion et d'inscription (visiteur anonyme).
            publicVersion?: string;
            // Livraison en service : sa date et l'identifiant de sa modification.
            appRelease: { releasedAt: string; commit: string | null } | null;
            supportAccess: SupportAccessNotice | null;
            [key: string]: unknown;
        };
    }
}

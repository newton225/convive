import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import { registerServiceWorker } from '@/lib/register-service-worker';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';

const appName = import.meta.env.VITE_APP_NAME || 'Convive';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
            // Le parcours invite (README ecrans 3 a 11) n'est jamais authentifie : l'envelopper
            // dans `AppLayout` par defaut plantait au rendu (`UserInfo` lit `user.avatar` sur un
            // `auth.user` qui vaut `null` pour un visiteur anonyme), page blanche silencieuse,
            // decouvert le 2026-09-22. Chaque page publique compose deja son propre habillage.
            case name.startsWith('public/'):
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
            case name.startsWith('tenants/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();

// Le service worker (PWA) : coquille hors ligne, billet et ecran de scan disponibles sans reseau.
registerServiceWorker();

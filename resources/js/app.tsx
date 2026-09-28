import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import { registerServiceWorker } from '@/lib/register-service-worker';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import PageLayout from '@/layouts/page-layout';
import SettingsLayout from '@/layouts/settings/layout';

const appName = import.meta.env.VITE_APP_NAME || 'Convive';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
            case name === 'showcase':
            // Le parcours invite (README ecrans 3 a 11) n'est jamais authentifie : l'envelopper
            // dans `AppLayout` par defaut plantait au rendu (`UserInfo` lit `user.avatar` sur un
            // `auth.user` qui vaut `null` pour un visiteur anonyme), page blanche silencieuse,
            // decouvert le 2026-09-22. Chaque page publique compose deja son propre habillage.
            case name.startsWith('public/'):
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            // Reglages personnels : leur propre sous-menu (profil, securite, organisations...).
            case name.startsWith('settings/'):
            case name === 'tenants/index':
                return [AppLayout, SettingsLayout];
            // Evenements et organisation : le menu de gauche suffit, pas de sous-menu.
            default:
                return [AppLayout, PageLayout];
        }
    },
    strictMode: true,
    defaults: {
        // Inertia remonte en haut de page apres chaque envoi de formulaire. Quand le serveur
        // renvoie la meme page (enregistrement, erreur de validation), ce saut fait perdre a
        // l'utilisateur l'endroit ou il travaillait : on garde la position, sauf si la reponse
        // mene ailleurs (creation d'un evenement qui ouvre sa fiche, par exemple).
        visitOptions: (_href, options) =>
            options.method && options.method !== 'get'
                ? {
                      preserveScroll: (page) =>
                          options.preserveScroll === true ||
                          new URL(page.url, window.location.origin).pathname ===
                              window.location.pathname,
                  }
                : {},
    },
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

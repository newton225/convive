import { useEffect, useState } from 'react';

type InstallPromptEvent = Event & {
    prompt: () => Promise<void>;
};

const isInstallPromptEvent = (event: Event): event is InstallPromptEvent =>
    'prompt' in event && typeof event.prompt === 'function';

const DismissedKey = 'convive:install-dismissed';

/**
 * L'invitation a installer l'application (PWA). Le navigateur decide quand elle est possible
 * (`beforeinstallprompt`) : on garde l'evenement pour la declencher a notre convenance, et on ne la
 * repropose pas dans la meme session apres un « plus tard ».
 */
export function useInstallPrompt() {
    const [promptEvent, setPromptEvent] = useState<InstallPromptEvent | null>(
        null,
    );
    const [dismissed, setDismissed] = useState(() => {
        try {
            return sessionStorage.getItem(DismissedKey) === '1';
        } catch {
            return false;
        }
    });

    useEffect(() => {
        const onPrompt = (event: Event) => {
            event.preventDefault();

            if (isInstallPromptEvent(event)) {
                setPromptEvent(event);
            }
        };
        const onInstalled = () => setPromptEvent(null);

        window.addEventListener('beforeinstallprompt', onPrompt);
        window.addEventListener('appinstalled', onInstalled);

        return () => {
            window.removeEventListener('beforeinstallprompt', onPrompt);
            window.removeEventListener('appinstalled', onInstalled);
        };
    }, []);

    return {
        canInstall: promptEvent !== null && !dismissed,
        install: async () => {
            await promptEvent?.prompt();
            setPromptEvent(null);
        },
        dismiss: () => {
            setDismissed(true);

            try {
                sessionStorage.setItem(DismissedKey, '1');
            } catch {
                // Sans stockage, l'invitation reviendra au prochain chargement : sans gravite.
            }
        },
    };
}

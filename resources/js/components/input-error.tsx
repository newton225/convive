import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import type { HTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

/**
 * Le message d'erreur d'un champ. Il disparait des que la personne retouche le champ qu'il
 * accompagne (toute saisie ou tout changement dans le bloc qui le contient) : l'erreur date du
 * dernier envoi, elle ne dit plus rien de la nouvelle valeur. Un nouvel envoi la rend, si le serveur
 * la renvoie.
 *
 * Compromis : Inertia n'efface pas lui-meme les erreurs a la saisie, et ce composant est le seul
 * point commun aux formulaires de l'application. Le bloc surveille est le parent direct du message,
 * c'est-a-dire le champ lui-meme dans les formulaires de l'application.
 */
export default function InputError({
    message,
    className = '',
    ...props
}: HTMLAttributes<HTMLParagraphElement> & { message?: string }) {
    const ref = useRef<HTMLParagraphElement>(null);
    const [dismissed, setDismissed] = useState(false);

    useEffect(() => {
        const container = ref.current?.parentElement;

        if (!container || !message) {
            return;
        }

        const dismiss = () => setDismissed(true);

        container.addEventListener('input', dismiss);
        container.addEventListener('change', dismiss);

        return () => {
            container.removeEventListener('input', dismiss);
            container.removeEventListener('change', dismiss);
        };
    }, [message]);

    // Un nouvel envoi redonne la parole au serveur : s'il renvoie la meme erreur, elle se revoit.
    useEffect(() => router.on('start', () => setDismissed(false)), []);

    useEffect(() => setDismissed(false), [message]);

    return message && !dismissed ? (
        <p
            {...props}
            ref={ref}
            className={cn('text-destructive text-sm', className)}
        >
            {message}
        </p>
    ) : null;
}

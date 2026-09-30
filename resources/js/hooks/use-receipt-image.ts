import { useEffect, useState } from 'react';

export type ReceiptImageState =
    | { status: 'idle' }
    | { status: 'loading' }
    | { status: 'ready'; src: string }
    | { status: 'error' };

/**
 * Charge la capture d'un recu pour l'afficher dans la page, sans telechargement.
 *
 * Le serveur sert toujours le recu en piece jointe, `nosniff` et CSP `sandbox` (SECURITY.md H1) :
 * ouvrir son adresse telecharge le fichier et ne l'affiche jamais comme un document de
 * l'application. On lit ici ses octets, on verifie que c'est bien une image, et on les confie a
 * une URL `blob:` destinee a une balise `img`, ou aucun script ne s'execute. Chaque chargement
 * passe par la route journalisee (SECURITY.md H2) : un apercu compte comme une consultation.
 * `attempt` relance le chargement apres un echec.
 */
export function useReceiptImage(
    url: string | null,
    attempt = 0,
): ReceiptImageState {
    const [state, setState] = useState<ReceiptImageState>({ status: 'idle' });

    useEffect(() => {
        if (url === null) {
            setState({ status: 'idle' });

            return;
        }

        const controller = new AbortController();
        let objectUrl: string | null = null;

        setState({ status: 'loading' });

        fetch(url, {
            credentials: 'same-origin',
            headers: { Accept: 'image/*' },
            signal: controller.signal,
        })
            .then(async (response) => {
                // Une session expiree renvoie la page de connexion, pas une erreur : seule une
                // image est acceptee.
                const type = response.headers.get('Content-Type') ?? '';

                if (!response.ok || !type.startsWith('image/')) {
                    throw new Error(`receipt ${response.status}`);
                }

                objectUrl = URL.createObjectURL(await response.blob());
                setState({ status: 'ready', src: objectUrl });
            })
            .catch(() => {
                if (!controller.signal.aborted) {
                    setState({ status: 'error' });
                }
            });

        return () => {
            controller.abort();

            if (objectUrl !== null) {
                URL.revokeObjectURL(objectUrl);
            }
        };
    }, [url, attempt]);

    return state;
}

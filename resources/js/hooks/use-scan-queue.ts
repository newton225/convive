import { useCallback, useRef, useState } from 'react';
import { readQueue, writeQueue } from '@/lib/scan-queue';

export type SyncOutcome = 'accepted' | 'already_scanned' | 'refused' | 'failed';

/**
 * La file des scans faits hors ligne et leur synchronisation, un par un, dans l'ordre. Un envoi
 * qui echoue (`failed`) arrete la synchronisation et garde le reste en file : rien n'est perdu, la
 * prochaine tentative reprend au meme endroit. Un billet accepte hors ligne que le serveur refuse ou
 * a deja vu est compte « a revoir » : l'agent doit le savoir, il est peut-etre deja entre.
 */
export function useScanQueue(
    eventId: number,
    syncOne: (token: string) => Promise<SyncOutcome>,
) {
    const [queued, setQueued] = useState(() => readQueue(eventId).length);
    const [syncing, setSyncing] = useState(false);
    const [review, setReview] = useState(0);
    const syncingRef = useRef(false);

    const enqueue = useCallback(
        (token: string) => {
            const next = [
                ...readQueue(eventId),
                { token, scannedAt: new Date().toISOString() },
            ];

            writeQueue(eventId, next);
            setQueued(next.length);
        },
        [eventId],
    );

    const sync = useCallback(async () => {
        if (syncingRef.current) {
            return;
        }

        syncingRef.current = true;
        setSyncing(true);

        let remaining = readQueue(eventId);

        while (remaining.length > 0) {
            const outcome = await syncOne(remaining[0].token);

            if (outcome === 'failed') {
                break;
            }

            if (outcome !== 'accepted') {
                setReview((count) => count + 1);
            }

            remaining = remaining.slice(1);
            writeQueue(eventId, remaining);
            setQueued(remaining.length);
        }

        syncingRef.current = false;
        setSyncing(false);
    }, [eventId, syncOne]);

    return {
        queued,
        syncing,
        review,
        enqueue,
        sync,
        dismissReview: () => setReview(0),
    };
}

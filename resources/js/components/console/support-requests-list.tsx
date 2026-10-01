import { router } from '@inertiajs/react';
import { Hand } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { formatDateTime } from '@/lib/format-date';
import { take } from '@/routes/console/support-requests';
import type { ConsoleSupportRequest } from '@/types';

type Props = {
    requests: ConsoleSupportRequest[];
};

/**
 * Les demandes d'aide en attente, vues par l'equipe Convive (README section 3) : des organisations
 * veulent ouvrir leur espace et ne voient personne a qui le faire. « Prendre en charge » rend la
 * personne visible de l'organisation et previent ses Proprietaires ; l'acces reste a ouvrir par eux.
 */
export function SupportRequestsList({ requests }: Props) {
    const { t, locale } = useTranslation();
    const [taking, setTaking] = useState<number | null>(null);

    const takeRequest = (requestId: number) => {
        router.post(
            take(requestId).url,
            {},
            {
                preserveScroll: true,
                onStart: () => setTaking(requestId),
                onFinish: () => setTaking(null),
            },
        );
    };

    if (requests.length === 0) {
        return null;
    }

    return (
        <section className="space-y-2" data-test="console-support-requests">
            <h3 className="text-sm font-medium">
                {t('console.support_requests.title')}
            </h3>
            <p className="text-muted-foreground text-sm">
                {t('console.support_requests.hint')}
            </p>
            <ul className="divide-border divide-y">
                {requests.map((request) => (
                    <li
                        key={request.id}
                        className="flex flex-wrap items-center justify-between gap-3 py-2 text-sm"
                    >
                        <span className="min-w-0 flex-1">
                            <span className="font-medium">
                                {request.organisation}
                            </span>
                            <span className="text-muted-foreground ml-2">
                                {t('console.support_requests.from', {
                                    name: request.requestedBy ?? '',
                                    date: formatDateTime(
                                        request.requestedAt,
                                        locale,
                                    ),
                                })}
                            </span>
                            <span className="block">{request.reason}</span>
                            {request.takenBy ? (
                                <span className="text-muted-foreground block">
                                    {request.takenByMe
                                        ? t(
                                              'console.support_requests.taken_by_me',
                                          )
                                        : t(
                                              'console.support_requests.taken_by',
                                              { operator: request.takenBy },
                                          )}
                                </span>
                            ) : null}
                        </span>
                        {request.takenByMe ? null : (
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={taking !== null}
                                onClick={() => takeRequest(request.id)}
                                data-test="console-support-request-take"
                            >
                                <Hand />
                                {t('console.support_requests.take')}
                            </Button>
                        )}
                    </li>
                ))}
            </ul>
        </section>
    );
}

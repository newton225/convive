import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import { formatDateTime } from '@/lib/format-date';
import type { RegistrationCancellation } from '@/types';

type Props = {
    cancellations: RegistrationCancellation[];
};

/**
 * Les dernieres annulations de l'evenement (prototype Convive.dc.html, base d'inscrits) : qui a
 * ete annule, pourquoi, par qui et quand, lisible sans filtrer la liste.
 */
export function CancellationsCard({ cancellations }: Props) {
    const { t, locale } = useTranslation();

    return (
        <Card data-test="registration-cancellations">
            <CardHeader>
                <CardTitle className="text-base">
                    {t('registrations.cancellations.title')}
                </CardTitle>
            </CardHeader>
            <CardContent>
                {cancellations.length === 0 ? (
                    <div className="space-y-1 text-sm">
                        <p className="font-medium">
                            {t('registrations.cancellations.empty_title')}
                        </p>
                        <p className="text-muted-foreground">
                            {t('registrations.cancellations.empty_description')}
                        </p>
                    </div>
                ) : (
                    <ul className="divide-y text-sm">
                        {cancellations.map((item, index) => (
                            <li
                                key={`${item.name}-${index}`}
                                className="space-y-0.5 py-2"
                            >
                                <p className="font-medium">{item.name}</p>
                                <p className="text-muted-foreground">
                                    {item.reason ??
                                        t(
                                            'registrations.cancellations.no_reason',
                                        )}
                                </p>
                                <p className="text-muted-foreground text-xs">
                                    {t('registrations.cancellations.by', {
                                        name:
                                            item.cancelledBy ??
                                            t(
                                                'registrations.cancellations.unknown_author',
                                            ),
                                        date: item.cancelledAt
                                            ? formatDateTime(
                                                  item.cancelledAt,
                                                  locale,
                                              )
                                            : '',
                                    })}
                                </p>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}

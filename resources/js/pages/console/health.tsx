import { Head } from '@inertiajs/react';
import { CircleAlert, CircleCheck } from 'lucide-react';
import { BackupCard } from '@/components/console/backup-card';
import { RepairDatabaseButton } from '@/components/console/repair-database-button';
import Heading from '@/components/heading';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { translate, useTranslation } from '@/hooks/use-translation';
import { health } from '@/routes/console';
import type {
    ConsoleBackup,
    ConsoleDatabaseIssue,
    Translations,
} from '@/types';

type Props = {
    databases: ConsoleDatabaseIssue[];
    backup: ConsoleBackup;
};

/**
 * README ecran 31 : la sante technique. Bases d'organisation absentes ou en retard de migrations
 * (CLAUDE.md, « Multi-locataire » : un locataire sans ses migrations est un locataire casse) et
 * sauvegardes. Le suivi des taches planifiees et des files n'est pas encore construit : rien ne
 * s'affiche a leur sujet, plutot qu'un jeu d'exemple.
 */
export default function Health({ databases, backup }: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('console.health.title')} />

            <div className="flex flex-col space-y-6">
                <Heading
                    variant="small"
                    title={t('console.health.title')}
                    description={t('console.health.description')}
                />

                <Card>
                    <CardHeader>
                        <CardTitle>{t('console.health.databases')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {databases.length === 0 ? (
                            <p className="flex items-center gap-2 text-sm">
                                <CircleCheck className="size-4" />
                                {t('console.health.databases_ok')}
                            </p>
                        ) : (
                            <ul className="divide-y text-sm">
                                {databases.map((item) => (
                                    <li
                                        key={item.slug}
                                        className="flex flex-wrap items-center justify-between gap-2 py-2"
                                    >
                                        <span className="flex items-center gap-2">
                                            <CircleAlert className="size-4 shrink-0" />
                                            <span className="font-medium">
                                                {item.name}
                                            </span>
                                            <span className="text-muted-foreground">
                                                {item.issue ===
                                                'missing_database'
                                                    ? t(
                                                          'console.health.issues.missing_database',
                                                      )
                                                    : t(
                                                          'console.health.issues.pending_migrations',
                                                          {
                                                              count:
                                                                  item.pendingMigrations ??
                                                                  0,
                                                          },
                                                      )}
                                            </span>
                                        </span>
                                        <RepairDatabaseButton
                                            slug={item.slug}
                                            name={item.name}
                                        />
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                <BackupCard backup={backup} />
            </div>
        </>
    );
}

Health.layout = (props: { translations: Translations }) => ({
    wide: true,
    breadcrumbs: [
        {
            title: translate(props.translations, 'console.health.title'),
            href: health(),
        },
    ],
});

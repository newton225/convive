import type { ColumnDef } from '@tanstack/react-table';
import { Check, Minus } from 'lucide-react';
import DataTable from '@/components/data-table';
import { useTranslation } from '@/hooks/use-translation';
import type { PermissionDomain, TenantProfile } from '@/types';

type Props = {
    profiles: TenantProfile[];
    catalogue: PermissionDomain[];
};

type MatrixRow = {
    value: string;
    module: string;
    action: string;
};

/**
 * Vue comparative des profils (prototype Convive.dc.html, « Roles et permissions ») : une ligne par
 * action de chaque module, une colonne par profil. Lecture seule ; la modification passe par
 * l'editeur de chaque profil. Chaque case dit « oui » ou « non » en texte pour les lecteurs
 * d'ecran, jamais par la seule icone.
 */
export function PermissionMatrix({ profiles, catalogue }: Props) {
    const { t } = useTranslation();

    const rows: MatrixRow[] = catalogue.flatMap((module) =>
        module.permissions.map((permission) => ({
            value: permission.value,
            module: module.label,
            action: permission.action,
        })),
    );

    const columns: ColumnDef<MatrixRow>[] = [
        {
            header: t('profiles.matrix.permission'),
            cell: ({ row }) => (
                <div>
                    <p className="text-muted-foreground text-xs">
                        {row.original.module}
                    </p>
                    <p>{row.original.action}</p>
                </div>
            ),
        },
        ...profiles.map((profile): ColumnDef<MatrixRow> => ({
            id: `profile-${profile.id}`,
            header: profile.name,
            cell: ({ row }) =>
                profile.permissions.includes(row.original.value) ? (
                    <span className="inline-flex items-center gap-1">
                        <Check className="size-4" />
                        <span className="sr-only">
                            {t('profiles.matrix.yes')}
                        </span>
                    </span>
                ) : (
                    <span className="text-muted-foreground inline-flex items-center gap-1">
                        <Minus className="size-4" />
                        <span className="sr-only">
                            {t('profiles.matrix.no')}
                        </span>
                    </span>
                ),
        })),
    ];

    return (
        <section className="space-y-3" data-test="profile-matrix">
            <div>
                <h2 className="text-base font-medium">
                    {t('profiles.matrix.title')}
                </h2>
                <p className="text-muted-foreground text-sm">
                    {t('profiles.matrix.description')}
                </p>
            </div>
            <DataTable
                columns={columns}
                data={rows}
                meta={{ currentPage: 1, lastPage: 1, total: rows.length }}
                onPageChange={() => undefined}
                emptyState={null}
            />
        </section>
    );
}

import { Search } from 'lucide-react';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';
import type { ProofSignalFilter } from '@/lib/proof-filters';
import { ProofSignalFilters } from '@/lib/proof-filters';

type Props = {
    search: string;
    onSearchChange: (value: string) => void;
    signalFilter: ProofSignalFilter;
    onSignalFilterChange: (value: ProofSignalFilter) => void;
    singleExpand: boolean;
    onSingleExpandChange: (value: boolean) => void;
    // Nombre de preuves apres recherche et filtre, toutes pages confondues.
    count: number;
};

/**
 * La barre au-dessus de la file des preuves : recherche, filtre par signal, nombre de preuves
 * affichees et mode d'ouverture des lignes. Elle ne filtre rien elle-meme : elle pose les valeurs
 * que les filtres de colonne de TanStack Table appliquent.
 */
export function ProofsToolbar({
    search,
    onSearchChange,
    signalFilter,
    onSignalFilterChange,
    singleExpand,
    onSingleExpandChange,
    count,
}: Props) {
    const { t } = useTranslation();

    return (
        <div className="flex flex-wrap items-center gap-3">
            <div className="relative min-w-56 flex-1 sm:max-w-sm">
                <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2" />
                <Input
                    type="search"
                    value={search}
                    onChange={(event) => onSearchChange(event.target.value)}
                    placeholder={t('proofs.toolbar.search_placeholder')}
                    aria-label={t('proofs.toolbar.search')}
                    className="pl-8"
                    data-test="proofs-search"
                />
            </div>

            <Select
                value={signalFilter}
                onValueChange={(value) => {
                    const filter = ProofSignalFilters.find(
                        (candidate) => candidate === value,
                    );

                    if (filter) {
                        onSignalFilterChange(filter);
                    }
                }}
            >
                <SelectTrigger
                    className="w-56"
                    aria-label={t('proofs.toolbar.filter_label')}
                    data-test="proofs-signal-filter"
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {ProofSignalFilters.map((filter) => (
                        <SelectItem key={filter} value={filter}>
                            {t(`proofs.filters.${filter}`)}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            <p
                className="text-muted-foreground text-sm tabular-nums"
                aria-live="polite"
                data-test="proofs-count"
            >
                {t('proofs.toolbar.count', { count })}
            </p>

            <label className="ml-auto flex min-h-9 items-center gap-2 text-sm">
                <Checkbox
                    checked={singleExpand}
                    onCheckedChange={(checked) =>
                        onSingleExpandChange(checked === true)
                    }
                    data-test="proofs-single-expand"
                />
                {t('proofs.details.single_expand')}
            </label>
        </div>
    );
}

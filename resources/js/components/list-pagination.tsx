import type { MouseEvent } from 'react';
import {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationItem,
    PaginationLink,
} from '@/components/ui/pagination';
import { useTranslation } from '@/hooks/use-translation';
import { pageWindow } from '@/lib/page-window';
import { cn } from '@/lib/utils';

export type PaginationMeta = {
    currentPage: number;
    lastPage: number;
    total: number;
};

type Props = {
    meta: PaginationMeta;
    onPageChange: (page: number) => void;
    className?: string;
};

/**
 * Les commandes d'une liste paginee, par le serveur le plus souvent (decision du 2026-10-07) : Premier, Precedent,
 * les numeros autour de la page courante, Suivant, Dernier. La page change par `onPageChange`, qui
 * garde la recherche et les filtres dans l'adresse.
 */
export function ListPagination({ meta, onPageChange, className }: Props) {
    const { t } = useTranslation();

    if (meta.lastPage <= 1) {
        return null;
    }

    const go = (page: number) => (event: MouseEvent<HTMLAnchorElement>) => {
        event.preventDefault();

        if (page !== meta.currentPage && page >= 1 && page <= meta.lastPage) {
            onPageChange(page);
        }
    };

    const atStart = meta.currentPage <= 1;
    const atEnd = meta.currentPage >= meta.lastPage;
    const disabled = 'pointer-events-none opacity-50';

    return (
        <div
            className={cn(
                'flex flex-col items-center gap-2 sm:flex-row sm:justify-between',
                className,
            )}
            data-test="list-pagination"
        >
            <p className="text-muted-foreground text-sm">
                {t('common.pagination.page_of', {
                    current: String(meta.currentPage),
                    last: String(meta.lastPage),
                })}
            </p>
            <Pagination
                aria-label={t('common.pagination.label')}
                className="mx-0 w-auto"
            >
                <PaginationContent className="flex-wrap justify-center">
                    <PaginationItem>
                        <PaginationLink
                            href="#"
                            size="default"
                            aria-disabled={atStart}
                            className={cn(atStart && disabled)}
                            onClick={go(1)}
                            data-test="pagination-first"
                        >
                            {t('common.pagination.first')}
                        </PaginationLink>
                    </PaginationItem>
                    <PaginationItem>
                        <PaginationLink
                            href="#"
                            size="default"
                            aria-disabled={atStart}
                            className={cn(atStart && disabled)}
                            onClick={go(meta.currentPage - 1)}
                            data-test="pagination-previous"
                        >
                            {t('common.pagination.previous')}
                        </PaginationLink>
                    </PaginationItem>
                    {pageWindow(meta.currentPage, meta.lastPage).map(
                        (item, index) =>
                            item === 'gap' ? (
                                <PaginationItem key={`gap-${index}`}>
                                    <PaginationEllipsis />
                                </PaginationItem>
                            ) : (
                                <PaginationItem key={item}>
                                    <PaginationLink
                                        href="#"
                                        isActive={item === meta.currentPage}
                                        aria-label={t(
                                            'common.pagination.go_to_page',
                                            { page: String(item) },
                                        )}
                                        onClick={go(item)}
                                        data-test="pagination-page"
                                    >
                                        {item}
                                    </PaginationLink>
                                </PaginationItem>
                            ),
                    )}
                    <PaginationItem>
                        <PaginationLink
                            href="#"
                            size="default"
                            aria-disabled={atEnd}
                            className={cn(atEnd && disabled)}
                            onClick={go(meta.currentPage + 1)}
                            data-test="pagination-next"
                        >
                            {t('common.pagination.next')}
                        </PaginationLink>
                    </PaginationItem>
                    <PaginationItem>
                        <PaginationLink
                            href="#"
                            size="default"
                            aria-disabled={atEnd}
                            className={cn(atEnd && disabled)}
                            onClick={go(meta.lastPage)}
                            data-test="pagination-last"
                        >
                            {t('common.pagination.last')}
                        </PaginationLink>
                    </PaginationItem>
                </PaginationContent>
            </Pagination>
        </div>
    );
}

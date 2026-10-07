import { router } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { SubmitButton } from '@/components/submit-button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { useTranslation } from '@/hooks/use-translation';
import { update } from '@/routes/tenants/events/seating/tables';

type Props = {
    tenantSlug: string;
    eventId: number;
    tableId: number;
    tableNumber: number;
    capacity: number;
};

/**
 * Le nombre de places d'une table precise (README ecran 21, decision du 2026-09-29 : les tables
 * n'ont pas toutes la meme taille). Le serveur refuse une table plus petite que ses occupants, et
 * une capacite totale sous les places deja prises : le refus s'affiche sous le champ.
 */
export function TableCapacityControl({
    tenantSlug,
    eventId,
    tableId,
    tableNumber,
    capacity,
}: Props) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [value, setValue] = useState(String(capacity));
    const [error, setError] = useState<string | undefined>();
    const [processing, setProcessing] = useState(false);

    const save = (event: React.FormEvent) => {
        event.preventDefault();
        setProcessing(true);
        router.patch(
            update([tenantSlug, eventId, tableId]).url,
            { capacity: Number(value) },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setError(undefined);
                    setOpen(false);
                },
                onError: (errors) => setError(errors.capacity),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <Popover
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                setValue(String(capacity));
                setError(undefined);
            }}
        >
            <PopoverTrigger
                className="text-muted-foreground hover:text-foreground focus-visible:ring-ring relative inline-flex size-6 items-center justify-center rounded-md outline-none after:absolute after:-inset-2.5 focus-visible:ring-2"
                aria-label={`${t('seating.capacity.edit')} (#${tableNumber})`}
                data-test="seating-table-capacity"
            >
                <Pencil className="size-3.5" />
            </PopoverTrigger>
            <PopoverContent align="end" className="w-60">
                <form onSubmit={save} className="space-y-3">
                    <div className="space-y-2">
                        <Label htmlFor={`capacity-${tableId}`} required>
                            {t('seating.capacity.edit')}
                        </Label>
                        <Input
                            id={`capacity-${tableId}`}
                            type="number"
                            min={1}
                            max={100}
                            value={value}
                            onChange={(event) => setValue(event.target.value)}
                            autoFocus
                        />
                        <InputError message={error} />
                    </div>
                    <SubmitButton processing={processing} className="w-full">
                        {t('seating.capacity.save')}
                    </SubmitButton>
                </form>
            </PopoverContent>
        </Popover>
    );
}

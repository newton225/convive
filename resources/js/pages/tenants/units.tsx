import { Form, Head, router } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { SubmitButton } from '@/components/submit-button';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { translate, useTranslation } from '@/hooks/use-translation';
import { edit, index as tenantsIndex } from '@/routes/tenants';
import { destroy, index, store, update } from '@/routes/tenants/units';
import type { Tenant, TenantUnit, Translations } from '@/types';

type Props = {
    tenant: Pick<Tenant, 'id' | 'name' | 'slug'>;
    units: TenantUnit[];
};

export default function Units({ tenant, units }: Props) {
    const { t } = useTranslation();
    const [deleting, setDeleting] = useState<TenantUnit | null>(null);

    return (
        <>
            <Head title={t('units.title')} />

            <h1 className="sr-only">{t('units.title')}</h1>

            <div className="flex flex-col space-y-6">
                <Heading
                    variant="small"
                    title={t('units.title')}
                    description={t('units.description')}
                />

                <Form
                    {...store.form(tenant.slug)}
                    resetOnSuccess
                    className="flex flex-wrap items-start gap-2"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid min-w-64 flex-1 gap-2">
                                <Label htmlFor="unit-name" className="sr-only">
                                    {t('units.fields.name')}
                                </Label>
                                <Input
                                    id="unit-name"
                                    name="name"
                                    data-test="unit-name"
                                    placeholder={t(
                                        'units.fields.name_placeholder',
                                    )}
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>

                            <SubmitButton
                                data-test="unit-create"
                                processing={processing}
                            >
                                <Plus /> {t('units.actions.create')}
                            </SubmitButton>
                        </>
                    )}
                </Form>

                <div className="space-y-2">
                    {units.map((unit) => (
                        <Form
                            key={unit.id}
                            {...update.form([tenant.slug, unit.id])}
                            className="flex flex-wrap items-center gap-3 rounded-lg border p-3"
                            data-test="unit-row"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <Input
                                        name="name"
                                        aria-label={t('units.fields.name')}
                                        defaultValue={unit.name}
                                        className="min-w-48 flex-1"
                                        data-test="unit-row-name"
                                    />

                                    <input
                                        type="hidden"
                                        name="position"
                                        value={unit.position}
                                    />

                                    <label className="flex items-center gap-2 text-sm">
                                        <Checkbox
                                            name="is_active"
                                            value="1"
                                            defaultChecked={unit.isActive}
                                            data-test="unit-row-active"
                                        />
                                        {t('units.fields.is_active')}
                                    </label>

                                    {unit.isNone ? (
                                        <Badge variant="secondary">
                                            {t('units.badges.none')}
                                        </Badge>
                                    ) : null}

                                    <SubmitButton
                                        variant="secondary"
                                        size="sm"
                                        processing={processing}
                                    >
                                        {t('common.actions.save')}
                                    </SubmitButton>

                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        data-test="unit-row-delete"
                                        onClick={() => setDeleting(unit)}
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </Button>

                                    <InputError
                                        message={errors.name}
                                        className="w-full"
                                    />
                                </>
                            )}
                        </Form>
                    ))}
                </div>
            </div>

            <Dialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t('units.confirm_delete.title')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('units.confirm_delete.description', {
                                name: deleting?.name ?? '',
                            })}
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button variant="secondary">
                                {t('common.actions.cancel')}
                            </Button>
                        </DialogClose>

                        <Button
                            variant="destructive"
                            data-test="unit-delete-confirm"
                            onClick={() => {
                                if (deleting) {
                                    router.delete(
                                        destroy([tenant.slug, deleting.id]).url,
                                        { onSuccess: () => setDeleting(null) },
                                    );
                                }
                            }}
                        >
                            {t('units.actions.delete')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

Units.layout = (props: {
    tenant: Pick<Tenant, 'name' | 'slug'>;
    translations: Translations;
}) => ({
    breadcrumbs: [
        {
            title: translate(props.translations, 'tenants.index.title'),
            href: tenantsIndex(),
        },
        {
            title: props.tenant.name,
            href: edit(props.tenant.slug),
        },
        {
            title: translate(props.translations, 'units.title'),
            href: index(props.tenant.slug),
        },
    ],
});

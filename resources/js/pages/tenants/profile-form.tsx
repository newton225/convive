import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Info } from 'lucide-react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { PermissionModule } from '@/components/profiles/permission-module';
import { SubmitButton } from '@/components/submit-button';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { translate, useTranslation } from '@/hooks/use-translation';
import {
    moduleState,
    toggleModule,
    togglePermission,
} from '@/lib/profile-permissions';
import { index, store, update } from '@/routes/tenants/profiles';
import type {
    PermissionDomain,
    ProfileEditorProfile,
    Translations,
} from '@/types';

type Props = {
    tenant: { slug: string };
    profile: ProfileEditorProfile | null;
    catalogue: PermissionDomain[];
    heldPermissions: string[];
};

/**
 * L'editeur de profils (CLAUDE.md, « Profils et permissions ») : le nom du profil, puis un bloc par
 * module du back-office avec ses actions a cocher. Les regles de cochage vivent dans
 * `lib/profile-permissions` ; les regles d'autorisation (rien accorder qu'on ne detient pas, profil
 * systeme intouchable) sont rejouees par le serveur.
 */
export default function ProfileForm({
    tenant,
    profile,
    catalogue,
    heldPermissions,
}: Props) {
    const { t } = useTranslation();
    const form = useForm({
        name: profile?.name ?? '',
        description: profile?.description ?? '',
        requires_two_factor: profile?.requiresTwoFactor ?? false,
        permissions: profile?.permissions ?? [],
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (profile) {
            form.patch(update([tenant.slug, profile.id]).url);
        } else {
            form.post(store(tenant.slug).url);
        }
    };

    const permissionErrors = Object.entries(form.errors)
        .filter(
            ([key]) => key === 'permissions' || key.startsWith('permissions.'),
        )
        .map(([, message]) => message);

    const title = profile
        ? t('profiles.form.edit_title', { name: profile.name })
        : t('profiles.form.create_title');

    return (
        <>
            <Head title={title} />

            <form
                onSubmit={submit}
                className="space-y-8"
                data-test="profile-form"
            >
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <Heading
                        variant="small"
                        title={title}
                        description={t('profiles.form.intro')}
                    />
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={index(tenant.slug)}>
                            <ArrowLeft /> {t('profiles.form.back')}
                        </Link>
                    </Button>
                </div>

                {profile && profile.memberCount > 0 ? (
                    <p className="bg-muted flex items-start gap-2 rounded-lg p-3 text-sm">
                        <Info className="mt-0.5 size-4 shrink-0" />
                        {t('profiles.form.members_notice', {
                            count: profile.memberCount,
                        })}
                    </p>
                ) : null}

                <div className="grid max-w-xl gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="profile-name">
                            {t('profiles.fields.name')}
                        </Label>
                        <Input
                            id="profile-name"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            placeholder={t('profiles.fields.name_placeholder')}
                            data-test="profile-name"
                            required
                        />
                        <InputError message={form.errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="profile-description">
                            {t('profiles.fields.description')}
                        </Label>
                        <Input
                            id="profile-description"
                            value={form.data.description}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
                            placeholder={t(
                                'profiles.fields.description_placeholder',
                            )}
                            data-test="profile-description"
                        />
                        <InputError message={form.errors.description} />
                    </div>

                    <div className="grid gap-1">
                        <label
                            htmlFor="profile-two-factor"
                            className="flex min-h-11 items-center gap-2 text-sm"
                        >
                            <Checkbox
                                id="profile-two-factor"
                                checked={form.data.requires_two_factor}
                                onCheckedChange={(checked) =>
                                    form.setData(
                                        'requires_two_factor',
                                        checked === true,
                                    )
                                }
                                data-test="profile-requires-two-factor"
                            />
                            {t('profiles.fields.requires_two_factor')}
                        </label>
                        <p className="text-muted-foreground text-xs">
                            {t('profiles.fields.requires_two_factor_hint')}
                        </p>
                    </div>
                </div>

                <section className="space-y-3" data-error-for="permissions">
                    <h2 className="text-base font-medium">
                        {t('profiles.form.modules')}
                    </h2>

                    <div className="grid gap-3 xl:grid-cols-2">
                        {catalogue.map((module) => (
                            <PermissionModule
                                key={module.value}
                                module={module}
                                selected={form.data.permissions}
                                held={heldPermissions}
                                state={moduleState(
                                    form.data.permissions,
                                    module,
                                )}
                                onToggleModule={(checked) =>
                                    form.setData(
                                        'permissions',
                                        toggleModule(
                                            form.data.permissions,
                                            module,
                                            checked,
                                            heldPermissions,
                                        ),
                                    )
                                }
                                onTogglePermission={(permission, checked) =>
                                    form.setData(
                                        'permissions',
                                        togglePermission(
                                            form.data.permissions,
                                            permission,
                                            checked,
                                            catalogue,
                                            heldPermissions,
                                        ),
                                    )
                                }
                            />
                        ))}
                    </div>

                    {permissionErrors.map((message) => (
                        <InputError key={message} message={message} />
                    ))}
                </section>

                <div className="flex flex-wrap gap-2">
                    <SubmitButton
                        processing={form.processing}
                        data-test="profile-submit"
                    >
                        {t('common.actions.save')}
                    </SubmitButton>
                    <Button variant="secondary" asChild>
                        <Link href={index(tenant.slug)}>
                            {t('common.actions.cancel')}
                        </Link>
                    </Button>
                </div>
            </form>
        </>
    );
}

ProfileForm.layout = (props: {
    tenant: { slug: string };
    profile: ProfileEditorProfile | null;
    translations: Translations;
}) => ({
    wide: true,
    breadcrumbs: [
        {
            title: translate(props.translations, 'profiles.title'),
            href: index(props.tenant.slug),
        },
        {
            title: props.profile
                ? props.profile.name
                : translate(props.translations, 'profiles.form.create_title'),
            href: index(props.tenant.slug),
        },
    ],
});

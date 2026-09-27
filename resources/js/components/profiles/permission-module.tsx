import { Checkbox } from '@/components/ui/checkbox';
import { useTranslation } from '@/hooks/use-translation';
import type { ModuleState } from '@/lib/profile-permissions';
import type { PermissionDomain, PermissionOption } from '@/types';

type Props = {
    module: PermissionDomain;
    selected: string[];
    held: string[];
    state: ModuleState;
    disabled?: boolean;
    onToggleModule: (checked: boolean) => void;
    onTogglePermission: (
        permission: PermissionOption,
        checked: boolean,
    ) => void;
};

/**
 * Un module de l'editeur de profils : son nom, une case « Tout », puis ses actions a cocher. Une
 * action que l'acteur ne detient pas est grisee et dit pourquoi : il ne peut pas l'accorder.
 */
export function PermissionModule({
    module,
    selected,
    held,
    state,
    disabled = false,
    onToggleModule,
    onTogglePermission,
}: Props) {
    const { t } = useTranslation();
    const moduleId = `module-${module.value}`;

    return (
        <div
            role="group"
            aria-labelledby={`${moduleId}-title`}
            className="bg-muted/40 space-y-3 rounded-lg p-4"
            data-test={`profile-module-${module.value}`}
        >
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h3 id={`${moduleId}-title`} className="font-medium">
                    {module.label}
                </h3>
                <label
                    htmlFor={moduleId}
                    className="text-muted-foreground flex min-h-11 items-center gap-2 text-sm"
                >
                    <Checkbox
                        id={moduleId}
                        checked={
                            state === 'all'
                                ? true
                                : state === 'some'
                                  ? 'indeterminate'
                                  : false
                        }
                        disabled={disabled}
                        onCheckedChange={(checked) =>
                            onToggleModule(checked === true)
                        }
                        data-test="profile-module-all"
                    />
                    {t('profiles.form.all')}
                </label>
            </div>

            <div className="flex flex-wrap gap-x-5 gap-y-1">
                {module.permissions.map((permission) => {
                    const grantable = held.includes(permission.value);
                    const id = `permission-${permission.value}`;

                    return (
                        <label
                            key={permission.value}
                            htmlFor={id}
                            title={
                                grantable
                                    ? permission.label
                                    : t('profiles.errors.permission_not_held')
                            }
                            className={
                                grantable
                                    ? 'flex min-h-11 items-center gap-2 text-sm'
                                    : 'text-muted-foreground flex min-h-11 items-center gap-2 text-sm'
                            }
                        >
                            <Checkbox
                                id={id}
                                checked={selected.includes(permission.value)}
                                disabled={disabled || !grantable}
                                onCheckedChange={(checked) =>
                                    onTogglePermission(
                                        permission,
                                        checked === true,
                                    )
                                }
                                data-test="profile-permission"
                                data-permission={permission.value}
                            />
                            {permission.action}
                        </label>
                    );
                })}
            </div>
        </div>
    );
}

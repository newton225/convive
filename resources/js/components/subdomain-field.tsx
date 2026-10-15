import { useState } from 'react';
import InputError from '@/components/input-error';
import { LabelWithHelp } from '@/components/label-with-help';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/use-translation';

type Props = {
    defaultValue: string;
    publicDomain: string;
    error?: string;
};

/**
 * Le champ « sous-domaine » de l'organisation. Un sous-domaine ne contient que des minuscules : la
 * saisie est passee en minuscules au fil de l'eau (le serveur le fait aussi, avant de valider), et
 * l'adresse que vos invites recevront se lit sous le champ, avec le vrai domaine de l'application.
 * Tant que le champ est vide, l'adresse montrée est un exemple.
 */
export function SubdomainField({ defaultValue, publicDomain, error }: Props) {
    const { t } = useTranslation();
    const [value, setValue] = useState(defaultValue);

    const example = t('organisation.placeholders.subdomain');
    const typed = value.trim() !== '';

    return (
        <div className="grid max-w-md gap-2">
            <LabelWithHelp
                htmlFor="subdomain"
                required
                label={t('organisation.fields.subdomain')}
                help={t('organisation.help.subdomain')}
            />
            <Input
                id="subdomain"
                name="subdomain"
                data-test="organisation-subdomain"
                value={value}
                onChange={(event) => setValue(event.target.value.toLowerCase())}
                placeholder={example}
                aria-describedby="subdomain-preview"
                autoCapitalize="none"
                autoComplete="off"
                spellCheck={false}
            />
            <p
                id="subdomain-preview"
                aria-live="polite"
                className="text-muted-foreground text-sm"
            >
                {typed
                    ? t('organisation.help.subdomain_preview')
                    : t('organisation.help.subdomain_example')}{' '}
                <strong
                    data-test="organisation-subdomain-address"
                    className={typed ? 'text-foreground' : undefined}
                >
                    {`${typed ? value : example}.${publicDomain}`}
                </strong>
            </p>
            <InputError message={error} />
        </div>
    );
}

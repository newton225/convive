import { ExternalLink } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { LabelWithHelp } from '@/components/label-with-help';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/use-translation';

type Props = {
    defaultValue: string;
    error?: string;
};

// Seul un lien https se teste : des coordonnees sont converties en lien par le serveur a
// l'enregistrement, et un autre schema (javascript:) ne doit jamais devenir un lien cliquable.
function readableHost(value: string): string | null {
    try {
        const url = new URL(value.trim());

        return url.protocol === 'https:' ? url.hostname : null;
    } catch {
        return null;
    }
}

export function VenueMapField({ defaultValue, error }: Props) {
    const { t } = useTranslation();
    const [value, setValue] = useState(defaultValue);
    const host = readableHost(value);

    return (
        <div className="grid gap-2">
            <LabelWithHelp
                htmlFor="venue_map_url"
                label={t('events.fields.venue_map_url')}
                help={t('events.help.venue_map_url')}
            />
            <Input
                id="venue_map_url"
                name="venue_map_url"
                data-test="event-venue_map_url"
                value={value}
                placeholder={t('events.fields.venue_map_url_placeholder')}
                onChange={(event) => setValue(event.target.value)}
            />
            {host ? (
                <div className="flex flex-wrap items-center gap-3">
                    <Button variant="outline" size="sm" asChild>
                        <a
                            href={value.trim()}
                            target="_blank"
                            rel="noopener noreferrer"
                            data-test="event-venue-map-preview"
                        >
                            <ExternalLink className="size-4" />
                            {t('events.venue_map.preview')}
                        </a>
                    </Button>
                    <span className="text-muted-foreground text-xs">
                        {t('events.venue_map.host', { host })}
                    </span>
                </div>
            ) : null}
            <InputError message={error} />
        </div>
    );
}

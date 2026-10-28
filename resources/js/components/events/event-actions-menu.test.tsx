import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vite-plus/test';
import { EventActionsMenu } from '@/components/events/event-actions-menu';
import type { EventListItem, TenantPermissions } from '@/types';

const post = vi.fn();

vi.mock('@inertiajs/react', () => ({
    Link: ({ children, href }: { children: React.ReactNode; href: string }) => (
        <a href={href}>{children}</a>
    ),
    router: { post: (...args: unknown[]) => post(...args) },
}));

vi.mock('@/hooks/use-translation', () => ({
    useTranslation: () => ({
        t: (key: string, params?: Record<string, string>) =>
            params?.name ? `${key}:${params.name}` : key,
        locale: 'fr',
    }),
}));

vi.mock('@/hooks/use-file-download', () => ({
    useFileDownload: () => ({ download: vi.fn() }),
}));

vi.mock('@/hooks/use-getting-started-return', () => ({
    useGettingStartedReturn: () => undefined,
}));

vi.mock('@/lib/permissions', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@/lib/permissions')>()),
    can: () => true,
}));

const event = {
    id: 7,
    name: 'Gala de la Jeunesse',
    status: 'open',
    seatsAtTables: true,
    publicUrl: null,
} as unknown as EventListItem;

const permissions = [] as unknown as TenantPermissions;

const byTest = (id: string) =>
    document.querySelector<HTMLElement>('[data-test="' + id + '"]');

const find = (id: string) =>
    waitFor(() => {
        const element = byTest(id);

        if (!element) {
            throw new Error(id);
        }

        return element;
    });

async function openMenu() {
    await userEvent.click(byTest('event-actions') as HTMLElement);
}

describe('EventActionsMenu : dupliquer', () => {
    beforeEach(() => {
        post.mockClear();
    });

    it("demande une confirmation et ne duplique rien tant qu'elle n'est pas donnee", async () => {
        render(
            <EventActionsMenu
                tenantSlug="acme"
                event={event}
                permissions={permissions}
                onClose={() => undefined}
            />,
        );

        await openMenu();
        fireEvent.click(await find('event-duplicate'));

        await find('event-duplicate-dialog');

        expect(
            screen.getByText(
                'events.confirm_duplicate.description:Gala de la Jeunesse',
            ),
        ).toBeTruthy();
        expect(post).not.toHaveBeenCalled();
    });

    it('duplique une fois la confirmation donnee', async () => {
        render(
            <EventActionsMenu
                tenantSlug="acme"
                event={event}
                permissions={permissions}
                onClose={() => undefined}
            />,
        );

        await openMenu();
        fireEvent.click(await find('event-duplicate'));
        await find('event-duplicate-dialog');

        fireEvent.click(
            screen.getByRole('button', {
                name: 'events.confirm_duplicate.confirm',
            }),
        );

        await waitFor(() => expect(post).toHaveBeenCalledTimes(1));
        expect(String(post.mock.calls[0][0])).toContain('/events/7/duplicate');
    });

    it('ne duplique rien quand on annule', async () => {
        render(
            <EventActionsMenu
                tenantSlug="acme"
                event={event}
                permissions={permissions}
                onClose={() => undefined}
            />,
        );

        await openMenu();
        fireEvent.click(await find('event-duplicate'));
        await find('event-duplicate-dialog');

        fireEvent.click(
            screen.getByRole('button', { name: 'common.actions.cancel' }),
        );

        await waitFor(() =>
            expect(byTest('event-duplicate-dialog')).toBeNull(),
        );
        expect(post).not.toHaveBeenCalled();
    });
});

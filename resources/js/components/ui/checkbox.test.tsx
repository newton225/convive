import { fireEvent, render } from '@testing-library/react';
import { describe, expect, it, vi } from 'vite-plus/test';
import { Checkbox } from '@/components/ui/checkbox';

describe('Checkbox', () => {
    it('previent le formulaire quand on la coche', async () => {
        vi.useFakeTimers();
        const onInput = vi.fn();
        const { getByRole } = render(
            <form onInput={onInput}>
                <Checkbox name="accounts[]" value="1" />
            </form>,
        );

        fireEvent.click(getByRole('checkbox'));
        vi.runAllTimers();

        expect(onInput).toHaveBeenCalledTimes(1);

        vi.useRealTimers();
    });
});

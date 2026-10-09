import { fireEvent, render } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { Checkbox } from '@/components/ui/checkbox';

// Radix mesure la case quand elle est dans un formulaire, jsdom n'a pas ResizeObserver.
class ResizeObserverStub {
    observe() {}
    unobserve() {}
    disconnect() {}
}

describe('Checkbox', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('previent le formulaire quand on la coche', async () => {
        vi.stubGlobal('ResizeObserver', ResizeObserverStub);
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

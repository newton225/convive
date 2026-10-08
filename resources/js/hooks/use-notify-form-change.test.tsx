import { render } from '@testing-library/react';
import { useState } from 'react';
import { describe, expect, it, vi } from 'vite-plus/test';
import { useNotifyFormChange } from '@/hooks/use-notify-form-change';

function Field({ onInput }: { onInput: () => void }) {
    const [rows, setRows] = useState(1);
    const element = useNotifyFormChange<HTMLDivElement>(rows);

    return (
        <form onInput={onInput}>
            <div ref={element}>
                <button type="button" onClick={() => setRows(rows + 1)}>
                    ajouter
                </button>
            </div>
        </form>
    );
}

describe('useNotifyFormChange', () => {
    it("n'envoie rien au premier rendu", () => {
        const onInput = vi.fn();

        render(<Field onInput={onInput} />);

        expect(onInput).not.toHaveBeenCalled();
    });

    it('previent le formulaire quand la valeur suivie change', async () => {
        const onInput = vi.fn();
        const { getByText } = render(<Field onInput={onInput} />);

        getByText('ajouter').click();
        await Promise.resolve();

        expect(onInput).toHaveBeenCalledTimes(1);
    });
});

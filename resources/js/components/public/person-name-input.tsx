import type { ComponentProps, FocusEvent, InputEvent } from 'react';
import { Input } from '@/components/ui/input';
import { finishPersonName, sanitizePersonName } from '@/lib/guest-input';

/**
 * Un nom de personne : les chiffres et les symboles ne s'ecrivent pas, jamais deux separateurs de suite, et un
 * collage est nettoye. Le serveur revalide (`App\Rules\PersonName`).
 */
export function PersonNameInput({
    onInput,
    onBlur,
    ...props
}: ComponentProps<typeof Input>) {
    function handleInput(event: InputEvent<HTMLInputElement>) {
        const input = event.currentTarget;
        const clean = sanitizePersonName(input.value);

        if (clean !== input.value) {
            const caret = Math.max(
                0,
                (input.selectionStart ?? clean.length) -
                    (input.value.length - clean.length),
            );

            input.value = clean;
            input.setSelectionRange(caret, caret);
        }

        onInput?.(event);
    }

    // A la sortie du champ, un separateur laisse a la fin ("Jean-") est retire.
    function handleBlur(event: FocusEvent<HTMLInputElement>) {
        event.currentTarget.value = finishPersonName(event.currentTarget.value);
        onBlur?.(event);
    }

    return (
        <Input
            type="text"
            autoComplete="name"
            maxLength={255}
            {...props}
            onInput={handleInput}
            onBlur={handleBlur}
        />
    );
}

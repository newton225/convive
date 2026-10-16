import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/use-translation';
import type { ProofRejectionReasonOption } from '@/types';

type Props = {
    reasons: ProofRejectionReasonOption[];
    reason: string;
    note: string;
    onReasonChange: (reason: string) => void;
    onNoteChange: (note: string) => void;
    errors: { reason?: string; note?: string };
};

/**
 * Le motif d'un rejet de preuve : un choix dans le catalogue, et une precision ecrite pour « autre ».
 * L'invite le lira sur la page de son dossier (decision du 2026-10-16).
 */
export function ProofRejectionFields({
    reasons,
    reason,
    note,
    onReasonChange,
    onNoteChange,
    errors,
}: Props) {
    const { t } = useTranslation();
    const requiresNote =
        reasons.find((option) => option.value === reason)?.requiresNote ??
        false;

    return (
        <div className="space-y-3" data-test="proof-rejection-fields">
            <div className="space-y-2">
                <Label htmlFor="proof-rejection-reason" required>
                    {t('proofs.rejection.reason')}
                </Label>
                <Select value={reason} onValueChange={onReasonChange}>
                    <SelectTrigger
                        id="proof-rejection-reason"
                        className="min-h-11 w-full"
                        data-test="proof-rejection-reason"
                    >
                        <SelectValue
                            placeholder={t(
                                'proofs.rejection.reason_placeholder',
                            )}
                        />
                    </SelectTrigger>
                    <SelectContent>
                        {reasons.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.reason} />
            </div>

            {requiresNote ? (
                <div className="space-y-2">
                    <Label htmlFor="proof-rejection-note" required>
                        {t('proofs.rejection.note')}
                    </Label>
                    <Textarea
                        id="proof-rejection-note"
                        value={note}
                        maxLength={300}
                        onChange={(event) => onNoteChange(event.target.value)}
                        placeholder={t('proofs.rejection.note_hint')}
                        data-test="proof-rejection-note"
                    />
                    <InputError message={errors.note} />
                </div>
            ) : null}
        </div>
    );
}

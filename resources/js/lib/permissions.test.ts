import { describe, expect, it } from 'vite-plus/test';
import { can, Permission } from '@/lib/permissions';

describe('can', () => {
    const treasurer = {
        values: [Permission.ProofsView, Permission.ProofsApprove],
    };

    it('dit vrai pour une permission détenue', () => {
        expect(can(treasurer, Permission.ProofsApprove)).toBe(true);
    });

    it('dit faux pour une permission non détenue', () => {
        expect(can(treasurer, Permission.TenantPaymentAccounts)).toBe(false);
    });

    it('dit faux pour un profil sans aucune permission', () => {
        expect(can({ values: [] }, Permission.EventsView)).toBe(false);
    });
});

import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import BidangTeks from './BidangTeks';

describe('BidangTeks (PRD §17.6 aksesibilitas)', () => {
    it('menghubungkan label ke input dan meneruskan perubahan nilai', () => {
        const SaatBerubah = vi.fn();
        render(<BidangTeks label="Email" nilai="" saatBerubah={SaatBerubah} />);

        fireEvent.change(screen.getByLabelText('Email'), { target: { value: 'rina@contoh.id' } });

        expect(SaatBerubah).toHaveBeenCalledWith('rina@contoh.id');
    });

    it('menandai input tidak valid dan mengaitkan pesan galat', () => {
        render(<BidangTeks label="Kode" nilai="1" saatBerubah={() => undefined} galat="Kode tidak cocok." />);

        const input = screen.getByLabelText('Kode');
        const galat = screen.getByText('Kode tidak cocok.');

        expect(input.getAttribute('aria-invalid')).toBe('true');
        expect(input.getAttribute('aria-describedby')).toContain(galat.id);
    });
});

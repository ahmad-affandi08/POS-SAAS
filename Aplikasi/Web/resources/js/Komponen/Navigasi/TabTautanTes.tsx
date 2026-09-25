import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import TabTautan, { KelasItemTab, kelasItemTabPanel } from './TabTautan';

vi.mock('@inertiajs/react', async () => (await import('@/Komponen/Katalog/TiruanInertia')).TiruanInertia);

afterEach(() => cleanup());

describe('TabTautan: satu gaya tab untuk seluruh web', () => {
    it('tab aktif ditandai aria-current dan garis bawah brand; tab lain tidak', () => {
        render(
            <TabTautan
                label="Bagian produk"
                tab={[
                    { label: 'Ringkasan', href: '/kelola/produk/1', aktif: true },
                    { label: 'Harga', href: '/kelola/produk/1/harga', aktif: false },
                ]}
            />,
        );

        const aktif = screen.getByRole('link', { name: 'Ringkasan' });
        const lain = screen.getByRole('link', { name: 'Harga' });
        expect(screen.getByRole('navigation', { name: 'Bagian produk' })).toBeTruthy();
        expect(aktif.getAttribute('aria-current')).toBe('page');
        expect(aktif.className).toContain('border-brand');
        expect(lain.getAttribute('aria-current')).toBeNull();
        expect(lain.className).toContain('border-transparent');
    });

    it('tab panel (Radix) memakai ukuran & tanda aktif yang sama dengan tab tautan', () => {
        for (const kelas of ['border-b-2', 'px-3', 'py-2', 'text-label', 'font-semibold']) {
            expect(KelasItemTab(true)).toContain(kelas);
            expect(kelasItemTabPanel).toContain(kelas);
        }
        expect(kelasItemTabPanel).toContain('data-[state=active]:border-brand');
    });
});

import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { Badge } from '@/Komponen/Ui/badge';
import { Button } from '@/Komponen/Ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Komponen/Ui/card';

/*
 * Semua komponen shadcn/ui terpasang (Komponen/Ui) belum dipakai halaman mana pun, sehingga `vite build`
 * tidak memuatnya. Test ini memastikan setiap modul benar-benar bisa dimuat (dependensi npm lengkap) dan
 * bahwa komponen memakai utilitas token (bukan warna lepas).
 */
const modulUi = import.meta.glob<Record<string, unknown>>('./Ui/*.{ts,tsx}', { eager: true });

describe('komponen shadcn/ui', () => {
    it('semua modul Komponen/Ui dapat dimuat dan mengekspor sesuatu', () => {
        const daftar = Object.entries(modulUi);
        expect(daftar.length).toBeGreaterThanOrEqual(62);
        for (const [path, modul] of daftar) {
            expect(Object.keys(modul).length, `${path} tidak mengekspor apa pun`).toBeGreaterThan(0);
        }
    });

    it('tombol, lencana, dan kartu memakai utilitas token tema', () => {
        render(
            <Card>
                <CardHeader>
                    <CardTitle>Ringkasan penjualan</CardTitle>
                </CardHeader>
                <CardContent>
                    <Badge variant="destructive">Void</Badge>
                    <Button>Simpan produk</Button>
                </CardContent>
            </Card>,
        );

        expect(screen.getByRole('button', { name: 'Simpan produk' }).className).toContain('bg-primary');
        expect(screen.getByText('Void').className).toContain('text-destructive-foreground');
        expect(screen.getByText('Ringkasan penjualan').closest('[data-slot="card"]')?.className).toContain('bg-card');
    });
});

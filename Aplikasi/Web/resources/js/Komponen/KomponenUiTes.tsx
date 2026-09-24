import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { Badge } from '@/Komponen/Ui/badge';
import { Button } from '@/Komponen/Ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Komponen/Ui/card';
import { Dialog, DialogContent, DialogTitle } from '@/Komponen/Ui/dialog';
import {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationItem,
    PaginationNext,
    PaginationPrevious,
} from '@/Komponen/Ui/pagination';
import { Sheet, SheetContent, SheetTitle } from '@/Komponen/Ui/sheet';
import { Spinner } from '@/Komponen/Ui/spinner';

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

    it('label pembaca layar komponen shadcn/ui berbahasa Indonesia', () => {
        const dialog = render(
            <Dialog open>
                <DialogContent aria-describedby={undefined}>
                    <DialogTitle>Tambah merek</DialogTitle>
                </DialogContent>
            </Dialog>,
        );
        expect(screen.getByRole('button', { name: 'Tutup' })).toBeTruthy();
        expect(screen.queryByText('Close')).toBeNull();
        dialog.unmount();

        render(
            <Sheet open>
                <SheetContent aria-describedby={undefined}>
                    <SheetTitle>Filter</SheetTitle>
                </SheetContent>
            </Sheet>,
        );
        expect(screen.getByRole('button', { name: 'Tutup' })).toBeTruthy();
        expect(screen.queryByText('Close')).toBeNull();
    });

    it('paginasi dan indikator memuat berbahasa Indonesia', () => {
        render(
            <>
                <Pagination>
                    <PaginationContent>
                        <PaginationItem>
                            <PaginationPrevious href="#" />
                        </PaginationItem>
                        <PaginationItem>
                            <PaginationEllipsis />
                        </PaginationItem>
                        <PaginationItem>
                            <PaginationNext href="#" />
                        </PaginationItem>
                    </PaginationContent>
                </Pagination>
                <Spinner />
            </>,
        );

        expect(screen.getByRole('navigation', { name: 'Paginasi' })).toBeTruthy();
        expect(screen.getByRole('link', { name: 'Ke halaman sebelumnya' })).toBeTruthy();
        expect(screen.getByRole('link', { name: 'Ke halaman berikutnya' })).toBeTruthy();
        expect(screen.getByText('Halaman lainnya')).toBeTruthy();
        expect(screen.getByRole('status', { name: 'Memuat' })).toBeTruthy();
    });
});

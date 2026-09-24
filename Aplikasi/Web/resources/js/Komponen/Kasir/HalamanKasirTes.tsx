import { cleanup, fireEvent, render, screen, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import HalamanKategoriKas from '@/Halaman/Kelola/Kasir/KategoriKas';
import HalamanPengaturanKasir, { UbahKeMasukanUang } from '@/Halaman/Kelola/Kasir/Pengaturan';
import HalamanDaftarShift, { BuatQueryShift } from '@/Halaman/Kelola/Kasir/Shift/Daftar';
import HalamanDetailShift from '@/Halaman/Kelola/Kasir/Shift/Detail';
import { AturHalamanUji, tiruanRouter } from '@/Komponen/Katalog/TiruanInertia';
import type { BarisShift, PropsDaftarShift, PropsDetailShift, PropsKategoriKas, SaringShift } from '@/Tipe/Kasir';

vi.mock('@inertiajs/react', async () => (await import('@/Komponen/Katalog/TiruanInertia')).TiruanInertia);

const saringKosong: SaringShift = { UuidOutlet: null, Status: null, Dari: '', Sampai: '', PerluTinjauan: false };

const barisShift: BarisShift = {
    Uuid: '01K5SHIFT00000000000000001',
    NamaOutlet: 'Kopi Senja Solo Baru',
    Perangkat: 'POS-001 — Kasir Depan',
    NamaKasir: 'Rina Wulandari',
    DibukaPada: '2026-09-24T01:15:00Z',
    TanggalBisnis: '2026-09-24',
    Status: 'Terbuka',
    LabelStatus: 'Terbuka',
    Bersama: false,
    PerluTinjauan: true,
    KasAwal: '1250000.00',
    TotalMasuk: '20000.00',
    TotalKeluar: '45000.00',
    TotalSetoran: '300000.00',
    KasNonPenjualan: '925000.00',
};

function PropsDaftar(data: BarisShift[], saring = saringKosong): PropsDaftarShift {
    return {
        Shift: { Data: data, HalamanSaatIni: 1, HalamanTerakhir: 1, Total: data.length },
        Saring: saring,
        OpsiOutlet: [{ Uuid: '01K5OUTLET0000000000000001', Nama: 'Kopi Senja Solo Baru' }],
        OpsiStatus: [{ Nilai: 'Terbuka', Label: 'Terbuka' }],
    };
}

describe('F-06 halaman kasir back-office', () => {
    beforeEach(() => AturHalamanUji({}, '/kelola/kasir/shift'));
    afterEach(() => cleanup());

    it('BuatQueryShift hanya mengirim saringan yang terisi', () => {
        expect(BuatQueryShift(saringKosong)).toEqual({});
        expect(
            BuatQueryShift({
                UuidOutlet: 'X',
                Status: 'Tertutup',
                Dari: '2026-09-01',
                Sampai: '2026-09-30',
                PerluTinjauan: true,
            }),
        ).toEqual({ outlet: 'X', status: 'Tertutup', dari: '2026-09-01', sampai: '2026-09-30', tinjauan: '1' });
    });

    it('daftar shift: Rupiah terformat, penanda perlu ditinjau, tautan ke detail; keadaan kosong', () => {
        render(<HalamanDaftarShift {...PropsDaftar([barisShift])} />);
        const tabel = screen.getByRole('table');

        expect(within(tabel).getByText('Rp 1.250.000')).toBeTruthy();
        expect(within(tabel).getByText('Perlu ditinjau')).toBeTruthy();
        expect(within(tabel).getByRole('link').getAttribute('href')).toBe(`/kelola/kasir/shift/${barisShift.Uuid}`);

        cleanup();
        render(<HalamanDaftarShift {...PropsDaftar([])} />);
        expect(screen.getByText(/Belum ada shift/)).toBeTruthy();
    });

    it('detail shift: alasan tinjauan, pecahan kas awal, mutasi dengan tautan jurnal; tanpa mutasi = keadaan kosong', () => {
        const props: PropsDetailShift = {
            Shift: {
                ...barisShift,
                DiterimaPada: '2026-09-24T05:00:00Z',
                AlasanTinjauan: 'BR-06.1: kasir sudah punya shift terbuka di perangkat lain.',
                PecahanKasAwal: [{ Nominal: '100000', Jumlah: 12 }],
            },
            MutasiKas: [
                {
                    Uuid: '01K5MUTASI0000000000000001',
                    Jenis: 'Keluar',
                    LabelJenis: 'Kas keluar',
                    NamaKategori: 'Beli es batu & galon',
                    Jumlah: '45000.00',
                    Catatan: 'Es batu 3 karung',
                    DicatatOleh: 'Rina Wulandari',
                    DicatatPada: '2026-09-24T03:00:00Z',
                    DisetujuiOleh: 'Budi Santoso',
                    NomorJurnal: 'JU/2026/09/000007',
                    UuidJurnal: '01K5JURNAL0000000000000007',
                },
            ],
        };

        render(<HalamanDetailShift {...props} />);
        expect(screen.getByText(/kasir sudah punya shift terbuka/)).toBeTruthy();
        expect(screen.getByText('Rp 100.000 × 12')).toBeTruthy();
        expect(screen.getByText('Disetujui Budi Santoso')).toBeTruthy();
        expect(screen.getByRole('link', { name: 'JU/2026/09/000007' }).getAttribute('href')).toBe(
            '/kelola/akuntansi/jurnal/01K5JURNAL0000000000000007',
        );

        cleanup();
        render(<HalamanDetailShift {...props} MutasiKas={[]} />);
        expect(screen.getByText(/Belum ada kas masuk/)).toBeTruthy();
    });

    it('kategori kas: keadaan kosong dan formulir tambah mengirim nama, jenis, akun', () => {
        const props: PropsKategoriKas = {
            Kategori: [],
            OpsiAkun: {
                Keluar: [
                    { Uuid: '01K5AKUN000000000000000001', Kode: '6-9000', Nama: 'Beban Lain-lain', Jenis: 'Beban' },
                ],
                Masuk: [],
            },
        };
        tiruanRouter.post.mockClear();
        render(<HalamanKategoriKas {...props} />);
        expect(screen.getByText(/Belum ada kategori kas/)).toBeTruthy();

        fireEvent.click(screen.getByRole('button', { name: 'Tambah kategori kas' }));
        fireEvent.change(screen.getByLabelText('Nama kategori'), { target: { value: 'Bayar parkir motor' } });
        fireEvent.change(screen.getByLabelText('Akun jurnal'), { target: { value: '01K5AKUN000000000000000001' } });
        fireEvent.click(screen.getByRole('button', { name: 'Simpan kategori' }));

        expect(tiruanRouter.post).toHaveBeenCalledWith(
            '/kelola/kasir/kategori-kas',
            { Nama: 'Bayar parkir motor', Jenis: 'Keluar', UuidAkun: '01K5AKUN000000000000000001' },
            expect.anything(),
        );
    });

    it('pengaturan kasir: batas tanpa float ("200000.00" → "200000") dan tombol simpan aktif setelah berubah', () => {
        expect(UbahKeMasukanUang('200000.00')).toBe('200000');
        expect(UbahKeMasukanUang('150000.50')).toBe('150000.50');

        render(<HalamanPengaturanKasir BatasKasKeluar="200000.00" ShiftBersama={false} />);
        const simpan = screen.getByRole('button', { name: 'Simpan pengaturan' });
        expect(simpan.hasAttribute('disabled')).toBe(true);

        fireEvent.click(screen.getByRole('checkbox'));
        expect(simpan.hasAttribute('disabled')).toBe(false);
    });
});

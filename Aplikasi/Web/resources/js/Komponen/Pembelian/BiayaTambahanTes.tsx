import { cleanup, fireEvent, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import HalamanBuatBiayaTambahan from '@/Halaman/Kelola/Pembelian/BiayaTambahan/Buat';
import HalamanDaftarBiayaTambahan from '@/Halaman/Kelola/Pembelian/BiayaTambahan/Daftar';
import HalamanDetailBiayaTambahan from '@/Halaman/Kelola/Pembelian/BiayaTambahan/Detail';
import { AturHalamanUji, kirimanForm, RenderUji } from '@/Komponen/Katalog/TiruanInertia';
import { BuatHasilTabel } from '@/Komponen/Persediaan/DataUjiPersediaan';

vi.mock('@inertiajs/react', async () => (await import('@/Komponen/Katalog/TiruanInertia')).TiruanInertia);

const Izin = { Kelola: true, Setujui: false, LihatJurnal: true, Persediaan: true };
const Grn = {
    Uuid: '01J9GRN0000000000000000001',
    Nomor: 'GR/SB/2610/0001',
    Tanggal: '2026-10-01',
    Status: 'Diposting' as const,
    NamaGudang: 'Gudang Utama',
    NamaPemasok: 'PT Sumber Pangan',
    Baris: [
        {
            Id: 1,
            NamaProduk: 'Beras Premium 5 kg',
            Sku: 'BRS-5',
            Jumlah: '10.0000',
            Nilai: '20000.00',
            Pelacakan: false,
        },
    ],
};

describe('Biaya tambahan pembelian (v3.41)', () => {
    beforeEach(() => {
        AturHalamanUji({}, '/kelola/pembelian/biaya-tambahan');
        window.history.replaceState({}, '', '/kelola/pembelian/biaya-tambahan');
    });
    afterEach(() => cleanup());

    it('daftar: porsi nilai stok & HPP, tautan rincian', () => {
        RenderUji(
            <HalamanDaftarBiayaTambahan
                Biaya={BuatHasilTabel([
                    {
                        Uuid: '01J9BY00000000000000000001',
                        Nomor: 'BY/2610/0001',
                        Tanggal: '2026-10-01',
                        Jenis: 'Ongkir',
                        LabelJenis: 'Ongkos kirim/ekspedisi',
                        NomorPenerimaan: Grn.Nomor,
                        NamaPenagih: 'JNE Trucking',
                        Jumlah: '1000.00',
                        KePersediaan: '700.00',
                        KeHpp: '300.00',
                        Status: 'Diposting',
                    },
                ])}
                OpsiJenis={[{ Nilai: 'Ongkir', Label: 'Ongkos kirim/ekspedisi' }]}
                OpsiStatus={[{ Nilai: 'Diposting', Label: 'Diposting' }]}
                Izin={Izin}
            />,
        );
        expect(screen.getAllByText('Rp 700').length).toBeGreaterThan(0);
        expect(screen.getAllByRole('link', { name: 'BY/2610/0001' })[0]?.getAttribute('href')).toBe(
            '/kelola/pembelian/biaya-tambahan/01J9BY00000000000000000001',
        );
    });

    it('buat: mengirim biaya untuk penerimaan dengan akun kas bawaan', () => {
        RenderUji(
            <HalamanBuatBiayaTambahan
                Penerimaan={Grn}
                OpsiJenis={[{ Nilai: 'Ongkir', Label: 'Ongkos kirim/ekspedisi' }]}
                OpsiDasar={[{ Nilai: 'Nilai', Label: 'Sebanding nilai barang' }]}
                OpsiAkun={[{ Uuid: '01J9AKN0000000000000000001', Kode: '1-1100', Nama: 'Kas Outlet' }]}
                OpsiPemasok={[]}
                HariIni="2026-10-01"
            />,
        );
        fireEvent.change(screen.getByLabelText(/^Jumlah/), { target: { value: '350000' } });
        fireEvent.click(screen.getByRole('button', { name: 'Simpan biaya tambahan' }));
        expect(kirimanForm.at(-1)).toMatchObject({
            metode: 'post',
            url: '/kelola/pembelian/biaya-tambahan',
            data: {
                UuidPenerimaan: Grn.Uuid,
                Jenis: 'Ongkir',
                DasarAlokasi: 'Nilai',
                UuidAkun: '01J9AKN0000000000000000001',
            },
        });
    });

    it('rincian: tombol batal hanya bila diizinkan', () => {
        const props = {
            Biaya: {
                Uuid: '01J9BY00000000000000000001',
                Nomor: 'BY/2610/0001',
                Tanggal: '2026-10-01',
                Jenis: 'Ongkir' as const,
                LabelJenis: 'Ongkos kirim/ekspedisi',
                LabelDasarAlokasi: 'Sebanding nilai barang',
                Jumlah: '1000.00',
                KePersediaan: '700.00',
                KeHpp: '300.00',
                Status: 'Diposting' as const,
                Catatan: null,
                AlasanBatal: null,
                NamaPenagih: null,
                UuidPenerimaan: Grn.Uuid,
                NomorPenerimaan: Grn.Nomor,
                NamaGudang: 'Gudang Utama',
            },
            Baris: [
                {
                    Id: 1,
                    NamaProduk: 'Beras Premium 5 kg',
                    Sku: 'BRS-5',
                    Alokasi: '1000.00',
                    KePersediaan: '700.00',
                    KeHpp: '300.00',
                },
            ],
            Jurnal: [],
            Izin,
        };
        RenderUji(<HalamanDetailBiayaTambahan {...props} Tindakan={{ Batalkan: true }} />);
        expect(screen.getByRole('button', { name: 'Batalkan biaya' })).toBeTruthy();
        cleanup();
        RenderUji(<HalamanDetailBiayaTambahan {...props} Tindakan={{ Batalkan: false }} />);
        expect(screen.queryByRole('button', { name: 'Batalkan biaya' })).toBeNull();
    });
});

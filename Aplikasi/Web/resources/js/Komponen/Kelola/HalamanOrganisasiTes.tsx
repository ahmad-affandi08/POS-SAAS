import { cleanup, fireEvent, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import HalamanDaftarOutlet from '@/Halaman/Kelola/Outlet/Daftar';
import HalamanDetailOutlet from '@/Halaman/Kelola/Outlet/Detail';
import HalamanPin from '@/Halaman/Kelola/Pin';
import { AturHalamanUji, RenderUji, tiruanRouter } from '@/Komponen/Katalog/TiruanInertia';

vi.mock('@inertiajs/react', async () => (await import('@/Komponen/Katalog/TiruanInertia')).TiruanInertia);

beforeEach(() => AturHalamanUji({}, '/kelola/outlet'));
afterEach(() => cleanup());

const batas = { Terpakai: 2, Batas: 5 };

describe('Kelola/Outlet (F-02 langkah 1, TabelData D-16)', () => {
    const outlet = [
        {
            Uuid: 'O-1',
            Kode: 'JKT1',
            Nama: 'Kopi Nusantara Sudirman',
            NamaMerek: 'Kopi Nusantara',
            NamaKota: 'Kota Jakarta Pusat',
            ZonaWaktu: 'WIB',
            JamTutupBuku: '04:00',
            JumlahGudang: 2,
            Status: 'Aktif' as const,
        },
        {
            Uuid: 'O-2',
            Kode: 'SBY1',
            Nama: 'Kopi Nusantara Tunjungan',
            NamaMerek: null,
            NamaKota: 'Kota Surabaya',
            ZonaWaktu: 'WIB',
            JamTutupBuku: '04:00',
            JumlahGudang: 1,
            Status: 'Diarsipkan' as const,
        },
    ];

    it('daftar outlet: tautan detail, status bertulis, dan cari menyaring baris', async () => {
        RenderUji(<HalamanDaftarOutlet Outlet={outlet} Merek={[]} Kota={[]} BatasOutlet={batas} />);

        expect(screen.getByRole('link', { name: 'Kopi Nusantara Sudirman' }).getAttribute('href')).toBe(
            '/kelola/outlet/O-1',
        );
        expect(screen.getByText('Diarsipkan')).toBeTruthy();

        fireEvent.change(screen.getByRole('searchbox'), { target: { value: 'Surabaya' } });
        await vi.waitFor(() => expect(screen.queryByText('Kopi Nusantara Sudirman')).toBeNull());
        expect(screen.getByText('Kopi Nusantara Tunjungan')).toBeTruthy();
    });

    it('detail outlet: lokasi stok diarsipkan lewat menu aksi baris', () => {
        RenderUji(
            <HalamanDetailOutlet
                Outlet={{
                    Uuid: 'O-1',
                    Kode: 'JKT1',
                    Nama: 'Kopi Nusantara Sudirman',
                    UuidMerek: null,
                    Alamat: null,
                    KodeKota: null,
                    ZonaWaktu: 'WIB',
                    JamTutupBuku: '04:00',
                    Pkp: false,
                    Nitku: null,
                    PungutPbjt: false,
                    Status: 'Aktif',
                    KodeTerkunci: true,
                }}
                Gudang={[{ Uuid: 'G-1', Kode: 'UTAMA', Nama: 'Gudang utama', Jenis: 'Jual', Status: 'Aktif' }]}
                Merek={[]}
                Kota={[]}
                JenisGudang={[{ Nilai: 'Jual', Label: 'Barang jual' }]}
                ModeMeja={{ Aktif: false, Area: [], Meja: [] }}
                BentukMeja={[]}
            />,
        );

        expect(screen.getByText('Barang jual')).toBeTruthy();
        // Tanpa mode meja & tanpa data meja: bagian meja tidak tampil.
        expect(screen.queryByRole('heading', { name: 'Meja & area' })).toBeNull();
        fireEvent.keyDown(screen.getByRole('button', { name: 'Aksi lokasi stok Gudang utama' }), { key: 'Enter' });
        fireEvent.click(screen.getByRole('menuitem', { name: 'Arsipkan' }));
        expect(tiruanRouter.post).toHaveBeenCalledWith('/kelola/outlet/O-1/gudang/G-1/arsipkan', {}, expect.anything());
    });

    it('F-10a meja: area & meja tampil, meja diarsipkan lewat aksi baris, tambah meja membuka formulir; mode mati hanya info', () => {
        const outletAktif = {
            Uuid: 'O-1',
            Kode: 'JKT1',
            Nama: 'Kopi Nusantara Sudirman',
            UuidMerek: null,
            Alamat: null,
            KodeKota: null,
            ZonaWaktu: 'WIB',
            JamTutupBuku: '04:00',
            Pkp: false,
            Nitku: null,
            PungutPbjt: false,
            Status: 'Aktif' as const,
            KodeTerkunci: true,
        };
        const modeMeja = {
            Aktif: true,
            Area: [{ Uuid: 'A-1', Nama: 'Teras Belakang', Urutan: 0, Status: 'Aktif' as const, JumlahMeja: 1 }],
            Meja: [
                {
                    Uuid: 'M-7',
                    Nama: '7',
                    UuidArea: 'A-1',
                    NamaArea: 'Teras Belakang',
                    Kapasitas: 4,
                    Bentuk: 'Bundar',
                    Urutan: 0,
                    Status: 'Aktif' as const,
                },
            ],
        };
        const bentuk = [
            { Nilai: 'Persegi', Label: 'Persegi' },
            { Nilai: 'Bundar', Label: 'Bundar' },
        ];
        const { unmount: Lepas } = RenderUji(
            <HalamanDetailOutlet
                Outlet={outletAktif}
                Gudang={[]}
                Merek={[]}
                Kota={[]}
                JenisGudang={[]}
                ModeMeja={modeMeja}
                BentukMeja={bentuk}
            />,
        );

        expect(screen.getByRole('heading', { name: 'Meja & area' })).toBeTruthy();
        expect(screen.getAllByText('Teras Belakang').length).toBeGreaterThan(0);
        expect(screen.getByText('4 orang')).toBeTruthy();
        fireEvent.keyDown(screen.getByRole('button', { name: 'Aksi meja 7' }), { key: 'Enter' });
        fireEvent.click(screen.getByRole('menuitem', { name: 'Arsipkan' }));
        expect(tiruanRouter.post).toHaveBeenCalledWith('/kelola/outlet/O-1/meja/M-7/arsipkan', {}, expect.anything());

        fireEvent.click(screen.getByRole('button', { name: 'Tambah meja' }));
        expect(screen.getByRole('heading', { name: 'Tambah meja' })).toBeTruthy();
        expect(screen.getByLabelText('Nama atau nomor meja')).toBeTruthy();
        Lepas();

        RenderUji(
            <HalamanDetailOutlet
                Outlet={outletAktif}
                Gudang={[]}
                Merek={[]}
                Kota={[]}
                JenisGudang={[]}
                ModeMeja={{ ...modeMeja, Aktif: false }}
                BentukMeja={bentuk}
            />,
        );
        expect(screen.getByText('Mode meja tidak aktif')).toBeTruthy();
        expect(screen.queryByRole('button', { name: 'Tambah meja' })).toBeNull();
    });
});

describe('Kelola/PIN (F-02 langkah 4)', () => {
    it('status PIN bertulis; atur ulang dari menu aksi membuka formulir PIN anggota', () => {
        RenderUji(
            <HalamanPin
                PinSayaDiatur
                Anggota={[
                    {
                        Uuid: 'U-2',
                        Nama: 'Budi Santoso',
                        Email: 'budi@kopinusantara.id',
                        NamaPeran: 'Kasir',
                        PinDiatur: false,
                    },
                ]}
            />,
        );

        expect(screen.getByText('Belum diatur')).toBeTruthy();
        fireEvent.keyDown(screen.getByRole('button', { name: 'Aksi Budi Santoso' }), { key: 'Enter' });
        fireEvent.click(screen.getByRole('menuitem', { name: 'Atur ulang PIN' }));
        expect(screen.getByRole('heading', { name: 'Atur ulang PIN Budi Santoso' })).toBeTruthy();
    });

    it('tanpa anggota: keadaan kosong', () => {
        RenderUji(<HalamanPin PinSayaDiatur={false} Anggota={[]} />);
        expect(screen.getByText('Belum ada anggota lain yang PIN-nya bisa Anda atur.')).toBeTruthy();
    });
});

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { cleanup, fireEvent, render, screen, within } from '@testing-library/react';
import { useState, type ReactElement, type ReactNode } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import HalamanHargaPaket from '@/Halaman/Pengelola/Katalog/HargaPaket';
import HalamanFitur from '@/Halaman/Pengelola/Katalog/Fitur';
import HalamanIntegrasi from '@/Halaman/Pengelola/Integrasi/Daftar';
import HalamanTarifPajak from '@/Halaman/Pengelola/Referensi/TarifPajak';
import HalamanWilayah from '@/Halaman/Pengelola/Referensi/Wilayah';
import HalamanEditorTemplate from '@/Halaman/Pengelola/TemplateSektor/Editor';
import BidangTanggal, { TulisTanggal, UraiTanggal } from '@/Komponen/Pengelola/BidangTanggal';
import TabReferensi from '@/Komponen/Pengelola/TabReferensi';
import FormAkun from '@/Komponen/Pengelola/TemplateSektor/FormAkun';
import type { HasilTabel } from '@/Komponen/TabelData/Tipe';
import { BukaMenu } from '@/Pengujian/InteraksiRadix';
import { IzinPengelola, type PropsBersamaPengelola } from '@/Tipe/Pengelola';
import type { IsiTemplate, PilihanEditorTemplate } from '@/Tipe/TemplateSektor';

/*
 * Test perilaku halaman Platform Pengelola setelah migrasi ke shadcn/ui: dialog formulir, konfirmasi four-eyes
 * (AlertDialog), Switch aktif integrasi, Tabs editor template, Checkbox COA, dan kalender tanggal. Nilai yang
 * dikirim harus sama persis dengan sebelum migrasi.
 */

type Kiriman = { metode: 'post' | 'put' | 'delete'; url: string; data: unknown };

const uji = vi.hoisted(() => ({
    kiriman: [] as { metode: 'post' | 'put' | 'delete'; url: string; data: unknown }[],
    halaman: { props: {} as Record<string, unknown>, url: '/' },
    // `useForm().transform` terakhir (satu formulir dikirim per aksi test); dikosongkan di AturHalaman.
    ubah: null as ((isian: unknown) => unknown) | null,
    router: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn(), reload: vi.fn(), visit: vi.fn() },
}));

vi.mock('@inertiajs/react', () => {
    function useForm<T extends object>(awal: T) {
        const [data, AturDataForm] = useState<T>(awal);
        const BuatKirim = (metode: Kiriman['metode']) => (url: string) => {
            uji.kiriman.push({ metode, url, data: uji.ubah ? uji.ubah(data) : data });
        };

        return {
            data,
            setData: (kunci: keyof T, nilai: unknown) => AturDataForm((lama) => ({ ...lama, [kunci]: nilai })),
            errors: (uji.halaman.props as { errors: Record<string, string> }).errors,
            processing: false,
            post: BuatKirim('post'),
            put: BuatKirim('put'),
            delete: BuatKirim('delete'),
            reset: vi.fn(),
            transform: (fungsi: (isian: T) => unknown) => {
                uji.ubah = fungsi as (isian: unknown) => unknown;
            },
        };
    }

    return {
        Head: () => null,
        Link: ({ href, children, ...sisa }: { href: string; children?: ReactNode }) => (
            <a href={href} {...sisa}>
                {children}
            </a>
        ),
        router: uji.router,
        usePage: () => uji.halaman,
        useForm,
    };
});

function AturHalaman(izin: string[], url = '/', galat: Record<string, string> = {}) {
    const props: PropsBersamaPengelola = {
        NamaAplikasi: 'Kasir',
        Lingkungan: 'Staging',
        Kilat: null,
        Pengguna: { Uuid: 'P1', Nama: 'Dewi Lestari', Email: 'dewi@contoh.id', KodePeran: [], Izin: izin },
        PeringatanSuperAdmin: false,
        PeringatanIntegrasi: [],
        PeringatanOperasional: [],
        errors: galat,
    };
    uji.halaman.props = props;
    uji.halaman.url = url;
    uji.kiriman.length = 0;
    uji.ubah = null;
    Object.values(uji.router).forEach((fungsi) => fungsi.mockClear());
}

/** Halaman dengan `TabelData` butuh TanStack Query (di aplikasi dipasang oleh `Pengelola.tsx`). */
function RenderDenganKueri(elemen: ReactElement) {
    const klien = new QueryClient({ defaultOptions: { queries: { retry: false } } });

    return render(<QueryClientProvider client={klien}>{elemen}</QueryClientProvider>);
}

function HasilUji<T>(Data: T[]): HasilTabel<T> {
    return { Data, Meta: { Halaman: 1, PerHalaman: 25, Total: Data.length, JumlahHalaman: 1 } };
}

beforeEach(() => AturHalaman([]));
afterEach(() => cleanup());

describe('BidangTanggal', () => {
    it('mengurai dan menulis TTTT-BB-HH tanpa geser zona waktu', () => {
        const tanggal = UraiTanggal('2026-01-31');
        expect(tanggal?.getFullYear()).toBe(2026);
        expect(tanggal?.getMonth()).toBe(0);
        expect(tanggal?.getDate()).toBe(31);
        expect(TulisTanggal(new Date(2026, 1, 3))).toBe('2026-02-03');
        expect(UraiTanggal('2026-02-30')).toBeUndefined();
        expect(UraiTanggal('2026-')).toBeUndefined();
    });

    it('isian tetap teks bebas dan kalender mengisi string yang sama', () => {
        const SaatBerubah = vi.fn();

        function Bungkus() {
            const [nilai, AturNilai] = useState('2026-03-10');

            return (
                <BidangTanggal
                    label="Berlaku mulai (TTTT-BB-HH)"
                    nilai={nilai}
                    saatBerubah={(baru) => {
                        SaatBerubah(baru);
                        AturNilai(baru);
                    }}
                    galat="Tanggal wajib diisi."
                />
            );
        }

        render(<Bungkus />);
        const isian = screen.getByLabelText<HTMLInputElement>('Berlaku mulai (TTTT-BB-HH)');
        expect(isian.getAttribute('aria-invalid')).toBe('true');
        expect(screen.getByText('Tanggal wajib diisi.').id).toBe(isian.getAttribute('aria-describedby'));

        fireEvent.change(isian, { target: { value: '2026-03-1' } });
        expect(SaatBerubah).toHaveBeenLastCalledWith('2026-03-1');

        fireEvent.change(isian, { target: { value: '2026-03-10' } });
        fireEvent.click(screen.getByRole('button', { name: 'Pilih Berlaku mulai (TTTT-BB-HH) dari kalender' }));
        const kalender = screen.getByRole('grid');
        fireEvent.click(within(kalender).getByText('17'));
        expect(SaatBerubah).toHaveBeenLastCalledWith('2026-03-17');
        expect(isian.value).toBe('2026-03-17');
    });
});

describe('NavigasiTab', () => {
    it('menandai tab aktif dengan aria-current', () => {
        AturHalaman([], '/referensi/hari-libur?saring[Tahun]=2026');
        render(<TabReferensi />);
        const navigasi = screen.getByRole('navigation', { name: 'Data referensi' });
        expect(within(navigasi).getByRole('link', { name: 'Hari libur' }).getAttribute('aria-current')).toBe('page');
        expect(within(navigasi).getByRole('link', { name: 'Tarif pajak' }).getAttribute('aria-current')).toBeNull();
    });
});

describe('Katalog fitur (P-04)', () => {
    it('formulir tambah di Dialog mengirim isian yang sama dan Esc menutup tanpa kirim', () => {
        AturHalaman([IzinPengelola.KatalogFiturKelola], '/katalog/fitur');
        render(<HalamanFitur Fitur={[]} />);
        expect(screen.getByRole('status').textContent).toContain('Belum ada fitur');

        fireEvent.click(screen.getByRole('button', { name: 'Tambah fitur' }));
        const dialog = screen.getByRole('dialog', { name: 'Tambah fitur' });
        fireEvent.change(within(dialog).getByLabelText('Kunci'), { target: { value: 'POS.MODE-MEJA' } });
        fireEvent.change(within(dialog).getByLabelText('Nama'), { target: { value: 'Mode meja' } });
        fireEvent.change(within(dialog).getByLabelText('Modul'), { target: { value: 'Pos' } });
        fireEvent.click(within(dialog).getByRole('button', { name: 'Simpan fitur' }));
        expect(uji.kiriman).toEqual([
            {
                metode: 'post',
                url: '/katalog/fitur',
                data: { Kunci: 'pos.mode-meja', Nama: 'Mode meja', Modul: 'Pos', Keterangan: '' },
            },
        ]);

        fireEvent.keyDown(dialog, { key: 'Escape' });
        expect(screen.queryByRole('dialog')).toBeNull();
        expect(uji.kiriman).toHaveLength(1);
    });

    it('tanpa izin kelola tidak ada tombol tambah/ubah', () => {
        render(<HalamanFitur Fitur={[{ Kunci: 'pos.meja', Nama: 'Meja', Modul: 'Pos', Keterangan: null }]} />);
        expect(screen.getByRole('table', { name: 'Katalog fitur' })).toBeTruthy();
        expect(screen.queryByRole('button', { name: 'Tambah fitur' })).toBeNull();
        expect(screen.queryByRole('button', { name: 'Ubah' })).toBeNull();
    });
});

describe('Harga paket four-eyes (P-04, BR-P04.5)', () => {
    const harga = {
        Uuid: 'H1',
        HargaBulanan: '199000.00',
        HargaTahunan: '1990000.00',
        BerlakuMulai: '2026-11-01',
        BerlakuSampai: null,
        TerapkanKePelangganLama: false,
        Status: 'MenungguTinjauan' as const,
        DaftarIdPenyusun: [7],
        IdPengaju: 7,
        Persetujuan: [],
    };
    const paket = { Uuid: 'PK1', Kode: 'PRO', Nama: 'Pro', HargaNegosiasi: false };

    it('peninjau lain menolak lewat AlertDialog; catatan & keputusan terkirim', () => {
        AturHalaman([IzinPengelola.KatalogPaketSetujui], '/katalog/paket/PK1/harga');
        render(<HalamanHargaPaket Paket={paket} Harga={[harga]} IdPengguna={9} />);
        expect(screen.getByText('Menunggu tinjauan')).toBeTruthy();

        fireEvent.click(screen.getByRole('button', { name: 'Tinjau harga' }));
        const dialog = screen.getByRole('alertdialog');
        expect(within(dialog).getByText('Pelanggan lama tetap memakai harga lamanya.')).toBeTruthy();
        fireEvent.change(within(dialog).getByLabelText('Catatan (wajib bila menolak)'), {
            target: { value: 'Terlalu mahal' },
        });
        fireEvent.click(within(dialog).getByRole('button', { name: 'Tolak harga' }));
        expect(uji.kiriman).toEqual([
            {
                metode: 'post',
                url: '/katalog/paket/PK1/harga/H1/tinjau',
                data: { Keputusan: 'Tolak', Catatan: 'Terlalu mahal' },
            },
        ]);

        fireEvent.click(within(screen.getByRole('alertdialog')).getByRole('button', { name: 'Batal' }));
        expect(screen.queryByRole('alertdialog')).toBeNull();
    });

    it('penyusun sendiri tidak bisa meninjau', () => {
        AturHalaman([IzinPengelola.KatalogPaketSetujui], '/katalog/paket/PK1/harga');
        render(<HalamanHargaPaket Paket={paket} Harga={[harga]} IdPengguna={7} />);
        expect(screen.queryByRole('button', { name: 'Tinjau harga' })).toBeNull();
    });
});

describe('Integrasi (P-05)', () => {
    it('Switch aktif mengirim aktifkan/nonaktifkan dengan alasan yang sama seperti tombol lama', () => {
        AturHalaman([IzinPengelola.IntegrasiKelola], '/integrasi');
        const slot = {
            Jenis: 'Email',
            LabelJenis: 'Email',
            Lingkungan: 'Produksi' as const,
            LingkunganServer: true,
            Penyedia: { Nilai: 'Smtp', Label: 'SMTP' },
            BidangPengaturan: [],
            BidangKredensial: [],
            Konfigurasi: {
                Uuid: 'I1',
                Pengaturan: {},
                PetunjukKredensial: {},
                Aktif: false,
                Status: 'Terhubung' as const,
                LabelStatus: 'Terhubung',
                TerakhirDiujiPada: null,
                HasilUji: null,
                KredensialDiubahPada: '2026-09-01',
                RotasiSetiapHari: 90,
                PerluRotasi: false,
            },
        };
        const { rerender: RenderUlang } = render(<HalamanIntegrasi Integrasi={[slot]} />);

        fireEvent.change(screen.getByLabelText('Alasan (wajib untuk produksi)'), {
            target: { value: 'Uji koneksi lolos' },
        });
        const saklar = screen.getByRole('switch', { name: 'Integrasi produksi aktif' });
        expect(saklar.getAttribute('aria-checked')).toBe('false');
        fireEvent.click(saklar);
        expect(uji.router.post).toHaveBeenCalledWith(
            '/integrasi/I1/aktifkan',
            { Alasan: 'Uji koneksi lolos' },
            expect.objectContaining({ preserveScroll: true }),
        );

        RenderUlang(<HalamanIntegrasi Integrasi={[{ ...slot, Konfigurasi: { ...slot.Konfigurasi, Aktif: true } }]} />);
        fireEvent.click(screen.getByRole('switch', { name: 'Integrasi produksi aktif' }));
        expect(uji.router.post).toHaveBeenLastCalledWith(
            '/integrasi/I1/nonaktifkan',
            { Alasan: 'Uji koneksi lolos' },
            expect.objectContaining({ preserveScroll: true }),
        );
    });
});

const pilihan: PilihanEditorTemplate = {
    ModeKasir: [{ Nilai: 'Retail', Label: 'Retail' }],
    TipeAkun: [
        { Nilai: 'Aset', Label: 'Aset', DigitAwal: '1', SaldoNormal: 'Debit' },
        { Nilai: 'Pendapatan', Label: 'Pendapatan', DigitAwal: '4', SaldoNormal: 'Kredit' },
    ],
    SaldoNormal: [],
    PeranAkun: [],
    ArahPembulatan: [{ Nilai: 'Terdekat', Label: 'Terdekat' }],
    MetodeHpp: [{ Nilai: 'RataRata', Label: 'Rata-rata' }],
    DasarPengenaan: [{ Nilai: 'Subtotal', Label: 'Subtotal' }],
    LaporanUnggulan: [],
    Fitur: [],
    Satuan: [{ Nilai: 'PCS', Label: 'pcs' }],
    JenisPajak: [],
    JenisProdukContoh: [{ Nilai: 'Stok', Label: 'Barang berstok' }],
};

describe('Template sektor (P-03)', () => {
    it('Checkbox kontra membalik saldo normal dan terkirim di PUT akun', () => {
        render(
            <FormAkun
                url="/template-sektor/RTL-GEN/versi/2"
                isi={{
                    Akun: [{ Kode: '1-1100', Nama: 'Kas', Tipe: 'Aset', SaldoNormal: 'Debit', Kontra: false }],
                    PemetaanAkun: {},
                    KelompokPajak: [],
                }}
                pilihan={pilihan}
                bolehUbah
            />,
        );
        const kontra = screen.getByRole('checkbox', { name: 'Akun kontra baris 1' });
        fireEvent.click(kontra);
        expect(kontra.getAttribute('aria-checked')).toBe('true');
        fireEvent.change(screen.getByLabelText('Tipe akun baris 1'), { target: { value: 'Pendapatan' } });
        fireEvent.click(screen.getByRole('button', { name: 'Simpan akun & pajak' }));
        expect(uji.kiriman).toEqual([
            {
                metode: 'put',
                url: '/template-sektor/RTL-GEN/versi/2/akun',
                data: {
                    Akun: [{ Kode: '1-1100', Nama: 'Kas', Tipe: 'Pendapatan', SaldoNormal: 'Debit', Kontra: true }],
                    PemetaanAkun: {},
                    KelompokPajak: [],
                },
            },
        ]);
    });

    it('editor: Tabs menjaga isian, terbit & hapus draf lewat AlertDialog (bukan window.confirm)', () => {
        AturHalaman(
            [IzinPengelola.TemplateTerbitkan, IzinPengelola.TemplateDrafKelola, IzinPengelola.TemplateAkunUbah],
            '/template-sektor/RTL-GEN/versi/2',
        );
        const Konfirmasi = vi.spyOn(window, 'confirm');
        const isi: IsiTemplate = {
            ModeKasir: ['Retail'],
            ModeKasirDefault: 'Retail',
            KunciFitur: [],
            KodeSatuan: ['PCS'],
            Kategori: [],
            StasiunDapur: [],
            AlasanVoid: [],
            AlasanPenyesuaian: [],
            LaporanUnggulan: [],
            Pengaturan: {
                PembulatanTunai: { Kelipatan: 100, Arah: 'Terdekat' },
                MetodeHpp: 'RataRata',
                PersenBiayaLayanan: '0',
                BiayaLayananMasukDpp: false,
                StokBolehMinus: false,
                HargaTermasukPajak: true,
            },
            ProdukContoh: [],
            Akun: [{ Kode: '1-1100', Nama: 'Kas', Tipe: 'Aset', SaldoNormal: 'Debit', Kontra: false }],
            PemetaanAkun: {},
            KelompokPajak: [],
        };
        render(
            <HalamanEditorTemplate
                Template={{ Kode: 'RTL-GEN', Nama: 'Retail umum', Keterangan: null }}
                Versi={{
                    Versi: 2,
                    Status: 'Draf',
                    Isi: isi,
                    HasilValidasi: { Lolos: true, Galat: [] },
                    DivalidasiPada: null,
                    DiterbitkanPada: null,
                    VersiAsal: 1,
                }}
                DaftarVersi={[
                    { Versi: 1, Status: 'Terbit', DiterbitkanPada: '2026-09-01T00:00:00Z' },
                    { Versi: 2, Status: 'Draf', DiterbitkanPada: null },
                ]}
                AdaDraf
                Pilihan={pilihan}
            />,
        );
        expect(screen.getByRole('link', { name: 'Versi 2 · Draf' }).getAttribute('aria-current')).toBe('page');

        fireEvent.mouseDown(screen.getByRole('tab', { name: 'Akun & pajak' }), { button: 0 });
        fireEvent.change(screen.getByLabelText('Nama akun baris 1'), { target: { value: 'Kas besar' } });
        fireEvent.mouseDown(screen.getByRole('tab', { name: 'Isi bisnis' }), { button: 0 });
        fireEvent.mouseDown(screen.getByRole('tab', { name: 'Akun & pajak' }), { button: 0 });
        expect(screen.getByLabelText<HTMLInputElement>('Nama akun baris 1').value).toBe('Kas besar');

        fireEvent.click(screen.getByRole('button', { name: 'Terbitkan' }));
        const dialog = screen.getByRole('alertdialog', { name: 'Terbitkan RTL-GEN versi 2?' });
        expect(uji.router.post).not.toHaveBeenCalled();
        fireEvent.click(within(dialog).getByRole('button', { name: 'Terbitkan' }));
        expect(uji.router.post).toHaveBeenCalledWith(
            '/template-sektor/RTL-GEN/versi/2/terbitkan',
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
        fireEvent.click(within(dialog).getByRole('button', { name: 'Batal' }));

        fireEvent.click(screen.getByRole('button', { name: 'Hapus draf' }));
        fireEvent.click(
            within(screen.getByRole('alertdialog', { name: 'Hapus draf versi 2?' })).getByRole('button', {
                name: 'Hapus draf',
            }),
        );
        expect(uji.router.delete).toHaveBeenCalledWith(
            '/template-sektor/RTL-GEN/versi/2',
            expect.objectContaining({ preserveScroll: true }),
        );
        expect(Konfirmasi).not.toHaveBeenCalled();
    });
});

describe('Referensi wilayah & tarif pajak (P-02, TabelData D-16)', () => {
    const wilayah = [
        { Kode: '33', Nama: 'Jawa Tengah', Tingkat: 'Provinsi', KodeInduk: null, ZonaWaktu: 'WIB' },
        { Kode: '33.74', Nama: 'Kota Semarang', Tingkat: 'KabupatenKota', KodeInduk: '33', ZonaWaktu: 'WIB' },
    ];
    const pilihanTingkat = [
        { Nilai: 'Provinsi', Label: 'Provinsi' },
        { Nilai: 'KabupatenKota', Label: 'Kabupaten/kota' },
    ];

    it('wilayah: tabel server dengan cari; ubah lewat aksi baris hanya dengan izin kelola', () => {
        AturHalaman([IzinPengelola.ReferensiWilayahKelola], '/referensi/wilayah');
        const { unmount: Lepas } = RenderDenganKueri(
            <HalamanWilayah Wilayah={HasilUji(wilayah)} PilihanTingkat={pilihanTingkat} PilihanZonaWaktu={['WIB']} />,
        );
        const tabel = screen.getByRole('table', { name: 'Daftar wilayah' });
        expect(within(tabel).getByText('Kabupaten/kota')).toBeTruthy();
        expect(screen.getByPlaceholderText('Cari nama atau kode wilayah')).toBeTruthy();

        BukaMenu(screen.getAllByRole('button', { name: 'Aksi baris' })[1] as HTMLElement);
        fireEvent.click(screen.getByRole('menuitem', { name: 'Ubah wilayah' }));
        expect(screen.getByRole('dialog', { name: 'Ubah Kota Semarang' })).toBeTruthy();
        Lepas();

        AturHalaman([], '/referensi/wilayah');
        RenderDenganKueri(
            <HalamanWilayah Wilayah={HasilUji(wilayah)} PilihanTingkat={pilihanTingkat} PilihanZonaWaktu={['WIB']} />,
        );
        expect(screen.queryByRole('button', { name: 'Aksi baris' })).toBeNull();
        expect(screen.queryByRole('button', { name: 'Tambah wilayah' })).toBeNull();
    });

    it('tarif pajak: peninjau lain meninjau dari aksi baris; tarif terbit tanpa aksi', () => {
        const dasar = {
            KodeJenisPajak: 'Ppn',
            NamaJenisPajak: 'PPN',
            Tarif: '12.000000',
            PengaliDppPembilang: 11,
            PengaliDppPenyebut: 12,
            KodeWilayah: null,
            BiayaLayananMasukDpp: false,
            BerlakuSampai: null,
            NomorDasarHukum: 'PMK 131 Tahun 2024',
            TautanDasarHukum: null,
            DaftarIdPenyusun: [7],
            IdPengaju: 7,
            PersetujuanDibutuhkan: 2,
            Persetujuan: [],
            JumlahSetuju: 0,
        };
        AturHalaman([IzinPengelola.ReferensiTarifPajakSetujui], '/referensi/tarif-pajak');
        RenderDenganKueri(
            <HalamanTarifPajak
                Tarif={HasilUji([
                    { ...dasar, Uuid: 'T1', BerlakuMulai: '2027-01-01', Status: 'MenungguTinjauan' as const },
                    { ...dasar, Uuid: 'T2', BerlakuMulai: '2025-01-01', Status: 'Terbit' as const },
                ])}
                JenisPajak={[{ Kode: 'Ppn', Nama: 'PPN', Cakupan: 'Nasional' }]}
                IdPengguna={9}
            />,
        );
        expect(screen.getByRole('table', { name: 'Daftar tarif pajak' })).toBeTruthy();
        expect(screen.getByText('0 dari 2 persetujuan')).toBeTruthy();

        const [aksiMenunggu, aksiTerbit] = screen.getAllByRole('button', { name: 'Aksi baris' });
        BukaMenu(aksiTerbit as HTMLElement);
        expect(
            screen.getByRole('menuitem', { name: 'Tidak ada aksi untuk tarif ini' }).getAttribute('aria-disabled'),
        ).toBe('true');
        fireEvent.keyDown(screen.getByRole('menu'), { key: 'Escape' });

        BukaMenu(aksiMenunggu as HTMLElement);
        fireEvent.click(screen.getByRole('menuitem', { name: 'Tinjau tarif' }));
        const dialog = screen.getByRole('alertdialog');
        fireEvent.click(within(dialog).getByRole('button', { name: 'Setujui tarif' }));
        expect(uji.kiriman).toEqual([
            { metode: 'post', url: '/referensi/tarif-pajak/T1/tinjau', data: { Keputusan: 'Setuju', Catatan: '' } },
        ]);
    });
});

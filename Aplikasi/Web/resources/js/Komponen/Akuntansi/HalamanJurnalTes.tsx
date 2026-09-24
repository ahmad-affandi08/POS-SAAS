import { act, cleanup, fireEvent, render, screen, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import HalamanDaftarJurnal, { BuatQueryJurnal } from '@/Halaman/Kelola/Akuntansi/Jurnal/Daftar';
import HalamanDetailJurnal from '@/Halaman/Kelola/Akuntansi/Jurnal/Detail';
import { AturHalamanUji, tiruanRouter } from '@/Komponen/Katalog/TiruanInertia';
import type { BarisDaftarJurnal, PropsDaftarJurnal, PropsDetailJurnal } from '@/Tipe/Akuntansi';

vi.mock('@inertiajs/react', async () => (await import('@/Komponen/Katalog/TiruanInertia')).TiruanInertia);

const barisJurnal: BarisDaftarJurnal = {
    Uuid: '01K5JURNAL0000000000000001',
    Nomor: 'JU/2026/09/000001',
    Tanggal: '2026-09-20',
    JenisSumber: 'StokAwal',
    LabelJenisSumber: 'Stok awal',
    NomorSumber: 'SA/2026/09/0001',
    TautanSumber: '/kelola/persediaan/stok-awal/01K5STOKAWA00000000000001',
    Keterangan: 'Stok awal Gudang Toko Outlet Utama',
    TotalDebit: '123456789012.34',
    Otomatis: true,
    Dibalik: true,
    Pembalik: false,
};

const pembalik: BarisDaftarJurnal = {
    ...barisJurnal,
    Uuid: '01K5JURNAL0000000000000002',
    Nomor: 'JU/2026/09/000002',
    Tanggal: '2026-09-24',
    Keterangan: 'Pembatalan stok awal SA/2026/09/0001: salah input jumlah minyak goreng',
    TotalDebit: '0.01',
    Dibalik: false,
    Pembalik: true,
    TautanSumber: null,
};

const saringKosong: PropsDaftarJurnal['Saring'] = { Kata: '', Dari: '', Sampai: '', JenisSumber: null };

function BuatPropsDaftar(data: BarisDaftarJurnal[], saring = saringKosong, halamanTerakhir = 1): PropsDaftarJurnal {
    return {
        Jurnal: { Data: data, HalamanSaatIni: 1, HalamanTerakhir: halamanTerakhir, Total: data.length },
        Saring: saring,
        OpsiJenisSumber: [{ Nilai: 'StokAwal', Label: 'Stok awal' }],
    };
}

const propsDetail: PropsDetailJurnal = {
    Jurnal: {
        ...barisJurnal,
        TotalDebit: '10601234.56',
        Periode: '2026-09',
        DibuatOleh: 'Rina Wulandari',
        DibuatPada: '2026-09-20T03:15:00Z',
        UuidJurnalDibalik: null,
        NomorJurnalDibalik: null,
        UuidPembalik: pembalik.Uuid,
        NomorPembalik: pembalik.Nomor,
    },
    Baris: [
        {
            Urutan: 1,
            KodeAkun: '1-1400',
            NamaAkun: 'Persediaan barang dagang',
            NamaOutlet: 'Outlet Utama Solo Baru',
            Debit: '10600834.56',
            Kredit: '0.00',
            Memo: null,
        },
        {
            Urutan: 2,
            KodeAkun: '5-1200',
            NamaAkun: 'Selisih HPP',
            NamaOutlet: null,
            Debit: '400.00',
            Kredit: '0.00',
            Memo: 'Stok minus sebelum stok awal',
        },
        {
            Urutan: 3,
            KodeAkun: '3-1000',
            NamaAkun: 'Ekuitas saldo awal',
            NamaOutlet: 'Outlet Utama Solo Baru',
            Debit: '0.00',
            Kredit: '10601234.56',
            Memo: null,
        },
    ],
    Total: { Debit: '10601234.56', Kredit: '10601234.56' },
};

describe('Kelola/Akuntansi/Jurnal (F-05a, DesainF05a E)', () => {
    beforeEach(() => AturHalamanUji({}, '/kelola/akuntansi/jurnal'));
    afterEach(() => cleanup());

    it('BuatQueryJurnal hanya mengirim saringan yang terisi', () => {
        expect(BuatQueryJurnal(saringKosong)).toEqual({});
        expect(
            BuatQueryJurnal({ Kata: '  SA/2026 ', Dari: '2026-09-01', Sampai: '2026-09-30', JenisSumber: 'StokAwal' }),
        ).toEqual({ kata: 'SA/2026', dari: '2026-09-01', sampai: '2026-09-30', jenis: 'StokAwal' });
    });

    it('daftar: nomor Mono bertautan ke detail, sumber bertautan, nilai Rupiah rata kanan, penanda pembalik', () => {
        render(<HalamanDaftarJurnal {...BuatPropsDaftar([pembalik, barisJurnal])} />);

        const tautanNomor = screen.getByRole('link', { name: 'JU/2026/09/000001' });
        expect(tautanNomor.getAttribute('href')).toBe(`/kelola/akuntansi/jurnal/${barisJurnal.Uuid}`);
        expect(tautanNomor.className).toContain('font-mono');
        expect(screen.getAllByRole('link', { name: 'SA/2026/09/0001' })[0]?.getAttribute('href')).toBe(
            barisJurnal.TautanSumber,
        );
        const nilai = screen.getByText('Rp 123.456.789.012,34');
        expect(nilai.className).toContain('tabular-nums');
        expect(nilai.className).toContain('text-right');
        expect(screen.getByText('Jurnal pembalik')).toBeTruthy();
        expect(screen.getByText('Sudah dibalik')).toBeTruthy();
        expect(screen.getByText('Rp 0,01')).toBeTruthy();
    });

    it('saringan: jenis sumber langsung diterapkan, kata & tanggal lewat tombol; kerangka memuat tampil', () => {
        render(<HalamanDaftarJurnal {...BuatPropsDaftar([barisJurnal])} />);

        fireEvent.change(screen.getByLabelText('Cari jurnal'), { target: { value: 'SA/2026/09' } });
        fireEvent.change(screen.getByLabelText('Dari tanggal'), { target: { value: '2026-09-01' } });
        expect(tiruanRouter.get).not.toHaveBeenCalled();

        fireEvent.change(screen.getByLabelText('Sumber'), { target: { value: 'StokAwal' } });
        expect(tiruanRouter.get).toHaveBeenLastCalledWith(
            '/kelola/akuntansi/jurnal',
            { kata: 'SA/2026/09', dari: '2026-09-01', jenis: 'StokAwal' },
            expect.objectContaining({ preserveState: true }),
        );

        fireEvent.click(screen.getByRole('button', { name: 'Terapkan saringan' }));
        expect(tiruanRouter.get).toHaveBeenCalledTimes(2);

        const opsi = tiruanRouter.get.mock.calls[1]?.[2] as { onStart: () => void };
        act(() => opsi.onStart());
        expect(screen.getByTestId('kerangka-jurnal')).toBeTruthy();
        expect(screen.getByText('Memuat jurnal…')).toBeTruthy();
    });

    it('kosong tanpa saringan mengarahkan ke stok awal; kosong dengan saringan menawarkan hapus saringan', () => {
        const tampilan = render(<HalamanDaftarJurnal {...BuatPropsDaftar([])} />);
        expect(screen.getByText('Belum ada jurnal. Jurnal terbentuk otomatis saat stok awal diposting.')).toBeTruthy();
        expect(screen.getByRole('link', { name: 'Buka stok awal' }).getAttribute('href')).toBe(
            '/kelola/persediaan/stok-awal',
        );
        tampilan.unmount();

        render(<HalamanDaftarJurnal {...BuatPropsDaftar([], { ...saringKosong, Kata: 'tidak ada' })} />);
        expect(screen.getByText('Tidak ada jurnal yang cocok dengan pencarian atau saringan ini.')).toBeTruthy();
        expect(screen.getAllByRole('link', { name: 'Hapus saringan' }).length).toBeGreaterThan(0);
    });

    it('galat server ditampilkan; paginasi membawa saringan', () => {
        AturHalamanUji({ Tanggal: 'Periode Agustus 2026 sudah dikunci.' }, '/kelola/akuntansi/jurnal');
        render(
            <HalamanDaftarJurnal
                {...BuatPropsDaftar([barisJurnal], { ...saringKosong, JenisSumber: 'StokAwal' }, 3)}
            />,
        );

        expect(screen.getByText('Periode Agustus 2026 sudah dikunci.')).toBeTruthy();
        expect(screen.getByRole('link', { name: /Berikutnya/ }).getAttribute('href')).toBe(
            '/kelola/akuntansi/jurnal?jenis=StokAwal&halaman=2',
        );
    });

    it('detail: baris akun, outlet/tingkat usaha, total seimbang, tautan pembalik dan sumber', () => {
        render(<HalamanDetailJurnal {...propsDetail} />);

        const tabel = screen.getByRole('table');
        const baris = within(tabel).getAllByRole('row');
        expect(baris).toHaveLength(5);
        expect(within(baris[1] as HTMLElement).getByText('1-1400').className).toContain('font-mono');
        expect(within(baris[1] as HTMLElement).getByText('Rp 10.600.834,56')).toBeTruthy();
        expect(within(baris[2] as HTMLElement).getByText('Tingkat usaha')).toBeTruthy();
        expect(within(baris[2] as HTMLElement).getByText('Stok minus sebelum stok awal')).toBeTruthy();
        expect(within(baris[4] as HTMLElement).getByText('Total (seimbang)')).toBeTruthy();
        expect(within(baris[4] as HTMLElement).getAllByText('Rp 10.601.234,56')).toHaveLength(2);

        expect(screen.getByText('Jurnal ini sudah dibalik')).toBeTruthy();
        expect(screen.getByRole('link', { name: 'JU/2026/09/000002' }).getAttribute('href')).toBe(
            `/kelola/akuntansi/jurnal/${pembalik.Uuid}`,
        );
        expect(screen.getByRole('link', { name: 'SA/2026/09/0001' }).getAttribute('href')).toBe(
            barisJurnal.TautanSumber,
        );
        expect(screen.getByText(/Otomatis oleh Rina Wulandari/)).toBeTruthy();
        expect(screen.getByRole('link', { name: 'Kembali ke daftar jurnal' }).getAttribute('href')).toBe(
            '/kelola/akuntansi/jurnal',
        );
    });

    it('detail jurnal pembalik manual: tautan ke jurnal yang dibalik, sumber tanpa tautan', () => {
        render(
            <HalamanDetailJurnal
                {...propsDetail}
                Jurnal={{
                    ...propsDetail.Jurnal,
                    ...pembalik,
                    Otomatis: false,
                    DibuatOleh: null,
                    UuidJurnalDibalik: barisJurnal.Uuid,
                    NomorJurnalDibalik: barisJurnal.Nomor,
                    UuidPembalik: null,
                    NomorPembalik: null,
                }}
            />,
        );

        expect(
            screen.getByText('Jurnal pembalik', { selector: '[data-slot="alert-title"], [data-slot="alert-title"] *' }),
        ).toBeTruthy();
        expect(screen.getByRole('link', { name: 'JU/2026/09/000001' }).getAttribute('href')).toBe(
            `/kelola/akuntansi/jurnal/${barisJurnal.Uuid}`,
        );
        expect(screen.queryByRole('link', { name: 'SA/2026/09/0001' })).toBeNull();
        expect(screen.getByText('Manual', { selector: '[data-jenis]' })).toBeTruthy();
        expect(screen.queryByText('Jurnal ini sudah dibalik')).toBeNull();
    });
});

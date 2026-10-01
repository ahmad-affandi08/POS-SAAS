import { cleanup, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import HalamanDaftarGiro from '@/Halaman/Kelola/Akuntansi/Giro/Daftar';
import { PeriksaGiro } from '@/Komponen/Akuntansi/BidangGiro';
import { AturHalamanUji, RenderUji } from '@/Komponen/Katalog/TiruanInertia';
import { BuatHasilTabel } from '@/Komponen/Persediaan/DataUjiPersediaan';
import type { BarisGiro } from '@/Tipe/Akuntansi';

vi.mock('@inertiajs/react', async () => (await import('@/Komponen/Katalog/TiruanInertia')).TiruanInertia);

const Giro: BarisGiro = {
    Uuid: '01J9GIR0000000000000000001',
    Arah: 'Masuk',
    NomorGiro: 'BG 123456',
    NamaBank: 'BCA',
    NamaPihak: 'Toko Makmur Jaya',
    NomorSumber: 'BP/2610/0001',
    TanggalTerima: '2026-10-01',
    TanggalJatuhTempo: '2026-10-08',
    Jumlah: '77000.00',
    Status: 'Menunggu',
    LabelStatus: 'Menunggu jatuh tempo',
    TanggalCair: null,
    AlasanTolak: null,
    JurnalCair: null,
};

describe('Giro & cek mundur (v3.42)', () => {
    beforeEach(() => {
        AturHalamanUji({}, '/kelola/akuntansi/giro');
        window.history.replaceState({}, '', '/kelola/akuntansi/giro');
    });
    afterEach(() => cleanup());

    it('periksa isian giro: nomor, bank, tanggal efektif tidak sebelum tanggal bayar', () => {
        expect(
            PeriksaGiro({ NomorGiro: 'BG 1', NamaBank: 'BCA', TanggalJatuhTempo: '2026-10-08' }, '2026-10-01'),
        ).toEqual({});
        expect(PeriksaGiro({ NomorGiro: '', NamaBank: ' ', TanggalJatuhTempo: '2026-09-30' }, '2026-10-01')).toEqual({
            NomorGiro: 'Isi nomor bilyet giro/cek.',
            NamaBank: 'Isi bank penerbit.',
            TanggalJatuhTempo: 'Tanggal efektif tidak boleh sebelum tanggal bayar.',
        });
    });

    it('daftar: ringkasan giro belum cair dan status', () => {
        RenderUji(
            <HalamanDaftarGiro
                Giro={{
                    ...BuatHasilTabel([Giro]),
                    Ringkasan: { MasukJumlah: 1, MasukNilai: '77000.00', KeluarJumlah: 0, KeluarNilai: '0.00' },
                }}
                OpsiStatus={[{ Nilai: 'Menunggu', Label: 'Menunggu jatuh tempo' }]}
                OpsiArah={[{ Nilai: 'Masuk', Label: 'Giro masuk (dari pelanggan)' }]}
                OpsiAkun={[{ Uuid: '01J9AKN0000000000000000002', Kode: '1-1200', Nama: 'Bank' }]}
                HariIni="2026-10-08"
                Izin={{ Kelola: true }}
            />,
        );
        expect(screen.getByText('Giro masuk belum cair')).toBeTruthy();
        expect(screen.getAllByText('Rp 77.000').length).toBeGreaterThan(0);
        expect(screen.getAllByText('BG 123456').length).toBeGreaterThan(0);
    });
});

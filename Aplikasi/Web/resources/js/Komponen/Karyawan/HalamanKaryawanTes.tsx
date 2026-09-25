import { cleanup, fireEvent, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import HalamanAbsensi, { FormatDurasiMenit } from '@/Halaman/Kelola/Karyawan/Absensi';
import HalamanDaftarKaryawan from '@/Halaman/Kelola/Karyawan/Daftar';
import HalamanJadwalKerja, {
    CekJam,
    GeserTanggal,
    RapikanJam,
    SusunSelBerubah,
} from '@/Halaman/Kelola/Karyawan/Jadwal';
import { AturHalamanUji, RenderUji, tiruanRouter } from '@/Komponen/Katalog/TiruanInertia';
import { BuatHasilTabel } from '@/Komponen/Persediaan/DataUjiPersediaan';
import type { BarisAbsensi, BarisJadwal, BarisKaryawan } from '@/Tipe/Karyawan';

vi.mock('@inertiajs/react', async () => (await import('@/Komponen/Katalog/TiruanInertia')).TiruanInertia);

const Outlet = { Uuid: '01J9OTL0000000000000000001', Nama: 'Kopi Senja Solo Baru' };
const Hari = ['2026-09-21', '2026-09-22', '2026-09-23', '2026-09-24', '2026-09-25', '2026-09-26', '2026-09-27'];

function BarisJadwalUji(): BarisJadwal {
    return {
        Uuid: '01J9KRY0000000000000000001',
        Nama: 'Dimas Pratama Wicaksono Adiwijaya',
        Jabatan: 'Barista',
        Aktif: true,
        Jadwal: {
            ...Object.fromEntries(Hari.map((t) => [t, null])),
            '2026-09-21': { JamMulai: '08:00', JamSelesai: '16:00', OutletLain: false },
            '2026-09-22': { JamMulai: '09:00', JamSelesai: '17:00', OutletLain: true },
        },
    };
}

describe('Halaman karyawan (F-18)', () => {
    beforeEach(() => {
        AturHalamanUji({}, '/kelola/karyawan');
        window.history.replaceState({}, '', '/kelola/karyawan');
        vi.clearAllMocks();
    });
    afterEach(() => cleanup());

    it('bantuan jadwal: geser tanggal, cek & rapikan jam, sel berubah (outlet lain dilewati), durasi', () => {
        expect(GeserTanggal('2026-09-28', -7)).toBe('2026-09-21');
        expect(GeserTanggal('2026-12-28', 7)).toBe('2027-01-04');
        expect([CekJam('08:00'), CekJam('24:00'), CekJam('8:00')]).toEqual([true, false, false]);
        expect([RapikanJam('800'), RapikanJam('0830'), RapikanJam('08:30')]).toEqual(['08:00', '08:30', '08:30']);
        const b = BarisJadwalUji();
        expect(
            SusunSelBerubah([b], {
                [b.Uuid]: {
                    '2026-09-21': { JamMulai: '', JamSelesai: '' },
                    '2026-09-22': { JamMulai: '10:00', JamSelesai: '18:00' },
                    '2026-09-23': { JamMulai: '22:00', JamSelesai: '06:00' },
                },
            }),
        ).toEqual([
            { UuidKaryawan: b.Uuid, Tanggal: '2026-09-21', JamMulai: null, JamSelesai: null },
            { UuidKaryawan: b.Uuid, Tanggal: '2026-09-23', JamMulai: '22:00', JamSelesai: '06:00' },
        ]);
        expect([FormatDurasiMenit(445), FormatDurasiMenit(45), FormatDurasiMenit(null)]).toEqual([
            '7 j 25 m',
            '45 m',
            '—',
        ]);
    });

    it('jadwal: ubah jam lalu simpan mengirim sel yang berubah; sel outlet lain terkunci', () => {
        window.history.replaceState({}, '', '/kelola/karyawan/jadwal');
        RenderUji(
            <HalamanJadwalKerja
                OpsiOutlet={[Outlet]}
                UuidOutlet={Outlet.Uuid}
                Senin="2026-09-21"
                Jadwal={{ Hari, Baris: [BarisJadwalUji()] }}
                Izin={{ Kelola: true }}
            />,
        );
        expect(screen.getByText('Di outlet lain 09:00–17:00')).toBeTruthy();
        const simpan = screen.getByRole('button', { name: 'Simpan jadwal' });
        expect((simpan as HTMLButtonElement).disabled).toBe(true);
        fireEvent.change(screen.getByLabelText('Dimas Pratama Wicaksono Adiwijaya Rabu mulai'), {
            target: { value: '07:30' },
        });
        expect((simpan as HTMLButtonElement).disabled).toBe(true);
        expect(screen.getByText(/isi keduanya atau kosongkan keduanya/)).toBeTruthy();
        fireEvent.change(screen.getByLabelText('Dimas Pratama Wicaksono Adiwijaya Rabu selesai'), {
            target: { value: '15:30' },
        });
        fireEvent.click(screen.getByRole('button', { name: 'Simpan jadwal' }));
        expect(tiruanRouter.put).toHaveBeenCalledWith(
            '/kelola/karyawan/jadwal',
            {
                UuidOutlet: Outlet.Uuid,
                Senin: '2026-09-21',
                Sel: [
                    {
                        UuidKaryawan: '01J9KRY0000000000000000001',
                        Tanggal: '2026-09-23',
                        JamMulai: '07:30',
                        JamSelesai: '15:30',
                    },
                ],
            },
            expect.anything(),
        );
    });

    it('daftar: tombol tambah & kolom gaji hanya untuk karyawan.kelola; absensi menampilkan terlambat', () => {
        const baris: BarisKaryawan = {
            Uuid: '01J9KRY0000000000000000001',
            Nama: 'Rina Wulandari',
            Jabatan: 'Barista',
            LevelStaf: 'Senior',
            GajiPokok: '3500000.00',
            UuidPengguna: null,
            NamaPengguna: null,
            UuidOutlet: null,
            NamaOutlet: null,
            Status: 'Aktif',
            LabelStatus: 'Aktif',
        };
        RenderUji(
            <HalamanDaftarKaryawan
                Karyawan={BuatHasilTabel([baris])}
                OpsiPengguna={[]}
                OpsiOutlet={[Outlet]}
                Izin={{ Kelola: true }}
            />,
        );
        expect(screen.getAllByRole('button', { name: 'Tambah karyawan' }).length).toBeGreaterThan(0);
        expect(screen.getAllByText('Rp 3.500.000').length).toBeGreaterThan(0);
        cleanup();

        RenderUji(
            <HalamanDaftarKaryawan
                Karyawan={BuatHasilTabel([{ ...baris, GajiPokok: null }])}
                OpsiPengguna={[]}
                OpsiOutlet={[Outlet]}
                Izin={{ Kelola: false }}
            />,
        );
        expect(screen.queryByRole('button', { name: 'Tambah karyawan' })).toBeNull();
        cleanup();

        window.history.replaceState({}, '', '/kelola/karyawan/absensi');
        const absensi: BarisAbsensi = {
            Uuid: '01J9ABS0000000000000000001',
            TanggalBisnis: '2026-09-25',
            UuidKaryawan: baris.Uuid,
            NamaKaryawan: baris.Nama,
            NamaOutlet: Outlet.Nama,
            JamMasuk: '09:40',
            JamKeluar: '17:05',
            KeluarBeda: false,
            DurasiMenit: 445,
            Jadwal: '09:00–17:00',
            TerlambatMenit: 40,
            Status: 'Terlambat',
            LabelStatus: 'Terlambat',
            AdaSwafotoMasuk: true,
            AdaSwafotoKeluar: false,
        };
        RenderUji(<HalamanAbsensi Absensi={BuatHasilTabel([absensi])} OpsiKaryawan={[]} OpsiOutlet={[Outlet]} />);
        expect(screen.getAllByText('Terlambat 40 menit').length).toBeGreaterThan(0);
        expect(screen.getAllByRole('link', { name: 'Swafoto masuk' })[0]?.getAttribute('href')).toBe(
            '/kelola/karyawan/absensi/01J9ABS0000000000000000001/swafoto/masuk',
        );
    });
});

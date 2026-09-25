import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import Tombol from '@/Komponen/Formulir/Tombol';
import DaftarGalatServer from '@/Komponen/Katalog/DaftarGalatServer';
import PesanHanyaLihat from '@/Komponen/Katalog/PesanHanyaLihat';
import { Button } from '@/Komponen/Ui/button';
import { Input } from '@/Komponen/Ui/input';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import type { BarisJadwal, PropsJadwalKerja } from '@/Tipe/Karyawan';

const alamat = '/kelola/karyawan/jadwal';
const namaHari = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

type Isian = Record<string, Record<string, { JamMulai: string; JamSelesai: string }>>;

/** Tanggal `YYYY-MM-DD` digeser [hari] (UTC, tanpa zona). */
export function GeserTanggal(tanggal: string, hari: number): string {
    const d = new Date(`${tanggal}T00:00:00Z`);
    d.setUTCDate(d.getUTCDate() + hari);

    return d.toISOString().slice(0, 10);
}

/** `08:00` valid; kosong = boleh (hapus jadwal). */
export function CekJam(jam: string): boolean {
    return /^([01]\d|2[0-3]):[0-5]\d$/.test(jam);
}

/** Rapikan ketikan jam: `800` → `08:00`, `0830` → `08:30`; lainnya apa adanya. */
export function RapikanJam(teks: string): string {
    const angka = teks.replace(/\D/g, '');

    if (angka.length === 3) {
        return `0${angka[0]}:${angka.slice(1)}`;
    }

    return angka.length === 4 ? `${angka.slice(0, 2)}:${angka.slice(2)}` : teks.trim();
}

function BuatIsian(baris: BarisJadwal[]): Isian {
    return Object.fromEntries(
        baris.map((b) => [
            b.Uuid,
            Object.fromEntries(
                Object.entries(b.Jadwal).map(([t, j]) => [
                    t,
                    { JamMulai: j?.JamMulai ?? '', JamSelesai: j?.JamSelesai ?? '' },
                ]),
            ),
        ]),
    );
}

/** Sel yang berubah dibanding jadwal awal (hanya outlet ini) untuk dikirim. */
export function SusunSelBerubah(baris: BarisJadwal[], isian: Isian) {
    const sel: { UuidKaryawan: string; Tanggal: string; JamMulai: string | null; JamSelesai: string | null }[] = [];

    for (const b of baris) {
        for (const [tanggal, awal] of Object.entries(b.Jadwal)) {
            if (awal?.OutletLain) {
                continue;
            }

            const i = isian[b.Uuid]?.[tanggal] ?? { JamMulai: '', JamSelesai: '' };

            if ((awal?.JamMulai ?? '') !== i.JamMulai || (awal?.JamSelesai ?? '') !== i.JamSelesai) {
                sel.push({
                    UuidKaryawan: b.Uuid,
                    Tanggal: tanggal,
                    JamMulai: i.JamMulai === '' ? null : i.JamMulai,
                    JamSelesai: i.JamSelesai === '' ? null : i.JamSelesai,
                });
            }
        }
    }

    return sel;
}

/** F-18 EMP-02: jadwal kerja mingguan per outlet (Senin–Minggu), simpan & salin minggu lalu. */
export default function HalamanJadwalKerja({ OpsiOutlet, UuidOutlet, Senin, Jadwal, Izin }: PropsJadwalKerja) {
    const { props } = usePage<PropsBersamaAplikasi>();
    const [isian, AturIsian] = useState<Isian>(() => BuatIsian(Jadwal.Baris));
    const [memproses, AturMemproses] = useState(false);
    const berubah = SusunSelBerubah(Jadwal.Baris, isian);
    const galatLokal = berubah.some(
        (s) =>
            (s.JamMulai === null) !== (s.JamSelesai === null) ||
            (s.JamMulai !== null && !CekJam(s.JamMulai)) ||
            (s.JamSelesai !== null && !CekJam(s.JamSelesai)),
    );
    const Buka = (uuidOutlet: string | null, senin: string) =>
        router.get(alamat, { ...(uuidOutlet ? { outlet: uuidOutlet } : {}), minggu: senin }, { preserveState: false });
    const Ubah = (uuid: string, tanggal: string, kolom: 'JamMulai' | 'JamSelesai', nilai: string) =>
        AturIsian((lama) => ({
            ...lama,
            [uuid]: {
                ...lama[uuid],
                [tanggal]: { ...(lama[uuid]?.[tanggal] ?? { JamMulai: '', JamSelesai: '' }), [kolom]: nilai },
            },
        }));
    const Simpan = () =>
        router.put(
            alamat,
            { UuidOutlet, Senin, Sel: berubah },
            { preserveScroll: true, onStart: () => AturMemproses(true), onFinish: () => AturMemproses(false) },
        );

    return (
        <TataLetakAplikasi judul="Jadwal kerja">
            <p className="max-w-3xl text-isi text-teks-sekunder">
                Isi jam kerja per hari (JJ:MM). Kosongkan untuk libur. Jam selesai lebih kecil dari jam mulai berarti
                shift lewat tengah malam. Keterlambatan di rekap absensi dihitung dari jadwal ini.
            </p>
            {!Izin.Kelola ? <PesanHanyaLihat izin="karyawan.kelola" objek="jadwal kerja" /> : null}
            <DaftarGalatServer galat={props.errors} />

            <div className="flex flex-wrap items-end gap-3">
                <div className="w-full sm:w-64">
                    <BidangPilihan
                        label="Outlet"
                        nilai={UuidOutlet ?? ''}
                        opsi={OpsiOutlet.map((o) => ({ Nilai: o.Uuid, Label: o.Nama }))}
                        saatBerubah={(uuid) => Buka(uuid, Senin)}
                    />
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Button variant="outline" onClick={() => Buka(UuidOutlet, GeserTanggal(Senin, -7))}>
                        Minggu sebelumnya
                    </Button>
                    <span className="text-isi font-semibold tabular-nums">
                        {Senin} s.d. {GeserTanggal(Senin, 6)}
                    </span>
                    <Button variant="outline" onClick={() => Buka(UuidOutlet, GeserTanggal(Senin, 7))}>
                        Minggu berikutnya
                    </Button>
                </div>
                {Izin.Kelola && UuidOutlet ? (
                    <Button
                        variant="outline"
                        onClick={() => router.post(`${alamat}/salin`, { UuidOutlet, Senin }, { preserveScroll: true })}
                    >
                        Salin minggu lalu
                    </Button>
                ) : null}
            </div>

            {Jadwal.Baris.length === 0 ? (
                <Pemberitahuan jenis="info" judul="Belum ada karyawan di outlet ini">
                    Tambahkan karyawan dengan outlet utama ini di menu Daftar karyawan.
                </Pemberitahuan>
            ) : (
                <ul className="flex flex-col gap-3" aria-label="Jadwal per karyawan">
                    {Jadwal.Baris.map((b) => (
                        <li key={b.Uuid} className="rounded-panel border border-garis bg-permukaan p-3">
                            <p className="font-semibold break-words">
                                {b.Nama}
                                {b.Jabatan ? (
                                    <span className="font-normal text-teks-sekunder"> · {b.Jabatan}</span>
                                ) : null}
                                {!b.Aktif ? <span className="font-normal text-teks-sekunder"> · nonaktif</span> : null}
                            </p>
                            <div className="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
                                {Jadwal.Hari.map((tanggal, i) => {
                                    const awal = b.Jadwal[tanggal];
                                    const nilai = isian[b.Uuid]?.[tanggal] ?? { JamMulai: '', JamSelesai: '' };
                                    const kunci = !Izin.Kelola || awal?.OutletLain === true || !b.Aktif;

                                    return (
                                        <fieldset
                                            key={tanggal}
                                            className="min-w-0 rounded-panel border border-garis p-2"
                                        >
                                            <legend className="px-1 text-label text-teks-sekunder">
                                                {namaHari[i]} {tanggal.slice(8, 10)}/{tanggal.slice(5, 7)}
                                            </legend>
                                            {awal?.OutletLain ? (
                                                <p className="text-keterangan text-teks-sekunder">
                                                    Di outlet lain {awal.JamMulai}–{awal.JamSelesai}
                                                </p>
                                            ) : (
                                                <div className="flex items-center gap-1">
                                                    {(['JamMulai', 'JamSelesai'] as const).map((kolom) => (
                                                        <Input
                                                            key={kolom}
                                                            aria-label={`${b.Nama} ${namaHari[i] ?? ''} ${kolom === 'JamMulai' ? 'mulai' : 'selesai'}`}
                                                            placeholder={kolom === 'JamMulai' ? '08:00' : '16:00'}
                                                            inputMode="numeric"
                                                            maxLength={5}
                                                            disabled={kunci}
                                                            value={nilai[kolom]}
                                                            aria-invalid={
                                                                nilai[kolom] !== '' && !CekJam(nilai[kolom])
                                                                    ? true
                                                                    : undefined
                                                            }
                                                            onChange={(e) =>
                                                                Ubah(b.Uuid, tanggal, kolom, e.target.value)
                                                            }
                                                            onBlur={(e) =>
                                                                Ubah(b.Uuid, tanggal, kolom, RapikanJam(e.target.value))
                                                            }
                                                            className="h-11 min-w-0 px-2 text-center font-mono tabular-nums"
                                                        />
                                                    ))}
                                                </div>
                                            )}
                                        </fieldset>
                                    );
                                })}
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            {Izin.Kelola && Jadwal.Baris.length > 0 ? (
                <div className="flex flex-wrap items-center gap-3">
                    <Tombol
                        type="button"
                        memproses={memproses}
                        disabled={berubah.length === 0 || galatLokal}
                        onClick={Simpan}
                    >
                        Simpan jadwal
                    </Tombol>
                    <span className="text-keterangan text-teks-sekunder">
                        {galatLokal
                            ? 'Periksa jam yang ditandai: format JJ:MM, isi keduanya atau kosongkan keduanya.'
                            : berubah.length === 0
                              ? 'Belum ada perubahan.'
                              : `${berubah.length} hari berubah.`}
                    </span>
                </div>
            ) : null}
        </TataLetakAplikasi>
    );
}

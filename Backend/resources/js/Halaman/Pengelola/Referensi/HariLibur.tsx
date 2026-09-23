import { router, useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import TabReferensi from '@/Komponen/Pengelola/TabReferensi';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import TataLetakPengelola from '@/TataLetak/TataLetakPengelola';
import { IzinPengelola, PunyaIzin, type Pilihan, type PropsBersamaPengelola } from '@/Tipe/Pengelola';

type HariLibur = {
    Uuid: string;
    Tanggal: string;
    Nama: string;
    Jenis: string;
    Status: 'Draf' | 'MenungguTinjauan' | 'Terbit' | 'Dibatalkan';
    NomorDasarHukum: string | null;
    PembatalanMenunggu: boolean;
    AlasanPembatalan: string | null;
    IdPengajuBatal: number | null;
    DibatalkanPada: string | null;
};

type PropsHariLibur = {
    Tahun: number;
    HariLibur: HariLibur[];
    IdPengajuMenunggu: number[];
    PeninjauMenunggu: number[];
    IdPengguna: number;
    PilihanJenis: Pilihan[];
};

const labelStatus = {
    Draf: { jenis: 'netral', teks: 'Draf' },
    MenungguTinjauan: { jenis: 'peringatan', teks: 'Menunggu tinjauan' },
    Terbit: { jenis: 'sukses', teks: 'Terbit' },
    Dibatalkan: { jenis: 'bahaya', teks: 'Dibatalkan' },
} as const;

const formatTanggal = new Intl.DateTimeFormat('id-ID', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    timeZone: 'UTC',
});

/** Hari libur nasional & cuti bersama per tahun (P-02). Wajib terbit paling lambat 1 Desember (BR-P02.4). */
export default function HalamanHariLibur({
    Tahun,
    HariLibur,
    IdPengajuMenunggu,
    PeninjauMenunggu,
    IdPengguna,
    PilihanJenis,
}: PropsHariLibur) {
    const { props } = usePage<PropsBersamaPengelola>();
    const bolehAjukan = PunyaIzin(props.Pengguna, IzinPengelola.ReferensiHariLiburAjukan);
    const bolehSetujui = PunyaIzin(props.Pengguna, IzinPengelola.ReferensiHariLiburSetujui);
    const [sunting, AturSunting] = useState<HariLibur | 'baru' | null>(null);
    const [meninjau, AturMeninjau] = useState(false);
    const [pembatalan, AturPembatalan] = useState<{ jenis: 'ajukan' | 'tinjau'; hari: HariLibur } | null>(null);
    const labelJenis = new Map(PilihanJenis.map((item) => [item.Nilai, item.Label]));
    const adaDraf = HariLibur.some((hari) => hari.Status === 'Draf');
    const adaMenunggu = HariLibur.some((hari) => hari.Status === 'MenungguTinjauan');
    const bisaTinjau =
        bolehSetujui &&
        adaMenunggu &&
        !IdPengajuMenunggu.includes(IdPengguna) &&
        !PeninjauMenunggu.includes(IdPengguna);

    const PilihTahun = (tahun: string) =>
        router.get('/referensi/hari-libur', { saring: { Tahun: tahun } }, { preserveState: false });
    const AjukanTahun = () => router.post(`/referensi/hari-libur/tahun/${Tahun}/ajukan`, {}, { preserveScroll: true });
    const HapusDraf = (hari: HariLibur) =>
        router.delete(`/referensi/hari-libur/${hari.Uuid}`, { preserveScroll: true });
    const pilihanTahun = [Tahun - 1, Tahun, Tahun + 1].map((tahun) => ({ Nilai: String(tahun), Label: String(tahun) }));

    return (
        <TataLetakPengelola
            judul="Referensi"
            aksi={
                bolehAjukan && sunting === null ? (
                    <Tombol onClick={() => AturSunting('baru')}>Tambah hari libur</Tombol>
                ) : null
            }
        >
            <TabReferensi />
            {props.errors.Umum ? <Pemberitahuan jenis="bahaya">{props.errors.Umum}</Pemberitahuan> : null}

            <div className="flex flex-wrap items-end justify-between gap-3">
                <div className="w-40">
                    <BidangPilihan label="Tahun" nilai={String(Tahun)} opsi={pilihanTahun} saatBerubah={PilihTahun} />
                </div>
                <div className="flex gap-2">
                    {bolehAjukan && adaDraf ? <Tombol onClick={AjukanTahun}>Ajukan semua draf {Tahun}</Tombol> : null}
                    {bisaTinjau && !meninjau ? (
                        <Tombol onClick={() => AturMeninjau(true)}>Tinjau hari libur {Tahun}</Tombol>
                    ) : null}
                </div>
            </div>

            {sunting !== null ? (
                <FormHariLibur
                    key={sunting === 'baru' ? 'baru' : sunting.Uuid}
                    hariLibur={sunting === 'baru' ? null : sunting}
                    tahun={Tahun}
                    pilihanJenis={PilihanJenis}
                    saatSelesai={() => AturSunting(null)}
                />
            ) : null}
            {pembatalan !== null ? (
                <FormPembatalan
                    key={`${pembatalan.jenis}-${pembatalan.hari.Uuid}`}
                    jenis={pembatalan.jenis}
                    hari={pembatalan.hari}
                    saatSelesai={() => AturPembatalan(null)}
                />
            ) : null}
            {meninjau ? (
                <FormTinjauTahun
                    tahun={Tahun}
                    jumlah={HariLibur.filter((hari) => hari.Status === 'MenungguTinjauan').length}
                    saatSelesai={() => AturMeninjau(false)}
                />
            ) : null}

            {HariLibur.length === 0 ? (
                <Pemberitahuan jenis="peringatan" judul={`Belum ada hari libur ${Tahun}`}>
                    Masukkan libur nasional & cuti bersama sesuai SKB terbaru. Hari libur tahun berikutnya wajib terbit
                    paling lambat 1 Desember.
                </Pemberitahuan>
            ) : (
                <section className="overflow-x-auto rounded-panel border border-garis bg-permukaan">
                    <table className="w-full text-left text-isi">
                        <caption className="sr-only">Hari libur tahun {Tahun}</caption>
                        <thead className="border-b border-garis text-label text-teks-sekunder">
                            <tr>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Tanggal
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Nama
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Jenis
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Dasar hukum
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Status
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    <span className="sr-only">Aksi</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {HariLibur.map((hari) => (
                                <tr key={hari.Uuid} className="border-b border-garis last:border-b-0">
                                    <td className="whitespace-nowrap px-4 py-2 text-teks-utama">
                                        {formatTanggal.format(new Date(`${hari.Tanggal}T00:00:00Z`))}
                                    </td>
                                    <td className="px-4 py-2 text-teks-utama">{hari.Nama}</td>
                                    <td className="px-4 py-2 text-teks-sekunder">
                                        {labelJenis.get(hari.Jenis) ?? hari.Jenis}
                                    </td>
                                    <td className="px-4 py-2 text-teks-sekunder">{hari.NomorDasarHukum ?? '—'}</td>
                                    <td className="px-4 py-2">
                                        <LabelStatus
                                            jenis={labelStatus[hari.Status].jenis}
                                            teks={labelStatus[hari.Status].teks}
                                        />
                                        {hari.PembatalanMenunggu ? (
                                            <p className="mt-1 text-keterangan text-peringatan">
                                                Pembatalan menunggu tinjauan: {hari.AlasanPembatalan}
                                            </p>
                                        ) : null}
                                    </td>
                                    <td className="px-4 py-2">
                                        {bolehAjukan && hari.Status === 'Draf' ? (
                                            <div className="flex justify-end gap-2">
                                                <Tombol varian="sekunder" onClick={() => AturSunting(hari)}>
                                                    Ubah
                                                </Tombol>
                                                <Tombol varian="bahaya" onClick={() => HapusDraf(hari)}>
                                                    Hapus
                                                </Tombol>
                                            </div>
                                        ) : null}
                                        {bolehAjukan && hari.Status === 'Terbit' && !hari.PembatalanMenunggu ? (
                                            <div className="flex justify-end">
                                                <Tombol
                                                    varian="bahaya"
                                                    onClick={() => AturPembatalan({ jenis: 'ajukan', hari })}
                                                >
                                                    Ajukan pembatalan
                                                </Tombol>
                                            </div>
                                        ) : null}
                                        {bolehSetujui &&
                                        hari.PembatalanMenunggu &&
                                        hari.IdPengajuBatal !== IdPengguna ? (
                                            <div className="flex justify-end">
                                                <Tombol onClick={() => AturPembatalan({ jenis: 'tinjau', hari })}>
                                                    Tinjau pembatalan
                                                </Tombol>
                                            </div>
                                        ) : null}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </section>
            )}
        </TataLetakPengelola>
    );
}

type PropsFormHariLibur = {
    hariLibur: HariLibur | null;
    tahun: number;
    pilihanJenis: Pilihan[];
    saatSelesai: () => void;
};

function FormHariLibur({ hariLibur, tahun, pilihanJenis, saatSelesai }: PropsFormHariLibur) {
    const formulir = useForm({
        Tanggal: hariLibur?.Tanggal ?? `${tahun}-`,
        Nama: hariLibur?.Nama ?? '',
        Jenis: hariLibur?.Jenis ?? 'Nasional',
        NomorDasarHukum: hariLibur?.NomorDasarHukum ?? '',
    });

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        const opsi = { preserveScroll: true, onSuccess: saatSelesai };

        if (hariLibur === null) {
            formulir.post('/referensi/hari-libur', opsi);
        } else {
            formulir.put(`/referensi/hari-libur/${hariLibur.Uuid}`, opsi);
        }
    };

    return (
        <form
            onSubmit={Kirim}
            className="grid gap-4 rounded-panel border border-garis bg-permukaan p-6 sm:grid-cols-2"
            noValidate
        >
            <h2 className="text-subjudul font-semibold text-teks-utama sm:col-span-2">
                {hariLibur === null ? 'Tambah hari libur' : `Ubah ${hariLibur.Nama}`}
            </h2>
            <BidangTeks
                label="Tanggal (TTTT-BB-HH)"
                kode
                nilai={formulir.data.Tanggal}
                saatBerubah={(nilai) => formulir.setData('Tanggal', nilai)}
                galat={formulir.errors.Tanggal}
            />
            <BidangTeks
                label="Nama"
                nilai={formulir.data.Nama}
                saatBerubah={(nilai) => formulir.setData('Nama', nilai)}
                galat={formulir.errors.Nama}
            />
            <BidangPilihan
                label="Jenis"
                nilai={formulir.data.Jenis}
                opsi={pilihanJenis}
                saatBerubah={(nilai) => formulir.setData('Jenis', nilai)}
                galat={formulir.errors.Jenis}
            />
            <BidangTeks
                label="Nomor dasar hukum"
                keterangan="Misal SKB 3 Menteri. Wajib sebelum diajukan."
                nilai={formulir.data.NomorDasarHukum}
                saatBerubah={(nilai) => formulir.setData('NomorDasarHukum', nilai)}
                galat={formulir.errors.NomorDasarHukum}
            />
            <div className="flex gap-2 sm:col-span-2">
                <Tombol type="submit" memproses={formulir.processing}>
                    Simpan draf
                </Tombol>
                <Tombol varian="sekunder" onClick={saatSelesai}>
                    Batal
                </Tombol>
            </div>
        </form>
    );
}

function FormTinjauTahun({ tahun, jumlah, saatSelesai }: { tahun: number; jumlah: number; saatSelesai: () => void }) {
    const formulir = useForm({ Keputusan: 'Setuju', Catatan: '' });

    const Kirim = (keputusan: 'Setuju' | 'Tolak') => {
        formulir.transform((data) => ({ ...data, Keputusan: keputusan }));
        formulir.post(`/referensi/hari-libur/tahun/${tahun}/tinjau`, { preserveScroll: true, onSuccess: saatSelesai });
    };

    return (
        <section className="flex flex-col gap-4 rounded-panel border border-garis bg-permukaan p-6">
            <h2 className="text-subjudul font-semibold text-teks-utama">
                Tinjau {jumlah} hari libur tahun {tahun}
            </h2>
            <p className="text-isi text-teks-sekunder">
                Cocokkan setiap tanggal dengan SKB. Setelah terbit, data tidak bisa diubah.
            </p>
            <BidangTeks
                label="Catatan (wajib bila menolak)"
                nilai={formulir.data.Catatan}
                saatBerubah={(nilai) => formulir.setData('Catatan', nilai)}
                galat={formulir.errors.Catatan}
                maxLength={500}
            />
            <div className="flex gap-2">
                <Tombol memproses={formulir.processing} onClick={() => Kirim('Setuju')}>
                    Terbitkan hari libur
                </Tombol>
                <Tombol varian="bahaya" disabled={formulir.processing} onClick={() => Kirim('Tolak')}>
                    Tolak pengajuan
                </Tombol>
                <Tombol varian="sekunder" onClick={saatSelesai}>
                    Batal
                </Tombol>
            </div>
        </section>
    );
}

type PropsFormPembatalan = { jenis: 'ajukan' | 'tinjau'; hari: HariLibur; saatSelesai: () => void };

function FormPembatalan({ jenis, hari, saatSelesai }: PropsFormPembatalan) {
    const formulir = useForm({ Alasan: '', Keputusan: 'Setuju', Catatan: '' });

    const Ajukan = () =>
        formulir.post(`/referensi/hari-libur/${hari.Uuid}/pembatalan`, {
            preserveScroll: true,
            onSuccess: saatSelesai,
        });
    const Tinjau = (keputusan: 'Setuju' | 'Tolak') => {
        formulir.transform((data) => ({ Keputusan: keputusan, Catatan: data.Catatan }));
        formulir.post(`/referensi/hari-libur/${hari.Uuid}/pembatalan/tinjau`, {
            preserveScroll: true,
            onSuccess: saatSelesai,
        });
    };

    return (
        <section className="flex flex-col gap-4 rounded-panel border border-bahaya bg-permukaan p-6">
            <h2 className="text-subjudul font-semibold text-teks-utama">
                {jenis === 'ajukan' ? `Ajukan pembatalan ${hari.Nama}` : `Tinjau pembatalan ${hari.Nama}`}
            </h2>
            <p className="text-isi text-teks-sekunder">
                {jenis === 'ajukan'
                    ? 'Hari libur tetap berlaku sampai pembatalan disetujui anggota lain. Untuk menggeser tanggal, batalkan lalu tambahkan hari libur baru.'
                    : `Alasan: ${hari.AlasanPembatalan ?? '—'}. Bila disetujui, hari libur tidak lagi dipakai tenant; datanya tetap tersimpan.`}
            </p>
            {jenis === 'ajukan' ? (
                <BidangTeks
                    label="Alasan pembatalan"
                    keterangan="Misal nomor SKB perubahan."
                    nilai={formulir.data.Alasan}
                    saatBerubah={(nilai) => formulir.setData('Alasan', nilai)}
                    galat={formulir.errors.Alasan}
                    maxLength={500}
                    autoFocus
                />
            ) : (
                <BidangTeks
                    label="Catatan (wajib bila menolak)"
                    nilai={formulir.data.Catatan}
                    saatBerubah={(nilai) => formulir.setData('Catatan', nilai)}
                    galat={formulir.errors.Catatan}
                    maxLength={500}
                />
            )}
            <div className="flex gap-2">
                {jenis === 'ajukan' ? (
                    <Tombol varian="bahaya" memproses={formulir.processing} onClick={Ajukan}>
                        Ajukan pembatalan
                    </Tombol>
                ) : (
                    <>
                        <Tombol varian="bahaya" memproses={formulir.processing} onClick={() => Tinjau('Setuju')}>
                            Setujui pembatalan
                        </Tombol>
                        <Tombol varian="sekunder" disabled={formulir.processing} onClick={() => Tinjau('Tolak')}>
                            Tolak pembatalan
                        </Tombol>
                    </>
                )}
                <Tombol varian="sekunder" onClick={saatSelesai}>
                    Batal
                </Tombol>
            </div>
        </section>
    );
}

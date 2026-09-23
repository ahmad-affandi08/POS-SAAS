import { router, useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import KotakCentang from '@/Komponen/Formulir/KotakCentang';
import Tombol from '@/Komponen/Formulir/Tombol';
import TabReferensi from '@/Komponen/Pengelola/TabReferensi';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import Paginasi from '@/Komponen/Umpan/Paginasi';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { FormatPersen } from '@/Pustaka/Format';
import { FormatTanggal } from '@/Pustaka/FormatWaktu';
import TataLetakPengelola from '@/TataLetak/TataLetakPengelola';
import { IzinPengelola, PunyaIzin, type DaftarBerhalaman, type PropsBersamaPengelola } from '@/Tipe/Pengelola';

type Persetujuan = { Peninjau: string; IdPeninjau: number; Keputusan: 'Setuju' | 'Tolak'; Catatan: string | null };

type Tarif = {
    Uuid: string;
    KodeJenisPajak: string;
    NamaJenisPajak: string;
    Tarif: string;
    PengaliDppPembilang: number;
    PengaliDppPenyebut: number;
    KodeWilayah: string | null;
    BiayaLayananMasukDpp: boolean;
    BerlakuMulai: string;
    BerlakuSampai: string | null;
    Status: 'Draf' | 'MenungguTinjauan' | 'Terbit' | 'Berakhir';
    NomorDasarHukum: string | null;
    TautanDasarHukum: string | null;
    IdPengaju: number | null;
    PersetujuanDibutuhkan: number;
    Persetujuan: Persetujuan[];
    JumlahSetuju: number;
};

type JenisPajak = { Kode: string; Nama: string; Cakupan: 'Nasional' | 'Daerah' | 'Kustom' };

type PropsTarifPajak = {
    Tarif: DaftarBerhalaman<Tarif>;
    JenisPajak: JenisPajak[];
    Saring: { Status: string | null };
    IdPengguna: number;
};

const labelStatus = {
    Draf: { jenis: 'netral', teks: 'Draf' },
    MenungguTinjauan: { jenis: 'peringatan', teks: 'Menunggu tinjauan' },
    Terbit: { jenis: 'sukses', teks: 'Terbit' },
    Berakhir: { jenis: 'netral', teks: 'Berakhir' },
} as const;

/** Tarif pajak master bertanggal dengan persetujuan four-eyes (P-02, BR-P02.1, BR-P02.2). */
export default function HalamanTarifPajak({ Tarif, JenisPajak, Saring, IdPengguna }: PropsTarifPajak) {
    const { props } = usePage<PropsBersamaPengelola>();
    const bolehAjukan = PunyaIzin(props.Pengguna, IzinPengelola.ReferensiTarifPajakAjukan);
    const bolehSetujui = PunyaIzin(props.Pengguna, IzinPengelola.ReferensiTarifPajakSetujui);
    const [sunting, AturSunting] = useState<Tarif | 'baru' | null>(null);
    const [ditinjau, AturDitinjau] = useState<Tarif | null>(null);
    const Ajukan = (tarif: Tarif) =>
        router.post(`/referensi/tarif-pajak/${tarif.Uuid}/ajukan`, {}, { preserveScroll: true });
    const SaringStatus = (status: string) =>
        router.get('/referensi/tarif-pajak', status ? { saring: { Status: status } } : {}, { preserveState: true });

    return (
        <TataLetakPengelola
            judul="Referensi"
            aksi={
                bolehAjukan && sunting === null ? (
                    <Tombol onClick={() => AturSunting('baru')}>Buat draf tarif</Tombol>
                ) : null
            }
        >
            <TabReferensi />
            <Pemberitahuan jenis="info" judul="Aturan tarif pajak">
                Tarif terbit tidak pernah diubah atau dihapus; koreksi dibuat sebagai tarif baru dengan tanggal berlaku
                baru. Tarif nasional butuh 2 penyetuju, tarif daerah 1 penyetuju, dan pengaju tidak boleh menyetujui
                drafnya sendiri.
            </Pemberitahuan>
            {props.errors.Umum ? <Pemberitahuan jenis="bahaya">{props.errors.Umum}</Pemberitahuan> : null}

            {sunting !== null ? (
                <FormTarif
                    key={sunting === 'baru' ? 'baru' : sunting.Uuid}
                    tarif={sunting === 'baru' ? null : sunting}
                    jenisPajak={JenisPajak}
                    saatSelesai={() => AturSunting(null)}
                />
            ) : null}
            {ditinjau !== null ? (
                <FormTinjau key={ditinjau.Uuid} tarif={ditinjau} saatSelesai={() => AturDitinjau(null)} />
            ) : null}

            <div className="w-56">
                <BidangPilihan
                    label="Status"
                    nilai={Saring.Status ?? ''}
                    kosong="Semua status"
                    opsi={[
                        { Nilai: 'Draf', Label: 'Draf' },
                        { Nilai: 'MenungguTinjauan', Label: 'Menunggu tinjauan' },
                        { Nilai: 'Terbit', Label: 'Terbit' },
                    ]}
                    saatBerubah={SaringStatus}
                />
            </div>

            {Tarif.Data.length === 0 ? (
                <Pemberitahuan jenis="info" judul="Belum ada tarif pajak">
                    Jalankan seeder untuk membuat draf PPN bawaan, atau buat draf tarif baru.
                </Pemberitahuan>
            ) : (
                <section className="overflow-x-auto rounded-panel border border-garis bg-permukaan">
                    <table className="w-full text-left text-isi">
                        <caption className="sr-only">Daftar tarif pajak</caption>
                        <thead className="border-b border-garis text-label text-teks-sekunder">
                            <tr>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Pajak
                                </th>
                                <th scope="col" className="px-4 py-2 text-right font-semibold">
                                    Tarif
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Berlaku
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
                            {Tarif.Data.map((tarif) => {
                                const status = labelStatus[tarif.Status];
                                const sudahMemutuskan = tarif.Persetujuan.some(
                                    (item) => item.IdPeninjau === IdPengguna,
                                );
                                const bisaTinjau =
                                    bolehSetujui &&
                                    tarif.Status === 'MenungguTinjauan' &&
                                    tarif.IdPengaju !== IdPengguna &&
                                    !sudahMemutuskan;

                                return (
                                    <tr key={tarif.Uuid} className="border-b border-garis align-top last:border-b-0">
                                        <td className="px-4 py-3">
                                            <p className="font-semibold text-teks-utama">{tarif.NamaJenisPajak}</p>
                                            <p className="text-keterangan text-teks-sekunder">
                                                {tarif.KodeWilayah ? `Wilayah ${tarif.KodeWilayah}` : 'Nasional'}
                                                {tarif.BiayaLayananMasukDpp ? ' · biaya layanan masuk DPP' : ''}
                                            </p>
                                        </td>
                                        <td className="px-4 py-3 text-right tabular-nums">
                                            <p className="text-teks-utama">{FormatPersen(tarif.Tarif)}%</p>
                                            <p className="text-keterangan text-teks-sekunder">
                                                DPP {tarif.PengaliDppPembilang}/{tarif.PengaliDppPenyebut}
                                            </p>
                                        </td>
                                        <td className="px-4 py-3 text-teks-sekunder">
                                            {FormatTanggal(tarif.BerlakuMulai)} –{' '}
                                            {tarif.BerlakuSampai ? FormatTanggal(tarif.BerlakuSampai) : 'seterusnya'}
                                        </td>
                                        <td className="px-4 py-3 text-teks-sekunder">
                                            {tarif.TautanDasarHukum ? (
                                                <a
                                                    href={tarif.TautanDasarHukum}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="text-brand underline"
                                                >
                                                    {tarif.NomorDasarHukum ?? 'Dokumen'}
                                                </a>
                                            ) : (
                                                (tarif.NomorDasarHukum ?? '—')
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <LabelStatus jenis={status.jenis} teks={status.teks} />
                                            {tarif.Status === 'MenungguTinjauan' ? (
                                                <p className="mt-1 text-keterangan text-teks-sekunder">
                                                    {tarif.JumlahSetuju} dari {tarif.PersetujuanDibutuhkan} persetujuan
                                                </p>
                                            ) : null}
                                            {tarif.Persetujuan.map((item) => (
                                                <p key={item.IdPeninjau} className="text-keterangan text-teks-sekunder">
                                                    {item.Keputusan === 'Setuju' ? 'Disetujui' : 'Ditolak'}{' '}
                                                    {item.Peninjau}
                                                    {item.Catatan ? `: ${item.Catatan}` : ''}
                                                </p>
                                            ))}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex justify-end gap-2">
                                                {bolehAjukan && tarif.Status === 'Draf' ? (
                                                    <>
                                                        <Tombol varian="sekunder" onClick={() => AturSunting(tarif)}>
                                                            Ubah
                                                        </Tombol>
                                                        <Tombol onClick={() => Ajukan(tarif)}>Ajukan</Tombol>
                                                    </>
                                                ) : null}
                                                {bisaTinjau ? (
                                                    <Tombol onClick={() => AturDitinjau(tarif)}>Tinjau</Tombol>
                                                ) : null}
                                            </div>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </section>
            )}
            <Paginasi
                alamat="/referensi/tarif-pajak"
                saring={Saring.Status ? { 'saring[Status]': Saring.Status } : {}}
                halamanSaatIni={Tarif.HalamanSaatIni}
                halamanTerakhir={Tarif.HalamanTerakhir}
                total={Tarif.Total}
                label="Halaman tarif pajak"
            />
        </TataLetakPengelola>
    );
}

function FormTarif({
    tarif,
    jenisPajak,
    saatSelesai,
}: {
    tarif: Tarif | null;
    jenisPajak: JenisPajak[];
    saatSelesai: () => void;
}) {
    const formulir = useForm({
        KodeJenisPajak: tarif?.KodeJenisPajak ?? jenisPajak[0]?.Kode ?? '',
        Tarif: tarif ? FormatPersen(tarif.Tarif).replace(',', '.') : '',
        PengaliDppPembilang: String(tarif?.PengaliDppPembilang ?? 1),
        PengaliDppPenyebut: String(tarif?.PengaliDppPenyebut ?? 1),
        KodeWilayah: tarif?.KodeWilayah ?? '',
        BiayaLayananMasukDpp: tarif?.BiayaLayananMasukDpp ?? false,
        BerlakuMulai: tarif?.BerlakuMulai ?? '',
        NomorDasarHukum: tarif?.NomorDasarHukum ?? '',
        TautanDasarHukum: tarif?.TautanDasarHukum ?? '',
    });
    const cakupan = jenisPajak.find((jenis) => jenis.Kode === formulir.data.KodeJenisPajak)?.Cakupan;

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        const opsi = { preserveScroll: true, onSuccess: saatSelesai };

        if (tarif === null) {
            formulir.post('/referensi/tarif-pajak', opsi);
        } else {
            formulir.put(`/referensi/tarif-pajak/${tarif.Uuid}`, opsi);
        }
    };

    return (
        <form
            onSubmit={Kirim}
            className="grid gap-4 rounded-panel border border-garis bg-permukaan p-6 sm:grid-cols-2"
            noValidate
        >
            <h2 className="text-subjudul font-semibold text-teks-utama sm:col-span-2">
                {tarif === null ? 'Buat draf tarif pajak' : 'Ubah draf tarif pajak'}
            </h2>
            <BidangPilihan
                label="Jenis pajak"
                nilai={formulir.data.KodeJenisPajak}
                opsi={jenisPajak.map((jenis) => ({ Nilai: jenis.Kode, Label: jenis.Nama }))}
                saatBerubah={(nilai) => formulir.setData('KodeJenisPajak', nilai)}
                galat={formulir.errors.KodeJenisPajak}
            />
            <BidangTeks
                label="Tarif (persen)"
                inputMode="decimal"
                keterangan="Pakai titik untuk desimal, misal 10 atau 10.5."
                nilai={formulir.data.Tarif}
                saatBerubah={(nilai) => formulir.setData('Tarif', nilai)}
                galat={formulir.errors.Tarif}
            />
            <div className="grid grid-cols-2 gap-2">
                <BidangTeks
                    label="Pengali DPP: pembilang"
                    inputMode="numeric"
                    nilai={formulir.data.PengaliDppPembilang}
                    saatBerubah={(nilai) => formulir.setData('PengaliDppPembilang', nilai)}
                    galat={formulir.errors.PengaliDppPembilang}
                />
                <BidangTeks
                    label="Penyebut"
                    inputMode="numeric"
                    keterangan="PPN non-mewah: 11/12. Penuh: 1/1."
                    nilai={formulir.data.PengaliDppPenyebut}
                    saatBerubah={(nilai) => formulir.setData('PengaliDppPenyebut', nilai)}
                    galat={formulir.errors.PengaliDppPenyebut}
                />
            </div>
            {cakupan === 'Daerah' ? (
                <BidangTeks
                    label="Kode kabupaten/kota"
                    kode
                    keterangan="Misal 33.74 untuk Kota Semarang."
                    nilai={formulir.data.KodeWilayah}
                    saatBerubah={(nilai) => formulir.setData('KodeWilayah', nilai)}
                    galat={formulir.errors.KodeWilayah}
                />
            ) : null}
            <BidangTeks
                label="Berlaku mulai (TTTT-BB-HH)"
                kode
                nilai={formulir.data.BerlakuMulai}
                saatBerubah={(nilai) => formulir.setData('BerlakuMulai', nilai)}
                galat={formulir.errors.BerlakuMulai}
            />
            <BidangTeks
                label="Nomor dasar hukum"
                keterangan="Nomor PMK atau Perda. Wajib sebelum diajukan."
                nilai={formulir.data.NomorDasarHukum}
                saatBerubah={(nilai) => formulir.setData('NomorDasarHukum', nilai)}
                galat={formulir.errors.NomorDasarHukum}
            />
            <BidangTeks
                label="Tautan dokumen (opsional)"
                nilai={formulir.data.TautanDasarHukum}
                saatBerubah={(nilai) => formulir.setData('TautanDasarHukum', nilai)}
                galat={formulir.errors.TautanDasarHukum}
            />
            {cakupan === 'Daerah' ? (
                <KotakCentang
                    label="Biaya layanan masuk dasar pengenaan pajak"
                    nilai={formulir.data.BiayaLayananMasukDpp}
                    saatBerubah={(nilai) => formulir.setData('BiayaLayananMasukDpp', nilai)}
                />
            ) : null}
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

function FormTinjau({ tarif, saatSelesai }: { tarif: Tarif; saatSelesai: () => void }) {
    const formulir = useForm({ Keputusan: 'Setuju', Catatan: '' });

    const Kirim = (keputusan: 'Setuju' | 'Tolak') => {
        formulir.transform((data) => ({ ...data, Keputusan: keputusan }));
        formulir.post(`/referensi/tarif-pajak/${tarif.Uuid}/tinjau`, { preserveScroll: true, onSuccess: saatSelesai });
    };

    return (
        <section className="flex flex-col gap-4 rounded-panel border border-garis bg-permukaan p-6">
            <h2 className="text-subjudul font-semibold text-teks-utama">
                Tinjau {tarif.NamaJenisPajak} {FormatPersen(tarif.Tarif)}% mulai {FormatTanggal(tarif.BerlakuMulai)}
            </h2>
            <p className="text-isi text-teks-sekunder">
                Periksa tarif, pengali DPP {tarif.PengaliDppPembilang}/{tarif.PengaliDppPenyebut}, tanggal berlaku, dan
                dasar hukum {tarif.NomorDasarHukum ?? ''}. Setelah terbit, tarif tidak bisa diubah.
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
                    Setujui tarif
                </Tombol>
                <Tombol varian="bahaya" disabled={formulir.processing} onClick={() => Kirim('Tolak')}>
                    Tolak tarif
                </Tombol>
                <Tombol varian="sekunder" onClick={saatSelesai}>
                    Batal
                </Tombol>
            </div>
        </section>
    );
}

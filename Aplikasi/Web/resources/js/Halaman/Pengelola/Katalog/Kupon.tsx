import { useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import GrupCentang from '@/Komponen/Formulir/GrupCentang';
import KotakCentang from '@/Komponen/Formulir/KotakCentang';
import Tombol from '@/Komponen/Formulir/Tombol';
import TabKatalog from '@/Komponen/Pengelola/TabKatalog';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { FormatPersen, FormatRupiah } from '@/Pustaka/Format';
import { FormatTanggal } from '@/Pustaka/FormatWaktu';
import TataLetakPengelola from '@/TataLetak/TataLetakPengelola';
import { IzinPengelola, PunyaIzin, type PropsBersamaPengelola } from '@/Tipe/Pengelola';

type Kupon = {
    Kode: string;
    Jenis: 'Persen' | 'Nominal';
    Nilai: string;
    DurasiBulan: number;
    Kuota: number | null;
    DaftarKodePaket: string[] | null;
    BerlakuSampai: string | null;
    Aktif: boolean;
};

type PropsKupon = { Kupon: Kupon[]; Paket: { Kode: string; Nama: string }[] };

/** Kupon langganan (P-04). Pemakaian dicatat saat penagihan (P-08). */
export default function HalamanKupon({ Kupon, Paket }: PropsKupon) {
    const { props } = usePage<PropsBersamaPengelola>();
    const bolehKelola = PunyaIzin(props.Pengguna, IzinPengelola.KatalogKuponKelola);
    const [sunting, AturSunting] = useState<Kupon | 'baru' | null>(null);

    return (
        <TataLetakPengelola
            judul="Katalog"
            aksi={
                bolehKelola && sunting === null ? <Tombol onClick={() => AturSunting('baru')}>Buat kupon</Tombol> : null
            }
        >
            <TabKatalog />
            {sunting !== null ? (
                <FormKupon
                    key={sunting === 'baru' ? 'baru' : sunting.Kode}
                    kupon={sunting === 'baru' ? null : sunting}
                    paket={Paket}
                    saatSelesai={() => AturSunting(null)}
                />
            ) : null}
            {Kupon.length === 0 ? (
                <Pemberitahuan jenis="info" judul="Belum ada kupon">
                    Buat kupon untuk promo langganan, misal diskon 50% selama 3 bulan.
                </Pemberitahuan>
            ) : (
                <section className="overflow-x-auto rounded-panel border border-garis bg-permukaan">
                    <table className="w-full text-left text-isi">
                        <caption className="sr-only">Daftar kupon langganan</caption>
                        <thead className="border-b border-garis text-label text-teks-sekunder">
                            <tr>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Kode
                                </th>
                                <th scope="col" className="px-4 py-2 text-right font-semibold">
                                    Diskon
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Ketentuan
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
                            {Kupon.map((kupon) => (
                                <tr key={kupon.Kode} className="border-b border-garis align-top last:border-b-0">
                                    <td className="px-4 py-3 font-mono text-label">{kupon.Kode}</td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {kupon.Jenis === 'Persen'
                                            ? `${FormatPersen(kupon.Nilai)}%`
                                            : FormatRupiah(kupon.Nilai)}
                                    </td>
                                    <td className="px-4 py-3 text-keterangan text-teks-sekunder">
                                        <span className="block">{kupon.DurasiBulan} bulan</span>
                                        <span className="block">Kuota: {kupon.Kuota ?? 'tanpa batas'}</span>
                                        <span className="block">
                                            Paket: {kupon.DaftarKodePaket?.join(', ') ?? 'semua'}
                                        </span>
                                        <span className="block">
                                            Berlaku sampai:{' '}
                                            {kupon.BerlakuSampai ? FormatTanggal(kupon.BerlakuSampai) : 'tanpa batas'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3">
                                        <LabelStatus
                                            jenis={kupon.Aktif ? 'sukses' : 'netral'}
                                            teks={kupon.Aktif ? 'Aktif' : 'Nonaktif'}
                                        />
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        {bolehKelola ? (
                                            <Tombol varian="sekunder" onClick={() => AturSunting(kupon)}>
                                                Ubah
                                            </Tombol>
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

function FormKupon({
    kupon,
    paket,
    saatSelesai,
}: {
    kupon: Kupon | null;
    paket: { Kode: string; Nama: string }[];
    saatSelesai: () => void;
}) {
    const formulir = useForm<{
        Kode: string;
        Jenis: string;
        Nilai: string;
        DurasiBulan: string;
        Kuota: string;
        DaftarKodePaket: string[];
        BerlakuSampai: string;
        Aktif: boolean;
    }>({
        Kode: kupon?.Kode ?? '',
        Jenis: kupon?.Jenis ?? 'Persen',
        Nilai: kupon?.Nilai.replace(/\.00$/, '') ?? '',
        DurasiBulan: String(kupon?.DurasiBulan ?? 1),
        Kuota: kupon?.Kuota == null ? '' : String(kupon.Kuota),
        DaftarKodePaket: kupon?.DaftarKodePaket ?? [],
        BerlakuSampai: kupon?.BerlakuSampai ?? '',
        Aktif: kupon?.Aktif ?? true,
    });

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        const opsi = { preserveScroll: true, onSuccess: saatSelesai };

        if (kupon === null) {
            formulir.post('/katalog/kupon', opsi);
        } else {
            formulir.put(`/katalog/kupon/${encodeURIComponent(kupon.Kode)}`, opsi);
        }
    };

    return (
        <form
            onSubmit={Kirim}
            className="grid gap-4 rounded-panel border border-garis bg-permukaan p-6 sm:grid-cols-2"
            noValidate
        >
            <h2 className="text-subjudul font-semibold text-teks-utama sm:col-span-2">
                {kupon === null ? 'Buat kupon' : `Ubah ${kupon.Kode}`}
            </h2>
            <BidangTeks
                label="Kode"
                kode
                keterangan="Huruf besar/angka/tanda hubung. Tidak bisa diubah."
                nilai={formulir.data.Kode}
                saatBerubah={(nilai) => formulir.setData('Kode', nilai.toUpperCase())}
                galat={formulir.errors.Kode}
                disabled={kupon !== null}
            />
            <BidangPilihan
                label="Jenis diskon"
                nilai={formulir.data.Jenis}
                opsi={[
                    { Nilai: 'Persen', Label: 'Persen (%)' },
                    { Nilai: 'Nominal', Label: 'Nominal (Rp)' },
                ]}
                saatBerubah={(nilai) => formulir.setData('Jenis', nilai)}
                galat={formulir.errors.Jenis}
            />
            <BidangTeks
                label={formulir.data.Jenis === 'Persen' ? 'Diskon (%)' : 'Diskon (Rp)'}
                inputMode="decimal"
                nilai={formulir.data.Nilai}
                saatBerubah={(nilai) => formulir.setData('Nilai', nilai)}
                galat={formulir.errors.Nilai}
            />
            <BidangTeks
                label="Durasi (bulan)"
                inputMode="numeric"
                nilai={formulir.data.DurasiBulan}
                saatBerubah={(nilai) => formulir.setData('DurasiBulan', nilai)}
                galat={formulir.errors.DurasiBulan}
            />
            <BidangTeks
                label="Kuota pemakaian (opsional)"
                inputMode="numeric"
                nilai={formulir.data.Kuota}
                saatBerubah={(nilai) => formulir.setData('Kuota', nilai)}
                galat={formulir.errors.Kuota}
            />
            <BidangTeks
                label="Berlaku sampai (TTTT-BB-HH, opsional)"
                kode
                nilai={formulir.data.BerlakuSampai}
                saatBerubah={(nilai) => formulir.setData('BerlakuSampai', nilai)}
                galat={formulir.errors.BerlakuSampai}
            />
            <div className="sm:col-span-2">
                <GrupCentang
                    legenda="Berlaku untuk paket (kosongkan untuk semua paket)"
                    opsi={paket.map((item) => ({ nilai: item.Kode, label: item.Nama }))}
                    terpilih={formulir.data.DaftarKodePaket}
                    saatBerubah={(terpilih) => formulir.setData('DaftarKodePaket', terpilih)}
                    galat={formulir.errors.DaftarKodePaket}
                />
            </div>
            <KotakCentang
                label="Aktif"
                nilai={formulir.data.Aktif}
                saatBerubah={(nilai) => formulir.setData('Aktif', nilai)}
            />
            <div className="flex gap-2 sm:col-span-2">
                <Tombol type="submit" memproses={formulir.processing}>
                    Simpan kupon
                </Tombol>
                <Tombol varian="sekunder" onClick={saatSelesai}>
                    Batal
                </Tombol>
            </div>
        </form>
    );
}

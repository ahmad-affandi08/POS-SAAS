import { useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import TabKatalog from '@/Komponen/Pengelola/TabKatalog';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { FormatRupiah } from '@/Pustaka/Format';
import TataLetakPengelola from '@/TataLetak/TataLetakPengelola';
import { IzinPengelola, PunyaIzin, type PropsBersamaPengelola } from '@/Tipe/Pengelola';

type Addon = {
    Kode: string;
    Nama: string;
    HargaBulanan: string;
    KunciFitur: string | null;
    TambahanBatas: Record<string, number>;
    Status: 'Aktif' | 'Diarsipkan';
};

type PropsAddon = { Addon: Addon[]; Fitur: { Kunci: string; Nama: string }[]; KolomBatas: string[] };

const labelBatas: Record<string, string> = {
    BatasOutlet: 'Outlet',
    BatasPerangkatPerOutlet: 'Perangkat per outlet',
    BatasPengguna: 'Pengguna',
    BatasSku: 'SKU',
    KuotaPesanWaBulanan: 'Pesan WA per bulan',
    BatasPenyimpananMb: 'Penyimpanan (MB)',
};

/** Add-on langganan: fitur dan/atau tambahan batas yang dibeli terpisah (P-04). */
export default function HalamanAddon({ Addon, Fitur, KolomBatas }: PropsAddon) {
    const { props } = usePage<PropsBersamaPengelola>();
    const bolehKelola = PunyaIzin(props.Pengguna, IzinPengelola.KatalogAddonKelola);
    const [sunting, AturSunting] = useState<Addon | 'baru' | null>(null);
    const namaFitur = new Map(Fitur.map((fitur) => [fitur.Kunci, fitur.Nama]));

    return (
        <TataLetakPengelola
            judul="Katalog"
            aksi={
                bolehKelola && sunting === null ? (
                    <Tombol onClick={() => AturSunting('baru')}>Tambah add-on</Tombol>
                ) : null
            }
        >
            <TabKatalog />
            {sunting !== null ? (
                <FormAddon
                    key={sunting === 'baru' ? 'baru' : sunting.Kode}
                    addon={sunting === 'baru' ? null : sunting}
                    fitur={Fitur}
                    kolomBatas={KolomBatas}
                    saatSelesai={() => AturSunting(null)}
                />
            ) : null}
            {Addon.length === 0 ? (
                <Pemberitahuan jenis="info" judul="Belum ada add-on">
                    Tambahkan add-on seperti outlet tambahan, self-order QR, atau kuota WhatsApp.
                </Pemberitahuan>
            ) : (
                <section className="overflow-x-auto rounded-panel border border-garis bg-permukaan">
                    <table className="w-full text-left text-isi">
                        <caption className="sr-only">Daftar add-on</caption>
                        <thead className="border-b border-garis text-label text-teks-sekunder">
                            <tr>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Add-on
                                </th>
                                <th scope="col" className="px-4 py-2 text-right font-semibold">
                                    Harga/bulan
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Memberi
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
                            {Addon.map((addon) => (
                                <tr key={addon.Kode} className="border-b border-garis align-top last:border-b-0">
                                    <td className="px-4 py-3">
                                        <p className="font-semibold text-teks-utama">{addon.Nama}</p>
                                        <p className="font-mono text-keterangan text-teks-sekunder">{addon.Kode}</p>
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {FormatRupiah(addon.HargaBulanan)}
                                    </td>
                                    <td className="px-4 py-3 text-keterangan text-teks-sekunder">
                                        {addon.KunciFitur ? (
                                            <span className="block">
                                                Fitur: {namaFitur.get(addon.KunciFitur) ?? addon.KunciFitur}
                                            </span>
                                        ) : null}
                                        {Object.entries(addon.TambahanBatas).map(([kolom, nilai]) => (
                                            <span key={kolom} className="block">
                                                +{nilai} {labelBatas[kolom] ?? kolom}
                                            </span>
                                        ))}
                                    </td>
                                    <td className="px-4 py-3">
                                        <LabelStatus
                                            jenis={addon.Status === 'Aktif' ? 'sukses' : 'netral'}
                                            teks={addon.Status}
                                        />
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        {bolehKelola ? (
                                            <Tombol varian="sekunder" onClick={() => AturSunting(addon)}>
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

type PropsFormAddon = {
    addon: Addon | null;
    fitur: { Kunci: string; Nama: string }[];
    kolomBatas: string[];
    saatSelesai: () => void;
};

function FormAddon({ addon, fitur, kolomBatas, saatSelesai }: PropsFormAddon) {
    const formulir = useForm<{
        Kode: string;
        Nama: string;
        HargaBulanan: string;
        KunciFitur: string;
        TambahanBatas: Record<string, string>;
        Status: string;
    }>({
        Kode: addon?.Kode ?? '',
        Nama: addon?.Nama ?? '',
        HargaBulanan: addon?.HargaBulanan.replace(/\.00$/, '') ?? '',
        KunciFitur: addon?.KunciFitur ?? '',
        TambahanBatas: Object.fromEntries(
            kolomBatas.map((kolom) => [kolom, addon?.TambahanBatas[kolom] ? String(addon.TambahanBatas[kolom]) : '']),
        ),
        Status: addon?.Status ?? 'Aktif',
    });
    const galat = formulir.errors as Record<string, string | undefined>;

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        const opsi = { preserveScroll: true, onSuccess: saatSelesai };

        if (addon === null) {
            formulir.post('/katalog/add-on', opsi);
        } else {
            formulir.put(`/katalog/add-on/${encodeURIComponent(addon.Kode)}`, opsi);
        }
    };

    return (
        <form
            onSubmit={Kirim}
            className="grid gap-4 rounded-panel border border-garis bg-permukaan p-6 sm:grid-cols-2"
            noValidate
        >
            <h2 className="text-subjudul font-semibold text-teks-utama sm:col-span-2">
                {addon === null ? 'Tambah add-on' : `Ubah ${addon.Nama}`}
            </h2>
            <BidangTeks
                label="Kode"
                kode
                keterangan="Huruf besar, misal OUTLET_TAMBAHAN. Tidak bisa diubah."
                nilai={formulir.data.Kode}
                saatBerubah={(nilai) => formulir.setData('Kode', nilai.toUpperCase())}
                galat={formulir.errors.Kode}
                disabled={addon !== null}
            />
            <BidangTeks
                label="Nama"
                nilai={formulir.data.Nama}
                saatBerubah={(nilai) => formulir.setData('Nama', nilai)}
                galat={formulir.errors.Nama}
            />
            <BidangTeks
                label="Harga per bulan (Rp)"
                inputMode="decimal"
                keterangan="Berlaku untuk tagihan berikutnya."
                nilai={formulir.data.HargaBulanan}
                saatBerubah={(nilai) => formulir.setData('HargaBulanan', nilai)}
                galat={formulir.errors.HargaBulanan}
            />
            <BidangPilihan
                label="Fitur yang diberikan"
                nilai={formulir.data.KunciFitur}
                kosong="Tidak ada (hanya tambahan batas)"
                opsi={fitur.map((item) => ({ Nilai: item.Kunci, Label: item.Nama }))}
                saatBerubah={(nilai) => formulir.setData('KunciFitur', nilai)}
                galat={formulir.errors.KunciFitur}
            />
            <fieldset className="grid gap-3 sm:col-span-2 sm:grid-cols-3">
                <legend className="mb-2 text-label font-semibold text-teks-utama">
                    Tambahan batas per unit (kosongkan bila tidak ada)
                </legend>
                {kolomBatas.map((kolom) => (
                    <BidangTeks
                        key={kolom}
                        label={labelBatas[kolom] ?? kolom}
                        inputMode="numeric"
                        nilai={formulir.data.TambahanBatas[kolom] ?? ''}
                        saatBerubah={(nilai) =>
                            formulir.setData('TambahanBatas', { ...formulir.data.TambahanBatas, [kolom]: nilai })
                        }
                        galat={galat[`TambahanBatas.${kolom}`]}
                    />
                ))}
            </fieldset>
            {galat.TambahanBatas ? (
                <p className="text-keterangan font-semibold text-bahaya sm:col-span-2">{galat.TambahanBatas}</p>
            ) : null}
            <BidangPilihan
                label="Status"
                nilai={formulir.data.Status}
                opsi={[
                    { Nilai: 'Aktif', Label: 'Aktif (bisa dibeli)' },
                    { Nilai: 'Diarsipkan', Label: 'Diarsipkan (tidak dijual lagi)' },
                ]}
                saatBerubah={(nilai) => formulir.setData('Status', nilai)}
                galat={formulir.errors.Status}
            />
            <div className="flex gap-2 sm:col-span-2">
                <Tombol type="submit" memproses={formulir.processing}>
                    Simpan add-on
                </Tombol>
                <Tombol varian="sekunder" onClick={saatSelesai}>
                    Batal
                </Tombol>
            </div>
        </form>
    );
}

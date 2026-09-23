import { Link, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import { jenisLabelStatusTiket, type StatusTiket } from '@/Komponen/Dukungan/StatusTiket';
import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import KotakCentang from '@/Komponen/Formulir/KotakCentang';
import Tombol from '@/Komponen/Formulir/Tombol';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import Paginasi from '@/Komponen/Umpan/Paginasi';
import { FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import TataLetakPengelola from '@/TataLetak/TataLetakPengelola';
import type { DaftarBerhalaman, Pilihan } from '@/Tipe/Pengelola';

type BarisAntrean = {
    Uuid: string;
    Nomor: string;
    Judul: string;
    NamaTenant: string;
    Kategori: string;
    Prioritas: 'Mendesak' | 'Tinggi' | 'Normal' | 'Rendah';
    LabelPrioritas: string;
    Status: StatusTiket;
    LabelStatus: string;
    PenanggungJawab: string | null;
    BatasSlaPada: string;
    ResponsPertamaPada: string | null;
    LewatSla: boolean;
    PesanTerakhirPada: string | null;
};

type Saring = { Status: string; Prioritas: string; LewatSla: boolean; Milik: string; Kata: string };

type PropsAntrean = {
    Tiket: DaftarBerhalaman<BarisAntrean>;
    Saring: Saring;
    PilihanStatus: Pilihan[];
    PilihanPrioritas: Pilihan[];
};

const jenisLabelPrioritas = { Mendesak: 'bahaya', Tinggi: 'peringatan', Normal: 'netral', Rendah: 'netral' } as const;

function BuatQuery(saring: Saring): Record<string, string> {
    return Object.fromEntries(
        Object.entries({
            status: saring.Status === 'terbuka' ? '' : saring.Status,
            prioritas: saring.Prioritas,
            'lewat-sla': saring.LewatSla ? '1' : '',
            milik: saring.Milik,
            kata: saring.Kata,
        }).filter(([, nilai]) => nilai !== ''),
    );
}

/** Antrean tiket dukungan semua tenant (P-09). Tiket terbuka diurutkan dari batas SLA terdekat. */
export default function AntreanTiket({ Tiket, Saring, PilihanStatus, PilihanPrioritas }: PropsAntrean) {
    const [saring, AturSaring] = useState<Saring>(Saring);
    const Terapkan = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        router.get('/dukungan/tiket', BuatQuery(saring), { preserveState: true });
    };

    return (
        <TataLetakPengelola judul="Tiket dukungan">
            <form
                onSubmit={Terapkan}
                className="grid grid-cols-1 items-end gap-3 rounded-panel border border-garis bg-permukaan p-4 sm:grid-cols-2 lg:grid-cols-6"
            >
                <BidangPilihan
                    label="Status"
                    nilai={saring.Status}
                    opsi={[
                        { Nilai: 'terbuka', Label: 'Semua yang terbuka' },
                        { Nilai: 'semua', Label: 'Semua status' },
                        ...PilihanStatus,
                    ]}
                    saatBerubah={(nilai) => AturSaring({ ...saring, Status: nilai })}
                />
                <BidangPilihan
                    label="Prioritas"
                    nilai={saring.Prioritas}
                    opsi={PilihanPrioritas}
                    kosong="Semua prioritas"
                    saatBerubah={(nilai) => AturSaring({ ...saring, Prioritas: nilai })}
                />
                <BidangPilihan
                    label="Penanggung jawab"
                    nilai={saring.Milik}
                    opsi={[
                        { Nilai: 'saya', Label: 'Tiket saya' },
                        { Nilai: 'belum', Label: 'Belum ada' },
                    ]}
                    kosong="Semua"
                    saatBerubah={(nilai) => AturSaring({ ...saring, Milik: nilai })}
                />
                <BidangTeks
                    label="Cari nomor/judul"
                    nilai={saring.Kata}
                    saatBerubah={(nilai) => AturSaring({ ...saring, Kata: nilai })}
                />
                <KotakCentang
                    label="Hanya lewat SLA"
                    nilai={saring.LewatSla}
                    saatBerubah={(nilai) => AturSaring({ ...saring, LewatSla: nilai })}
                />
                <Tombol type="submit" varian="sekunder">
                    Terapkan saringan
                </Tombol>
            </form>

            {Tiket.Data.length === 0 ? (
                <p className="rounded-panel border border-garis bg-permukaan px-4 py-6 text-isi text-teks-sekunder">
                    Tidak ada tiket yang cocok dengan saringan ini.
                </p>
            ) : (
                <section className="overflow-x-auto rounded-panel border border-garis bg-permukaan">
                    <table className="w-full min-w-[960px] text-left text-isi">
                        <caption className="sr-only">Antrean tiket dukungan</caption>
                        <thead className="border-b border-garis text-label text-teks-sekunder">
                            <tr>
                                <th scope="col" className="px-3 py-2 font-semibold">
                                    Nomor
                                </th>
                                <th scope="col" className="px-3 py-2 font-semibold">
                                    Judul & tenant
                                </th>
                                <th scope="col" className="px-3 py-2 font-semibold">
                                    Prioritas
                                </th>
                                <th scope="col" className="px-3 py-2 font-semibold">
                                    Status
                                </th>
                                <th scope="col" className="px-3 py-2 font-semibold">
                                    Penanggung jawab
                                </th>
                                <th scope="col" className="px-3 py-2 font-semibold">
                                    Batas respons (SLA)
                                </th>
                                <th scope="col" className="px-3 py-2 font-semibold">
                                    Pesan terakhir
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {Tiket.Data.map((tiket) => (
                                <tr key={tiket.Uuid} className="border-b border-garis align-top last:border-b-0">
                                    <td className="whitespace-nowrap px-3 py-2 font-mono text-label">
                                        <Link
                                            href={`/dukungan/tiket/${tiket.Uuid}`}
                                            className="font-semibold text-brand underline outline-none focus-visible:ring-2 focus-visible:ring-brand"
                                        >
                                            {tiket.Nomor}
                                        </Link>
                                    </td>
                                    <td className="px-3 py-2">
                                        <span className="block break-words text-teks-utama">{tiket.Judul}</span>
                                        <span className="text-keterangan text-teks-sekunder">
                                            {tiket.NamaTenant} · {tiket.Kategori}
                                        </span>
                                    </td>
                                    <td className="px-3 py-2">
                                        <LabelStatus
                                            jenis={jenisLabelPrioritas[tiket.Prioritas]}
                                            teks={tiket.LabelPrioritas}
                                        />
                                    </td>
                                    <td className="px-3 py-2">
                                        <LabelStatus
                                            jenis={jenisLabelStatusTiket[tiket.Status]}
                                            teks={tiket.LabelStatus}
                                        />
                                    </td>
                                    <td className="px-3 py-2 text-teks-utama">
                                        {tiket.PenanggungJawab ?? 'Belum ada'}
                                    </td>
                                    <td className="whitespace-nowrap px-3 py-2 text-teks-sekunder">
                                        <span className="block">{FormatTanggalWaktu(tiket.BatasSlaPada)}</span>
                                        {tiket.LewatSla ? (
                                            <LabelStatus jenis="bahaya" teks="Lewat SLA" />
                                        ) : tiket.ResponsPertamaPada ? (
                                            <span className="text-keterangan">Sudah direspons</span>
                                        ) : null}
                                    </td>
                                    <td className="whitespace-nowrap px-3 py-2 text-teks-sekunder">
                                        {FormatTanggalWaktu(tiket.PesanTerakhirPada)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </section>
            )}

            <Paginasi
                alamat="/dukungan/tiket"
                saring={BuatQuery(Saring)}
                halamanSaatIni={Tiket.HalamanSaatIni}
                halamanTerakhir={Tiket.HalamanTerakhir}
                total={Tiket.Total}
                label="Halaman antrean tiket"
            />
        </TataLetakPengelola>
    );
}

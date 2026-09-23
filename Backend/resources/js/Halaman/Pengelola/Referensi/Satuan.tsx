import { useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import KotakCentang from '@/Komponen/Formulir/KotakCentang';
import Tombol from '@/Komponen/Formulir/Tombol';
import TabReferensi from '@/Komponen/Pengelola/TabReferensi';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import TataLetakPengelola from '@/TataLetak/TataLetakPengelola';
import { IzinPengelola, PunyaIzin, type PropsBersamaPengelola } from '@/Tipe/Pengelola';

type Satuan = { Kode: string; Nama: string; Simbol: string; BolehDesimal: boolean; Aktif: boolean };

/** Satuan standar platform, disalin ke tenant oleh template sektor (P-02). */
export default function HalamanSatuan({ Satuan }: { Satuan: Satuan[] }) {
    const { props } = usePage<PropsBersamaPengelola>();
    const bolehKelola = PunyaIzin(props.Pengguna, IzinPengelola.ReferensiSatuanKelola);
    const [sunting, AturSunting] = useState<Satuan | 'baru' | null>(null);

    return (
        <TataLetakPengelola
            judul="Referensi"
            aksi={
                bolehKelola && sunting === null ? (
                    <Tombol onClick={() => AturSunting('baru')}>Tambah satuan</Tombol>
                ) : null
            }
        >
            <TabReferensi />
            {sunting !== null ? (
                <FormSatuan
                    key={sunting === 'baru' ? 'baru' : sunting.Kode}
                    satuan={sunting === 'baru' ? null : sunting}
                    saatSelesai={() => AturSunting(null)}
                />
            ) : null}

            {Satuan.length === 0 ? (
                <Pemberitahuan jenis="info" judul="Belum ada satuan standar">
                    Tambahkan satuan pertama, misal pcs atau kg.
                </Pemberitahuan>
            ) : (
                <section className="overflow-x-auto rounded-panel border border-garis bg-permukaan">
                    <table className="w-full text-left text-isi">
                        <caption className="sr-only">Daftar satuan standar</caption>
                        <thead className="border-b border-garis text-label text-teks-sekunder">
                            <tr>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Kode
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Nama
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Simbol
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Jumlah desimal
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
                            {Satuan.map((satuan) => (
                                <tr key={satuan.Kode} className="border-b border-garis last:border-b-0">
                                    <td className="px-4 py-2 font-mono text-label">{satuan.Kode}</td>
                                    <td className="px-4 py-2 text-teks-utama">{satuan.Nama}</td>
                                    <td className="px-4 py-2 font-mono text-label text-teks-sekunder">
                                        {satuan.Simbol}
                                    </td>
                                    <td className="px-4 py-2 text-teks-sekunder">
                                        {satuan.BolehDesimal ? 'Boleh (misal 1,5)' : 'Bilangan bulat'}
                                    </td>
                                    <td className="px-4 py-2">
                                        <LabelStatus
                                            jenis={satuan.Aktif ? 'sukses' : 'netral'}
                                            teks={satuan.Aktif ? 'Aktif' : 'Nonaktif'}
                                        />
                                    </td>
                                    <td className="px-4 py-2 text-right">
                                        {bolehKelola ? (
                                            <Tombol varian="sekunder" onClick={() => AturSunting(satuan)}>
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

function FormSatuan({ satuan, saatSelesai }: { satuan: Satuan | null; saatSelesai: () => void }) {
    const formulir = useForm({
        Kode: satuan?.Kode ?? '',
        Nama: satuan?.Nama ?? '',
        Simbol: satuan?.Simbol ?? '',
        BolehDesimal: satuan?.BolehDesimal ?? false,
        Aktif: satuan?.Aktif ?? true,
    });

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        const opsi = { preserveScroll: true, onSuccess: saatSelesai };

        if (satuan === null) {
            formulir.post('/referensi/satuan', opsi);
        } else {
            formulir.put(`/referensi/satuan/${encodeURIComponent(satuan.Kode)}`, opsi);
        }
    };

    return (
        <form
            onSubmit={Kirim}
            className="grid gap-4 rounded-panel border border-garis bg-permukaan p-6 sm:grid-cols-3"
            noValidate
        >
            <h2 className="text-subjudul font-semibold text-teks-utama sm:col-span-3">
                {satuan === null ? 'Tambah satuan' : `Ubah ${satuan.Nama}`}
            </h2>
            <BidangTeks
                label="Kode"
                kode
                keterangan="Huruf besar, misal KG. Tidak bisa diubah."
                nilai={formulir.data.Kode}
                saatBerubah={(nilai) => formulir.setData('Kode', nilai.toUpperCase())}
                galat={formulir.errors.Kode}
                disabled={satuan !== null}
            />
            <BidangTeks
                label="Nama"
                nilai={formulir.data.Nama}
                saatBerubah={(nilai) => formulir.setData('Nama', nilai)}
                galat={formulir.errors.Nama}
            />
            <BidangTeks
                label="Simbol"
                nilai={formulir.data.Simbol}
                saatBerubah={(nilai) => formulir.setData('Simbol', nilai)}
                galat={formulir.errors.Simbol}
            />
            <KotakCentang
                label="Boleh jumlah desimal (misal 1,5 kg)"
                nilai={formulir.data.BolehDesimal}
                saatBerubah={(nilai) => formulir.setData('BolehDesimal', nilai)}
            />
            <KotakCentang
                label="Aktif"
                nilai={formulir.data.Aktif}
                saatBerubah={(nilai) => formulir.setData('Aktif', nilai)}
            />
            <div className="flex gap-2 sm:col-span-3">
                <Tombol type="submit" memproses={formulir.processing}>
                    Simpan satuan
                </Tombol>
                <Tombol varian="sekunder" onClick={saatSelesai}>
                    Batal
                </Tombol>
            </div>
        </form>
    );
}

import { useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import KotakCentang from '@/Komponen/Formulir/KotakCentang';
import Tombol from '@/Komponen/Formulir/Tombol';
import TabReferensi from '@/Komponen/Pengelola/TabReferensi';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import TataLetakPengelola from '@/TataLetak/TataLetakPengelola';
import { IzinPengelola, PunyaIzin, type Pilihan, type PropsBersamaPengelola } from '@/Tipe/Pengelola';

type Referensi = { Kode: string; Nama: string; Jenis: string; Aktif: boolean };

type PropsBank = { Referensi: Referensi[]; PilihanJenis: Pilihan[] };

/** Referensi pembayaran: bank, dompet digital, jaringan EDC, penerbit QRIS (P-02). */
export default function HalamanBank({ Referensi, PilihanJenis }: PropsBank) {
    const { props } = usePage<PropsBersamaPengelola>();
    const bolehKelola = PunyaIzin(props.Pengguna, IzinPengelola.ReferensiBankKelola);
    const [sunting, AturSunting] = useState<Referensi | 'baru' | null>(null);
    const labelJenis = new Map(PilihanJenis.map((item) => [item.Nilai, item.Label]));

    return (
        <TataLetakPengelola
            judul="Referensi"
            aksi={
                bolehKelola && sunting === null ? (
                    <Tombol onClick={() => AturSunting('baru')}>Tambah referensi</Tombol>
                ) : null
            }
        >
            <TabReferensi />
            {sunting !== null ? (
                <FormBank
                    key={sunting === 'baru' ? 'baru' : sunting.Kode}
                    referensi={sunting === 'baru' ? null : sunting}
                    pilihanJenis={PilihanJenis}
                    saatSelesai={() => AturSunting(null)}
                />
            ) : null}

            {Referensi.length === 0 ? (
                <Pemberitahuan jenis="info" judul="Belum ada referensi pembayaran">
                    Tambahkan bank, dompet digital, jaringan EDC, atau penerbit QRIS yang bisa dipilih tenant.
                </Pemberitahuan>
            ) : (
                <section className="overflow-x-auto rounded-panel border border-garis bg-permukaan">
                    <table className="w-full text-left text-isi">
                        <caption className="sr-only">Daftar referensi pembayaran</caption>
                        <thead className="border-b border-garis text-label text-teks-sekunder">
                            <tr>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Kode
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Nama
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Jenis
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
                            {Referensi.map((referensi) => (
                                <tr key={referensi.Kode} className="border-b border-garis last:border-b-0">
                                    <td className="px-4 py-2 font-mono text-label">{referensi.Kode}</td>
                                    <td className="px-4 py-2 text-teks-utama">{referensi.Nama}</td>
                                    <td className="px-4 py-2 text-teks-sekunder">
                                        {labelJenis.get(referensi.Jenis) ?? referensi.Jenis}
                                    </td>
                                    <td className="px-4 py-2">
                                        <LabelStatus
                                            jenis={referensi.Aktif ? 'sukses' : 'netral'}
                                            teks={referensi.Aktif ? 'Aktif' : 'Nonaktif'}
                                        />
                                    </td>
                                    <td className="px-4 py-2 text-right">
                                        {bolehKelola ? (
                                            <Tombol varian="sekunder" onClick={() => AturSunting(referensi)}>
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

function FormBank({
    referensi,
    pilihanJenis,
    saatSelesai,
}: {
    referensi: Referensi | null;
    pilihanJenis: Pilihan[];
    saatSelesai: () => void;
}) {
    const formulir = useForm({
        Kode: referensi?.Kode ?? '',
        Nama: referensi?.Nama ?? '',
        Jenis: referensi?.Jenis ?? 'Bank',
        Aktif: referensi?.Aktif ?? true,
    });

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        const opsi = { preserveScroll: true, onSuccess: saatSelesai };

        if (referensi === null) {
            formulir.post('/referensi/bank', opsi);
        } else {
            formulir.put(`/referensi/bank/${encodeURIComponent(referensi.Kode)}`, opsi);
        }
    };

    return (
        <form
            onSubmit={Kirim}
            className="grid gap-4 rounded-panel border border-garis bg-permukaan p-6 sm:grid-cols-2"
            noValidate
        >
            <h2 className="text-subjudul font-semibold text-teks-utama sm:col-span-2">
                {referensi === null ? 'Tambah referensi pembayaran' : `Ubah ${referensi.Nama}`}
            </h2>
            <BidangTeks
                label="Kode"
                kode
                keterangan="Huruf besar tanpa spasi, misal BCA atau GOPAY. Tidak bisa diubah."
                nilai={formulir.data.Kode}
                saatBerubah={(nilai) => formulir.setData('Kode', nilai.toUpperCase())}
                galat={formulir.errors.Kode}
                disabled={referensi !== null}
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
            <KotakCentang
                label="Aktif (bisa dipilih tenant)"
                nilai={formulir.data.Aktif}
                saatBerubah={(nilai) => formulir.setData('Aktif', nilai)}
            />
            <div className="flex gap-2 sm:col-span-2">
                <Tombol type="submit" memproses={formulir.processing}>
                    Simpan referensi
                </Tombol>
                <Tombol varian="sekunder" onClick={saatSelesai}>
                    Batal
                </Tombol>
            </div>
        </form>
    );
}

import { Link, router, useForm } from '@inertiajs/react';
import { useRef, useState, type FormEvent } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import KartuKodeAktivasi from '@/Komponen/Kelola/KartuKodeAktivasi';
import RingkasanGalatFormulir, { FokusGalatPertama } from '@/Komponen/PanduanAwal/RingkasanGalatFormulir';
import TataLetakPanduan from '@/Komponen/PanduanAwal/TataLetakPanduan';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { CekBatasPenuh, FormatBatas } from '@/Tipe/Organisasi';
import { AlamatPanduan, type PropsPerangkatPanduan } from '@/Tipe/PanduanAwal';

type StatusPerangkat = PropsPerangkatPanduan['Perangkat'][number]['Status'];

const labelStatus: Record<StatusPerangkat, { jenis: 'sukses' | 'peringatan' | 'netral'; teks: string }> = {
    Aktif: { jenis: 'sukses', teks: 'Aktif' },
    BelumDiaktifkan: { jenis: 'peringatan', teks: 'Belum diaktifkan' },
    Dicabut: { jenis: 'netral', teks: 'Dicabut' },
};

/** Langkah 6 F-01: tambah perangkat kasir di outlet panduan dan aktifkan dengan kode + QR (memakai ulang F-02b). */
export default function HalamanPerangkatPanduan({
    Progres,
    Outlet,
    Perangkat,
    KodeAktivasiBaru,
    BolehKelolaPerangkat,
}: PropsPerangkatPanduan) {
    const elemenFormulir = useRef<HTMLFormElement>(null);
    const [memproses, AturMemproses] = useState<string | null>(null);
    const batasPenuh = CekBatasPenuh(Outlet.BatasPerangkat);
    const formulir = useForm({ Nama: Perangkat.length === 0 ? 'Kasir 1' : '' });

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.post(AlamatPanduan.Perangkat, {
            preserveScroll: true,
            onSuccess: () => formulir.setData('Nama', ''),
            onError: () => FokusGalatPertama(elemenFormulir.current),
        });
    };

    const BuatKodeBaru = (uuid: string) =>
        router.post(
            `${AlamatPanduan.Perangkat}/${uuid}/kode-aktivasi`,
            {},
            { preserveScroll: true, onStart: () => AturMemproses(uuid), onFinish: () => AturMemproses(null) },
        );

    return (
        <TataLetakPanduan progres={Progres} langkah="Perangkat" lanjut="selesaikan">
            <p className="text-isi text-teks-sekunder">
                Daftarkan HP, tablet, atau komputer yang dipakai kasir di outlet{' '}
                <span className="font-semibold text-teks-utama">{Outlet.Nama}</span>, lalu aktifkan aplikasi kasir
                dengan kode atau QR. Pemakaian:{' '}
                <span className="font-semibold text-teks-utama">{FormatBatas(Outlet.BatasPerangkat, 'perangkat')}</span>
                .
            </p>

            {KodeAktivasiBaru ? <KartuKodeAktivasi kode={KodeAktivasiBaru} /> : null}

            {!BolehKelolaPerangkat ? (
                <Pemberitahuan jenis="info" judul="Anda belum bisa menambah perangkat">
                    Minta Pemilik atau Admin menambahkan perangkat kasir.
                </Pemberitahuan>
            ) : batasPenuh ? (
                <Pemberitahuan jenis="info" judul="Batas perangkat paket tercapai">
                    {FormatBatas(Outlet.BatasPerangkat, 'perangkat')} di outlet ini. Cabut perangkat yang tidak dipakai
                    di menu Perangkat, atau tambah add-on perangkat di{' '}
                    <Link href="/kelola/langganan" className="font-semibold text-brand underline">
                        menu Langganan
                    </Link>
                    .
                </Pemberitahuan>
            ) : (
                <form
                    ref={elemenFormulir}
                    onSubmit={Kirim}
                    className="flex flex-col gap-4 rounded-panel border border-garis bg-permukaan p-4 sm:p-6"
                    noValidate
                >
                    <RingkasanGalatFormulir galat={formulir.errors} />
                    <div className="max-w-md">
                        <BidangTeks
                            label="Nama perangkat kasir"
                            nilai={formulir.data.Nama}
                            saatBerubah={(nilai) => formulir.setData('Nama', nilai)}
                            galat={formulir.errors.Nama}
                            keterangan='Misal "Kasir Depan" atau "Tablet Bar".'
                            maxLength={100}
                            required
                        />
                    </div>
                    <div>
                        <Tombol type="submit" memproses={formulir.processing}>
                            Tambah perangkat kasir
                        </Tombol>
                    </div>
                </form>
            )}

            {Perangkat.length === 0 ? (
                <p className="rounded-panel border border-garis bg-permukaan px-4 py-6 text-isi text-teks-sekunder">
                    Belum ada perangkat kasir di outlet ini. Tambahkan satu untuk mulai berjualan di aplikasi kasir.
                </p>
            ) : (
                <section className="overflow-x-auto rounded-panel border border-garis bg-permukaan">
                    <table className="w-full min-w-[560px] text-left text-isi">
                        <caption className="sr-only">Perangkat di outlet {Outlet.Nama}</caption>
                        <thead className="border-b border-garis text-label text-teks-sekunder">
                            <tr>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Kode
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Nama
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
                            {Perangkat.map((baris) => (
                                <tr key={baris.Uuid} className="border-b border-garis last:border-b-0">
                                    <td className="px-4 py-2 font-mono text-label text-teks-utama">{baris.Kode}</td>
                                    <td className="px-4 py-2 text-teks-utama">
                                        <span className="break-words">{baris.Nama}</span>
                                        <span className="block text-keterangan text-teks-sekunder">
                                            {baris.LabelJenis}
                                        </span>
                                    </td>
                                    <td className="px-4 py-2">
                                        <LabelStatus
                                            jenis={labelStatus[baris.Status].jenis}
                                            teks={labelStatus[baris.Status].teks}
                                        />
                                    </td>
                                    <td className="px-4 py-2 text-right">
                                        {BolehKelolaPerangkat && baris.Status !== 'Dicabut' ? (
                                            <Tombol
                                                varian="sekunder"
                                                onClick={() => BuatKodeBaru(baris.Uuid)}
                                                memproses={memproses === baris.Uuid}
                                                disabled={memproses !== null}
                                            >
                                                {baris.Status === 'Aktif' ? 'Pindahkan ke HP lain' : 'Buat kode baru'}
                                            </Tombol>
                                        ) : null}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </section>
            )}
        </TataLetakPanduan>
    );
}

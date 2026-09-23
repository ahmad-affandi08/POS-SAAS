import { Link, useForm } from '@inertiajs/react';
import { useId, useRef, type FormEvent } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import KotakCentang from '@/Komponen/Formulir/KotakCentang';
import Tombol from '@/Komponen/Formulir/Tombol';
import RingkasanGalatFormulir, { FokusGalatPertama } from '@/Komponen/PanduanAwal/RingkasanGalatFormulir';
import TataLetakPanduan from '@/Komponen/PanduanAwal/TataLetakPanduan';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { FormatPersen } from '@/Pustaka/Format';
import { FormatTanggal } from '@/Pustaka/FormatWaktu';
import { FormatMasukanPersen, NormalisasiMasukanPersen } from '@/Pustaka/MasukanUang';
import { AlamatPanduan, type PropsPajak, type TarifTampil } from '@/Tipe/PanduanAwal';

type IsianPajak = PropsPajak['Nilai'];

/** "10.000000" berlaku "2024-01-01" → "10% berlaku mulai 1 Jan 2024 (Perda No. 1 Tahun 2024)". */
function JelaskanTarif(tarif: TarifTampil): string {
    const tanggal = /^\d{4}-\d{2}-\d{2}/.exec(tarif.BerlakuMulai)?.[0];
    const berlaku = tanggal ? ` berlaku mulai ${FormatTanggal(tanggal)}` : '';
    const dasarHukum = tarif.NomorDasarHukum ? ` (${tarif.NomorDasarHukum})` : '';

    return `${FormatPersen(tarif.Tarif)}%${berlaku}${dasarHukum}`;
}

/** Langkah 3 F-01: konfirmasi usulan pajak dari template, status PKP, dan tarif kota (tarif dari server, CLAUDE.md #12). */
export default function HalamanPajak({
    Progres,
    Pkp,
    Kota,
    Nilai,
    SudahDikonfirmasi,
    TarifPbjt,
    TarifPpn,
    KelompokPajak,
    AlasanUsulan,
}: PropsPajak) {
    const elemenFormulir = useRef<HTMLFormElement>(null);
    const idGalatPbjt = useId();
    const formulir = useForm<IsianPajak>({ ...Nilai });
    const tautanProfil =
        Progres.Langkah.find((item) => item.Kunci === 'ProfilUsaha')?.Tautan ?? AlamatPanduan.ProfilUsaha;

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.post(AlamatPanduan.Pajak, {
            preserveScroll: true,
            onError: () => FokusGalatPertama(elemenFormulir.current),
        });
    };

    return (
        <TataLetakPanduan progres={Progres} langkah="Pajak">
            {SudahDikonfirmasi ? (
                <Pemberitahuan jenis="info" judul="Pengaturan pajak sudah disimpan">
                    Ubah bila perlu, lalu simpan lagi.
                </Pemberitahuan>
            ) : AlasanUsulan.length > 0 ? (
                <Pemberitahuan jenis="info" judul="Usulan dari template dan data usaha Anda">
                    <ul className="list-disc pl-5">
                        {AlasanUsulan.map((alasan) => (
                            <li key={alasan}>{alasan}</li>
                        ))}
                    </ul>
                    <p className="mt-1">Periksa usulan ini, ubah bila perlu, lalu simpan.</p>
                </Pemberitahuan>
            ) : null}

            <form
                ref={elemenFormulir}
                onSubmit={Kirim}
                className="flex flex-col gap-6 rounded-panel border border-garis bg-permukaan p-4 sm:p-6"
                noValidate
            >
                <RingkasanGalatFormulir galat={formulir.errors} />

                <section aria-labelledby="judul-ppn" className="flex flex-col gap-1">
                    <h2 id="judul-ppn" className="text-subjudul font-semibold text-teks-utama">
                        PPN
                    </h2>
                    <p className="text-isi text-teks-sekunder">
                        {Pkp
                            ? 'Usaha Anda PKP, jadi penjualan barang kena pajak dipungut PPN.'
                            : 'Usaha Anda bukan PKP, jadi tidak memungut PPN.'}{' '}
                        <Link href={tautanProfil} className="font-semibold text-brand underline">
                            Ubah status PKP di Profil usaha
                        </Link>
                        .
                    </p>
                    {Pkp && TarifPpn ? (
                        <p className="text-isi text-teks-utama">
                            Tarif PPN {JelaskanTarif(TarifPpn)}
                            {TarifPpn.PengaliDppPembilang !== TarifPpn.PengaliDppPenyebut
                                ? `, dihitung dari DPP ${String(TarifPpn.PengaliDppPembilang)}/${String(TarifPpn.PengaliDppPenyebut)} harga jual`
                                : ''}
                            .
                        </p>
                    ) : null}
                    {Pkp && !TarifPpn ? (
                        <p className="text-isi text-teks-sekunder">
                            Tarif PPN belum tersedia di sistem. Anda tetap bisa menyimpan; kami akan melengkapinya.
                        </p>
                    ) : null}
                </section>

                <section aria-labelledby="judul-pbjt" className="flex flex-col gap-2">
                    <h2 id="judul-pbjt" className="text-subjudul font-semibold text-teks-utama">
                        PBJT makanan & minuman (pajak daerah)
                    </h2>
                    <KotakCentang
                        label="Pungut PBJT di outlet ini"
                        nilai={formulir.data.PungutPbjt}
                        saatBerubah={(nilai) => formulir.setData('PungutPbjt', nilai)}
                    />
                    {formulir.errors.PungutPbjt ? (
                        <p id={idGalatPbjt} className="text-keterangan font-semibold text-bahaya">
                            {formulir.errors.PungutPbjt}
                        </p>
                    ) : null}
                    {Kota === null ? (
                        <Pemberitahuan jenis="peringatan" judul="Kota outlet belum diisi">
                            Isi kota di langkah{' '}
                            <Link href={tautanProfil} className="font-semibold text-brand underline">
                                Profil usaha
                            </Link>
                            . Tarif PBJT mengikuti kota.
                        </Pemberitahuan>
                    ) : TarifPbjt ? (
                        <p className="text-isi text-teks-utama">
                            Tarif PBJT {Kota.Nama}: {JelaskanTarif(TarifPbjt)}. Service charge{' '}
                            {TarifPbjt.BiayaLayananMasukDpp ? 'ikut' : 'tidak ikut'} dikenai PBJT di kota ini.
                        </p>
                    ) : formulir.data.PungutPbjt ? (
                        <Pemberitahuan jenis="peringatan" judul="Tarif PBJT belum tersedia">
                            Tarif PBJT {Kota.Nama} belum tersedia di sistem. Anda tetap bisa menyimpan; kami akan
                            melengkapinya.
                        </Pemberitahuan>
                    ) : null}
                </section>

                <section aria-labelledby="judul-layanan" className="flex flex-col gap-2">
                    <h2 id="judul-layanan" className="text-subjudul font-semibold text-teks-utama">
                        Service charge (biaya layanan)
                    </h2>
                    <KotakCentang
                        label="Kenakan service charge (biaya layanan)"
                        nilai={formulir.data.BiayaLayananAktif}
                        saatBerubah={(nilai) => formulir.setData('BiayaLayananAktif', nilai)}
                    />
                    {formulir.data.BiayaLayananAktif ? (
                        <div className="max-w-xs">
                            <BidangTeks
                                label="Persentase service charge"
                                nilai={FormatMasukanPersen(formulir.data.PersenBiayaLayanan)}
                                saatBerubah={(nilai) =>
                                    formulir.setData('PersenBiayaLayanan', NormalisasiMasukanPersen(nilai))
                                }
                                galat={formulir.errors.PersenBiayaLayanan}
                                keterangan="0 sampai 10 persen, misal 5 atau 7,5."
                                inputMode="decimal"
                                maxLength={5}
                            />
                        </div>
                    ) : null}
                </section>

                <fieldset className="flex flex-col gap-2">
                    <legend className="mb-1 text-subjudul font-semibold text-teks-utama">Harga jual di menu</legend>
                    <label className="flex min-h-10 items-start gap-2 text-isi text-teks-utama">
                        <input
                            type="radio"
                            name="HargaTermasukPajak"
                            className="mt-0.5 size-4 accent-brand"
                            checked={formulir.data.HargaTermasukPajak}
                            onChange={() => formulir.setData('HargaTermasukPajak', true)}
                        />
                        <span>
                            Sudah termasuk pajak
                            <span className="block text-keterangan text-teks-sekunder">
                                Pelanggan membayar sesuai harga di menu; pajak dihitung dari dalam harga.
                            </span>
                        </span>
                    </label>
                    <label className="flex min-h-10 items-start gap-2 text-isi text-teks-utama">
                        <input
                            type="radio"
                            name="HargaTermasukPajak"
                            className="mt-0.5 size-4 accent-brand"
                            checked={!formulir.data.HargaTermasukPajak}
                            onChange={() => formulir.setData('HargaTermasukPajak', false)}
                        />
                        <span>
                            Belum termasuk pajak
                            <span className="block text-keterangan text-teks-sekunder">
                                Pajak ditambahkan di atas harga menu saat pembayaran.
                            </span>
                        </span>
                    </label>
                    {formulir.errors.HargaTermasukPajak ? (
                        <p className="text-keterangan font-semibold text-bahaya">
                            {formulir.errors.HargaTermasukPajak}
                        </p>
                    ) : null}
                </fieldset>

                <section aria-labelledby="judul-kelompok" className="flex flex-col gap-2">
                    <h2 id="judul-kelompok" className="text-subjudul font-semibold text-teks-utama">
                        Kelompok pajak dari template
                    </h2>
                    {KelompokPajak.length === 0 ? (
                        <p className="text-isi text-teks-sekunder">
                            Belum ada kelompok pajak. Terapkan template di langkah Jenis usaha & template untuk
                            menyiapkannya.
                        </p>
                    ) : (
                        <div className="overflow-x-auto rounded-panel border border-garis">
                            <table className="w-full min-w-[420px] text-left text-isi">
                                <caption className="sr-only">Kelompok pajak</caption>
                                <thead className="border-b border-garis text-label text-teks-sekunder">
                                    <tr>
                                        <th scope="col" className="px-4 py-2 font-semibold">
                                            Kelompok
                                        </th>
                                        <th scope="col" className="px-4 py-2 font-semibold">
                                            Pajak yang dikenakan
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {KelompokPajak.map((kelompok) => (
                                        <tr key={kelompok.Nama} className="border-b border-garis last:border-b-0">
                                            <td className="px-4 py-2 align-top break-words text-teks-utama">
                                                {kelompok.Nama}
                                            </td>
                                            <td className="px-4 py-2 text-teks-sekunder">
                                                {kelompok.Pajak.length === 0 ? (
                                                    'Tanpa pajak'
                                                ) : (
                                                    <ul>
                                                        {kelompok.Pajak.map((pajak) => (
                                                            <li key={pajak.KodeJenisPajak}>
                                                                {pajak.NamaJenisPajak} · {pajak.LabelDasarPengenaan}
                                                            </li>
                                                        ))}
                                                    </ul>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>

                <div>
                    <Tombol type="submit" memproses={formulir.processing}>
                        Simpan pengaturan pajak
                    </Tombol>
                </div>
            </form>
        </TataLetakPanduan>
    );
}

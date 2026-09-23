import { useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import DaftarGalatServer from '@/Komponen/Katalog/DaftarGalatServer';
import KeadaanKosong from '@/Komponen/Katalog/KeadaanKosong';
import PesanHanyaLihat from '@/Komponen/Katalog/PesanHanyaLihat';
import RingkasanGalatFormulir from '@/Komponen/PanduanAwal/RingkasanGalatFormulir';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import type { KategoriPajakProduk, PropsDaftarKelompokPajak } from '@/Tipe/Katalog';

type KelompokPajak = PropsDaftarKelompokPajak['KelompokPajak'][number];
type FormKelompokPajak = {
    Nama: string;
    Kategori: KategoriPajakProduk;
    Pajak: { KodeJenisPajak: string; DasarPengenaan: string }[];
};

const kodePpn = 'Ppn';
const kodePbjt = 'PbjtMakananMinuman';

/** Kategori tanpa rincian pajak (DesainF03 C.3 SimpanKelompokPajak). */
export function CekKategoriTanpaPajak(kategori: KategoriPajakProduk): boolean {
    return kategori === 'BebasPpn' || kategori === 'NonPajak';
}

/** Cermin aturan konsistensi KelompokPajakTidakSesuai di peramban; null = sesuai. Server tetap penentu. */
export function PeriksaKonsistensiPajak(kategori: KategoriPajakProduk, kode: string[]): string | null {
    const adaPpn = kode.includes(kodePpn);
    const adaPbjt = kode.includes(kodePbjt);

    if (new Set(kode).size !== kode.length) {
        return 'Jenis pajak yang sama tidak boleh dipilih dua kali.';
    }

    switch (kategori) {
        case 'KenaPpn':
            return adaPpn && !adaPbjt ? null : 'Kategori Kena PPN wajib memuat PPN dan tidak boleh memuat PBJT.';
        case 'KenaPbjt':
            return adaPbjt && !adaPpn
                ? null
                : 'Kategori Kena PBJT wajib memuat PBJT makanan & minuman dan tidak boleh memuat PPN.';
        case 'BebasPpn':
        case 'NonPajak':
            return kode.length === 0 ? null : 'Kategori ini tidak memakai rincian pajak.';
        case 'Lainnya':
            return adaPpn || adaPbjt ? 'Kategori Lainnya untuk pajak selain PPN dan PBJT.' : null;
    }
}

function FormKelompok({
    kelompok,
    props,
    saatSelesai,
}: {
    kelompok: KelompokPajak | null;
    props: PropsDaftarKelompokPajak;
    saatSelesai: () => void;
}) {
    const formulir = useForm<FormKelompokPajak>({
        Nama: kelompok?.Nama ?? '',
        Kategori: kelompok?.Kategori ?? 'KenaPbjt',
        Pajak:
            kelompok?.Pajak.map((item) => ({
                KodeJenisPajak: item.KodeJenisPajak,
                DasarPengenaan: item.DasarPengenaan,
            })) ?? [],
    });
    const data = formulir.data;
    const galat = formulir.errors as Record<string, string | undefined>;
    const [periksa, AturPeriksa] = useState(false);
    const tanpaPajak = CekKategoriTanpaPajak(data.Kategori);
    const galatKonsistensi = periksa
        ? PeriksaKonsistensiPajak(
              data.Kategori,
              data.Pajak.map((item) => item.KodeJenisPajak),
          )
        : null;
    const AturPajak = (pajak: FormKelompokPajak['Pajak']) => formulir.setData((lama) => ({ ...lama, Pajak: pajak }));

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        AturPeriksa(true);

        if (
            PeriksaKonsistensiPajak(
                data.Kategori,
                data.Pajak.map((item) => item.KodeJenisPajak),
            ) !== null
        ) {
            return;
        }

        const opsi = { preserveScroll: true, onSuccess: saatSelesai };

        if (kelompok === null) {
            formulir.post('/kelola/kelompok-pajak', opsi);
        } else {
            formulir.put(`/kelola/kelompok-pajak/${kelompok.Uuid}`, opsi);
        }
    };

    return (
        <form
            onSubmit={Kirim}
            noValidate
            aria-label={kelompok ? `Ubah kelompok pajak ${kelompok.Nama}` : 'Tambah kelompok pajak'}
            className="flex flex-col gap-4 rounded-panel border border-garis bg-permukaan p-4"
        >
            <RingkasanGalatFormulir galat={{ ...galat, ...(galatKonsistensi ? { Pajak: galatKonsistensi } : {}) }} />
            <div className="grid gap-3 sm:grid-cols-2">
                <BidangTeks
                    label="Nama kelompok pajak"
                    nilai={data.Nama}
                    saatBerubah={(nilai) => formulir.setData('Nama', nilai)}
                    galat={galat.Nama}
                    keterangan="Misal Makan & minum, Barang retail, atau Jasa."
                    maxLength={100}
                    autoFocus
                    required
                />
                <BidangPilihan
                    label="Kategori pajak"
                    nilai={data.Kategori}
                    opsi={props.Kategori}
                    saatBerubah={(nilai) =>
                        formulir.setData((lama) => ({
                            ...lama,
                            Kategori: nilai as KategoriPajakProduk,
                            Pajak: CekKategoriTanpaPajak(nilai as KategoriPajakProduk) ? [] : lama.Pajak,
                        }))
                    }
                    galat={galat.Kategori}
                />
            </div>
            {tanpaPajak ? (
                <p className="text-isi text-teks-sekunder">Produk di kelompok ini dijual tanpa pajak.</p>
            ) : (
                <fieldset className="flex flex-col gap-2">
                    <legend className="text-label font-semibold text-teks-utama">
                        Pajak yang dikenakan (berurutan)
                    </legend>
                    <p className="text-keterangan text-teks-sekunder">
                        Tarif tidak disimpan di sini; tarif diambil dari tabel tarif yang berlaku pada tanggal
                        transaksi.
                    </p>
                    {data.Pajak.map((pajak, indeks) => (
                        <div
                            key={indeks}
                            className="grid items-end gap-2 rounded-kontrol border border-garis p-3 sm:grid-cols-[1fr_1fr_auto]"
                        >
                            <BidangPilihan
                                label={`Jenis pajak ${String(indeks + 1)}`}
                                nilai={pajak.KodeJenisPajak}
                                kosong="Pilih jenis pajak"
                                opsi={props.JenisPajak.map((item) => ({
                                    Nilai: item.Kode,
                                    Label: `${item.Nama} (${item.Cakupan})`,
                                }))}
                                saatBerubah={(nilai) =>
                                    AturPajak(
                                        data.Pajak.map((item, i) =>
                                            i === indeks ? { ...item, KodeJenisPajak: nilai } : item,
                                        ),
                                    )
                                }
                                galat={galat[`Pajak.${String(indeks)}.KodeJenisPajak`]}
                            />
                            <BidangPilihan
                                label={`Dasar pengenaan ${String(indeks + 1)}`}
                                nilai={pajak.DasarPengenaan}
                                kosong="Pilih dasar pengenaan"
                                opsi={props.DasarPengenaan}
                                saatBerubah={(nilai) =>
                                    AturPajak(
                                        data.Pajak.map((item, i) =>
                                            i === indeks ? { ...item, DasarPengenaan: nilai } : item,
                                        ),
                                    )
                                }
                                galat={galat[`Pajak.${String(indeks)}.DasarPengenaan`]}
                            />
                            <button
                                type="button"
                                onClick={() => AturPajak(data.Pajak.filter((_, i) => i !== indeks))}
                                className="h-10 text-label font-semibold text-bahaya underline outline-none focus-visible:ring-2 focus-visible:ring-brand"
                                aria-label={`Hapus pajak ${String(indeks + 1)}`}
                            >
                                Hapus
                            </button>
                        </div>
                    ))}
                    <p>
                        <button
                            type="button"
                            onClick={() =>
                                AturPajak([
                                    ...data.Pajak,
                                    { KodeJenisPajak: '', DasarPengenaan: props.DasarPengenaan[0]?.Nilai ?? '' },
                                ])
                            }
                            className="h-10 rounded-kontrol border border-garis-input bg-permukaan px-3 text-label font-semibold text-teks-utama outline-none focus-visible:ring-2 focus-visible:ring-brand"
                        >
                            Tambah pajak
                        </button>
                    </p>
                </fieldset>
            )}
            <div aria-live="polite">
                {(galatKonsistensi ?? galat.Pajak) ? (
                    <p className="text-keterangan font-semibold text-bahaya">{galatKonsistensi ?? galat.Pajak}</p>
                ) : null}
            </div>
            <div className="flex flex-wrap gap-2">
                <Tombol type="submit" memproses={formulir.processing}>
                    Simpan kelompok pajak
                </Tombol>
                <Tombol varian="sekunder" onClick={saatSelesai}>
                    Batal
                </Tombol>
            </div>
        </form>
    );
}

/** F-03 kelompok pajak produk (kategori PPN/PBJT/bebas/non-pajak/lainnya). Ubah butuh izin akuntansi.kelola. */
export default function HalamanDaftarKelompokPajak(propsHalaman: PropsDaftarKelompokPajak) {
    const { KelompokPajak, Izin } = propsHalaman;
    const { props } = usePage<PropsBersamaAplikasi>();
    const [sunting, AturSunting] = useState<KelompokPajak | 'baru' | null>(null);

    return (
        <TataLetakAplikasi judul="Kelompok pajak">
            {!Izin.KelolaPajak ? <PesanHanyaLihat izin="akuntansi.kelola" objek="kelompok pajak" /> : null}
            <DaftarGalatServer galat={props.errors} kecuali={sunting !== null ? Object.keys(props.errors) : []} />
            <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="max-w-2xl text-isi text-teks-sekunder">
                    Setiap produk yang dijual memakai satu kelompok pajak. PBJT makanan & minuman dan PPN tidak boleh
                    dikenakan bersamaan pada satu produk.
                </p>
                {Izin.KelolaPajak && sunting === null ? (
                    <Tombol onClick={() => AturSunting('baru')}>Tambah kelompok pajak</Tombol>
                ) : null}
            </div>
            {sunting !== null ? (
                <FormKelompok
                    key={sunting === 'baru' ? 'baru' : sunting.Uuid}
                    kelompok={sunting === 'baru' ? null : sunting}
                    props={propsHalaman}
                    saatSelesai={() => AturSunting(null)}
                />
            ) : null}
            {KelompokPajak.length === 0 ? (
                <KeadaanKosong judul="Belum ada kelompok pajak. Tambah kelompok pajak sebelum menambah produk yang dijual." />
            ) : (
                <section className="overflow-x-auto rounded-panel border border-garis bg-permukaan">
                    <table className="w-full min-w-[640px] text-left text-isi">
                        <caption className="sr-only">Daftar kelompok pajak</caption>
                        <thead className="border-b border-garis text-label text-teks-sekunder">
                            <tr>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Kelompok
                                </th>
                                <th scope="col" className="px-4 py-2 font-semibold">
                                    Pajak
                                </th>
                                <th scope="col" className="px-4 py-2 text-right font-semibold">
                                    Produk
                                </th>
                                {Izin.KelolaPajak ? (
                                    <th scope="col" className="px-4 py-2 font-semibold">
                                        <span className="sr-only">Aksi</span>
                                    </th>
                                ) : null}
                            </tr>
                        </thead>
                        <tbody>
                            {KelompokPajak.map((item) => (
                                <tr key={item.Uuid} className="border-b border-garis align-top last:border-b-0">
                                    <td className="px-4 py-2">
                                        <span className="block font-semibold break-words text-teks-utama">
                                            {item.Nama}
                                        </span>
                                        <span className="text-keterangan text-teks-sekunder">{item.LabelKategori}</span>
                                    </td>
                                    <td className="px-4 py-2 text-teks-sekunder">
                                        {item.Pajak.length === 0 ? (
                                            'Tanpa pajak'
                                        ) : (
                                            <ol className="flex flex-col gap-0.5">
                                                {item.Pajak.map((pajak) => (
                                                    <li key={pajak.KodeJenisPajak}>
                                                        {pajak.NamaJenisPajak} · {pajak.LabelDasarPengenaan}
                                                    </li>
                                                ))}
                                            </ol>
                                        )}
                                    </td>
                                    <td className="px-4 py-2 text-right tabular-nums">{item.JumlahProduk}</td>
                                    {Izin.KelolaPajak ? (
                                        <td className="px-4 py-2">
                                            <button
                                                type="button"
                                                onClick={() => AturSunting(item)}
                                                className="text-label font-semibold text-brand underline outline-none focus-visible:ring-2 focus-visible:ring-brand"
                                                aria-label={`Ubah kelompok pajak ${item.Nama}`}
                                            >
                                                Ubah
                                            </button>
                                        </td>
                                    ) : null}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </section>
            )}
        </TataLetakAplikasi>
    );
}

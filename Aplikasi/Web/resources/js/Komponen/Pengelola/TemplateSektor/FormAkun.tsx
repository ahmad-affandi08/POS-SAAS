import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import RingkasanGalat from '@/Komponen/Pengelola/TemplateSektor/RingkasanGalat';
import type { AkunTemplate, IsiAkunTemplate, PilihanEditorTemplate } from '@/Tipe/TemplateSektor';

type PropsFormAkun = {
    url: string;
    isi: IsiAkunTemplate;
    pilihan: PilihanEditorTemplate;
    bolehUbah: boolean;
};

const kelasSel = 'px-2 py-2';
const kelasInput =
    'h-10 w-full rounded-kontrol border border-garis-input bg-permukaan px-2 text-isi text-teks-utama outline-none focus-visible:ring-2 focus-visible:ring-brand disabled:bg-latar disabled:text-teks-sekunder';

/** Saldo normal mengikuti tipe akun (§11.2); akun kontra memakai kebalikannya. */
function HitungSaldoNormal(pilihan: PilihanEditorTemplate, tipe: string, kontra: boolean): string {
    const normal = pilihan.TipeAkun.find((item) => item.Nilai === tipe)?.SaldoNormal ?? 'Debit';

    return kontra ? (normal === 'Debit' ? 'Kredit' : 'Debit') : normal;
}

/** COA, pemetaan akun, dan kelompok pajak template (BR-P03.5, Keuangan). */
export default function FormAkun({ url, isi, pilihan, bolehUbah }: PropsFormAkun) {
    const formulir = useForm<IsiAkunTemplate>({ ...isi, PemetaanAkun: { ...isi.PemetaanAkun } });
    const galat = formulir.errors as Record<string, string | undefined>;
    const data = formulir.data;

    const UbahAkun = (indeks: number, perubahan: Partial<AkunTemplate>) => {
        const akun = data.Akun.map((baris, posisi) => {
            if (posisi !== indeks) {
                return baris;
            }

            const baru = { ...baris, ...perubahan };

            return { ...baru, SaldoNormal: HitungSaldoNormal(pilihan, baru.Tipe, baru.Kontra) };
        });
        formulir.setData('Akun', akun);
    };
    const TambahAkun = () =>
        formulir.setData('Akun', [
            ...data.Akun,
            { Kode: '', Nama: '', Tipe: 'Beban', SaldoNormal: 'Debit', Kontra: false },
        ]);
    const HapusAkun = (indeks: number) =>
        formulir.setData(
            'Akun',
            data.Akun.filter((_, posisi) => posisi !== indeks),
        );

    const UbahKelompok = (indeks: number, kelompok: IsiAkunTemplate['KelompokPajak'][number]) =>
        formulir.setData(
            'KelompokPajak',
            data.KelompokPajak.map((baris, posisi) => (posisi === indeks ? kelompok : baris)),
        );

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        formulir.put(`${url}/akun`, { preserveScroll: true });
    };

    return (
        <form
            onSubmit={Kirim}
            className="flex flex-col gap-6 rounded-panel border border-garis bg-permukaan p-6"
            noValidate
        >
            <div>
                <h2 className="text-subjudul font-semibold text-teks-utama">Akun & pajak</h2>
                <p className="text-keterangan text-teks-sekunder">Diubah oleh Keuangan.</p>
            </div>
            <RingkasanGalat galat={galat} />
            <fieldset disabled={!bolehUbah} className="flex flex-col gap-6">
                <section className="flex flex-col gap-2">
                    <h3 className="text-label font-semibold text-teks-utama">Bagan akun (COA)</h3>
                    {data.Akun.length === 0 ? (
                        <p className="text-keterangan text-teks-sekunder">
                            Belum ada akun. Tambahkan akun atau salin template yang sudah ada saat membuat template.
                        </p>
                    ) : null}
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[40rem] text-left text-isi">
                            <caption className="sr-only">Bagan akun template</caption>
                            <thead className="border-b border-garis text-label text-teks-sekunder">
                                <tr>
                                    <th scope="col" className={`${kelasSel} w-28 font-semibold`}>
                                        Kode
                                    </th>
                                    <th scope="col" className={`${kelasSel} font-semibold`}>
                                        Nama akun
                                    </th>
                                    <th scope="col" className={`${kelasSel} w-40 font-semibold`}>
                                        Tipe
                                    </th>
                                    <th scope="col" className={`${kelasSel} w-24 font-semibold`}>
                                        Kontra
                                    </th>
                                    <th scope="col" className={`${kelasSel} w-24 font-semibold`}>
                                        Saldo normal
                                    </th>
                                    <th scope="col" className={kelasSel}>
                                        <span className="sr-only">Aksi</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {data.Akun.map((akun, indeks) => (
                                    <tr key={indeks} className="border-b border-garis last:border-b-0">
                                        <td className={kelasSel}>
                                            <input
                                                aria-label={`Kode akun baris ${indeks + 1}`}
                                                className={`${kelasInput} font-mono`}
                                                value={akun.Kode}
                                                onChange={(peristiwa) =>
                                                    UbahAkun(indeks, { Kode: peristiwa.target.value })
                                                }
                                            />
                                        </td>
                                        <td className={kelasSel}>
                                            <input
                                                aria-label={`Nama akun baris ${indeks + 1}`}
                                                className={kelasInput}
                                                value={akun.Nama}
                                                onChange={(peristiwa) =>
                                                    UbahAkun(indeks, { Nama: peristiwa.target.value })
                                                }
                                            />
                                        </td>
                                        <td className={kelasSel}>
                                            <select
                                                aria-label={`Tipe akun baris ${indeks + 1}`}
                                                className={kelasInput}
                                                value={akun.Tipe}
                                                onChange={(peristiwa) =>
                                                    UbahAkun(indeks, { Tipe: peristiwa.target.value })
                                                }
                                            >
                                                {pilihan.TipeAkun.map((tipe) => (
                                                    <option key={tipe.Nilai} value={tipe.Nilai}>
                                                        {tipe.DigitAwal}- {tipe.Label}
                                                    </option>
                                                ))}
                                            </select>
                                        </td>
                                        <td className={kelasSel}>
                                            <input
                                                type="checkbox"
                                                aria-label={`Akun kontra baris ${indeks + 1}`}
                                                className="size-4 accent-brand"
                                                checked={akun.Kontra}
                                                onChange={(peristiwa) =>
                                                    UbahAkun(indeks, { Kontra: peristiwa.target.checked })
                                                }
                                            />
                                        </td>
                                        <td className={`${kelasSel} text-teks-sekunder`}>{akun.SaldoNormal}</td>
                                        <td className={`${kelasSel} text-right`}>
                                            {bolehUbah ? (
                                                <Tombol varian="bahaya" onClick={() => HapusAkun(indeks)}>
                                                    Hapus
                                                </Tombol>
                                            ) : null}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {bolehUbah ? (
                        <div>
                            <Tombol varian="sekunder" onClick={TambahAkun}>
                                Tambah akun
                            </Tombol>
                        </div>
                    ) : null}
                </section>

                <section className="flex flex-col gap-2">
                    <h3 className="text-label font-semibold text-teks-utama">Pemetaan akun per jenis transaksi</h3>
                    <div className="grid gap-4 sm:grid-cols-2">
                        {pilihan.PeranAkun.map((peran) => (
                            <BidangPilihan
                                key={peran.Nilai}
                                label={peran.Label}
                                nilai={data.PemetaanAkun[peran.Nilai] ?? ''}
                                kosong="Belum dipetakan"
                                opsi={data.Akun.filter(
                                    (akun) =>
                                        akun.Tipe === peran.Tipe &&
                                        akun.Kontra === peran.WajibKontra &&
                                        akun.Kode !== '',
                                ).map((akun) => ({ Nilai: akun.Kode, Label: `${akun.Kode} ${akun.Nama}` }))}
                                saatBerubah={(nilai) =>
                                    formulir.setData('PemetaanAkun', { ...data.PemetaanAkun, [peran.Nilai]: nilai })
                                }
                                galat={galat[`PemetaanAkun.${peran.Nilai}`]}
                            />
                        ))}
                    </div>
                </section>

                <section className="flex flex-col gap-3">
                    <h3 className="text-label font-semibold text-teks-utama">Kelompok pajak default</h3>
                    {data.KelompokPajak.length === 0 ? (
                        <p className="text-keterangan text-teks-sekunder">
                            Belum ada kelompok pajak. Usaha non-PKP tanpa pajak daerah boleh kosong.
                        </p>
                    ) : null}
                    {data.KelompokPajak.map((kelompok, indeks) => (
                        <div key={indeks} className="flex flex-col gap-3 rounded-kontrol border border-garis p-4">
                            <BidangTeks
                                label="Nama kelompok"
                                nilai={kelompok.Nama}
                                saatBerubah={(nilai) => UbahKelompok(indeks, { ...kelompok, Nama: nilai })}
                                galat={galat[`KelompokPajak.${indeks}.Nama`]}
                            />
                            {kelompok.Detail.map((detail, posisi) => (
                                <div key={posisi} className="grid gap-3 sm:grid-cols-3">
                                    <BidangPilihan
                                        label="Jenis pajak"
                                        nilai={detail.KodeJenisPajak}
                                        opsi={pilihan.JenisPajak}
                                        saatBerubah={(nilai) =>
                                            UbahKelompok(indeks, {
                                                ...kelompok,
                                                Detail: kelompok.Detail.map((baris, ke) =>
                                                    ke === posisi ? { ...baris, KodeJenisPajak: nilai } : baris,
                                                ),
                                            })
                                        }
                                    />
                                    <BidangPilihan
                                        label="Dasar pengenaan"
                                        nilai={detail.DasarPengenaan}
                                        opsi={pilihan.DasarPengenaan}
                                        saatBerubah={(nilai) =>
                                            UbahKelompok(indeks, {
                                                ...kelompok,
                                                Detail: kelompok.Detail.map((baris, ke) =>
                                                    ke === posisi ? { ...baris, DasarPengenaan: nilai } : baris,
                                                ),
                                            })
                                        }
                                    />
                                    {bolehUbah ? (
                                        <div className="flex items-end">
                                            <Tombol
                                                varian="bahaya"
                                                onClick={() =>
                                                    UbahKelompok(indeks, {
                                                        ...kelompok,
                                                        Detail: kelompok.Detail.filter((_, ke) => ke !== posisi),
                                                    })
                                                }
                                            >
                                                Hapus pajak
                                            </Tombol>
                                        </div>
                                    ) : null}
                                </div>
                            ))}
                            {bolehUbah ? (
                                <div className="flex flex-wrap gap-2">
                                    <Tombol
                                        varian="sekunder"
                                        onClick={() =>
                                            UbahKelompok(indeks, {
                                                ...kelompok,
                                                Detail: [
                                                    ...kelompok.Detail,
                                                    {
                                                        KodeJenisPajak: pilihan.JenisPajak[0]?.Nilai ?? '',
                                                        DasarPengenaan: 'Subtotal',
                                                        Urutan: kelompok.Detail.length + 1,
                                                    },
                                                ],
                                            })
                                        }
                                    >
                                        Tambah pajak
                                    </Tombol>
                                    <Tombol
                                        varian="bahaya"
                                        onClick={() =>
                                            formulir.setData(
                                                'KelompokPajak',
                                                data.KelompokPajak.filter((_, ke) => ke !== indeks),
                                            )
                                        }
                                    >
                                        Hapus kelompok
                                    </Tombol>
                                </div>
                            ) : null}
                        </div>
                    ))}
                    {bolehUbah ? (
                        <div>
                            <Tombol
                                varian="sekunder"
                                onClick={() =>
                                    formulir.setData('KelompokPajak', [...data.KelompokPajak, { Nama: '', Detail: [] }])
                                }
                            >
                                Tambah kelompok pajak
                            </Tombol>
                        </div>
                    ) : null}
                </section>
            </fieldset>
            {bolehUbah ? (
                <div>
                    <Tombol type="submit" memproses={formulir.processing}>
                        Simpan akun & pajak
                    </Tombol>
                </div>
            ) : null}
        </form>
    );
}

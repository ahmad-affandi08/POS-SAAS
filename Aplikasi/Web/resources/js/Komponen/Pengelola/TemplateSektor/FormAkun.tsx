import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import RingkasanGalat from '@/Komponen/Pengelola/TemplateSektor/RingkasanGalat';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/Komponen/Ui/card';
import { Checkbox } from '@/Komponen/Ui/checkbox';
import { Input } from '@/Komponen/Ui/input';
import { Separator } from '@/Komponen/Ui/separator';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/Komponen/Ui/table';
import type { AkunTemplate, IsiAkunTemplate, PilihanEditorTemplate } from '@/Tipe/TemplateSektor';
import PilihanCari from '@/Komponen/Formulir/PilihanCari';

type PropsFormAkun = {
    url: string;
    isi: IsiAkunTemplate;
    pilihan: PilihanEditorTemplate;
    bolehUbah: boolean;
};

const kelasSel = 'px-2 py-2 whitespace-normal [&_[data-slot=native-select-wrapper]]:w-full';
const kelasKepala = 'px-2 text-label font-semibold text-teks-sekunder';
const kelasInput = 'h-8 pointer-coarse:h-11 px-2 text-isi';

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
        <Card className="gap-4">
            <CardHeader>
                <CardTitle>
                    <h2 className="text-subjudul font-semibold text-teks-utama">Akun & pajak</h2>
                </CardTitle>
                <CardDescription className="text-keterangan text-teks-sekunder">Diubah oleh Keuangan.</CardDescription>
            </CardHeader>
            <form onSubmit={Kirim} className="flex flex-col gap-6" noValidate>
                <CardContent className="flex flex-col gap-6">
                    <RingkasanGalat galat={galat} />
                    <fieldset disabled={!bolehUbah} className="flex flex-col gap-6">
                        <section className="flex flex-col gap-2">
                            <h3 className="text-label font-semibold text-teks-utama">Bagan akun (COA)</h3>
                            {data.Akun.length === 0 ? (
                                <p className="text-keterangan text-teks-sekunder">
                                    Belum ada akun. Tambahkan akun atau salin template yang sudah ada saat membuat
                                    template.
                                </p>
                            ) : null}
                            <Table className="min-w-[40rem] text-isi">
                                <TableCaption className="sr-only">Bagan akun template</TableCaption>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead scope="col" className={`${kelasKepala} w-28`}>
                                            Kode
                                        </TableHead>
                                        <TableHead scope="col" className={kelasKepala}>
                                            Nama akun
                                        </TableHead>
                                        <TableHead scope="col" className={`${kelasKepala} w-40`}>
                                            Tipe
                                        </TableHead>
                                        <TableHead scope="col" className={`${kelasKepala} w-24`}>
                                            Kontra
                                        </TableHead>
                                        <TableHead scope="col" className={`${kelasKepala} w-24`}>
                                            Saldo normal
                                        </TableHead>
                                        <TableHead scope="col" className={kelasKepala}>
                                            <span className="sr-only">Aksi</span>
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {data.Akun.map((akun, indeks) => (
                                        <TableRow key={indeks}>
                                            <TableCell className={kelasSel}>
                                                <Input
                                                    aria-label={`Kode akun baris ${indeks + 1}`}
                                                    className={`${kelasInput} font-mono`}
                                                    value={akun.Kode}
                                                    onChange={(peristiwa) =>
                                                        UbahAkun(indeks, { Kode: peristiwa.target.value })
                                                    }
                                                />
                                            </TableCell>
                                            <TableCell className={kelasSel}>
                                                <Input
                                                    aria-label={`Nama akun baris ${indeks + 1}`}
                                                    className={kelasInput}
                                                    value={akun.Nama}
                                                    onChange={(peristiwa) =>
                                                        UbahAkun(indeks, { Nama: peristiwa.target.value })
                                                    }
                                                />
                                            </TableCell>
                                            <TableCell className={kelasSel}>
                                                <PilihanCari
                                                    label="Tipe akun"
                                                    aria-label={`Tipe akun baris ${indeks + 1}`}
                                                    className={kelasInput}
                                                    nilai={akun.Tipe}
                                                    opsi={pilihan.TipeAkun.map((tipe) => ({
                                                        Nilai: tipe.Nilai,
                                                        Label: `${tipe.DigitAwal}- ${tipe.Label}`,
                                                    }))}
                                                    saatBerubah={(nilai) => UbahAkun(indeks, { Tipe: nilai })}
                                                />
                                            </TableCell>
                                            <TableCell className={kelasSel}>
                                                <Checkbox
                                                    aria-label={`Akun kontra baris ${indeks + 1}`}
                                                    checked={akun.Kontra}
                                                    onCheckedChange={(dicentang) =>
                                                        UbahAkun(indeks, { Kontra: dicentang === true })
                                                    }
                                                />
                                            </TableCell>
                                            <TableCell className={`${kelasSel} text-teks-sekunder`}>
                                                {akun.SaldoNormal}
                                            </TableCell>
                                            <TableCell className={`${kelasSel} text-right`}>
                                                {bolehUbah ? (
                                                    <Tombol varian="bahaya" onClick={() => HapusAkun(indeks)}>
                                                        Hapus
                                                    </Tombol>
                                                ) : null}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                            {bolehUbah ? (
                                <div>
                                    <Tombol varian="sekunder" onClick={TambahAkun}>
                                        Tambah akun
                                    </Tombol>
                                </div>
                            ) : null}
                        </section>

                        <Separator />
                        <section className="flex flex-col gap-2">
                            <h3 className="text-label font-semibold text-teks-utama">
                                Pemetaan akun per jenis transaksi
                            </h3>
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
                                            formulir.setData('PemetaanAkun', {
                                                ...data.PemetaanAkun,
                                                [peran.Nilai]: nilai,
                                            })
                                        }
                                        galat={galat[`PemetaanAkun.${peran.Nilai}`]}
                                    />
                                ))}
                            </div>
                        </section>

                        <Separator />
                        <section className="flex flex-col gap-3">
                            <h3 className="text-label font-semibold text-teks-utama">Kelompok pajak default</h3>
                            {data.KelompokPajak.length === 0 ? (
                                <p className="text-keterangan text-teks-sekunder">
                                    Belum ada kelompok pajak. Usaha non-PKP tanpa pajak daerah boleh kosong.
                                </p>
                            ) : null}
                            {data.KelompokPajak.map((kelompok, indeks) => (
                                <Card key={indeks} className="gap-3 p-4 shadow-none">
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
                                                                Detail: kelompok.Detail.filter(
                                                                    (_, ke) => ke !== posisi,
                                                                ),
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
                                </Card>
                            ))}
                            {bolehUbah ? (
                                <div>
                                    <Tombol
                                        varian="sekunder"
                                        onClick={() =>
                                            formulir.setData('KelompokPajak', [
                                                ...data.KelompokPajak,
                                                { Nama: '', Detail: [] },
                                            ])
                                        }
                                    >
                                        Tambah kelompok pajak
                                    </Tombol>
                                </div>
                            ) : null}
                        </section>
                    </fieldset>
                </CardContent>
                {bolehUbah ? (
                    <CardFooter>
                        <Tombol type="submit" memproses={formulir.processing}>
                            Simpan akun & pajak
                        </Tombol>
                    </CardFooter>
                ) : null}
            </form>
        </Card>
    );
}

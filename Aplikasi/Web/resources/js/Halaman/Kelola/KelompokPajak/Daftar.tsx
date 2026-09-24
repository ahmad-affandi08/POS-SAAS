import { useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangPilihan from '@/Komponen/Formulir/BidangPilihan';
import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import Tombol from '@/Komponen/Formulir/Tombol';
import DaftarGalatServer from '@/Komponen/Katalog/DaftarGalatServer';
import PesanHanyaLihat from '@/Komponen/Katalog/PesanHanyaLihat';
import RingkasanGalatFormulir from '@/Komponen/PanduanAwal/RingkasanGalatFormulir';
import TabelData from '@/Komponen/TabelData/TabelData';
import type { KolomTabel } from '@/Komponen/TabelData/Tipe';
import { Button } from '@/Komponen/Ui/button';
import { Card } from '@/Komponen/Ui/card';
import { DropdownMenuItem } from '@/Komponen/Ui/dropdown-menu';
import { FieldDescription, FieldError, FieldLegend, FieldSet } from '@/Komponen/Ui/field';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/Komponen/Ui/sheet';
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
            className="flex flex-col gap-4"
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
                <FieldSet className="gap-2">
                    <FieldLegend variant="label" className="mb-0 text-label font-semibold text-teks-utama">
                        Pajak yang dikenakan (berurutan)
                    </FieldLegend>
                    <FieldDescription className="text-keterangan">
                        Tarif tidak disimpan di sini; tarif diambil dari tabel tarif yang berlaku pada tanggal
                        transaksi.
                    </FieldDescription>
                    {data.Pajak.map((pajak, indeks) => (
                        <Card key={indeks} className="grid items-end gap-2 p-3 shadow-none sm:grid-cols-[1fr_1fr_auto]">
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
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => AturPajak(data.Pajak.filter((_, i) => i !== indeks))}
                                className="h-10 text-destructive"
                                aria-label={`Hapus pajak ${String(indeks + 1)}`}
                            >
                                Hapus
                            </Button>
                        </Card>
                    ))}
                    <p>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                AturPajak([
                                    ...data.Pajak,
                                    { KodeJenisPajak: '', DasarPengenaan: props.DasarPengenaan[0]?.Nilai ?? '' },
                                ])
                            }
                        >
                            Tambah pajak
                        </Button>
                    </p>
                </FieldSet>
            )}
            <div aria-live="polite">
                {(galatKonsistensi ?? galat.Pajak) ? (
                    <FieldError className="text-keterangan font-semibold">{galatKonsistensi ?? galat.Pajak}</FieldError>
                ) : null}
            </div>
            <div className="flex flex-wrap gap-2">
                <Tombol type="submit" memproses={formulir.processing}>
                    Simpan kelompok pajak
                </Tombol>
                <Button type="button" variant="outline" onClick={saatSelesai}>
                    Batal
                </Button>
            </div>
        </form>
    );
}

const kolom: KolomTabel<KelompokPajak>[] = [
    {
        id: 'Nama',
        accessorKey: 'Nama',
        header: 'Kelompok',
        meta: { label: 'Kelompok', prioritas: 'utama', wajib: true },
        cell: ({ row: { original: item } }) => (
            <>
                <span className="block font-semibold break-words text-teks-utama">{item.Nama}</span>
                <span className="text-keterangan text-teks-sekunder">{item.LabelKategori}</span>
            </>
        ),
    },
    {
        id: 'Kategori',
        accessorKey: 'Kategori',
        header: 'Kategori pajak',
        meta: { label: 'Kategori pajak', prioritas: 'rendah', kelasSel: 'text-teks-sekunder' },
        cell: ({ row }) => row.original.LabelKategori,
    },
    {
        id: 'Pajak',
        header: 'Pajak',
        enableSorting: false,
        meta: { label: 'Pajak', prioritas: 'penting', kelasSel: 'text-teks-sekunder' },
        cell: ({ row: { original: item } }) =>
            item.Pajak.length === 0 ? (
                'Tanpa pajak'
            ) : (
                <ol className="flex flex-col gap-0.5">
                    {item.Pajak.map((pajak) => (
                        <li key={pajak.KodeJenisPajak}>
                            {pajak.NamaJenisPajak} · {pajak.LabelDasarPengenaan}
                        </li>
                    ))}
                </ol>
            ),
    },
    {
        id: 'JumlahProduk',
        accessorKey: 'JumlahProduk',
        header: 'Produk',
        meta: { label: 'Jumlah produk', angka: true, prioritas: 'penting' },
    },
];

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
                {Izin.KelolaPajak ? (
                    <Button type="button" onClick={() => AturSunting('baru')}>
                        Tambah kelompok pajak
                    </Button>
                ) : null}
            </div>
            <Sheet open={sunting !== null} onOpenChange={(buka) => (buka ? undefined : AturSunting(null))}>
                {sunting !== null ? (
                    <SheetContent className="w-full overflow-y-auto sm:max-w-xl">
                        <SheetHeader>
                            <SheetTitle>
                                {sunting === 'baru' ? 'Tambah kelompok pajak' : `Ubah kelompok pajak ${sunting.Nama}`}
                            </SheetTitle>
                            <SheetDescription>
                                PBJT makanan & minuman dan PPN tidak boleh dikenakan bersamaan pada satu produk.
                            </SheetDescription>
                        </SheetHeader>
                        <div className="px-4 pb-4">
                            <FormKelompok
                                key={sunting === 'baru' ? 'baru' : sunting.Uuid}
                                kelompok={sunting === 'baru' ? null : sunting}
                                props={propsHalaman}
                                saatSelesai={() => AturSunting(null)}
                            />
                        </div>
                    </SheetContent>
                ) : null}
            </Sheet>
            <TabelData
                id="katalog-kelompok-pajak"
                label="Daftar kelompok pajak"
                kolom={kolom}
                sumber={{ mode: 'lokal', data: KelompokPajak }}
                ambilIdBaris={(item) => item.Uuid}
                urutBawaan="Nama"
                cari="Cari nama kelompok pajak"
                saring={[
                    {
                        id: 'Kategori',
                        label: 'Kategori pajak',
                        jenis: 'pilihanBanyak',
                        opsi: propsHalaman.Kategori.map((o) => ({ nilai: o.Nilai, label: o.Label })),
                    },
                ]}
                labelBaris={(item) => item.Nama}
                {...(Izin.KelolaPajak
                    ? {
                          aksiBaris: (item: KelompokPajak) => (
                              <DropdownMenuItem onSelect={() => AturSunting(item)}>
                                  Ubah kelompok pajak
                              </DropdownMenuItem>
                          ),
                      }
                    : {})}
                kosong={{
                    judul: 'Belum ada kelompok pajak. Tambah kelompok pajak sebelum menambah produk yang dijual.',
                }}
            />
        </TataLetakAplikasi>
    );
}

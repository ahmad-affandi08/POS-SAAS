import { router, useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

import BidangTeks from '@/Komponen/Formulir/BidangTeks';
import BidangUang from '@/Komponen/Formulir/BidangUang';
import KotakCentang from '@/Komponen/Formulir/KotakCentang';
import Tombol from '@/Komponen/Formulir/Tombol';
import { JenisBahan } from '@/Komponen/Katalog/BantuanKatalog';
import BidangJumlah from '@/Komponen/Katalog/BidangJumlah';
import DaftarGalatServer from '@/Komponen/Katalog/DaftarGalatServer';
import KeadaanKosong from '@/Komponen/Katalog/KeadaanKosong';
import PemilihProduk from '@/Komponen/Katalog/PemilihProduk';
import PesanHanyaLihat from '@/Komponen/Katalog/PesanHanyaLihat';
import RingkasanGalatFormulir, { FokusGalatPertama } from '@/Komponen/PanduanAwal/RingkasanGalatFormulir';
import LabelStatus from '@/Komponen/Umpan/LabelStatus';
import { FormatRupiah } from '@/Pustaka/Format';
import { BandingkanDesimal, CekDesimalValid, FormatJumlahSatuan } from '@/Pustaka/MasukanJumlah';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import type { FormKelompokPilihan, FormPilihan, PropsDaftarKelompokPilihan } from '@/Tipe/Katalog';

type Kelompok = PropsDaftarKelompokPilihan['KelompokPilihan'][number];
/** Baris pilihan di formulir + nama bahan untuk tampilan (tidak dikirim ke server). */
type BarisPilihan = FormPilihan & { NamaBahan: string | null };
type PilihanTampil = FormPilihan & { NamaProdukBahan: string | null; SimbolSatuanBahan: string | null };

/**
 * Kontrak E.9 menulis `FormKelompokPilihan & { Pilihan: (FormPilihan & {…})[] }`; interseksi dua tipe array membuat
 * TypeScript memakai elemen `FormPilihan` saat `.map`. Nilai runtime-nya memang berisi NamaProdukBahan, jadi dipersempit di sini.
 */
function AmbilPilihanTampil(kelompok: Kelompok): PilihanTampil[] {
    return kelompok.Pilihan as PilihanTampil[];
}

export const MaksimalPilihan = 50;

/** Aturan batas pilih (DesainF03 C.4 BatasPilihanTidakValid) dan nama unik; null = sesuai. */
export function PeriksaKelompokPilihan(
    data: Pick<FormKelompokPilihan, 'MinimalPilih' | 'MaksimalPilih'> & { Pilihan: FormPilihan[] },
): string | null {
    const minimal = data.MinimalPilih === '' ? 0 : Number.parseInt(data.MinimalPilih, 10);
    const maksimal = data.MaksimalPilih === '' ? 0 : Number.parseInt(data.MaksimalPilih, 10);
    const aktif = data.Pilihan.filter((item) => item.Aktif).length;
    const nama = data.Pilihan.map((item) => item.Nama.trim().toLowerCase());

    if (data.Pilihan.length === 0 || data.Pilihan.length > MaksimalPilihan) {
        return `Isi 1 sampai ${String(MaksimalPilihan)} pilihan.`;
    }

    if (maksimal < 1 || maksimal > 20) {
        return 'Maksimal pilih harus 1 sampai 20.';
    }

    if (minimal > maksimal) {
        return 'Minimal pilih tidak boleh lebih besar dari maksimal pilih.';
    }

    if (minimal > aktif) {
        return `Minimal pilih ${String(minimal)}, tetapi hanya ${String(aktif)} pilihan aktif.`;
    }

    if (nama.some((item) => item === '') || new Set(nama).size !== nama.length) {
        return 'Setiap pilihan perlu nama, dan nama tidak boleh sama dalam satu kelompok.';
    }

    return null;
}

/** Ringkasan aturan kelompok: "Wajib pilih 1", "Opsional, maks 3". */
export function RingkasAturanPilih(minimal: string, maksimal: string): string {
    return minimal !== '' && minimal !== '0'
        ? `Wajib pilih ${minimal === maksimal ? minimal : `${minimal}–${maksimal}`}`
        : `Opsional, maks ${maksimal}`;
}

function FormKelompok({
    kelompok,
    bolehUbahHarga,
    saatSelesai,
}: {
    kelompok: Kelompok | null;
    bolehUbahHarga: boolean;
    saatSelesai: () => void;
}) {
    const formulir = useForm<Omit<FormKelompokPilihan, 'Pilihan'> & { Pilihan: BarisPilihan[] }>({
        Nama: kelompok?.Nama ?? '',
        MinimalPilih: kelompok?.MinimalPilih ?? '0',
        MaksimalPilih: kelompok?.MaksimalPilih ?? '1',
        Urutan: kelompok?.Urutan ?? '0',
        Pilihan: (kelompok ? AmbilPilihanTampil(kelompok) : null)?.map((item) => ({
            Uuid: item.Uuid,
            Nama: item.Nama,
            Harga: item.Harga,
            Aktif: item.Aktif,
            UuidProdukBahan: item.UuidProdukBahan,
            Jumlah: item.Jumlah,
            NamaBahan: item.NamaProdukBahan ? `${item.NamaProdukBahan} (${item.SimbolSatuanBahan ?? ''})` : null,
        })) ?? [{ Uuid: null, Nama: '', Harga: '0', Aktif: true, UuidProdukBahan: null, Jumlah: '', NamaBahan: null }],
    });
    const data = formulir.data;
    const galat = formulir.errors as Record<string, string | undefined>;
    const [periksa, AturPeriksa] = useState(false);
    const [elemen, AturElemen] = useState<HTMLFormElement | null>(null);
    const galatLokal = periksa ? PeriksaKelompokPilihan(data) : null;
    const UbahPilihan = (indeks: number, perubahan: Partial<BarisPilihan>) =>
        formulir.setData((lama) => ({
            ...lama,
            Pilihan: lama.Pilihan.map((item, i) => (i === indeks ? { ...item, ...perubahan } : item)),
        }));

    const Kirim = (peristiwa: FormEvent) => {
        peristiwa.preventDefault();
        AturPeriksa(true);

        if (PeriksaKelompokPilihan(data) !== null) {
            return;
        }

        const opsi = {
            preserveScroll: true,
            onSuccess: saatSelesai,
            onError: () => FokusGalatPertama(elemen),
        };

        formulir.transform((isi) => ({
            ...isi,
            Pilihan: isi.Pilihan.map((pilihan) => ({
                Uuid: pilihan.Uuid,
                Nama: pilihan.Nama,
                Harga: pilihan.Harga,
                Aktif: pilihan.Aktif,
                UuidProdukBahan: pilihan.UuidProdukBahan,
                Jumlah: pilihan.Jumlah,
            })),
        }));

        if (kelompok === null) {
            formulir.post('/kelola/kelompok-pilihan', opsi);
        } else {
            formulir.put(`/kelola/kelompok-pilihan/${kelompok.Uuid}`, opsi);
        }
    };

    return (
        <form
            ref={AturElemen}
            onSubmit={Kirim}
            noValidate
            aria-label={kelompok ? `Ubah kelompok pilihan ${kelompok.Nama}` : 'Tambah kelompok pilihan'}
            className="flex flex-col gap-4 rounded-panel border border-garis bg-permukaan p-4"
        >
            <RingkasanGalatFormulir galat={{ ...galat, ...(galatLokal ? { Pilihan: galatLokal } : {}) }} />
            <div className="grid gap-3 sm:grid-cols-4">
                <div className="sm:col-span-2">
                    <BidangTeks
                        label="Nama kelompok"
                        nilai={data.Nama}
                        saatBerubah={(nilai) => formulir.setData('Nama', nilai)}
                        galat={galat.Nama}
                        keterangan="Misal Level gula, Ukuran, atau Topping."
                        maxLength={100}
                        autoFocus
                        required
                    />
                </div>
                <BidangTeks
                    label="Minimal pilih"
                    nilai={data.MinimalPilih}
                    saatBerubah={(nilai) => formulir.setData('MinimalPilih', nilai.replace(/\D/g, ''))}
                    galat={galat.MinimalPilih}
                    inputMode="numeric"
                    maxLength={2}
                    keterangan="0 = boleh dilewati kasir."
                />
                <BidangTeks
                    label="Maksimal pilih"
                    nilai={data.MaksimalPilih}
                    saatBerubah={(nilai) => formulir.setData('MaksimalPilih', nilai.replace(/\D/g, ''))}
                    galat={galat.MaksimalPilih}
                    inputMode="numeric"
                    maxLength={2}
                />
            </div>
            <fieldset className="flex flex-col gap-3">
                <legend className="text-label font-semibold text-teks-utama">Pilihan ({data.Pilihan.length})</legend>
                {data.Pilihan.map((pilihan, indeks) => (
                    <div
                        key={pilihan.Uuid ?? `baru-${String(indeks)}`}
                        className="flex flex-col gap-3 rounded-kontrol border border-garis p-3"
                    >
                        <div className="grid gap-3 sm:grid-cols-3">
                            <BidangTeks
                                label={`Nama pilihan ${String(indeks + 1)}`}
                                nilai={pilihan.Nama}
                                saatBerubah={(nilai) => UbahPilihan(indeks, { Nama: nilai })}
                                galat={galat[`Pilihan.${String(indeks)}.Nama`]}
                                maxLength={100}
                            />
                            <BidangUang
                                label={`Tambahan harga ${String(indeks + 1)}`}
                                nilai={pilihan.Harga}
                                saatBerubah={(nilai) => UbahPilihan(indeks, { Harga: nilai })}
                                galat={galat[`Pilihan.${String(indeks)}.Harga`]}
                                keterangan={
                                    bolehUbahHarga
                                        ? 'Isi 0 bila gratis.'
                                        : 'Perlu izin produk.harga.ubah untuk harga selain 0.'
                                }
                                disabled={!bolehUbahHarga}
                            />
                            <div className="flex items-end">
                                <KotakCentang
                                    label="Aktif di kasir"
                                    nilai={pilihan.Aktif}
                                    saatBerubah={(nilai) => UbahPilihan(indeks, { Aktif: nilai })}
                                />
                            </div>
                        </div>
                        {pilihan.UuidProdukBahan === null ? (
                            <PemilihProduk
                                label={`Bahan terpakai ${String(indeks + 1)} (opsional)`}
                                jenis={JenisBahan}
                                keterangan="Untuk memotong stok bahan, misal topping keju 20 gram."
                                saatPilih={(produk) =>
                                    UbahPilihan(indeks, {
                                        UuidProdukBahan: produk.Uuid,
                                        NamaBahan: `${produk.Nama} (${produk.Satuan.find((s) => s.Uuid === produk.UuidSatuanDasar)?.Simbol ?? ''})`,
                                        Jumlah: '',
                                    })
                                }
                                galat={galat[`Pilihan.${String(indeks)}.UuidProdukBahan`]}
                            />
                        ) : (
                            <div className="grid items-end gap-3 sm:grid-cols-3">
                                <p className="text-isi text-teks-utama sm:col-span-1">
                                    <span className="block text-label font-semibold">Bahan terpakai</span>
                                    {pilihan.NamaBahan ?? 'Bahan'}
                                </p>
                                <BidangJumlah
                                    label={`Jumlah bahan ${String(indeks + 1)} (satuan dasar)`}
                                    nilai={pilihan.Jumlah}
                                    saatBerubah={(nilai) => UbahPilihan(indeks, { Jumlah: nilai })}
                                    galat={galat[`Pilihan.${String(indeks)}.Jumlah`]}
                                />
                                <button
                                    type="button"
                                    onClick={() =>
                                        UbahPilihan(indeks, { UuidProdukBahan: null, Jumlah: '', NamaBahan: null })
                                    }
                                    className="h-10 text-left text-label font-semibold text-bahaya underline outline-none focus-visible:ring-2 focus-visible:ring-brand"
                                >
                                    Lepas bahan
                                </button>
                            </div>
                        )}
                        {data.Pilihan.length > 1 ? (
                            <p>
                                <button
                                    type="button"
                                    onClick={() =>
                                        formulir.setData((lama) => ({
                                            ...lama,
                                            Pilihan: lama.Pilihan.filter((_, i) => i !== indeks),
                                        }))
                                    }
                                    className="text-label font-semibold text-bahaya underline outline-none focus-visible:ring-2 focus-visible:ring-brand"
                                >
                                    Hapus pilihan {pilihan.Nama || String(indeks + 1)}
                                </button>
                            </p>
                        ) : null}
                    </div>
                ))}
                {data.Pilihan.length < MaksimalPilihan ? (
                    <p>
                        <button
                            type="button"
                            onClick={() =>
                                formulir.setData((lama) => ({
                                    ...lama,
                                    Pilihan: [
                                        ...lama.Pilihan,
                                        {
                                            Uuid: null,
                                            Nama: '',
                                            Harga: '0',
                                            Aktif: true,
                                            UuidProdukBahan: null,
                                            Jumlah: '',
                                            NamaBahan: null,
                                        },
                                    ],
                                }))
                            }
                            className="h-10 rounded-kontrol border border-garis-input bg-permukaan px-3 text-label font-semibold text-teks-utama outline-none focus-visible:ring-2 focus-visible:ring-brand"
                        >
                            Tambah pilihan
                        </button>
                    </p>
                ) : null}
            </fieldset>
            <div aria-live="polite">
                {(galatLokal ?? galat.Pilihan) ? (
                    <p className="text-keterangan font-semibold text-bahaya">{galatLokal ?? galat.Pilihan}</p>
                ) : null}
            </div>
            <div className="flex flex-wrap gap-2">
                <Tombol type="submit" memproses={formulir.processing}>
                    Simpan kelompok pilihan
                </Tombol>
                <Tombol varian="sekunder" onClick={saatSelesai}>
                    Batal
                </Tombol>
            </div>
        </form>
    );
}

/** F-03 kelompok pilihan (modifier) dengan harga tambahan dan bahan opsional untuk potong stok. */
export default function HalamanDaftarKelompokPilihan({ KelompokPilihan, Izin }: PropsDaftarKelompokPilihan) {
    const { props } = usePage<PropsBersamaAplikasi>();
    const [sunting, AturSunting] = useState<Kelompok | 'baru' | null>(null);
    const [hapus, AturHapus] = useState<Kelompok | null>(null);

    return (
        <TataLetakAplikasi judul="Pilihan (modifier)">
            {!Izin.Kelola ? <PesanHanyaLihat izin="produk.kelola" objek="kelompok pilihan" /> : null}
            <DaftarGalatServer galat={props.errors} kecuali={sunting !== null ? Object.keys(props.errors) : []} />
            <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="max-w-2xl text-isi text-teks-sekunder">
                    Pilihan yang ditanyakan kasir saat menjual, misal Level gula atau Topping. Pasang ke produk dari
                    halaman produk, tab Pilihan.
                </p>
                {Izin.Kelola && sunting === null ? (
                    <Tombol onClick={() => AturSunting('baru')}>Tambah kelompok pilihan</Tombol>
                ) : null}
            </div>
            {sunting !== null ? (
                <FormKelompok
                    key={sunting === 'baru' ? 'baru' : sunting.Uuid}
                    kelompok={sunting === 'baru' ? null : sunting}
                    bolehUbahHarga={Izin.UbahHarga}
                    saatSelesai={() => AturSunting(null)}
                />
            ) : null}
            {hapus !== null ? (
                <div
                    role="alertdialog"
                    aria-labelledby="judul-hapus-kelompok"
                    className="flex flex-col gap-2 rounded-panel border border-l-4 border-bahaya bg-permukaan p-4"
                >
                    <p id="judul-hapus-kelompok" className="text-label font-semibold text-teks-utama">
                        Hapus kelompok {hapus.Nama}?
                    </p>
                    <p className="text-isi text-teks-sekunder">
                        Kelompok ini dilepas dari {hapus.JumlahProduk} produk. Transaksi lama tidak berubah.
                    </p>
                    <div className="flex flex-wrap gap-2">
                        <Tombol
                            varian="bahaya"
                            autoFocus
                            onClick={() =>
                                router.delete(`/kelola/kelompok-pilihan/${hapus.Uuid}`, {
                                    preserveScroll: true,
                                    onFinish: () => AturHapus(null),
                                })
                            }
                        >
                            Ya, hapus kelompok
                        </Tombol>
                        <Tombol varian="sekunder" onClick={() => AturHapus(null)}>
                            Batal
                        </Tombol>
                    </div>
                </div>
            ) : null}
            {KelompokPilihan.length === 0 ? (
                <KeadaanKosong judul="Belum ada kelompok pilihan. Tambah kelompok, misal Level gula: Normal, Kurang manis, Tanpa gula." />
            ) : (
                <ul className="flex flex-col gap-3">
                    {KelompokPilihan.map((kelompok) => (
                        <li
                            key={kelompok.Uuid}
                            className="flex flex-col gap-2 rounded-panel border border-garis bg-permukaan p-4"
                        >
                            <div className="flex flex-wrap items-start justify-between gap-2">
                                <div className="min-w-0">
                                    <p className="font-semibold break-words text-teks-utama">{kelompok.Nama}</p>
                                    <p className="text-keterangan text-teks-sekunder">
                                        {RingkasAturanPilih(kelompok.MinimalPilih, kelompok.MaksimalPilih)} · dipakai{' '}
                                        {kelompok.JumlahProduk} produk
                                    </p>
                                </div>
                                {Izin.Kelola ? (
                                    <span className="flex gap-3">
                                        <button
                                            type="button"
                                            onClick={() => AturSunting(kelompok)}
                                            className="text-label font-semibold text-brand underline outline-none focus-visible:ring-2 focus-visible:ring-brand"
                                            aria-label={`Ubah kelompok ${kelompok.Nama}`}
                                        >
                                            Ubah
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => AturHapus(kelompok)}
                                            className="text-label font-semibold text-bahaya underline outline-none focus-visible:ring-2 focus-visible:ring-brand"
                                            aria-label={`Hapus kelompok ${kelompok.Nama}`}
                                        >
                                            Hapus
                                        </button>
                                    </span>
                                ) : null}
                            </div>
                            <table className="w-full text-left text-label">
                                <caption className="sr-only">Pilihan di {kelompok.Nama}</caption>
                                <thead className="sr-only">
                                    <tr>
                                        <th scope="col">Pilihan</th>
                                        <th scope="col">Bahan</th>
                                        <th scope="col">Tambahan harga</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {AmbilPilihanTampil(kelompok).map((pilihan) => (
                                        <tr key={pilihan.Uuid ?? pilihan.Nama} className="border-t border-garis">
                                            <td className="py-1 pr-2 text-teks-utama">
                                                {pilihan.Nama}{' '}
                                                {!pilihan.Aktif ? <LabelStatus jenis="netral" teks="Nonaktif" /> : null}
                                            </td>
                                            <td className="px-2 py-1 text-teks-sekunder">
                                                {pilihan.NamaProdukBahan
                                                    ? `${pilihan.NamaProdukBahan} ${FormatJumlahSatuan(pilihan.Jumlah, pilihan.SimbolSatuanBahan ?? '')}`
                                                    : ''}
                                            </td>
                                            <td className="py-1 pl-2 text-right tabular-nums">
                                                {CekDesimalValid(pilihan.Harga) &&
                                                BandingkanDesimal(pilihan.Harga, '0') === 0
                                                    ? 'Gratis'
                                                    : `+${FormatRupiah(pilihan.Harga)}`}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </li>
                    ))}
                </ul>
            )}
        </TataLetakAplikasi>
    );
}

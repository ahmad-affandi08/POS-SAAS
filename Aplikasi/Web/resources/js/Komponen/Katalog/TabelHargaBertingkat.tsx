import BidangUang from '@/Komponen/Formulir/BidangUang';
import { FormatRupiah } from '@/Pustaka/Format';
import { BandingkanDesimal, CekDesimalBulat, CekDesimalPositif, FormatMasukanJumlah } from '@/Pustaka/MasukanJumlah';
import type { BarisHarga } from '@/Tipe/Katalog';

import BidangJumlah from './BidangJumlah';

type GalatBaris = { JumlahMinimum?: string; Harga?: string };

type OpsiPeriksa = {
    /** Harga dasar wajib punya baris JumlahMinimum = 1 (tidak berlaku untuk daftar harga). */
    wajibDasar: boolean;
    /** Satuan boleh desimal: JumlahMinimum pecahan diizinkan. */
    bolehDesimal: boolean;
};

/**
 * Validasi baris harga di peramban (cermin DesainF03 C.3; server tetap penentu). Murni string, tanpa number.
 * Hasil: galat per indeks baris dan galat umum tabel.
 */
export function PeriksaBarisHarga(
    baris: BarisHarga[],
    { wajibDasar, bolehDesimal }: OpsiPeriksa,
): { perBaris: Record<number, GalatBaris>; umum: string | null } {
    const perBaris: Record<number, GalatBaris> = {};

    baris.forEach((item, indeks) => {
        const galat: GalatBaris = {};

        if (!CekDesimalPositif(item.JumlahMinimum)) {
            galat.JumlahMinimum = 'Isi jumlah minimum lebih dari 0.';
        } else if (!bolehDesimal && !CekDesimalBulat(item.JumlahMinimum)) {
            galat.JumlahMinimum = 'Satuan ini tidak boleh pecahan. Isi bilangan bulat.';
        } else if (
            baris.some(
                (lain, indeksLain) =>
                    indeksLain < indeks &&
                    CekDesimalPositif(lain.JumlahMinimum) &&
                    BandingkanDesimal(lain.JumlahMinimum, item.JumlahMinimum) === 0,
            )
        ) {
            galat.JumlahMinimum = 'Jumlah minimum ini sudah ada di baris lain.';
        }

        if (item.Harga === '') {
            galat.Harga = 'Isi harga. Tulis 0 bila gratis.';
        }

        if (galat.JumlahMinimum !== undefined || galat.Harga !== undefined) {
            perBaris[indeks] = galat;
        }
    });

    const adaDasar = baris.some(
        (item) => CekDesimalPositif(item.JumlahMinimum) && BandingkanDesimal(item.JumlahMinimum, '1') === 0,
    );
    const umum = wajibDasar && baris.length > 0 && !adaDasar ? 'Harga dasar untuk jumlah minimum 1 wajib ada.' : null;

    return { perBaris, umum };
}

/** Urutkan baris naik menurut JumlahMinimum (perbandingan desimal string); baris tidak valid di akhir. */
export function UrutkanBarisHarga<T extends BarisHarga>(baris: T[]): T[] {
    return [...baris].sort((a, b) => {
        const validA = CekDesimalPositif(a.JumlahMinimum);
        const validB = CekDesimalPositif(b.JumlahMinimum);

        if (validA && validB) {
            return BandingkanDesimal(a.JumlahMinimum, b.JumlahMinimum);
        }

        return validA ? -1 : validB ? 1 : 0;
    });
}

/** Ringkasan satu baris: "1+ pcs: Rp 5.000" / "12+ pcs: Rp 4.500". */
export function RingkasBarisHarga(baris: BarisHarga, simbol: string): string {
    return `${FormatMasukanJumlah(baris.JumlahMinimum)}+ ${simbol}: ${FormatRupiah(baris.Harga)}`;
}

type PropsTabelHargaBertingkat = {
    /** Judul tabel untuk pembaca layar dan caption, misal "Harga per pcs". */
    judul: string;
    baris: BarisHarga[];
    saatBerubah: (baris: BarisHarga[]) => void;
    simbolSatuan: string;
    bolehDesimal: boolean;
    wajibDasar: boolean;
    /** Galat server dengan kunci relatif, misal {"0.Harga": "…", "1.JumlahMinimum": "…"}. */
    galatServer?: Record<string, string | undefined>;
    /** Tampilkan galat validasi lokal (setelah pengguna mencoba menyimpan). */
    tampilkanGalat?: boolean;
    disabled?: boolean;
};

/**
 * Harga dasar + harga bertingkat per satuan (price engine lapis 5): baris pertama JumlahMinimum 1,
 * tingkat berikutnya misal 12+ = Rp 4.500. Uang lewat BidangUang, jumlah lewat BidangJumlah: semua string.
 */
export default function TabelHargaBertingkat({
    judul,
    baris,
    saatBerubah,
    simbolSatuan,
    bolehDesimal,
    wajibDasar,
    galatServer = {},
    tampilkanGalat = false,
    disabled = false,
}: PropsTabelHargaBertingkat) {
    const hasilPeriksa = PeriksaBarisHarga(baris, { wajibDasar, bolehDesimal });
    const perBaris = tampilkanGalat ? hasilPeriksa.perBaris : {};
    const umum = tampilkanGalat ? hasilPeriksa.umum : null;
    const Ubah = (indeks: number, perubahan: Partial<BarisHarga>) =>
        saatBerubah(baris.map((item, i) => (i === indeks ? { ...item, ...perubahan } : item)));

    return (
        <div className="flex flex-col gap-2">
            {baris.length === 0 ? (
                <p className="rounded-kontrol border border-dashed border-garis-input px-3 py-2 text-isi text-teks-sekunder">
                    Belum ada harga. Tanpa harga dasar, satuan ini hanya untuk pembelian dan tidak muncul di kasir.
                </p>
            ) : (
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[420px] text-left text-isi">
                        <caption className="sr-only">{judul}</caption>
                        <thead className="border-b border-garis text-label text-teks-sekunder">
                            <tr>
                                <th scope="col" className="py-1 pr-2 text-right font-semibold">
                                    Mulai jumlah ({simbolSatuan})
                                </th>
                                <th scope="col" className="px-2 py-1 text-right font-semibold">
                                    Harga per {simbolSatuan}
                                </th>
                                <th scope="col" className="py-1 pl-2 font-semibold">
                                    <span className="sr-only">Aksi</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {baris.map((item, indeks) => {
                                const dasar =
                                    wajibDasar &&
                                    indeks === 0 &&
                                    CekDesimalPositif(item.JumlahMinimum) &&
                                    BandingkanDesimal(item.JumlahMinimum, '1') === 0;

                                return (
                                    <tr key={indeks} className="align-top">
                                        <td className="py-1 pr-2">
                                            <BidangJumlah
                                                label={`Mulai jumlah baris ${String(indeks + 1)}`}
                                                labelTersembunyi
                                                nilai={item.JumlahMinimum}
                                                saatBerubah={(nilai) => Ubah(indeks, { JumlahMinimum: nilai })}
                                                desimal={bolehDesimal ? 4 : 0}
                                                akhiran={simbolSatuan}
                                                disabled={disabled || dasar}
                                                galat={
                                                    galatServer[`${String(indeks)}.JumlahMinimum`] ??
                                                    perBaris[indeks]?.JumlahMinimum
                                                }
                                            />
                                        </td>
                                        <td className="px-2 py-1">
                                            <BidangUang
                                                label={`Harga baris ${String(indeks + 1)}`}
                                                labelTersembunyi
                                                nilai={item.Harga}
                                                saatBerubah={(nilai) => Ubah(indeks, { Harga: nilai })}
                                                disabled={disabled}
                                                galat={
                                                    galatServer[`${String(indeks)}.Harga`] ?? perBaris[indeks]?.Harga
                                                }
                                            />
                                        </td>
                                        <td className="py-1 pl-2">
                                            {dasar ? (
                                                <span className="inline-flex h-10 items-center text-keterangan text-teks-sekunder">
                                                    Harga dasar
                                                </span>
                                            ) : (
                                                <button
                                                    type="button"
                                                    disabled={disabled}
                                                    onClick={() => saatBerubah(baris.filter((_, i) => i !== indeks))}
                                                    className="h-10 text-label font-semibold text-bahaya underline outline-none focus-visible:ring-2 focus-visible:ring-brand"
                                                    aria-label={`Hapus tingkat harga baris ${String(indeks + 1)}`}
                                                >
                                                    Hapus
                                                </button>
                                            )}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            )}
            <div aria-live="polite">
                {umum ? <p className="text-keterangan font-semibold text-bahaya">{umum}</p> : null}
            </div>
            <div className="flex flex-wrap gap-2">
                {!disabled ? (
                    <button
                        type="button"
                        onClick={() =>
                            saatBerubah([...baris, { JumlahMinimum: baris.length === 0 ? '1' : '', Harga: '' }])
                        }
                        className="h-10 rounded-kontrol border border-garis-input bg-permukaan px-3 text-label font-semibold text-teks-utama outline-none focus-visible:ring-2 focus-visible:ring-brand"
                    >
                        {baris.length === 0 && wajibDasar ? 'Isi harga dasar' : 'Tambah harga bertingkat'}
                    </button>
                ) : null}
                {!disabled && baris.length > 1 ? (
                    <button
                        type="button"
                        onClick={() => saatBerubah(UrutkanBarisHarga(baris))}
                        className="h-10 px-2 text-label font-semibold text-brand underline outline-none focus-visible:ring-2 focus-visible:ring-brand"
                    >
                        Urutkan menurut jumlah
                    </button>
                ) : null}
            </div>
        </div>
    );
}

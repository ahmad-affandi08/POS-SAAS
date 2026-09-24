/**
 * Pemeriksaan lokal form stok awal (DesainF05a C.6.1 & D `SimpanStokAwalPermintaan`). Hanya untuk umpan balik cepat;
 * server tetap memeriksa ulang dan pesannya menang. Semua angka tetap string desimal (CLAUDE.md #7).
 */
import { PeriksaNomorSeri } from '@/Komponen/Persediaan/BidangNomorSeri';
import {
    AmbilTandaDesimal,
    BandingkanDesimal,
    CekDesimalBulat,
    CekDesimalValid,
    CekSkalaMaksimal,
    SkalaHpp,
    SkalaJumlah,
} from '@/Pustaka/HitungDesimal';
import type { BarisFormStokAwal, MasukanStokAwal } from '@/Tipe/Persediaan';

/** Panjang maksimal nomor batch (`Baris.*.NomorBatch` ≤ 60). */
export const PanjangMaksimalNomorBatch = 60;

export type GalatBarisStokAwal = Partial<
    Record<'Jumlah' | 'HppSatuan' | 'NomorBatch' | 'TanggalKedaluwarsa' | 'NomorSeri', string | undefined>
>;

export type AturanBaris = { WajibKedaluwarsaBatch: boolean; MaksimalNomorSeriPerBaris: number };

/** Galat per baris (kosong = baris valid). */
export function PeriksaBarisStokAwal(baris: BarisFormStokAwal, aturan: AturanBaris): GalatBarisStokAwal {
    const galat: GalatBarisStokAwal = {};

    if (baris.Pelacakan === 'Seri') {
        const pesan = PeriksaNomorSeri(baris.NomorSeri, aturan.MaksimalNomorSeriPerBaris);

        if (pesan !== null) {
            galat.NomorSeri = pesan;
        }
    } else if (!CekDesimalValid(baris.Jumlah) || AmbilTandaDesimal(baris.Jumlah) <= 0) {
        galat.Jumlah = 'Isi jumlah lebih dari 0.';
    } else if (!CekSkalaMaksimal(baris.Jumlah, SkalaJumlah)) {
        galat.Jumlah = 'Jumlah paling banyak 4 angka di belakang koma.';
    } else if (!baris.BolehDesimal && !CekDesimalBulat(baris.Jumlah)) {
        galat.Jumlah = `Satuan ${baris.SimbolSatuan} harus bilangan bulat.`;
    }

    if (baris.HppSatuan === '') {
        galat.HppSatuan = 'Isi harga modal per satuan (boleh 0).';
    } else if (!CekSkalaMaksimal(baris.HppSatuan, SkalaHpp) || AmbilTandaDesimal(baris.HppSatuan) < 0) {
        galat.HppSatuan = 'Harga modal paling banyak 6 angka di belakang koma.';
    }

    if (baris.Pelacakan === 'Batch') {
        const nomor = (baris.NomorBatch ?? '').trim();

        if (nomor === '') {
            galat.NomorBatch = 'Isi nomor batch.';
        } else if (nomor.length > PanjangMaksimalNomorBatch) {
            galat.NomorBatch = `Nomor batch paling panjang ${String(PanjangMaksimalNomorBatch)} karakter.`;
        }

        if (aturan.WajibKedaluwarsaBatch && !baris.TanggalKedaluwarsa) {
            galat.TanggalKedaluwarsa = 'Isi tanggal kedaluwarsa batch ini.';
        }
    }

    return galat;
}

/** Indeks baris yang menggandakan (produk, batch) baris sebelumnya (`BarisGanda`). */
export function CariBarisGanda(daftar: readonly BarisFormStokAwal[]): Set<number> {
    const terlihat = new Set<string>();
    const ganda = new Set<number>();

    daftar.forEach((baris, indeks) => {
        const kunci = `${baris.UuidProduk}|${baris.Pelacakan === 'Batch' ? (baris.NomorBatch ?? '').trim().toUpperCase() : ''}`;

        if (terlihat.has(kunci)) {
            ganda.add(indeks);
        }

        terlihat.add(kunci);
    });

    return ganda;
}

/** Tanggal dokumen: wajib dan tidak setelah hari ini di outlet (`TanggalDiMasaDepan`). Format YYYY-MM-DD. */
export function PeriksaTanggalStokAwal(tanggal: string, hariIni: string): string | null {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(tanggal)) {
        return 'Isi tanggal stok awal.';
    }

    return tanggal > hariIni ? 'Tanggal stok awal tidak boleh setelah hari ini.' : null;
}

/** Jumlah baris mutasi setelah posting: nomor seri dihitung satu per nomor (`BatasPostingLangsung`). */
export function HitungBarisMutasi(daftar: readonly Pick<BarisFormStokAwal, 'Pelacakan' | 'NomorSeri'>[]): number {
    return daftar.reduce((jumlah, baris) => jumlah + (baris.Pelacakan === 'Seri' ? baris.NomorSeri.length : 1), 0);
}

/** Baris form → isian `MasukanStokAwal.Baris` (field pelacakan yang tidak relevan dikosongkan). */
export function SusunMasukanBaris(baris: BarisFormStokAwal): MasukanStokAwal['Baris'][number] {
    const batch = baris.Pelacakan === 'Batch';
    const seri = baris.Pelacakan === 'Seri';

    return {
        UuidProduk: baris.UuidProduk,
        Jumlah: seri ? String(baris.NomorSeri.length) : baris.Jumlah,
        HppSatuan: baris.HppSatuan,
        NomorBatch: batch ? (baris.NomorBatch ?? '').trim() || null : null,
        TanggalKedaluwarsa: batch ? baris.TanggalKedaluwarsa || null : null,
        NomorSeri: seri ? baris.NomorSeri : [],
    };
}

/** True bila saldo di lokasi ini negatif (H-16: selisih HPP dicatat saat posting). */
export function CekSaldoMinus(saldo: string | null): boolean {
    return saldo !== null && CekDesimalValid(saldo) && BandingkanDesimal(saldo, '0') < 0;
}

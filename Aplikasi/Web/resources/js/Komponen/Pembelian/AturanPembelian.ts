import {
    BandingkanDesimal,
    BulatkanDesimal,
    CekDesimalBulat,
    CekDesimalValid,
    JumlahkanDesimal,
    KalikanDesimal,
    KurangiDesimal,
    SkalaJumlah,
    SkalaUang,
} from '@/Pustaka/HitungDesimal';
import type { BarisIsianBebas, ProdukPembelian } from '@/Tipe/Pembelian';

/**
 * Aturan & perkiraan baris pembelian di peramban (F-04 fase 1). Semua angka string desimal (CLAUDE.md #7); server
 * tetap sumber kebenaran (subtotal, PPN, alokasi ongkir dihitung ulang saat disimpan).
 */

let nomorKunci = 0;

/** Kunci React unik per baris (bukan data; tidak dikirim ke server). */
export function BuatKunciBaris(): string {
    nomorKunci += 1;

    return `baris-${String(nomorKunci)}`;
}

/** Baris isian baru dari produk terpilih; satuan beli bawaan (bila ada) terpilih. */
export function BuatBarisDariProduk(produk: ProdukPembelian): BarisIsianBebas {
    const bawaan = produk.Satuan.find((s) => s.DefaultBeli);

    return {
        Kunci: BuatKunciBaris(),
        UuidProduk: produk.Uuid,
        NamaProduk: produk.Nama,
        Sku: produk.Sku,
        Pelacakan: produk.Pelacakan,
        SimbolSatuan: produk.SimbolSatuan,
        BolehDesimal: produk.BolehDesimal,
        Satuan: produk.Satuan,
        UuidProdukSatuan: bawaan?.Uuid ?? null,
        Jumlah: '',
        Harga: '',
        Diskon: '',
        NomorBatch: '',
        TanggalKedaluwarsa: '',
        NomorSeri: [],
    };
}

/** Konversi satuan terpilih ke satuan dasar ("1" bila satuan dasar). */
export function AmbilKonversi(baris: Pick<BarisIsianBebas, 'Satuan' | 'UuidProdukSatuan'>): string {
    return baris.Satuan.find((s) => s.Uuid === baris.UuidProdukSatuan)?.Konversi ?? '1';
}

/** Simbol satuan terpilih. */
export function AmbilSimbol(baris: Pick<BarisIsianBebas, 'Satuan' | 'UuidProdukSatuan' | 'SimbolSatuan'>): string {
    return baris.Satuan.find((s) => s.Uuid === baris.UuidProdukSatuan)?.Simbol ?? baris.SimbolSatuan;
}

/** Jumlah dalam satuan dasar (jumlah × konversi) atau null bila jumlah belum valid. */
export function HitungJumlahDasar(jumlah: string, konversi: string): string | null {
    return CekDesimalValid(jumlah) ? KalikanDesimal(jumlah, konversi, SkalaJumlah) : null;
}

/** Subtotal baris = Jumlah × Harga (HalfUp 2 desimal) − Diskon; null bila isian belum valid. */
export function HitungSubtotal(jumlah: string, harga: string, diskon: string): string | null {
    if (!CekDesimalValid(jumlah) || !CekDesimalValid(harga)) {
        return null;
    }

    const bruto = KalikanDesimal(jumlah, harga, SkalaUang);

    return BulatkanDesimal(KurangiDesimal(bruto, diskon === '' ? '0' : diskon), SkalaUang);
}

/** Σ subtotal baris yang valid. */
export function HitungTotalBaris(baris: readonly Pick<BarisIsianBebas, 'Jumlah' | 'Harga' | 'Diskon'>[]): string {
    return BulatkanDesimal(
        JumlahkanDesimal(
            baris.map((b) => HitungSubtotal(b.Jumlah, b.Harga, b.Diskon)).filter((n): n is string => n !== null),
        ),
        SkalaUang,
    );
}

export type GalatBarisPembelian = Partial<Record<'Jumlah' | 'Harga' | 'Diskon' | 'NomorBatch' | 'NomorSeri', string>>;

/**
 * Pemeriksaan lokal satu baris: jumlah > 0 (bulat dalam satuan dasar bila satuannya tanpa desimal), harga wajib bila
 * `wajibHarga`, diskon ≤ bruto, batch wajib untuk produk batch, nomor seri = jumlah dasar untuk produk seri.
 */
export function PeriksaBaris(
    baris: Pick<
        BarisIsianBebas,
        | 'Jumlah'
        | 'Harga'
        | 'Diskon'
        | 'Pelacakan'
        | 'BolehDesimal'
        | 'SimbolSatuan'
        | 'NomorBatch'
        | 'NomorSeri'
        | 'Satuan'
        | 'UuidProdukSatuan'
    >,
    opsi: { wajibHarga: boolean; pelacakan: boolean },
): GalatBarisPembelian {
    const galat: GalatBarisPembelian = {};
    const konversi = AmbilKonversi(baris);

    if (!CekDesimalValid(baris.Jumlah) || BandingkanDesimal(baris.Jumlah, '0') <= 0) {
        galat.Jumlah = 'Isi jumlah lebih dari 0.';
    } else {
        const dasar = KalikanDesimal(baris.Jumlah, konversi, SkalaJumlah);

        if (!baris.BolehDesimal && !CekDesimalBulat(dasar)) {
            galat.Jumlah = `Jumlah harus bilangan bulat ${baris.SimbolSatuan}.`;
        } else if (
            opsi.pelacakan &&
            baris.Pelacakan === 'Seri' &&
            String(baris.NomorSeri.length) !== BulatkanDesimal(dasar, 0)
        ) {
            galat.NomorSeri = `Isi tepat ${BulatkanDesimal(dasar, 0)} nomor seri (satu per unit).`;
        }
    }

    if (opsi.wajibHarga && (!CekDesimalValid(baris.Harga) || baris.Harga === '')) {
        galat.Harga = 'Isi harga.';
    }

    if (baris.Diskon !== '' && CekDesimalValid(baris.Jumlah) && CekDesimalValid(baris.Harga)) {
        const bruto = KalikanDesimal(baris.Jumlah, baris.Harga, SkalaUang);

        if (BandingkanDesimal(baris.Diskon, bruto) > 0) {
            galat.Diskon = 'Diskon tidak boleh melebihi jumlah × harga.';
        }
    }

    if (opsi.pelacakan && baris.Pelacakan === 'Batch' && baris.NomorBatch.trim() === '') {
        galat.NomorBatch = 'Isi nomor batch.';
    }

    return galat;
}

/** Baris siap kirim ke server (key = nama kolom server). */
export type MasukanBarisPembelian = {
    UuidProduk: string;
    UuidProdukSatuan: string | null;
    Jumlah: string;
    Harga: string;
    Diskon: string;
    NomorBatch?: string | null;
    TanggalKedaluwarsa?: string | null;
    NomorSeri?: string[];
};

/** Baris siap kirim (key = nama kolom server). */
export function SusunMasukanBaris(baris: BarisIsianBebas, pelacakan: boolean): MasukanBarisPembelian {
    return {
        UuidProduk: baris.UuidProduk,
        UuidProdukSatuan: baris.UuidProdukSatuan,
        Jumlah: baris.Jumlah,
        Harga: baris.Harga === '' ? '0' : baris.Harga,
        Diskon: baris.Diskon === '' ? '0' : baris.Diskon,
        ...(pelacakan
            ? {
                  NomorBatch: baris.Pelacakan === 'Batch' ? baris.NomorBatch.trim() : null,
                  TanggalKedaluwarsa:
                      baris.Pelacakan === 'Batch' && baris.TanggalKedaluwarsa !== '' ? baris.TanggalKedaluwarsa : null,
                  NomorSeri: baris.Pelacakan === 'Seri' ? baris.NomorSeri : [],
              }
            : {}),
    };
}

/** Status sisa umur hutang → jenis label. */
export function AmbilJenisUmur(umur: string | null): 'sukses' | 'peringatan' | 'bahaya' | 'netral' {
    if (umur === null || umur === 'BelumJatuhTempo') {
        return 'netral';
    }

    return umur === 'Hari0Sampai30' ? 'peringatan' : 'bahaya';
}

/** URL cari produk pembelian (produk berstok + satuan beli). */
export function BuatUrlCariProdukPembelian(kata: string, uuidGudang: string | null): string {
    const parameter = new URLSearchParams({ kata, batas: '20' });

    if (uuidGudang) {
        parameter.set('gudang', uuidGudang);
    }

    return `/kelola/pembelian/produk/cari?${parameter.toString()}`;
}

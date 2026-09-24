import type { HasilTabel } from '@/Komponen/TabelData/Tipe';
/**
 * Props tiruan halaman persediaan F-05a untuk test Vitest, mengikuti kontrak `Tipe/Persediaan.ts` (DesainF05a E).
 * Termasuk data ekstrem §17.6.6: nama 60 karakter, nilai Rp 1.250.000.000, stok minus, dan ribuan baris.
 * Bukan kode produksi.
 */
import type {
    BarisDaftarStokAwal,
    BarisFormStokAwal,
    BarisKartuStok,
    BarisSaldoStok,
    HasilCariProdukStok,
    IzinPersediaan,
    KesiapanAkun,
    OpsiGudang,
    PropsDetailStokAwal,
} from '@/Tipe/Persediaan';
import type { DaftarBerhalaman } from '@/Tipe/Pengelola';

export const NamaPanjang = 'Es Kopi Susu Gula Aren Spesial Ukuran Jumbo Tanpa Es Batu XL';
export const NilaiEkstrem = '1250000000.00';

export const UuidDokumen = '01J9ZC5V7Q8R2T4W6Y8A0B2C4D';

export const IzinPenuh: IzinPersediaan = {
    Lihat: true,
    Kelola: true,
    PostingStokAwal: true,
    LihatJurnal: true,
    UbahPengaturan: true,
};
export const IzinLihat: IzinPersediaan = {
    Lihat: true,
    Kelola: false,
    PostingStokAwal: false,
    LihatJurnal: false,
    UbahPengaturan: false,
};
/** Staf gudang: boleh membuat draf, tidak boleh posting (DesainF05a B.5). */
export const IzinStafGudang: IzinPersediaan = { ...IzinLihat, Kelola: true };

export const AkunSiap: KesiapanAkun = { Siap: true, PeranBelumDipetakan: [] };
export const AkunBelumSiap: KesiapanAkun = {
    Siap: false,
    PeranBelumDipetakan: [{ Kunci: 'EkuitasSaldoAwal', Label: 'Ekuitas saldo awal' }],
};

export const GudangUtama: OpsiGudang = {
    Uuid: '01J9GDG0000000000000000001',
    Kode: 'GU',
    Nama: 'Gudang Utama',
    Jenis: 'Gudang',
    NamaOutlet: 'Kopi Nusantara Solo',
    Aktif: true,
};
export const GudangLama: OpsiGudang = {
    Uuid: '01J9GDG0000000000000000002',
    Kode: 'GL',
    Nama: 'Gudang Lama',
    Jenis: 'Gudang',
    NamaOutlet: null,
    Aktif: false,
};

/** Hasil `TabelData` (D-16) satu halaman. */
export function BuatHasilTabel<T>(data: T[], total = data.length, perHalaman = 25): HasilTabel<T> {
    return {
        Data: data,
        Meta: {
            Halaman: 1,
            PerHalaman: perHalaman,
            Total: total,
            JumlahHalaman: Math.max(1, Math.ceil(total / perHalaman)),
        },
    };
}

export function BuatHalaman<T>(data: T[], perubahan: Partial<DaftarBerhalaman<T>> = {}): DaftarBerhalaman<T> {
    return { Data: data, HalamanSaatIni: 1, HalamanTerakhir: 1, Total: data.length, ...perubahan };
}

function UuidUrut(awalan: string, nomor: number): string {
    return `${awalan}${String(nomor).padStart(26 - awalan.length, '0')}`;
}

export function BuatBarisDaftarStokAwal(
    nomor: number,
    perubahan: Partial<BarisDaftarStokAwal> = {},
): BarisDaftarStokAwal {
    return {
        Uuid: UuidUrut('01J9SA', nomor),
        Nomor: `SA/2026/09/${String(nomor).padStart(4, '0')}`,
        Tanggal: '2026-09-01',
        NamaGudang: 'Gudang Utama',
        NamaOutlet: 'Kopi Nusantara Solo',
        Status: 'Diposting',
        LabelStatus: 'Diposting',
        Sumber: 'Manual',
        JumlahBaris: 12,
        TotalNilai: '12345.68',
        DibuatOleh: 'Rina Wulandari',
        DiubahPada: '2026-09-01T03:00:00Z',
        ...perubahan,
    };
}

export function BuatHasilCari(
    perubahan: Partial<HasilCariProdukStok['Data'][number]> = {},
): HasilCariProdukStok['Data'][number] {
    return {
        Uuid: '01J9PRD0000000000000000001',
        Nama: 'Biji Kopi Arabika Gayo',
        Sku: 'KOPI-GAYO-1KG',
        Jenis: 'BahanBaku',
        Pelacakan: 'Tidak',
        SimbolSatuan: 'kg',
        BolehDesimal: true,
        SaldoDiGudang: '0.0000',
        HppRataRata: null,
        StokAwalSudahAda: false,
        ...perubahan,
    };
}

export function BuatBarisForm(perubahan: Partial<BarisFormStokAwal> = {}): BarisFormStokAwal {
    return {
        UuidProduk: '01J9PRD0000000000000000001',
        NamaProduk: 'Biji Kopi Arabika Gayo',
        Sku: 'KOPI-GAYO-1KG',
        SimbolSatuan: 'kg',
        BolehDesimal: true,
        Pelacakan: 'Tidak',
        SaldoDiGudang: '0.0000',
        Jumlah: '10',
        HppSatuan: '1234.5678',
        NomorBatch: null,
        TanggalKedaluwarsa: null,
        NomorSeri: [],
        ...perubahan,
    };
}

export function BuatPropsDetail(perubahan: Partial<PropsDetailStokAwal> = {}): PropsDetailStokAwal {
    return {
        StokAwal: {
            Uuid: UuidDokumen,
            Nomor: null,
            Status: 'Draf',
            LabelStatus: 'Draf',
            Sumber: 'Manual',
            UuidImpor: null,
            NamaGudang: 'Gudang Utama',
            NamaOutlet: 'Kopi Nusantara Solo',
            Tanggal: '2026-09-01',
            Catatan: 'Hitung fisik awal bulan',
            JumlahBaris: 3,
            TotalNilai: '28345.68',
            PesanGalat: null,
            DibuatOleh: 'Rina Wulandari',
            DibuatPada: '2026-09-01T03:00:00Z',
            DipostingOleh: null,
            DipostingPada: null,
            DibatalkanOleh: null,
            DibatalkanPada: null,
            AlasanBatal: null,
            VersiDiubahPada: '2026-09-01T03:00:00.000000Z',
        },
        Baris: [
            {
                Urutan: 1,
                UuidProduk: '01J9PRD0000000000000000001',
                NamaProduk: 'Biji Kopi Arabika Gayo',
                Sku: 'KOPI-GAYO-1KG',
                SimbolSatuan: 'kg',
                Pelacakan: 'Tidak',
                Jumlah: '10.0000',
                HppSatuan: '1234.567800',
                Nilai: '12345.68',
                NomorBatch: null,
                TanggalKedaluwarsa: null,
                NomorSeri: [],
            },
            {
                Urutan: 2,
                UuidProduk: '01J9PRD0000000000000000002',
                NamaProduk: 'Susu UHT Full Cream 1 L',
                Sku: 'SUSU-UHT-1L',
                SimbolSatuan: 'pcs',
                Pelacakan: 'Batch',
                Jumlah: '12.0000',
                HppSatuan: '1000.000000',
                Nilai: '12000.00',
                NomorBatch: 'B-2026-09',
                TanggalKedaluwarsa: '2027-01-31',
                NomorSeri: [],
            },
            {
                Urutan: 3,
                UuidProduk: '01J9PRD0000000000000000003',
                NamaProduk: 'Mesin Espresso Mini',
                Sku: 'MSN-ESP-01',
                SimbolSatuan: 'unit',
                Pelacakan: 'Seri',
                Jumlah: '3.0000',
                HppSatuan: '1333.333333',
                Nilai: '4000.00',
                NomorBatch: null,
                TanggalKedaluwarsa: null,
                NomorSeri: ['SN-001', 'SN-002', 'SN-003'],
            },
        ],
        Jurnal: [],
        Riwayat: [
            {
                StatusDari: null,
                StatusKe: 'Draf',
                LabelStatusKe: 'Draf',
                Oleh: 'Rina Wulandari',
                Pada: '2026-09-01T03:00:00Z',
                Alasan: null,
            },
        ],
        Tindakan: { Ubah: true, Buang: true, Posting: true, Batalkan: false },
        Izin: IzinPenuh,
        KesiapanAkun: AkunSiap,
        BatasPostingLangsung: 300,
        ...perubahan,
    };
}

export function BuatBarisSaldo(nomor: number, perubahan: Partial<BarisSaldoStok> = {}): BarisSaldoStok {
    return {
        UuidProduk: UuidUrut('01J9PRD', nomor),
        NamaProduk: `Produk ${String(nomor)}`,
        Sku: `PRD-${String(nomor).padStart(6, '0')}`,
        SimbolSatuan: 'pcs',
        Pelacakan: 'Tidak',
        UuidGudang: GudangUtama.Uuid,
        NamaGudang: 'Gudang Utama',
        NamaOutlet: 'Kopi Nusantara Solo',
        GudangAktif: true,
        JumlahTersedia: '10.0000',
        HppRataRata: '1234.568000',
        NilaiPersediaan: '12345.68',
        DiubahPada: '2026-09-01T03:00:00Z',
        Batch: [],
        JumlahNomorSeri: null,
        TautanKartuStok: `/kelola/persediaan/kartu-stok?produk=${UuidUrut('01J9PRD', nomor)}&gudang=${GudangUtama.Uuid}`,
        ...perubahan,
    };
}

export function BuatBarisKartu(perubahan: Partial<BarisKartuStok> = {}): BarisKartuStok {
    return {
        TanggalBisnis: '2026-09-01',
        DicatatPada: '2026-09-01T03:00:00Z',
        JenisMutasi: 'StokAwal',
        LabelJenisMutasi: 'Stok awal',
        NomorReferensi: 'SA/2026/09/0001',
        TautanReferensi: `/kelola/persediaan/stok-awal/${UuidDokumen}`,
        Masuk: '10.0000',
        Keluar: null,
        HppSatuan: '1234.567800',
        TotalHpp: '12345.68',
        SaldoSetelah: '10.0000',
        NilaiSetelah: '12345.68',
        NomorBatch: null,
        NomorSeri: null,
        DicatatOleh: 'Rina Wulandari',
        ...perubahan,
    };
}

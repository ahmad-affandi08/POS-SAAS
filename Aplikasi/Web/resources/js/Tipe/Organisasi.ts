import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';

/** Kunci izin tenant yang dipakai layar back-office (enum IzinTenant di Backend, format D-06). */
export const IzinTenant = {
    OutletLihat: 'outlet.lihat',
    OutletKelola: 'outlet.kelola',
    PenggunaLihat: 'pengguna.lihat',
    PenggunaUndang: 'pengguna.undang',
    PenggunaUbah: 'pengguna.ubah',
    PenggunaNonaktifkan: 'pengguna.nonaktifkan',
    PeranKelola: 'peran.kelola',
    AuditLihat: 'audit.lihat',
    // F-02b perangkat POS & PIN kasir.
    PerangkatLihat: 'perangkat.lihat',
    PerangkatKelola: 'perangkat.kelola',
    PenggunaPinAtur: 'pengguna.pin.atur',
    LanggananKelola: 'langganan.kelola',
    BantuanTiketLihat: 'bantuan.tiket.lihat',
    BantuanTiketKelola: 'bantuan.tiket.kelola',
    // F-01 panduan awal (profil usaha, template sektor, pajak, produk awal, metode pembayaran).
    PanduanAwalKelola: 'panduan-awal.kelola',
    // F-03 master produk, harga & pajak (batas stok = persediaan.kelola, kelompok pajak = akuntansi.kelola).
    ProdukLihat: 'produk.lihat',
    ProdukKelola: 'produk.kelola',
    ProdukHargaUbah: 'produk.harga.ubah',
    PersediaanKelola: 'persediaan.kelola',
    AkuntansiKelola: 'akuntansi.kelola',
    // F-05a stok awal & buku stok (posting stok awal menulis jurnal; jurnal dilihat dengan laporan.keuangan.lihat).
    PersediaanLihat: 'persediaan.lihat',
    PersediaanStokAwalPosting: 'persediaan.stok-awal.posting',
    LaporanKeuanganLihat: 'laporan.keuangan.lihat',
    // F-06 shift & kas: daftar shift memakai laporan.penjualan.lihat, persetujuan kas keluar di POS.
    LaporanPenjualanLihat: 'laporan.penjualan.lihat',
    KasKeluarSetujui: 'kas.keluar.setujui',
} as const;

export type KunciIzinTenant = (typeof IzinTenant)[keyof typeof IzinTenant];

/** Hanya untuk menampilkan/menyembunyikan menu & tombol. Server tetap penentu (WajibIzinTenant). */
export function PunyaIzinTenant(akses: PropsBersamaAplikasi['Akses'], izin: KunciIzinTenant): boolean {
    return akses !== null && (akses.Pemilik || akses.Izin.includes(izin));
}

export type Pilihan = { Nilai: string; Label: string };

export type Batas = { Batas: number | null; Terpakai: number };

export type Kota = { Kode: string; Nama: string; NamaProvinsi: string | null; ZonaWaktu: string };

export type StatusOrganisasi = 'Aktif' | 'Diarsipkan';

/** Teks pemakaian batas paket, misal "2 dari 3 outlet" atau "4 pengguna (tanpa batas)". */
export function FormatBatas(batas: Batas, objek: string): string {
    return batas.Batas === null
        ? `${String(batas.Terpakai)} ${objek} (tanpa batas)`
        : `${String(batas.Terpakai)} dari ${String(batas.Batas)} ${objek}`;
}

export function CekBatasPenuh(batas: Batas): boolean {
    return batas.Batas !== null && batas.Terpakai >= batas.Batas;
}

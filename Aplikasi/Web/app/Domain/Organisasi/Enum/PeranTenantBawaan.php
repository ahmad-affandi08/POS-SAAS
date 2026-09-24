<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Enum;

/**
 * Peran bawaan yang dibuat untuk setiap tenant (PRD §19.1). Nilai = kolom `Peran.Kode`. Peran khusus sektor
 * (Pelayan, Dapur/Barista, Apoteker, Salesman) ditambahkan template sektor di F-01.
 *
 * Peran bawaan diselaraskan sistem (tidak diubah tenant); tenant menyesuaikan lewat peran kustom.
 * `Pemilik` selalu lolos pemeriksaan izin, apa pun isi tabel `PeranIzin`.
 */
enum PeranTenantBawaan: string
{
    case Pemilik = 'Pemilik';
    case Admin = 'Admin';
    case ManajerOutlet = 'ManajerOutlet';
    case Supervisor = 'Supervisor';
    case Kasir = 'Kasir';
    case StafGudang = 'StafGudang';
    case StafPembelian = 'StafPembelian';
    case Akuntan = 'Akuntan';

    public function AmbilNama(): string
    {
        return match ($this) {
            self::Pemilik => 'Pemilik (Owner)',
            self::Admin => 'Admin',
            self::ManajerOutlet => 'Manajer Outlet',
            self::Supervisor => 'Supervisor',
            self::Kasir => 'Kasir',
            self::StafGudang => 'Staf Gudang',
            self::StafPembelian => 'Staf Pembelian',
            self::Akuntan => 'Akuntan',
        };
    }

    public function AmbilKeterangan(): string
    {
        return match ($this) {
            self::Pemilik => 'Semua akses di semua outlet, termasuk langganan.',
            self::Admin => 'Semua akses kecuali langganan & kepemilikan.',
            self::ManajerOutlet => 'Operasional outlet yang ditugaskan: produk, stok, persetujuan, laporan outlet.',
            self::Supervisor => 'Persetujuan di POS (void, diskon, kas keluar), buka ulang shift.',
            self::Kasir => 'POS: jual, bayar, simpan pesanan, cetak, buka/tutup shift sendiri.',
            self::StafGudang => 'Penerimaan, transfer, opname, penyesuaian stok (butuh persetujuan).',
            self::StafPembelian => 'Pemasok & pesanan pembelian.',
            self::Akuntan => 'Keuangan, jurnal, pajak, tutup buku; membaca semua laporan.',
        };
    }

    /** Peran yang wajar diberi akses ke semua outlet saat diundang (bisa diubah per pengguna). */
    public function CekSemuaOutletBawaan(): bool
    {
        return in_array($this, [self::Pemilik, self::Admin, self::Akuntan], true);
    }

    /**
     * @return list<IzinTenant>
     */
    public function AmbilIzin(): array
    {
        return match ($this) {
            self::Pemilik => IzinTenant::cases(),
            self::Admin => array_values(array_filter(IzinTenant::cases(), fn (IzinTenant $izin) => ! $izin->CekKhususPemilik())),
            self::ManajerOutlet => [
                IzinTenant::OutletLihat,
                IzinTenant::PenggunaLihat,
                IzinTenant::ProdukLihat,
                IzinTenant::ProdukKelola,
                IzinTenant::PersediaanLihat,
                IzinTenant::PersediaanKelola,
                IzinTenant::PersediaanPenyesuaianSetujui,
                IzinTenant::PersediaanStokAwalPosting,
                IzinTenant::PenjualanBuat,
                IzinTenant::PenjualanVoid,
                IzinTenant::PenjualanDiskonManual,
                IzinTenant::KasKeluarSetujui,
                IzinTenant::PenjualanDiskonSetujui,
                IzinTenant::LaporanPenjualanLihat,
                // F-02b: perangkat & PIN kasir di outlet yang ditugaskan.
                IzinTenant::PerangkatLihat,
                IzinTenant::PerangkatKelola,
                IzinTenant::PenggunaPinAtur,
                IzinTenant::BantuanTiketLihat,
                IzinTenant::BantuanTiketKelola,
            ],
            self::Supervisor => [
                IzinTenant::ProdukLihat,
                IzinTenant::PenjualanBuat,
                IzinTenant::PenjualanVoid,
                IzinTenant::PenjualanDiskonManual,
                // F-06 BR-06.4: PIN supervisor untuk kas keluar di atas batas.
                IzinTenant::KasKeluarSetujui,
                // F-07b BR-07.3: PIN supervisor untuk diskon manual di atas batas kasir.
                IzinTenant::PenjualanDiskonSetujui,
            ],
            self::Kasir => [IzinTenant::ProdukLihat, IzinTenant::PenjualanBuat],
            self::StafGudang => [IzinTenant::ProdukLihat, IzinTenant::PersediaanLihat, IzinTenant::PersediaanKelola],
            self::StafPembelian => [IzinTenant::ProdukLihat, IzinTenant::PersediaanLihat, IzinTenant::PembelianKelola],
            self::Akuntan => [
                IzinTenant::OutletLihat,
                IzinTenant::ProdukLihat,
                IzinTenant::PersediaanLihat,
                // F-05a: Akuntan memposting stok awal karena menulis jurnal ekuitas saldo awal.
                IzinTenant::PersediaanStokAwalPosting,
                IzinTenant::LaporanPenjualanLihat,
                IzinTenant::LaporanKeuanganLihat,
                IzinTenant::AkuntansiKelola,
            ],
        };
    }
}

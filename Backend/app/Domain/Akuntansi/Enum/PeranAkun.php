<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Enum;

/**
 * Kunci `PemetaanAkun` (PRD §11.1, §11.3): peran akun yang dipakai `AturanPosting` per jenis peristiwa.
 * Template sektor wajib memetakan semua peran ke akun COA-nya dengan tipe yang sesuai (BR-P03.3).
 */
enum PeranAkun: string
{
    case KasOutlet = 'KasOutlet';
    case KasBrankas = 'KasBrankas';
    case Bank = 'Bank';
    case PiutangSettlement = 'PiutangSettlement';
    case PiutangUsaha = 'PiutangUsaha';
    case PiutangKaryawan = 'PiutangKaryawan';
    case PersediaanBarangDagang = 'PersediaanBarangDagang';
    case PersediaanBahanBaku = 'PersediaanBahanBaku';
    case PersediaanDalamPerjalanan = 'PersediaanDalamPerjalanan';
    case PpnMasukan = 'PpnMasukan';
    case HutangUsaha = 'HutangUsaha';
    case HutangBelumDifakturkan = 'HutangBelumDifakturkan';
    case HutangKonsinyasi = 'HutangKonsinyasi';
    case PpnKeluaran = 'PpnKeluaran';
    case HutangPbjt = 'HutangPbjt';
    case UangMukaPelanggan = 'UangMukaPelanggan';
    case DepositPelanggan = 'DepositPelanggan';
    case PendapatanDiterimaDimuka = 'PendapatanDiterimaDimuka';
    case EkuitasSaldoAwal = 'EkuitasSaldoAwal';
    case LabaDitahan = 'LabaDitahan';
    case Penjualan = 'Penjualan';
    case DiskonPenjualan = 'DiskonPenjualan';
    case ReturPenjualan = 'ReturPenjualan';
    case PendapatanJasa = 'PendapatanJasa';
    case PendapatanServiceCharge = 'PendapatanServiceCharge';
    case PendapatanLain = 'PendapatanLain';
    case Hpp = 'Hpp';
    case SelisihHpp = 'SelisihHpp';
    case Waste = 'Waste';
    case BebanBiayaPembayaran = 'BebanBiayaPembayaran';
    case BebanSelisihKas = 'BebanSelisihKas';

    public function AmbilTipeAkun(): TipeAkun
    {
        return match ($this) {
            self::KasOutlet, self::KasBrankas, self::Bank, self::PiutangSettlement, self::PiutangUsaha,
            self::PiutangKaryawan, self::PersediaanBarangDagang, self::PersediaanBahanBaku,
            self::PersediaanDalamPerjalanan, self::PpnMasukan => TipeAkun::Aset,
            self::HutangUsaha, self::HutangBelumDifakturkan, self::HutangKonsinyasi, self::PpnKeluaran,
            self::HutangPbjt, self::UangMukaPelanggan, self::DepositPelanggan,
            self::PendapatanDiterimaDimuka => TipeAkun::Kewajiban,
            self::EkuitasSaldoAwal, self::LabaDitahan => TipeAkun::Ekuitas,
            self::Penjualan, self::DiskonPenjualan, self::ReturPenjualan, self::PendapatanJasa,
            self::PendapatanServiceCharge, self::PendapatanLain => TipeAkun::Pendapatan,
            self::Hpp, self::SelisihHpp, self::Waste => TipeAkun::Hpp,
            self::BebanBiayaPembayaran, self::BebanSelisihKas => TipeAkun::Beban,
        };
    }

    /** Diskon & retur penjualan adalah akun kontra pendapatan (§11.2). */
    public function CekWajibKontra(): bool
    {
        return $this === self::DiskonPenjualan || $this === self::ReturPenjualan;
    }

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::KasOutlet => 'Kas outlet',
            self::KasBrankas => 'Kas brankas',
            self::Bank => 'Bank',
            self::PiutangSettlement => 'Piutang settlement (QRIS/EDC/gateway/ojol)',
            self::PiutangUsaha => 'Piutang usaha',
            self::PiutangKaryawan => 'Piutang karyawan (kasbon)',
            self::PersediaanBarangDagang => 'Persediaan barang dagang',
            self::PersediaanBahanBaku => 'Persediaan bahan baku',
            self::PersediaanDalamPerjalanan => 'Persediaan dalam perjalanan',
            self::PpnMasukan => 'PPN masukan',
            self::HutangUsaha => 'Hutang usaha',
            self::HutangBelumDifakturkan => 'Hutang belum difakturkan (GRNI)',
            self::HutangKonsinyasi => 'Hutang konsinyasi',
            self::PpnKeluaran => 'PPN keluaran',
            self::HutangPbjt => 'Hutang PB1/PBJT',
            self::UangMukaPelanggan => 'Uang muka pelanggan (DP)',
            self::DepositPelanggan => 'Deposit pelanggan / gift card',
            self::PendapatanDiterimaDimuka => 'Pendapatan diterima dimuka',
            self::EkuitasSaldoAwal => 'Ekuitas saldo awal',
            self::LabaDitahan => 'Laba ditahan',
            self::Penjualan => 'Penjualan',
            self::DiskonPenjualan => 'Diskon penjualan',
            self::ReturPenjualan => 'Retur penjualan',
            self::PendapatanJasa => 'Pendapatan jasa',
            self::PendapatanServiceCharge => 'Pendapatan service charge',
            self::PendapatanLain => 'Pendapatan lain (selisih kas lebih, pembulatan)',
            self::Hpp => 'Harga pokok penjualan',
            self::SelisihHpp => 'Selisih HPP / penyesuaian persediaan',
            self::Waste => 'Waste / barang rusak',
            self::BebanBiayaPembayaran => 'Beban biaya pembayaran (MDR, komisi ojol)',
            self::BebanSelisihKas => 'Beban selisih kas',
        };
    }
}

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
    case PiutangPencairan = 'PiutangPencairan';
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
    case PendapatanBiayaLayanan = 'PendapatanBiayaLayanan';
    case PendapatanLain = 'PendapatanLain';
    case Hpp = 'Hpp';
    case SelisihHpp = 'SelisihHpp';
    case SusutPersediaan = 'SusutPersediaan';
    case BebanBiayaPembayaran = 'BebanBiayaPembayaran';
    case BebanSelisihKas = 'BebanSelisihKas';

    /**
     * Kunci lama sebelum istilah kamus §13.7.1 ditetapkan (DesainF01 H1). Versi template yang sudah terbit tidak boleh
     * diubah (BR-P03.4), jadi kunci lama di dalamnya tetap dibaca lewat alias ini, bukan ditulis ulang.
     */
    public const KUNCI_LAMA = [
        'PiutangSettlement' => 'PiutangPencairan',
        'Waste' => 'SusutPersediaan',
    ];

    /** Peran dari kunci `PemetaanAkun`, termasuk kunci lama. Null bila kunci tidak dikenal. */
    public static function DariKunci(string $kunci): ?self
    {
        return self::tryFrom(self::KUNCI_LAMA[$kunci] ?? $kunci);
    }

    /**
     * Pemetaan dengan kunci peran terbaru. Kunci lama diganti kunci barunya; bila keduanya ada, kunci baru menang.
     * Kunci yang tidak dikenal dibiarkan apa adanya agar tetap dilaporkan validator.
     *
     * @param  array<array-key, mixed>  $pemetaan
     * @return array<array-key, mixed>
     */
    public static function NormalisasiPemetaan(array $pemetaan): array
    {
        $hasil = [];

        foreach ($pemetaan as $kunci => $kode) {
            $peran = self::DariKunci((string) $kunci);
            $kunciBaru = $peran === null ? $kunci : $peran->value;

            if ($peran !== null && $kunciBaru !== $kunci && array_key_exists($kunciBaru, $pemetaan)) {
                continue;
            }

            $hasil[$kunciBaru] = $kode;
        }

        return $hasil;
    }

    public function AmbilTipeAkun(): TipeAkun
    {
        return match ($this) {
            self::KasOutlet, self::KasBrankas, self::Bank, self::PiutangPencairan, self::PiutangUsaha,
            self::PiutangKaryawan, self::PersediaanBarangDagang, self::PersediaanBahanBaku,
            self::PersediaanDalamPerjalanan, self::PpnMasukan => TipeAkun::Aset,
            self::HutangUsaha, self::HutangBelumDifakturkan, self::HutangKonsinyasi, self::PpnKeluaran,
            self::HutangPbjt, self::UangMukaPelanggan, self::DepositPelanggan,
            self::PendapatanDiterimaDimuka => TipeAkun::Kewajiban,
            self::EkuitasSaldoAwal, self::LabaDitahan => TipeAkun::Ekuitas,
            self::Penjualan, self::DiskonPenjualan, self::ReturPenjualan, self::PendapatanJasa,
            self::PendapatanBiayaLayanan, self::PendapatanLain => TipeAkun::Pendapatan,
            self::Hpp, self::SelisihHpp, self::SusutPersediaan => TipeAkun::Hpp,
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
            self::PiutangPencairan => 'Piutang pencairan (QRIS/EDC/gateway/ojol)',
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
            self::PendapatanBiayaLayanan => 'Pendapatan biaya layanan',
            self::PendapatanLain => 'Pendapatan lain (selisih kas lebih, pembulatan)',
            self::Hpp => 'Harga pokok penjualan',
            self::SelisihHpp => 'Selisih HPP / penyesuaian persediaan',
            self::SusutPersediaan => 'Susut & barang rusak',
            self::BebanBiayaPembayaran => 'Beban biaya pembayaran (MDR, komisi ojol)',
            self::BebanSelisihKas => 'Beban selisih kas',
        };
    }
}

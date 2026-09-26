<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Layanan;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Pelanggan\Enum\JenisMutasiDeposit;
use App\Domain\Pelanggan\Enum\SumberMutasiDeposit;
use Carbon\CarbonImmutable;

/**
 * Layanan publik domain Pelanggan untuk domain Penjualan (F-16d bagian 1): mutasi deposit dicatat di transaksi DB yang
 * sama dengan dokumen sumbernya (isi deposit, penjualan, void, retur), idempoten per dokumen.
 * - Isi (+) & batal isi (−, hanya bila saldo cukup; diperiksa pemanggil lewat `PeriksaBisaBatalIsi`).
 * - Pemakaian (−) saat penjualan dibayar deposit diterima: penjualan sudah terjadi di kasir, jadi saldo tetap dipotong
 *   walau kurang (misal dua perangkat memakai saldo yang sama); kekurangannya dikembalikan sebagai alasan tinjauan.
 * - Void mengembalikan pemakaian (`BatalPemakaian` +); retur boleh direfund ke deposit (`Refund` +).
 */
final class PencatatDepositPenjualan
{
    public function __construct(private readonly BukuDeposit $buku) {}

    public function CatatIsi(int $idPelanggan, int $idIsi, string $nomor, Uang $jumlah, CarbonImmutable $tanggal, int $idPengguna): void
    {
        $this->buku->Catat($idPelanggan, $jumlah, JenisMutasiDeposit::Isi, SumberMutasiDeposit::IsiDeposit, $idIsi, $nomor, $tanggal, idPengguna: $idPengguna);
    }

    /** Pesan galat bila saldo pelanggan (dikunci) kurang dari jumlah isi yang akan dibatalkan; null = boleh. */
    public function PeriksaBisaBatalIsi(int $idPelanggan, Uang $jumlah): ?string
    {
        $saldo = $this->buku->AmbilSaldoTerkunci($idPelanggan);

        return $saldo->Bandingkan($jumlah) < 0
            ? "Saldo deposit pelanggan tinggal {$saldo->FormatRupiah()}, kurang dari {$jumlah->FormatRupiah()} yang akan dibatalkan."
            : null;
    }

    public function BatalkanIsi(int $idPelanggan, int $idIsi, string $nomor, Uang $jumlah, CarbonImmutable $tanggal, int $idPengguna, string $alasan): void
    {
        $this->buku->Catat($idPelanggan, Uang::Nol()->Kurangi($jumlah), JenisMutasiDeposit::BatalIsi, SumberMutasiDeposit::IsiDeposit, $idIsi, $nomor, $tanggal, $alasan, $idPengguna);
    }

    /**
     * Potong deposit untuk penjualan. Hasil: daftar masalah untuk tinjauan (kosong = saldo cukup).
     *
     * @return list<string>
     */
    public function CatatPemakaian(int $idPelanggan, int $idPenjualan, string $nomor, Uang $jumlah, CarbonImmutable $tanggal, int $idPengguna): array
    {
        if ($this->buku->CariMutasi(JenisMutasiDeposit::Pemakaian, SumberMutasiDeposit::Penjualan, $idPenjualan) !== null) {
            return [];
        }

        $saldo = $this->buku->AmbilSaldoTerkunci($idPelanggan);
        $this->buku->Catat($idPelanggan, Uang::Nol()->Kurangi($jumlah), JenisMutasiDeposit::Pemakaian, SumberMutasiDeposit::Penjualan, $idPenjualan, $nomor, $tanggal, idPengguna: $idPengguna);

        return $saldo->Bandingkan($jumlah) < 0
            ? ["saldo deposit {$saldo->FormatRupiah()} kurang dari pembayaran {$jumlah->FormatRupiah()}, saldo menjadi minus"]
            : [];
    }

    /** Void: pemakaian deposit penjualan itu dikembalikan (idempoten). Hasil: jumlah yang dikembalikan. */
    public function BatalkanPemakaian(int $idPenjualan, string $nomor, CarbonImmutable $tanggal, int $idPengguna): Uang
    {
        $pakai = $this->buku->CariMutasi(JenisMutasiDeposit::Pemakaian, SumberMutasiDeposit::Penjualan, $idPenjualan);

        if ($pakai === null) {
            return Uang::Nol();
        }

        $kembali = Uang::Nol()->Kurangi(Uang::Dari($pakai->Jumlah));
        $this->buku->Catat($pakai->IdPelanggan, $kembali, JenisMutasiDeposit::BatalPemakaian, SumberMutasiDeposit::Penjualan, $idPenjualan, $nomor, $tanggal, 'Void penjualan', $idPengguna);

        return $kembali;
    }

    public function CatatRefund(int $idPelanggan, int $idRetur, string $nomor, Uang $jumlah, CarbonImmutable $tanggal, int $idPengguna): void
    {
        $this->buku->Catat($idPelanggan, $jumlah, JenisMutasiDeposit::Refund, SumberMutasiDeposit::ReturPenjualan, $idRetur, $nomor, $tanggal, idPengguna: $idPengguna);
    }

    public function AmbilSaldo(int $idPelanggan): Uang
    {
        return $this->buku->AmbilSaldo($idPelanggan);
    }
}

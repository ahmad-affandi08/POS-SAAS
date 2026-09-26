<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Layanan;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Pelanggan\Enum\JenisMutasiDeposit;
use App\Domain\Pelanggan\Enum\SumberMutasiDeposit;
use App\Domain\Pelanggan\Model\MutasiDeposit;
use App\Domain\Pelanggan\Model\Pelanggan;
use Carbon\CarbonImmutable;

/**
 * Buku deposit pelanggan (F-16d bagian 1, CRM-04). Dipanggil di dalam transaksi DB pemanggil.
 * - `Catat`: satu baris bertanda; baris pelanggan dikunci (`FOR UPDATE`) sehingga `SaldoSetelah` dan cache
 *   `Pelanggan.SaldoDeposit` berurutan walau dua perangkat mengirim bersamaan. Saldo boleh minus (penjualan offline
 *   yang sudah terjadi); pemanggil yang melarang minus memeriksa `AmbilSaldoTerkunci` lebih dulu.
 * - Idempoten per (Jenis, JenisSumber, IdSumber): pemanggilan ulang mengembalikan baris yang sudah ada.
 */
final class BukuDeposit
{
    public function Catat(
        int $idPelanggan,
        Uang $jumlah,
        JenisMutasiDeposit $jenis,
        SumberMutasiDeposit $sumber,
        ?int $idSumber,
        ?string $nomorSumber,
        CarbonImmutable $tanggal,
        ?string $keterangan = null,
        ?int $idPengguna = null,
        ?int $idAkunKasBank = null,
    ): MutasiDeposit {
        $ada = $this->CariAda($jenis, $sumber, $idSumber);

        if ($ada !== null) {
            return $ada;
        }

        $pelanggan = Pelanggan::query()->whereKey($idPelanggan)->lockForUpdate()->firstOrFail();
        $saldo = Uang::Dari($pelanggan->SaldoDeposit)->Tambah($jumlah);
        $pelanggan->SaldoDeposit = $saldo->KeString();
        $pelanggan->save();

        return MutasiDeposit::query()->create([
            'IdPelanggan' => $idPelanggan,
            'Jenis' => $jenis,
            'Jumlah' => $jumlah->KeString(),
            'SaldoSetelah' => $saldo->KeString(),
            'JenisSumber' => $sumber,
            'IdSumber' => $idSumber,
            'NomorSumber' => $nomorSumber === null ? null : mb_substr($nomorSumber, 0, 80),
            'Tanggal' => $tanggal->toDateString(),
            'IdAkunKasBank' => $idAkunKasBank,
            'Keterangan' => $keterangan === null ? null : mb_substr($keterangan, 0, 255),
            'IdPengguna' => $idPengguna,
        ]);
    }

    /** Saldo terkini dengan mengunci baris pelanggan (untuk pemeriksaan "saldo cukup" di transaksi pemanggil). */
    public function AmbilSaldoTerkunci(int $idPelanggan): Uang
    {
        return Uang::Dari(Pelanggan::query()->whereKey($idPelanggan)->lockForUpdate()->firstOrFail()->SaldoDeposit);
    }

    public function AmbilSaldo(int $idPelanggan): Uang
    {
        return Uang::Dari((string) (Pelanggan::query()->whereKey($idPelanggan)->value('SaldoDeposit') ?? '0'));
    }

    public function CariMutasi(JenisMutasiDeposit $jenis, SumberMutasiDeposit $sumber, int $idSumber): ?MutasiDeposit
    {
        return $this->CariAda($jenis, $sumber, $idSumber);
    }

    private function CariAda(JenisMutasiDeposit $jenis, SumberMutasiDeposit $sumber, ?int $idSumber): ?MutasiDeposit
    {
        return $idSumber === null ? null : MutasiDeposit::query()
            ->where('Jenis', $jenis->value)
            ->where('JenisSumber', $sumber->value)
            ->where('IdSumber', $idSumber)
            ->first();
    }
}

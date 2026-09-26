<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Layanan;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Pelanggan\Enum\JenisMutasiSesi;
use App\Domain\Pelanggan\Enum\StatusSaldoSesi;
use App\Domain\Pelanggan\Enum\SumberMutasiSesi;
use App\Domain\Pelanggan\Model\MutasiSesi;
use App\Domain\Pelanggan\Model\SaldoSesi;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;

/**
 * Buku sesi paket (F-16d bagian 2, CRM-04). Dipanggil di dalam transaksi DB pemanggil dengan baris `SaldoSesi` yang
 * sudah dikunci (`Kunci`). Setiap perubahan sisa sesi & nilai tercatat sebagai `MutasiSesi` bertanda (append-only),
 * idempoten per (Jenis, JenisSumber, IdSumber, IdSaldoSesi). Invarian: `SisaSesi` = Σ `JumlahSesi` dan `NilaiTersisa`
 * = Σ `Nilai` mutasinya; Σ `NilaiTersisa` saldo = saldo akun Pendapatan Diterima Dimuka dari paket sesi.
 */
final class BukuSesi
{
    public function Kunci(int $idSaldoSesi): ?SaldoSesi
    {
        return SaldoSesi::query()->whereKey($idSaldoSesi)->lockForUpdate()->first();
    }

    /**
     * Nilai yang diakui untuk `$jumlah` sesi: proporsional `NilaiTersisa × jumlah ÷ SisaSesi`; sesi terakhir mengakui
     * seluruh sisa nilai sehingga tidak ada selisih pembulatan.
     */
    public function HitungNilaiPakai(SaldoSesi $saldo, int $jumlah): Uang
    {
        $tersisa = Uang::Dari($saldo->NilaiTersisa);

        if ($saldo->SisaSesi <= 0 || $jumlah <= 0) {
            return Uang::Nol();
        }

        return $jumlah >= $saldo->SisaSesi ? $tersisa : Uang::Dari(BigDecimal::of($saldo->NilaiTersisa)->multipliedBy($jumlah)->dividedBy($saldo->SisaSesi, 2, RoundingMode::HalfUp));
    }

    /**
     * Catat satu mutasi dan perbarui saldo. `$sesi` & `$nilai` bertanda (keluar negatif). Status saldo mengikuti jenis:
     * Pakai sampai 0 → Habis; Hangus → Hangus; Batal/Refund → Dibatalkan; BatalPakai → Aktif lagi.
     */
    public function Catat(
        SaldoSesi $saldo,
        JenisMutasiSesi $jenis,
        int $sesi,
        Uang $nilai,
        SumberMutasiSesi $sumber,
        ?int $idSumber,
        ?string $nomorSumber,
        CarbonImmutable $tanggal,
        ?string $keterangan = null,
        ?int $idPengguna = null,
        ?int $idAkunKasBank = null,
    ): MutasiSesi {
        $ada = $this->CariMutasi($saldo->Id, $jenis, $sumber, $idSumber);

        if ($ada !== null) {
            return $ada;
        }

        $saldo->SisaSesi = max(0, $saldo->SisaSesi + $sesi);
        $saldo->NilaiTersisa = Uang::Dari($saldo->NilaiTersisa)->Tambah($nilai)->KeString();
        $saldo->Status = match ($jenis) {
            JenisMutasiSesi::Hangus => StatusSaldoSesi::Hangus,
            JenisMutasiSesi::Batal, JenisMutasiSesi::Refund => StatusSaldoSesi::Dibatalkan,
            default => $saldo->SisaSesi === 0 ? StatusSaldoSesi::Habis : StatusSaldoSesi::Aktif,
        };
        $saldo->save();

        return MutasiSesi::query()->create([
            'IdSaldoSesi' => $saldo->Id,
            'IdPelanggan' => $saldo->IdPelanggan,
            'Jenis' => $jenis,
            'JumlahSesi' => $sesi,
            'Nilai' => $nilai->KeString(),
            'SisaSetelah' => $saldo->SisaSesi,
            'JenisSumber' => $sumber,
            'IdSumber' => $idSumber,
            'NomorSumber' => $nomorSumber === null ? null : mb_substr($nomorSumber, 0, 80),
            'Tanggal' => $tanggal->toDateString(),
            'IdAkunKasBank' => $idAkunKasBank,
            'Keterangan' => $keterangan === null ? null : mb_substr($keterangan, 0, 255),
            'IdPengguna' => $idPengguna,
        ]);
    }

    public function CariMutasi(int $idSaldoSesi, JenisMutasiSesi $jenis, SumberMutasiSesi $sumber, ?int $idSumber): ?MutasiSesi
    {
        return $idSumber === null ? null : MutasiSesi::query()
            ->where('IdSaldoSesi', $idSaldoSesi)
            ->where('Jenis', $jenis->value)
            ->where('JenisSumber', $sumber->value)
            ->where('IdSumber', $idSumber)
            ->first();
    }
}

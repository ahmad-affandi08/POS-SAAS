<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Aksi;

use App\Domain\Akuntansi\Aksi\BalikkanJurnal;
use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Pelanggan\Enum\JenisMutasiSesi;
use App\Domain\Pelanggan\Enum\StatusPemakaianSesi;
use App\Domain\Pelanggan\Enum\StatusSaldoSesi;
use App\Domain\Pelanggan\Enum\SumberMutasiSesi;
use App\Domain\Pelanggan\Layanan\BukuSesi;
use App\Domain\Pelanggan\Model\MutasiSesi;
use App\Domain\Pelanggan\Model\PemakaianSesi;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Batalkan pemakaian sesi yang salah catat (F-16d bagian 2, izin `pelanggan.sesi.kelola`): dokumen pemakaian tidak
 * diedit, statusnya menjadi `Dibatalkan`, sesi & nilai dikembalikan lewat mutasi `BatalPakai` (+), dan jurnal J-16.3-nya
 * dibalik. Hanya bila saldonya masih `Aktif`/`Habis` (belum hangus, dikembalikan, atau di-void). Audit
 * `pelanggan.sesi-pakai-batal`.
 */
final class BatalkanPemakaianSesi
{
    public function __construct(
        private readonly BukuSesi $buku,
        private readonly BalikkanJurnal $balikkan,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(PemakaianSesi $pemakaian, string $alasan, int $idPengguna, CarbonImmutable $tanggal): PemakaianSesi
    {
        $alasan = trim($alasan);

        if (mb_strlen($alasan) < 5) {
            throw new PelanggaranAturanBisnis('AlasanWajib', 'Tulis alasan minimal 5 karakter.', 'Alasan');
        }

        return DB::transaction(function () use ($pemakaian, $alasan, $idPengguna, $tanggal): PemakaianSesi {
            $dokumen = PemakaianSesi::query()->whereKey($pemakaian->Id)->lockForUpdate()->firstOrFail();

            if ($dokumen->Status !== StatusPemakaianSesi::Diterima) {
                throw new PelanggaranAturanBisnis('PemakaianSudahDibatalkan', 'Pemakaian sesi ini sudah dibatalkan.', 'Status');
            }

            $saldo = $dokumen->IdSaldoSesi === null ? null : $this->buku->Kunci($dokumen->IdSaldoSesi);
            $pakai = $saldo === null ? null : $this->buku->CariMutasi($saldo->Id, JenisMutasiSesi::Pakai, SumberMutasiSesi::PemakaianSesi, $dokumen->Id);

            if ($saldo !== null && $pakai instanceof MutasiSesi) {
                if (! in_array($saldo->Status, [StatusSaldoSesi::Aktif, StatusSaldoSesi::Habis], true)) {
                    throw new PelanggaranAturanBisnis('SaldoSesiTidakAktif', "Paket {$saldo->NamaPaket} sudah {$saldo->Status->AmbilLabel()}, pemakaiannya tidak bisa dibatalkan.", 'Status');
                }

                $mutasi = $this->buku->Catat($saldo, JenisMutasiSesi::BatalPakai, -$pakai->JumlahSesi, Uang::Nol()->Kurangi(Uang::Dari($pakai->Nilai)), SumberMutasiSesi::PemakaianSesi, $dokumen->Id, $saldo->NomorPenjualan, $tanggal, $alasan, $idPengguna);

                if ($dokumen->IdJurnal !== null) {
                    $mutasi->IdJurnal = $this->balikkan->Jalankan($dokumen->IdJurnal, $tanggal, mb_substr("Batal pemakaian sesi {$saldo->NamaPaket}: {$alasan}", 0, 255), JenisSumberJurnal::PemakaianSesi, $dokumen->Id, 'Batal', $idPengguna)->idJurnal;
                    $mutasi->save();
                }
            }

            $dokumen->Status = StatusPemakaianSesi::Dibatalkan;
            $dokumen->save();
            $this->audit->Catat('pelanggan.sesi-pakai-batal', $dokumen, ['Status' => StatusPemakaianSesi::Diterima->value], [
                'Status' => StatusPemakaianSesi::Dibatalkan->value,
                'Alasan' => $alasan,
            ], idPengguna: $idPengguna);

            return $dokumen;
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pelanggan\Enum\JenisMutasiPoin;
use App\Domain\Pelanggan\Enum\SumberMutasiPoin;
use App\Domain\Pelanggan\Kueri\PengaturanLoyaltiTenant;
use App\Domain\Pelanggan\Layanan\BukuPoin;
use App\Domain\Pelanggan\Model\Pelanggan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Penyesuaian poin manual (F-16b, izin `pelanggan.kelola`): ± poin dengan alasan wajib (min. 5 karakter), maks.
 * 100.000 poin per penyesuaian. Penambahan berlaku sesuai `MasaBerlakuBulan`; pengurangan tidak boleh membuat saldo
 * minus. Audit `pelanggan.poin-sesuaikan`.
 */
final class SesuaikanPoin
{
    public const BATAS = 100000;

    public function __construct(
        private readonly BukuPoin $buku,
        private readonly PengaturanLoyaltiTenant $pengaturan,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(Pelanggan $pelanggan, int $poin, string $alasan, int $idPengguna, CarbonImmutable $hariIni): int
    {
        $alasan = trim($alasan);

        if ($poin === 0 || abs($poin) > self::BATAS) {
            throw new PelanggaranAturanBisnis('PoinTidakValid', 'Isi poin selain 0, paling banyak 100.000.', 'Poin');
        }

        if (mb_strlen($alasan) < 5) {
            throw new PelanggaranAturanBisnis('AlasanWajib', 'Tulis alasan penyesuaian minimal 5 karakter.', 'Alasan');
        }

        return DB::transaction(function () use ($pelanggan, $poin, $alasan, $idPengguna, $hariIni): int {
            Pelanggan::query()->whereKey($pelanggan->Id)->lockForUpdate()->firstOrFail();
            $saldo = $this->buku->AmbilSaldo($pelanggan->Id);

            if ($poin < 0 && $saldo + $poin < 0) {
                throw new PelanggaranAturanBisnis('PoinTidakCukup', "Saldo poin hanya {$saldo}.", 'Poin');
            }

            if ($poin > 0) {
                $this->buku->Tambah($pelanggan->Id, $poin, JenisMutasiPoin::Penyesuaian, SumberMutasiPoin::Manual, null, $hariIni->addMonthsNoOverflow($this->pengaturan->Ambil()->masaBerlakuBulan), $alasan, $idPengguna);
            } else {
                $this->buku->Kurangi($pelanggan->Id, -$poin, JenisMutasiPoin::Penyesuaian, SumberMutasiPoin::Manual, null, null, $alasan, $idPengguna);
            }

            $this->audit->Catat('pelanggan.poin-sesuaikan', $pelanggan, ['Saldo' => $saldo], ['Saldo' => $saldo + $poin, 'Poin' => $poin, 'Alasan' => $alasan], idPengguna: $idPengguna);

            return $saldo + $poin;
        });
    }
}

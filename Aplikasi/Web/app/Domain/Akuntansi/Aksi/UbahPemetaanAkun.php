<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Aksi;

use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Akuntansi\Model\Akun;
use App\Domain\Akuntansi\Model\PemetaanAkun;
use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Tenant\Layanan\PenguncianTenant;
use Illuminate\Support\Facades\DB;

/**
 * F-13a pemetaan akun: peran akun → akun aktif, tingkat tenant (`idOutlet` null) atau override per outlet. Tipe &
 * sifat kontra akun divalidasi dengan aturan yang sama dengan BR-P03.3 (`PeranAkun::PeriksaAkun`). Akun peran
 * kas/bank otomatis ditandai akun kas/bank. Hanya berlaku untuk jurnal berikutnya (jurnal lama tidak diubah).
 * Baris Tenant dikunci (satu pemetaan per peran per tingkat). LogAudit `akun.pemetaan.ubah` (nilai lama/baru).
 */
final class UbahPemetaanAkun
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenguncianTenant $penguncian,
        private readonly PencatatAudit $audit,
    ) {}

    /**
     * @throws PelanggaranAturanBisnis AkunTidakDikenal, PemetaanAkunTidakValid
     */
    public function Jalankan(PeranAkun $peran, ?int $idOutlet, string $uuidAkun): PemetaanAkun
    {
        return DB::transaction(function () use ($peran, $idOutlet, $uuidAkun): PemetaanAkun {
            $this->penguncian->Kunci($this->konteks->Wajib());
            $akun = Akun::query()->where('Uuid', $uuidAkun)->where('Aktif', true)->first();

            if (! $akun instanceof Akun) {
                throw new PelanggaranAturanBisnis('AkunTidakDikenal', 'Akun tidak ditemukan atau nonaktif.', 'UuidAkun');
            }

            $galat = $peran->PeriksaAkun($akun->Jenis, $akun->CekKontra(), $akun->Kode);

            if ($galat !== []) {
                throw new PelanggaranAturanBisnis('PemetaanAkunTidakValid', implode(' ', $galat), 'UuidAkun');
            }

            $pemetaan = PemetaanAkun::query()
                ->where('Kunci', $peran->value)
                ->where(fn ($k) => $idOutlet === null ? $k->whereNull('IdOutlet') : $k->where('IdOutlet', $idOutlet))
                ->lockForUpdate()
                ->first();
            $idAkunLama = $pemetaan?->IdAkun;

            if ($idAkunLama === $akun->Id) {
                return $pemetaan;
            }

            if ($peran->CekPeranKasBank() && ! $akun->KasBank) {
                $akun->KasBank = true;
                $akun->save();
            }

            if ($pemetaan instanceof PemetaanAkun) {
                $pemetaan->IdAkun = $akun->Id;
                $pemetaan->save();
            } else {
                $pemetaan = PemetaanAkun::query()->create(['Kunci' => $peran->value, 'IdAkun' => $akun->Id, 'IdOutlet' => $idOutlet]);
            }

            $kodeLama = $idAkunLama === null ? null : Akun::query()->whereKey($idAkunLama)->value('Kode');
            $this->audit->Catat(
                'akun.pemetaan.ubah',
                $pemetaan,
                nilaiLama: ['Kunci' => $peran->value, 'IdOutlet' => $idOutlet, 'KodeAkun' => $kodeLama],
                nilaiBaru: ['Kunci' => $peran->value, 'IdOutlet' => $idOutlet, 'KodeAkun' => $akun->Kode],
            );

            return $pemetaan;
        });
    }
}

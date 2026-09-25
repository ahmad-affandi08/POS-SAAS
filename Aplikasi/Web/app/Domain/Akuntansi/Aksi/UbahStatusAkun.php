<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Aksi;

use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Akuntansi\Layanan\PenilaiPemakaianAkun;
use App\Domain\Akuntansi\Model\Akun;
use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use Illuminate\Support\Facades\DB;

/**
 * F-13a bagan akun: aktifkan/nonaktifkan akun. Akun nonaktif tidak bisa dipilih untuk pemetaan atau transaksi baru;
 * jurnal lamanya tetap. Akun yang masih dipetakan ke peran akun atau punya anak aktif tidak bisa dinonaktifkan
 * (jurnal otomatis berikutnya masih memakainya). LogAudit `akun.status`.
 */
final class UbahStatusAkun
{
    public function __construct(
        private readonly PenilaiPemakaianAkun $pemakaian,
        private readonly PencatatAudit $audit,
    ) {}

    /**
     * @throws PelanggaranAturanBisnis AkunMasihDipetakan, AkunPunyaAnakAktif, IndukNonaktif
     */
    public function Jalankan(Akun $akun, bool $aktif): Akun
    {
        return DB::transaction(function () use ($akun, $aktif): Akun {
            $akun = Akun::query()->whereKey($akun->Id)->lockForUpdate()->firstOrFail();

            if ($akun->Aktif === $aktif) {
                return $akun;
            }

            if (! $aktif) {
                $peran = $this->pemakaian->AmbilPeranDipetakan($akun->Id);

                if ($peran !== []) {
                    throw new PelanggaranAturanBisnis(
                        'AkunMasihDipetakan',
                        "Akun {$akun->Kode} masih dipetakan untuk ".implode(', ', array_map(fn (PeranAkun $p): string => $p->AmbilLabel(), $peran)).'. Ganti pemetaan akun dulu sebelum menonaktifkan.',
                    );
                }

                if (Akun::query()->where('IdInduk', $akun->Id)->where('Aktif', true)->exists()) {
                    throw new PelanggaranAturanBisnis('AkunPunyaAnakAktif', "Akun {$akun->Kode} masih punya akun anak yang aktif. Nonaktifkan akun anaknya dulu.");
                }
            } elseif ($akun->IdInduk !== null && Akun::query()->whereKey($akun->IdInduk)->where('Aktif', false)->exists()) {
                throw new PelanggaranAturanBisnis('IndukNonaktif', 'Akun induknya nonaktif. Aktifkan akun induk dulu.');
            }

            $akun->Aktif = $aktif;
            $akun->save();
            $this->audit->Catat('akun.status', $akun, nilaiLama: ['Aktif' => ! $aktif], nilaiBaru: ['Aktif' => $aktif, 'Kode' => $akun->Kode]);

            return $akun;
        });
    }
}

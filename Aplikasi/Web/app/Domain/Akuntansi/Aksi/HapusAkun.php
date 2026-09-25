<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Aksi;

use App\Domain\Akuntansi\Layanan\PenilaiPemakaianAkun;
use App\Domain\Akuntansi\Model\Akun;
use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Tenant\Layanan\PenguncianTenant;
use Illuminate\Support\Facades\DB;

/**
 * F-13a bagan akun: hapus akun yang belum pernah dipakai (tanpa jurnal, pemetaan, akun anak, transaksi kas & bank,
 * kategori kas, atau metode pembayaran). Akun yang dipakai hanya bisa dinonaktifkan (`AkunDipakai`). LogAudit
 * `akun.hapus`.
 */
final class HapusAkun
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenguncianTenant $penguncian,
        private readonly PenilaiPemakaianAkun $pemakaian,
        private readonly PencatatAudit $audit,
    ) {}

    /**
     * @throws PelanggaranAturanBisnis AkunDipakai
     */
    public function Jalankan(Akun $akun): void
    {
        DB::transaction(function () use ($akun): void {
            $this->penguncian->Kunci($this->konteks->Wajib());
            $akun = Akun::query()->whereKey($akun->Id)->lockForUpdate()->firstOrFail();
            $alasan = $this->pemakaian->AmbilAlasanDipakai($akun);

            if ($alasan !== null) {
                throw new PelanggaranAturanBisnis(
                    'AkunDipakai',
                    "Akun {$akun->Kode} {$akun->Nama} tidak bisa dihapus karena {$alasan}. Nonaktifkan akun ini bila tidak dipakai lagi.",
                );
            }

            $this->audit->Catat('akun.hapus', $akun, nilaiLama: ['Kode' => $akun->Kode, 'Nama' => $akun->Nama, 'Jenis' => $akun->Jenis->value]);
            $akun->delete();
        });
    }
}

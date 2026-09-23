<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Penjualan\Enum\JenisMetodePembayaran;
use App\Domain\Penjualan\Model\MetodePembayaran;
use Illuminate\Support\Facades\DB;

/**
 * F-01 langkah 5: menonaktifkan/mengaktifkan kembali metode pembayaran. Tunai selalu tersedia di kasir. Metode tidak
 * pernah dihapus karena dirujuk transaksi (F-08).
 */
final class UbahStatusMetodePembayaran
{
    public function __construct(private readonly PencatatAudit $audit) {}

    public function Jalankan(MetodePembayaran $metode, bool $aktif): MetodePembayaran
    {
        return DB::transaction(function () use ($metode, $aktif): MetodePembayaran {
            $metode = MetodePembayaran::query()->lockForUpdate()->findOrFail($metode->Id);

            if (! $aktif && $metode->Jenis === JenisMetodePembayaran::Tunai) {
                throw new PelanggaranAturanBisnis('TunaiWajib', 'Tunai selalu tersedia di kasir dan tidak bisa dinonaktifkan.');
            }

            if ($metode->Aktif === $aktif) {
                return $metode;
            }

            $metode->Aktif = $aktif;
            $metode->save();
            $this->audit->Catat(
                $aktif ? 'metode-pembayaran.aktifkan' : 'metode-pembayaran.nonaktifkan',
                $metode,
                nilaiLama: ['Aktif' => ! $aktif],
                nilaiBaru: ['Aktif' => $aktif],
            );

            return $metode;
        });
    }
}

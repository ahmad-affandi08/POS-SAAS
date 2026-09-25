<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Layanan;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Organisasi\Data\DataAnggotaOutlet;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Kueri\AnggotaOutlet;

/**
 * Pelaku void & retur penjualan (F-09 fase 1): kasir pengirim wajib anggota outlet ber-izin `penjualan.buat` atau
 * `penjualan.void` (`KasirTidakDitemukan`/`TanpaIzin`); penyetuju wajib anggota outlet ber-izin `penjualan.void`
 * (`PenyetujuTidakBerwenang`). PIN penyetuju diperiksa di perangkat; server memeriksa kewenangannya. Kasir yang sendiri
 * ber-izin `penjualan.void` boleh menjadi penyetujunya sendiri.
 */
final class PemeriksaPelakuPascaPenjualan
{
    public function __construct(private readonly AnggotaOutlet $anggota) {}

    public function CariKasir(int $idTenant, string $uuidPengguna, int $idOutlet): DataAnggotaOutlet
    {
        $kasir = $this->anggota->Cari($idTenant, $uuidPengguna, $idOutlet);

        if ($kasir === null) {
            throw new PelanggaranAturanBisnis('KasirTidakDitemukan', 'Kasir ini tidak terdaftar di outlet penjualan ini.', 'UuidPengguna');
        }

        if (! $kasir->CekIzin(IzinTenant::PenjualanBuat->value) && ! $kasir->CekIzin(IzinTenant::PenjualanVoid->value)) {
            throw new PelanggaranAturanBisnis('TanpaIzin', "{$kasir->nama} tidak punya izin melayani void atau retur.", 'UuidPengguna', 403);
        }

        return $kasir;
    }

    public function CariPenyetuju(int $idTenant, string $uuidPenyetuju, int $idOutlet): DataAnggotaOutlet
    {
        $penyetuju = $this->anggota->Cari($idTenant, $uuidPenyetuju, $idOutlet);

        if ($penyetuju === null || ! $penyetuju->CekIzin(IzinTenant::PenjualanVoid->value)) {
            throw new PelanggaranAturanBisnis('PenyetujuTidakBerwenang', 'Penyetuju tidak punya izin menyetujui void atau retur di outlet ini.', 'UuidPenyetuju', 403);
        }

        return $penyetuju;
    }
}

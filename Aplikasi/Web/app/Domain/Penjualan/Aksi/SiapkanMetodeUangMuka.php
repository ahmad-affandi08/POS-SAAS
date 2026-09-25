<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Penjualan\Enum\JenisMetodePembayaran;
use App\Domain\Penjualan\Model\MetodePembayaran;
use App\Domain\Tenant\Layanan\PenguncianTenant;
use Illuminate\Support\Facades\DB;

/**
 * F-12 bagian 2: metode pembayaran "Uang muka (DP)" dibuat sistem saat pre-order pertama tenant diterima. Dipakai
 * aplikasi kasir untuk memakai DP saat pesanan diambil (tidak tampil sebagai pilihan bayar biasa). Idempoten per tenant;
 * hasil = Id metode.
 */
final class SiapkanMetodeUangMuka
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenguncianTenant $penguncian,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(?int $idPengguna = null): MetodePembayaran
    {
        $ada = MetodePembayaran::query()->where('Jenis', JenisMetodePembayaran::UangMuka->value)->first();

        if ($ada !== null) {
            return $ada;
        }

        $idTenant = $this->konteks->Wajib();

        return DB::transaction(function () use ($idTenant, $idPengguna): MetodePembayaran {
            $this->penguncian->Kunci($idTenant);
            $ada = MetodePembayaran::query()->where('Jenis', JenisMetodePembayaran::UangMuka->value)->first();

            if ($ada !== null) {
                return $ada;
            }

            $metode = MetodePembayaran::query()->create([
                'Jenis' => JenisMetodePembayaran::UangMuka,
                'Nama' => 'Uang muka (DP)',
                'Aktif' => true,
                'Urutan' => ((int) MetodePembayaran::query()->max('Urutan')) + 1,
            ]);
            $this->audit->Catat('metode-pembayaran.buat', $metode, nilaiBaru: ['Jenis' => $metode->Jenis->value, 'Nama' => $metode->Nama], idPengguna: $idPengguna, idTenant: $idTenant);

            return $metode;
        });
    }
}

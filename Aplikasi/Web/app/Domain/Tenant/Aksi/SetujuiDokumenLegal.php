<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Tenant\Kueri\PersetujuanLegalTertunda;
use App\Domain\Tenant\Model\DokumenLegal;
use App\Domain\Tenant\Model\PersetujuanDokumenLegal;
use Illuminate\Support\Facades\DB;

/**
 * Owner menyetujui versi materiil dokumen legal yang berlaku (BR-P06.5): dicatat di `PersetujuanDokumenLegal` (tenant,
 * pengguna, versi, waktu, IP). Yang disetujui harus persis daftar yang ditampilkan; bila di antaranya ada versi lain
 * yang mulai berlaku, Owner diminta memuat ulang agar tidak menyetujui dokumen yang belum ia lihat.
 */
final class SetujuiDokumenLegal
{
    public function __construct(
        private readonly PersetujuanLegalTertunda $tertunda,
        private readonly PencatatAudit $audit,
    ) {}

    /**
     * @param  list<string>  $uuidDokumen  versi yang ditampilkan dan dicentang Owner
     */
    public function Jalankan(int $idTenant, int $idPengguna, array $uuidDokumen, ?string $ip): int
    {
        return DB::transaction(function () use ($idTenant, $idPengguna, $uuidDokumen, $ip): int {
            $wajib = $this->tertunda->Ambil($idTenant, $idPengguna, now());
            $kurang = array_filter($wajib, fn (DokumenLegal $dokumen) => ! in_array($dokumen->Uuid, $uuidDokumen, true));

            if ($kurang !== []) {
                throw new PelanggaranAturanBisnis('BR-P06.5', 'Ada versi dokumen yang baru berlaku. Muat ulang halaman lalu baca dan setujui lagi.');
            }

            foreach ($wajib as $dokumen) {
                PersetujuanDokumenLegal::query()->firstOrCreate(
                    ['IdDokumenLegal' => $dokumen->Id, 'IdTenant' => $idTenant, 'IdPengguna' => $idPengguna],
                    ['DisetujuiPada' => now(), 'Ip' => $ip],
                );
            }

            if ($wajib !== []) {
                $this->audit->Catat('legal.setujui', nilaiBaru: [
                    'Dokumen' => array_map(fn (DokumenLegal $dokumen) => $dokumen->Jenis->value.' v'.$dokumen->Versi, $wajib),
                ], idTenant: $idTenant, idPengguna: $idPengguna);
            }

            return count($wajib);
        });
    }
}

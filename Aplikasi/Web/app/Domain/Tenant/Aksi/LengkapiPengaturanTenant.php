<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Tenant\Layanan\PenguncianTenant;
use Illuminate\Support\Facades\DB;

/**
 * F-01 (BR-01.1): melengkapi `Tenant.Pengaturan` dengan nilai bawaan template. Aditif: hanya kunci yang belum ada;
 * nilai yang sudah diubah tenant tidak pernah ditimpa. Kunci daftar (`$gabungDaftar`, misal `Sektor`) digabung:
 * nilai lama + nilai baru tanpa duplikat, tidak ada yang dibuang (usaha campuran bertambah sektor).
 */
final class LengkapiPengaturanTenant
{
    public function __construct(
        private readonly PenguncianTenant $penguncian,
        private readonly PencatatAudit $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $bawaan
     * @param  array<string, list<string>>  $gabungDaftar  kunci daftar yang digabung (union) dengan nilai lama
     * @return list<string> kunci yang ditambahkan atau bertambah isinya
     */
    public function Jalankan(int $idTenant, array $bawaan, array $gabungDaftar = []): array
    {
        return DB::transaction(function () use ($idTenant, $bawaan, $gabungDaftar): array {
            $tenant = $this->penguncian->Kunci($idTenant);
            $pengaturan = $tenant->Pengaturan ?? [];
            $sebelum = $pengaturan;
            $ditambahkan = [];

            foreach ($bawaan as $kunci => $nilai) {
                if (! array_key_exists($kunci, $pengaturan)) {
                    $pengaturan[$kunci] = $nilai;
                    $ditambahkan[] = $kunci;
                }
            }

            foreach ($gabungDaftar as $kunci => $daftar) {
                $lama = is_array($pengaturan[$kunci] ?? null) ? array_values(array_filter($pengaturan[$kunci], 'is_string')) : [];
                $gabungan = array_values(array_unique([...$lama, ...$daftar]));

                if ($gabungan !== ($pengaturan[$kunci] ?? null)) {
                    $pengaturan[$kunci] = $gabungan;
                    $ditambahkan[] = $kunci;
                }
            }

            if ($ditambahkan === []) {
                return [];
            }

            $tenant->Pengaturan = $pengaturan;
            $tenant->save();
            $this->audit->Catat(
                'tenant.pengaturan.lengkapi',
                $tenant,
                nilaiLama: array_intersect_key($sebelum, array_flip($ditambahkan)),
                nilaiBaru: array_intersect_key($pengaturan, array_flip($ditambahkan)),
                idTenant: $idTenant,
            );

            return $ditambahkan;
        });
    }
}

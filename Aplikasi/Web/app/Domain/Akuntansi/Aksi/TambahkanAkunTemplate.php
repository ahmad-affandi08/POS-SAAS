<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Aksi;

use App\Domain\Akuntansi\Data\DataAkunTemplate;
use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Akuntansi\Model\Akun;
use App\Domain\Akuntansi\Model\PemetaanAkun;
use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use Illuminate\Support\Facades\DB;

/**
 * F-01 (BR-01.2, BR-01.1): COA inti + ekstensi sektor dari template menjadi `Akun` tenant, lalu pemetaan peran akun
 * tingkat tenant. Aditif & idempoten: akun yang kodenya sudah ada tidak diubah (termasuk yang diganti namanya
 * tenant), pemetaan yang sudah ada tidak ditimpa. Peran tidak dikenal dan akun yang tidak ada dilewati.
 * Pemanggil sudah memegang kunci baris Tenant (kirim ganda aman).
 */
final class TambahkanAkunTemplate
{
    public function __construct(private readonly PencatatAudit $audit) {}

    /**
     * @param  list<DataAkunTemplate>  $akun
     * @param  array<string, string>  $pemetaan  kunci peran → kode akun
     * @return array{Akun: list<string>, Pemetaan: list<string>}
     */
    public function Jalankan(array $akun, array $pemetaan): array
    {
        return DB::transaction(function () use ($akun, $pemetaan): array {
            $idPerKode = Akun::query()->pluck('Id', 'Kode')->all();
            $kodeBaru = [];

            foreach ($akun as $data) {
                if (array_key_exists($data->kode, $idPerKode)) {
                    continue;
                }

                $baris = Akun::query()->create([
                    'Kode' => $data->kode,
                    'Nama' => trim($data->nama),
                    'Jenis' => $data->tipe,
                    'SaldoNormal' => $data->saldoNormal,
                    'Sistem' => true,
                ]);
                $idPerKode[$baris->Kode] = $baris->Id;
                $kodeBaru[] = $baris->Kode;
            }

            $kunciAda = PemetaanAkun::query()->whereNull('IdOutlet')->pluck('Kunci')->all();
            $pemetaanBaru = [];

            foreach ($pemetaan as $kunci => $kode) {
                // Kunci lama (PiutangSettlement, Waste) di versi terbit dibaca lewat alias (§25 no. 16a, BR-P03.4).
                $peran = PeranAkun::DariKunci((string) $kunci);
                $idAkun = $idPerKode[$kode] ?? null;

                if ($peran === null || ! is_int($idAkun) || in_array($peran->value, $kunciAda, true)) {
                    continue;
                }

                PemetaanAkun::query()->create(['Kunci' => $peran->value, 'IdAkun' => $idAkun, 'IdOutlet' => null]);
                $kunciAda[] = $peran->value;
                $pemetaanBaru[] = $peran->value;
            }

            if ($kodeBaru !== [] || $pemetaanBaru !== []) {
                $this->audit->Catat('akun.tambah-template', nilaiBaru: ['Kode' => $kodeBaru, 'Pemetaan' => $pemetaanBaru]);
            }

            return ['Akun' => $kodeBaru, 'Pemetaan' => $pemetaanBaru];
        });
    }
}

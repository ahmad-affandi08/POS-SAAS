<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Kueri;

use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Akuntansi\Model\Akun;
use App\Domain\Akuntansi\Model\PemetaanAkun;
use App\Domain\Organisasi\Kueri\PetaUuidOutlet;

/**
 * Pemetaan peran akun untuk halaman `/kelola/akuntansi/pemetaan` (F-13a, tipe FE `PropsPemetaanAkun`): satu baris
 * tingkat tenant per `PeranAkun` ditambah baris override per outlet (hanya outlet yang boleh diakses pengguna).
 * Status mengikuti aturan `PenentuAkun`: tipe/kontra akun harus cocok (`PeranAkun::PeriksaAkun`), kunci baru menang
 * atas kunci lama. Opsi akun = akun aktif beserta tipe & sifat kontranya (disaring di FE per peran).
 */
final class DaftarPemetaanAkun
{
    public function __construct(private readonly PetaUuidOutlet $outlet) {}

    /**
     * @param  list<int>|null  $idOutletBoleh
     * @return array<string, mixed>
     */
    public function Ambil(?array $idOutletBoleh): array
    {
        $akun = Akun::query()->orderBy('Kode')->get()->keyBy('Id');
        $outlet = $this->outlet->AmbilRingkas($idOutletBoleh);
        $petaOutlet = array_column($outlet, null, 'Id');
        $pemetaan = [];

        // Kunci baru menang atas kunci lama pada tingkat yang sama.
        foreach (PemetaanAkun::query()->orderBy('Id')->get(['Kunci', 'IdAkun', 'IdOutlet']) as $baris) {
            $peran = PeranAkun::DariKunci($baris->Kunci);

            if ($peran === null) {
                continue;
            }

            $tingkat = $baris->IdOutlet ?? 0;

            if (! isset($pemetaan[$peran->value][$tingkat]) || $baris->Kunci === $peran->value) {
                $pemetaan[$peran->value][$tingkat] = $baris->IdAkun;
            }
        }

        $hasil = [];

        foreach (PeranAkun::cases() as $peran) {
            $hasil[] = self::Baris($peran, null, $pemetaan[$peran->value][0] ?? null, $akun->all());

            foreach ($pemetaan[$peran->value] ?? [] as $idOutlet => $idAkun) {
                if ($idOutlet !== 0 && isset($petaOutlet[$idOutlet])) {
                    $hasil[] = self::Baris($peran, $petaOutlet[$idOutlet], $idAkun, $akun->all());
                }
            }
        }

        return [
            'Pemetaan' => $hasil,
            'OpsiAkun' => array_values($akun->filter(fn (Akun $a): bool => $a->Aktif)->map(fn (Akun $a): array => [
                'Uuid' => $a->Uuid,
                'Kode' => $a->Kode,
                'Nama' => $a->Nama,
                'Jenis' => $a->Jenis->value,
                'Kontra' => $a->CekKontra(),
            ])->all()),
            'OpsiOutlet' => array_map(fn (array $o): array => ['Uuid' => $o['Uuid'], 'Nama' => $o['Nama']], $outlet),
        ];
    }

    /**
     * @param  array{Id: int, Uuid: string, Nama: string}|null  $outlet
     * @param  array<int, Akun>  $akun
     * @return array<string, mixed>
     */
    private static function Baris(PeranAkun $peran, ?array $outlet, ?int $idAkun, array $akun): array
    {
        $satu = $idAkun === null ? null : ($akun[$idAkun] ?? null);
        $galat = $satu === null ? [] : $peran->PeriksaAkun($satu->Jenis, $satu->CekKontra(), $satu->Kode);
        $status = match (true) {
            $satu === null => 'BelumDipetakan',
            $galat !== [] => 'TipeSalah',
            ! $satu->Aktif => 'AkunNonaktif',
            default => 'Sesuai',
        };

        return [
            'Id' => $peran->value.'|'.($outlet['Uuid'] ?? ''),
            'Kunci' => $peran->value,
            'LabelPeran' => $peran->AmbilLabel(),
            'TipeWajib' => $peran->AmbilTipeAkun()->value,
            'LabelTipeWajib' => $peran->AmbilTipeAkun()->AmbilLabel(),
            'WajibKontra' => $peran->CekWajibKontra(),
            'UuidOutlet' => $outlet['Uuid'] ?? null,
            'NamaOutlet' => $outlet['Nama'] ?? null,
            'UuidAkun' => $satu?->Uuid,
            'KodeAkun' => $satu?->Kode,
            'NamaAkun' => $satu?->Nama,
            'Status' => $status,
            'PesanStatus' => $galat === [] ? null : implode(' ', $galat),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Kueri;

use App\Domain\Organisasi\Aksi\PutuskanPersetujuanJarakJauh;
use App\Domain\Organisasi\Data\DataAnggotaOutlet;
use App\Domain\Organisasi\Enum\StatusPermintaanPersetujuan;
use App\Domain\Organisasi\Model\Outlet;
use App\Domain\Organisasi\Model\Pengguna;
use App\Domain\Organisasi\Model\Perangkat;
use App\Domain\Organisasi\Model\PermintaanPersetujuan;
use Illuminate\Support\Collection;

/**
 * Tampilan persetujuan jarak jauh (X4): satu permintaan untuk perangkat kasir (status + penyetuju bila disetujui), dan
 * antrean Menunggu untuk Aplikasi Owner (hanya outlet yang boleh diakses, izin cocok, bukan permintaan sendiri, belum
 * lewat waktu; terlama dulu). Permintaan Menunggu yang lewat waktu tampil sebagai Kedaluwarsa.
 */
final class PersetujuanJarakJauh
{
    public const BATAS_ANTREAN = 50;

    public function __construct(private readonly AksesPengguna $akses) {}

    /**
     * @return array<string, mixed>
     */
    public function Tampilkan(PermintaanPersetujuan $p): array
    {
        return $this->Petakan(collect([$p]))[0];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function AntreanPenyetuju(int $idTenant, int $idPengguna): array
    {
        $akses = $this->akses->Ambil($idTenant, $idPengguna);

        if ($akses === null) {
            return [];
        }

        $idOutlet = $this->akses->AmbilIdOutlet($idTenant, $idPengguna);
        $anggota = new DataAnggotaOutlet($idPengguna, '', '', $akses['Pemilik'], $akses['Izin']);
        $menunggu = PermintaanPersetujuan::query()
            ->where('Status', StatusPermintaanPersetujuan::Menunggu->value)
            ->where('KedaluwarsaPada', '>', now())
            ->where('IdPemohon', '!=', $idPengguna)
            ->when($idOutlet !== null, fn ($kueri) => $kueri->whereIn('IdOutlet', $idOutlet ?? []))
            ->orderBy('DibuatPada')
            ->orderBy('Id')
            ->limit(self::BATAS_ANTREAN * 2)
            ->get()
            ->filter(fn (PermintaanPersetujuan $p): bool => PutuskanPersetujuanJarakJauh::CekBolehMemutuskan($p, $anggota))
            ->take(self::BATAS_ANTREAN)
            ->values();

        return $this->Petakan($menunggu);
    }

    /**
     * @param  Collection<int, PermintaanPersetujuan>  $daftar
     * @return list<array<string, mixed>>
     */
    private function Petakan(Collection $daftar): array
    {
        $outlet = Outlet::query()->whereIn('Id', $daftar->pluck('IdOutlet')->unique()->all())->pluck('Nama', 'Id');
        $perangkat = Perangkat::query()->whereIn('Id', $daftar->pluck('IdPerangkat')->unique()->all())->pluck('Nama', 'Id');
        $pengguna = Pengguna::query()
            ->whereIn('Id', $daftar->pluck('IdPemohon')->merge($daftar->pluck('DiputuskanOleh'))->filter()->unique()->all())
            ->get()
            ->keyBy('Id');

        return array_values($daftar->map(function (PermintaanPersetujuan $p) use ($outlet, $perangkat, $pengguna): array {
            $status = $p->CekLewatWaktu() ? StatusPermintaanPersetujuan::Kedaluwarsa : $p->Status;
            $penyetuju = $p->DiputuskanOleh === null ? null : $pengguna->get($p->DiputuskanOleh);
            $aksesPenyetuju = $penyetuju === null || $status !== StatusPermintaanPersetujuan::Disetujui ? null : $this->akses->Ambil($p->IdTenant, $penyetuju->Id);

            return [
                'Uuid' => $p->Uuid,
                'Status' => $status->value,
                'LabelStatus' => $status->AmbilLabel(),
                'Judul' => $p->Judul,
                'Rincian' => array_values($p->Rincian),
                'Nilai' => $p->Nilai,
                'Izin' => $p->Izin,
                'HanyaPemilik' => $p->HanyaPemilik,
                'NamaOutlet' => (string) ($outlet[$p->IdOutlet] ?? ''),
                'NamaPerangkat' => (string) ($perangkat[$p->IdPerangkat] ?? ''),
                'NamaPemohon' => $pengguna->get($p->IdPemohon)->Nama ?? '',
                'DibuatPada' => $p->DibuatPada?->toIso8601ZuluString(),
                'KedaluwarsaPada' => $p->KedaluwarsaPada->toIso8601ZuluString(),
                'DiputuskanPada' => $p->DiputuskanPada?->toIso8601ZuluString(),
                'AlasanTolak' => $p->AlasanTolak,
                'Penyetuju' => $penyetuju === null || $aksesPenyetuju === null ? null : [
                    'Uuid' => $penyetuju->Uuid,
                    'Nama' => $penyetuju->Nama,
                    'Pemilik' => $aksesPenyetuju['Pemilik'],
                    'Izin' => $aksesPenyetuju['Izin'],
                ],
                'NamaPemutus' => $penyetuju->Nama ?? null,
            ];
        })->all());
    }
}

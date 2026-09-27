<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pemilik\V1;

use App\Domain\Organisasi\Aksi\PutuskanPersetujuanJarakJauh;
use App\Domain\Organisasi\Kueri\PersetujuanJarakJauh;
use App\Domain\Organisasi\Model\PermintaanPersetujuan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Persetujuan jarak jauh di Aplikasi Owner (X4, OWN-03, §17.3.4): `GET persetujuan` antrean Menunggu yang boleh
 * diputuskan pengguna (outlet akses + izin yang diminta, bukan permintaannya sendiri), `POST persetujuan/{uuid}/setujui`,
 * `POST persetujuan/{uuid}/tolak {Alasan}`. Permintaan di luar outlet akses = 404.
 */
final class PersetujuanKontroler extends DasarPemilikKontroler
{
    public function Daftar(Request $permintaan, PersetujuanJarakJauh $kueri): JsonResponse
    {
        return response()->json(['Persetujuan' => $kueri->AntreanPenyetuju($this->IdTenant(), $this->Pengguna($permintaan)->Id)]);
    }

    public function Setujui(Request $permintaan, string $persetujuan, PutuskanPersetujuanJarakJauh $putuskan, PersetujuanJarakJauh $kueri): JsonResponse
    {
        $hasil = $putuskan->Jalankan($this->Cari($permintaan, $persetujuan), $this->Pengguna($permintaan)->Uuid, true);

        return response()->json(['Persetujuan' => $kueri->Tampilkan($hasil)]);
    }

    public function Tolak(Request $permintaan, string $persetujuan, PutuskanPersetujuanJarakJauh $putuskan, PersetujuanJarakJauh $kueri): JsonResponse
    {
        $valid = $permintaan->validate(['Alasan' => ['required', 'string', 'min:5', 'max:255']]);
        $hasil = $putuskan->Jalankan($this->Cari($permintaan, $persetujuan), $this->Pengguna($permintaan)->Uuid, false, (string) $valid['Alasan']);

        return response()->json(['Persetujuan' => $kueri->Tampilkan($hasil)]);
    }

    private function Cari(Request $permintaan, string $uuid): PermintaanPersetujuan
    {
        $idOutlet = $this->IdOutletBoleh($permintaan);
        $p = PermintaanPersetujuan::query()
            ->where('Uuid', strtoupper($uuid))
            ->when($idOutlet !== null, fn ($kueri) => $kueri->whereIn('IdOutlet', $idOutlet ?? []))
            ->first();
        abort_if($p === null, 404);

        return $p;
    }
}

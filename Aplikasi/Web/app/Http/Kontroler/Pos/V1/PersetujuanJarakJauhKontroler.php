<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pos\V1;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Organisasi\Aksi\AjukanPersetujuanJarakJauh;
use App\Domain\Organisasi\Aksi\BatalkanPersetujuanJarakJauh;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Kueri\PersetujuanJarakJauh;
use App\Domain\Organisasi\Model\PermintaanPersetujuan;
use App\Http\Kontroler\Kontroler;
use App\Http\Perantara\AutentikasiPerangkat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Persetujuan jarak jauh dari aplikasi kasir (X4, §16.3 `POST /persetujuan/jarak-jauh`, butuh online):
 * `POST persetujuan/jarak-jauh {Uuid, Izin?, UuidPengguna, Judul, Rincian[{Label, Nilai}], Nilai?}` (Izin kosong = khusus
 * pemilik; idempoten per Uuid), `GET persetujuan/jarak-jauh/{uuid}` status untuk ditunggu perangkat (berisi penyetuju
 * bila disetujui), `POST persetujuan/jarak-jauh/{uuid}/batal` berhenti menunggu. Hanya perangkat pemohon yang bisa
 * membaca/membatalkan permintaannya.
 */
final class PersetujuanJarakJauhKontroler extends Kontroler
{
    public function Ajukan(Request $permintaan, AjukanPersetujuanJarakJauh $ajukan, PersetujuanJarakJauh $kueri): JsonResponse
    {
        $valid = $permintaan->validate([
            'Uuid' => ['required', 'string', 'ulid'],
            'Izin' => ['nullable', 'string', Rule::in(array_map(fn (IzinTenant $i): string => $i->value, AjukanPersetujuanJarakJauh::IZIN_BOLEH))],
            'UuidPengguna' => ['required', 'string', 'ulid'],
            'Judul' => ['required', 'string', 'max:150'],
            'Rincian' => ['present', 'array', 'max:12'],
            'Rincian.*.Label' => ['required', 'string', 'max:40'],
            'Rincian.*.Nilai' => ['required', 'string', 'max:160'],
            'Nilai' => ['nullable', 'string', 'regex:/^\d{1,16}(\.\d{1,2})?$/'],
        ]);

        /** @var list<array{Label: string, Nilai: string}> $rincian */
        $rincian = array_values(array_map(fn (array $r): array => ['Label' => (string) $r['Label'], 'Nilai' => (string) $r['Nilai']], (array) $valid['Rincian']));
        $hasil = $ajukan->Jalankan(
            AutentikasiPerangkat::AmbilPerangkat($permintaan),
            strtoupper((string) $valid['Uuid']),
            strtoupper((string) $valid['UuidPengguna']),
            is_string($valid['Izin'] ?? null) ? IzinTenant::from($valid['Izin']) : null,
            (string) $valid['Judul'],
            $rincian,
            is_string($valid['Nilai'] ?? null) ? Uang::Dari($valid['Nilai']) : null,
        );

        return response()->json(['Persetujuan' => $kueri->Tampilkan($hasil)], 201);
    }

    public function Tampilkan(Request $permintaan, string $persetujuan, PersetujuanJarakJauh $kueri): JsonResponse
    {
        return response()->json(['Persetujuan' => $kueri->Tampilkan($this->Cari($permintaan, $persetujuan))]);
    }

    public function Batal(Request $permintaan, string $persetujuan, BatalkanPersetujuanJarakJauh $batal, PersetujuanJarakJauh $kueri): JsonResponse
    {
        return response()->json(['Persetujuan' => $kueri->Tampilkan($batal->Jalankan($this->Cari($permintaan, $persetujuan)))]);
    }

    private function Cari(Request $permintaan, string $uuid): PermintaanPersetujuan
    {
        $p = PermintaanPersetujuan::query()
            ->where('Uuid', strtoupper($uuid))
            ->where('IdPerangkat', AutentikasiPerangkat::AmbilPerangkat($permintaan)->Id)
            ->first();
        abort_if($p === null, 404);

        return $p;
    }
}

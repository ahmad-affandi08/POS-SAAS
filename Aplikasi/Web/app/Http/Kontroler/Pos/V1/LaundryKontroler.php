<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pos\V1;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Kueri\AnggotaOutlet;
use App\Domain\Pemenuhan\Aksi\UbahStatusLaundry;
use App\Domain\Pemenuhan\Enum\StatusLaundry;
use App\Domain\Pemenuhan\Kueri\LaundryPos;
use App\Domain\Pemenuhan\Model\TiketLaundry;
use App\Http\Kontroler\Kontroler;
use App\Http\Perantara\AutentikasiPerangkat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Laundry di aplikasi kasir (§9.9, perlu online): `GET /api/pos/v1/laundry?kata=` cucian aktif outlet perangkat (tanpa
 * kata = siap diambil), `POST /api/pos/v1/laundry/{uuid}/status {Status, UuidPengguna}` ubah status proses atau tandai
 * diambil (izin `penjualan.buat` atau `laundry.kelola`; status sama = idempoten). Uuid tiket = Uuid penjualan.
 */
final class LaundryKontroler extends Kontroler
{
    public function Cari(Request $permintaan, LaundryPos $kueri): JsonResponse
    {
        $valid = $permintaan->validate(['kata' => ['nullable', 'string', 'max:60']]);

        return response()->json(['Tiket' => $kueri->Cari(AutentikasiPerangkat::AmbilPerangkat($permintaan)->IdOutlet, (string) ($valid['kata'] ?? ''))]);
    }

    public function UbahStatus(Request $permintaan, string $tiket, AnggotaOutlet $anggota, UbahStatusLaundry $ubah, LaundryPos $kueri): JsonResponse
    {
        $perangkat = AutentikasiPerangkat::AmbilPerangkat($permintaan);
        $valid = $permintaan->validate([
            'Status' => ['required', Rule::in(array_map(fn (StatusLaundry $s): string => $s->value, array_filter(StatusLaundry::cases(), fn (StatusLaundry $s): bool => $s !== StatusLaundry::Dibatalkan)))],
            'UuidPengguna' => ['required', 'string', 'ulid'],
        ]);
        $pelaku = $anggota->Cari($perangkat->IdTenant, strtoupper((string) $valid['UuidPengguna']), $perangkat->IdOutlet);

        if ($pelaku === null || (! $pelaku->CekIzin(IzinTenant::PenjualanBuat->value) && ! $pelaku->CekIzin(IzinTenant::LaundryKelola->value))) {
            throw new PelanggaranAturanBisnis('TanpaIzin', 'Pengguna ini tidak boleh mengubah status cucian.', 'UuidPengguna', 403);
        }

        $t = TiketLaundry::query()->where('Uuid', strtoupper($tiket))->where('IdOutlet', $perangkat->IdOutlet)->first();
        abort_if($t === null, 404);
        $ubah->Jalankan($t, StatusLaundry::from((string) $valid['Status']), $pelaku->id);

        return response()->json(['Tiket' => $kueri->Ambil($perangkat->IdOutlet, $t->Uuid)]);
    }
}

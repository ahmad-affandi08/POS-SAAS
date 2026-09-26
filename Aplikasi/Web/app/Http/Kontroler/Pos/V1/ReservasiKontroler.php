<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pos\V1;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Kueri\AnggotaOutlet;
use App\Domain\Organisasi\Kueri\ZonaWaktuOutlet;
use App\Domain\Pemenuhan\Aksi\UbahStatusReservasi;
use App\Domain\Pemenuhan\Enum\StatusReservasi;
use App\Domain\Pemenuhan\Kueri\ReservasiPos;
use App\Domain\Pemenuhan\Model\Reservasi;
use App\Http\Kontroler\Kontroler;
use App\Http\Perantara\AutentikasiPerangkat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * F-07 mode service bagian 2 (perlu online): `GET /api/pos/v1/reservasi?tanggal=` antrian reservasi outlet perangkat
 * (bawaan hari ini), `POST /api/pos/v1/reservasi/{uuid}/hadir` `{UuidPengguna}` check-in pelanggan (kasir ber-izin
 * `penjualan.buat` atau `reservasi.kelola`; idempoten bila sudah Hadir). Pembayaran lewat `Penjualan.Buat` dengan
 * `UuidReservasi` menyelesaikan reservasi.
 */
final class ReservasiKontroler extends Kontroler
{
    public function Ambil(Request $permintaan, ReservasiPos $kueri): JsonResponse
    {
        $valid = $permintaan->validate(['tanggal' => ['nullable', 'date_format:Y-m-d']]);

        return response()->json(['Reservasi' => $kueri->Ambil(AutentikasiPerangkat::AmbilPerangkat($permintaan)->IdOutlet, isset($valid['tanggal']) ? (string) $valid['tanggal'] : null)]);
    }

    public function Hadir(Request $permintaan, string $reservasi, AnggotaOutlet $anggota, UbahStatusReservasi $ubah, ReservasiPos $kueri, ZonaWaktuOutlet $zona): JsonResponse
    {
        $perangkat = AutentikasiPerangkat::AmbilPerangkat($permintaan);
        $valid = $permintaan->validate(['UuidPengguna' => ['required', 'string', 'ulid']]);
        $pelaku = $anggota->Cari($perangkat->IdTenant, strtoupper((string) $valid['UuidPengguna']), $perangkat->IdOutlet);

        if ($pelaku === null || (! $pelaku->CekIzin(IzinTenant::PenjualanBuat->value) && ! $pelaku->CekIzin(IzinTenant::ReservasiKelola->value))) {
            throw new PelanggaranAturanBisnis('TanpaIzin', 'Pengguna ini tidak boleh menerima pelanggan reservasi.', 'UuidPengguna', 403);
        }

        $r = Reservasi::query()->where('Uuid', strtoupper($reservasi))->where('IdOutlet', $perangkat->IdOutlet)->first();
        abort_if($r === null, 404);

        if ($r->Status !== StatusReservasi::Hadir) {
            $ubah->Jalankan($r, StatusReservasi::Hadir, $pelaku->id);
        }

        $baris = collect($kueri->Ambil($perangkat->IdOutlet, $r->MulaiPada->copy()->setTimezone($zona->Ambil($perangkat->IdOutlet))->toDateString()))->firstWhere('Uuid', $r->Uuid);

        return response()->json(['Reservasi' => $baris]);
    }
}

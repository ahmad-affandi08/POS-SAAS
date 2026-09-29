<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pemilik\V1;

use App\Domain\Organisasi\Aksi\DaftarkanPerangkatPengguna;
use App\Domain\Organisasi\Aksi\TandaiNotifikasiDibaca;
use App\Domain\Organisasi\Kueri\DaftarNotifikasiPengguna;
use App\Http\Perantara\AutentikasiPemilik;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** OWN-03: token FCM dan pusat notifikasi Aplikasi Owner. */
final class NotifikasiKontroler extends DasarPemilikKontroler
{
    public function Daftar(Request $permintaan, DaftarNotifikasiPengguna $daftar): JsonResponse
    {
        return response()->json($daftar->Ambil($this->Pengguna($permintaan)->Id));
    }

    public function TandaiDibaca(Request $permintaan, TandaiNotifikasiDibaca $tandai): JsonResponse
    {
        $valid = $permintaan->validate([
            'Semua' => ['sometimes', 'boolean'],
            'Uuid' => ['sometimes', 'array', 'max:100'],
            'Uuid.*' => ['required', 'ulid'],
        ]);
        $semua = (bool) ($valid['Semua'] ?? false);
        $uuid = array_values(array_map('strval', $valid['Uuid'] ?? []));

        return response()->json(['Jumlah' => $tandai->Jalankan($this->Pengguna($permintaan)->Id, $uuid, $semua)]);
    }

    public function DaftarkanToken(Request $permintaan, DaftarkanPerangkatPengguna $daftarkan): JsonResponse
    {
        $valid = $permintaan->validate([
            'Token' => ['required', 'string', 'min:20', 'max:4096'],
            'Platform' => ['required', Rule::in(['Android', 'Ios'])],
            'NamaPerangkat' => ['required', 'string', 'max:100'],
        ]);
        $hasil = $daftarkan->Jalankan(
            AutentikasiPemilik::AmbilToken($permintaan),
            (string) $valid['Token'],
            (string) $valid['Platform'],
            (string) $valid['NamaPerangkat'],
        );

        return response()->json(['Perangkat' => ['Uuid' => $hasil->Uuid, 'Platform' => $hasil->Platform]], 201);
    }
}

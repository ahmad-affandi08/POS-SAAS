<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pos\V1;

use App\Domain\Bengkel\Kueri\PerintahKerjaPos;
use App\Http\Kontroler\Kontroler;
use App\Http\Perantara\AutentikasiPerangkat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Perintah kerja bengkel di aplikasi kasir (§9.10, perlu online, hanya baca):
 * - `GET /api/pos/v1/perintah-kerja?status=siap-tagih|aktif` → `{PerintahKerja: [...]}` outlet perangkat (bawaan
 *   `siap-tagih`: disetujui pelanggan & belum ditagih; `aktif`: semua yang belum ditagih/dibatalkan).
 * - `GET /api/pos/v1/perintah-kerja/{uuidPerintahKerja}` → `{PerintahKerja: {...}}`; outlet/tenant lain = 404.
 *
 * Bentuk satu perintah kerja: `{Uuid, Nomor, Status, LabelStatus, SiapTagih, DibuatPada, Pelanggan {Uuid, Nama, NoHp
 * tersamar, KodeTier}|null, Kendaraan {Uuid, NomorPolisi, Label}|null, KmMasuk, Keluhan, CatatanQc, TotalDisetujui,
 * Baris [{Uuid, Jenis Jasa|Sparepart, UuidProduk, UuidProdukSatuan, NamaProduk, Jumlah, HargaSatuan, Diskon,
 * UuidKaryawan|null, NamaKaryawan|null, Catatan}]}` (hanya baris yang disetujui). Kasir memuatnya ke keranjang lalu
 * menagihnya lewat outbox `Penjualan.Buat` dengan `UuidPerintahKerja` (mekanik → `Baris[].Staf`).
 */
final class PerintahKerjaKontroler extends Kontroler
{
    public function Daftar(Request $permintaan, PerintahKerjaPos $kueri): JsonResponse
    {
        $valid = $permintaan->validate(['status' => ['nullable', 'string', 'in:siap-tagih,aktif']]);

        return response()->json(['PerintahKerja' => $kueri->Daftar(
            AutentikasiPerangkat::AmbilPerangkat($permintaan)->IdOutlet,
            ($valid['status'] ?? 'siap-tagih') === 'siap-tagih',
        )]);
    }

    public function Ambil(Request $permintaan, string $perintahKerja, PerintahKerjaPos $kueri): JsonResponse
    {
        $hasil = $kueri->Ambil(AutentikasiPerangkat::AmbilPerangkat($permintaan)->IdOutlet, $perintahKerja);
        abort_if($hasil === null, 404);

        return response()->json(['PerintahKerja' => $hasil]);
    }
}

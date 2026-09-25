<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pos\V1;

use App\Domain\Promo\Aksi\LepasVoucherPos;
use App\Domain\Promo\Aksi\PesanVoucherPos;
use App\Http\Kontroler\Kontroler;
use App\Http\Perantara\AutentikasiPerangkat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Voucher di POS (F-16c bagian 2, wajib online §18.4): `POST /api/pos/v1/voucher/pesan` memeriksa & memesan kode untuk
 * penjualan perangkat (`UuidPenjualan` = ULID penjualan yang sedang dibuat), `POST /api/pos/v1/voucher/lepas` melepasnya.
 * Pemakaian final terjadi saat item outbox `Penjualan.Buat` (kolom `Voucher`) diterima.
 */
final class VoucherKontroler extends Kontroler
{
    public function Pesan(Request $permintaan, PesanVoucherPos $pesan): JsonResponse
    {
        $valid = $this->Validasi($permintaan);

        return response()->json($pesan->Jalankan(
            (string) $valid['Kode'],
            strtoupper((string) $valid['UuidPenjualan']),
            AutentikasiPerangkat::AmbilPerangkat($permintaan)->Id,
        ));
    }

    public function Lepas(Request $permintaan, LepasVoucherPos $lepas): Response
    {
        $valid = $this->Validasi($permintaan);
        $lepas->Jalankan((string) $valid['Kode'], strtoupper((string) $valid['UuidPenjualan']));

        return response()->noContent();
    }

    /**
     * @return array{Kode: string, UuidPenjualan: string}
     */
    private function Validasi(Request $permintaan): array
    {
        /** @var array{Kode: string, UuidPenjualan: string} */
        return $permintaan->validate([
            'Kode' => ['required', 'string', 'max:30'],
            'UuidPenjualan' => ['required', 'string', 'ulid'],
        ], attributes: ['Kode' => 'kode voucher']);
    }
}

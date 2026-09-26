<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pos\V1;

use App\Domain\Promo\Kueri\PromoBerlaku;
use App\Http\Kontroler\Kontroler;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `GET /api/pos/v1/promo` (F-16c): promo aktif yang belum berakhir + mode resolusi konflik, disimpan perangkat untuk
 * dievaluasi `MesinPromo` (juga saat offline). Tenant tanpa fitur `promo.mesin` = daftar kosong. `?voucher=1` (aplikasi
 * yang mengenal syarat `WajibVoucher`, F-16c bagian 2) = promo wajib voucher ikut dikirim.
 */
final class PromoKontroler extends Kontroler
{
    public function Ambil(Request $permintaan, PromoBerlaku $berlaku): JsonResponse
    {
        $sekarang = CarbonImmutable::now();

        return response()->json([
            'ModeResolusi' => $berlaku->AmbilMode()->value,
            'Promo' => $berlaku->AmbilUntukPos($sekarang, $permintaan->boolean('voucher'), $permintaan->boolean('lanjutan')),
            'WaktuServer' => $sekarang->utc()->toIso8601ZuluString(),
        ]);
    }
}

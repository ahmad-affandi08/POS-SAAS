<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Kueri;

use App\Domain\Bersama\Status\StatusDataMaster;
use App\Domain\Tenant\Model\HargaPaket;
use Carbon\CarbonInterface;

/**
 * Harga paket untuk sebuah tagihan (dipakai penagihan P-08/F-19), BR-P04.1:
 * - hanya versi Terbit yang berlaku pada tanggal tagihan;
 * - langganan yang dimulai sebelum sebuah versi berlaku tetap memakai harga lamanya (grandfathering),
 *   kecuali versi itu ditandai `TerapkanKePelangganLama`.
 */
final class HargaPaketBerlaku
{
    public function Cari(int $idPaket, CarbonInterface $tanggalTagihan, ?CarbonInterface $langgananMulai = null): ?HargaPaket
    {
        $versi = HargaPaket::query()
            ->where('IdPaket', $idPaket)
            ->where('Status', StatusDataMaster::Terbit->value)
            ->whereDate('BerlakuMulai', '<=', $tanggalTagihan->toDateString())
            ->orderByDesc('BerlakuMulai')
            ->get();

        foreach ($versi as $harga) {
            $sudahBerlakuSaatMulai = $langgananMulai === null || $harga->BerlakuMulai->toDateString() <= $langgananMulai->toDateString();

            if ($sudahBerlakuSaatMulai || $harga->TerapkanKePelangganLama) {
                return $harga;
            }
        }

        return null;
    }
}

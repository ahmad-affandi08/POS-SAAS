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
 *   kecuali versi itu ditandai `TerapkanKePelangganLama`;
 * - bila langganan dimulai sebelum versi harga pertama, versi paling awal yang dipakai (tidak ada harga lebih lama
 *   untuk dikunci), sehingga hasilnya tidak pernah kosong selama paket punya harga berlaku.
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

        // Langganan lebih tua dari semua versi: pakai versi paling awal yang sudah berlaku.
        return $versi->last();
    }

    /** BR-P04.6: paket hanya boleh aktif bila sudah ada harga terbit yang BERLAKU pada tanggal tersebut. */
    public function CekAdaHargaBerlaku(int $idPaket, CarbonInterface $tanggal): bool
    {
        return HargaPaket::query()
            ->where('IdPaket', $idPaket)
            ->where('Status', StatusDataMaster::Terbit->value)
            ->whereDate('BerlakuMulai', '<=', $tanggal->toDateString())
            ->where(fn ($kueri) => $kueri->whereNull('BerlakuSampai')->orWhereDate('BerlakuSampai', '>=', $tanggal->toDateString()))
            ->exists();
    }
}

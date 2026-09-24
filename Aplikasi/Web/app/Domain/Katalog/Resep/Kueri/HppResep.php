<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Resep\Kueri;

use App\Domain\Katalog\Kontrak\PenyediaHppBahan;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Resep\Data\DataHppResep;
use App\Domain\Katalog\Resep\Model\Resep;
use App\Domain\Katalog\Resep\Model\ResepDetail;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Estimasi HPP per satuan dasar produk dari versi resep terbaru (BR-03.5, F-03 C.4, H7 disetujui):
 *
 *     JumlahKotor_d = JumlahDasar_d ÷ (1 − PersenSusut_d/100)     skala 4 (kuantitas), HalfUp
 *     Subtotal_d    = JumlahKotor_d × HppBahan_d                 skala 6 (HPP), HalfUp
 *     HppSatuan     = Σ Subtotal_d ÷ JumlahHasil                 skala 6, HalfUp
 *
 * `HppBahan_d` dari `PenyediaHppBahan` (rata-rata tenant). Bila satu saja bahan belum punya HPP, status
 * `BelumTersedia` dan `HppSatuan` null ("HPP belum tersedia"); sebelum F-05a selalu demikian (H1).
 */
final class HppResep
{
    public function __construct(private readonly PenyediaHppBahan $penyedia) {}

    public function Hitung(Produk $produk): DataHppResep
    {
        $resep = Resep::query()->where('IdProduk', $produk->Id)->orderByDesc('Versi')->first();

        if ($resep === null) {
            return new DataHppResep(DataHppResep::TANPA_RESEP, null, []);
        }

        $detail = ResepDetail::query()->with('ProdukBahan:Id,Nama')->where('IdResep', $resep->Id)->orderBy('Urutan')->get();
        $total = BigDecimal::zero();
        $lengkap = true;
        $baris = [];

        foreach ($detail as $bahan) {
            $jumlahKotor = self::HitungJumlahKotor($bahan->JumlahDasar, $bahan->PersenSusut);
            $hppBahan = $this->penyedia->AmbilHppSatuan($bahan->IdProdukBahan, null);
            $subtotal = null;

            if ($hppBahan === null) {
                $lengkap = false;
            } else {
                $hppBahan = $hppBahan->toScale(6, RoundingMode::HalfUp);
                $subtotal = $jumlahKotor->multipliedBy($hppBahan)->toScale(6, RoundingMode::HalfUp);
                $total = $total->plus($subtotal);
            }

            $baris[] = [
                'NamaBahan' => $bahan->ProdukBahan->Nama,
                'JumlahKotor' => (string) $jumlahKotor,
                'HppSatuanBahan' => $hppBahan === null ? null : (string) $hppBahan,
                'Subtotal' => $subtotal === null ? null : (string) $subtotal,
            ];
        }

        if (! $lengkap) {
            return new DataHppResep(DataHppResep::BELUM_TERSEDIA, null, $baris);
        }

        return new DataHppResep(DataHppResep::TERSEDIA, (string) $total->dividedBy($resep->JumlahHasil, 6, RoundingMode::HalfUp), $baris);
    }

    /** Jumlah kotor bahan termasuk susut (H7): JumlahDasar ÷ (1 − PersenSusut/100), skala 4 HalfUp. */
    public static function HitungJumlahKotor(string $jumlahDasar, string $persenSusut): BigDecimal
    {
        $faktor = BigDecimal::one()->minus(BigDecimal::of($persenSusut)->dividedByExact(100));

        return BigDecimal::of($jumlahDasar)->dividedBy($faktor, 4, RoundingMode::HalfUp);
    }
}

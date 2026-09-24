<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Resep\Layanan;

use App\Domain\Katalog\Resep\Model\Resep;
use App\Domain\Katalog\Resep\Model\ResepDetail;

/**
 * Mencegah resep melingkar (F-03 C.4 `ResepSiklus`): produk A memakai bahan B yang (lewat versi resep terbaru
 * bahan-bahannya, misal produk Produksi setengah jadi) ternyata memakai A lagi. Penelusuran DFS atas versi terbaru.
 */
final class PemeriksaSiklusResep
{
    /**
     * @param  list<int>  $idBahan  bahan langsung resep baru produk `idProduk`
     */
    public function CekSiklus(int $idProduk, array $idBahan): bool
    {
        $dikunjungi = [];
        $tumpukan = array_values(array_unique($idBahan));

        while ($tumpukan !== []) {
            $id = array_pop($tumpukan);

            if ($id === $idProduk) {
                return true;
            }

            if (isset($dikunjungi[$id])) {
                continue;
            }

            $dikunjungi[$id] = true;

            foreach ($this->AmbilBahanVersiTerbaru($id) as $idAnak) {
                if (! isset($dikunjungi[$idAnak])) {
                    $tumpukan[] = $idAnak;
                }
            }
        }

        return false;
    }

    /**
     * @return list<int>
     */
    private function AmbilBahanVersiTerbaru(int $idProduk): array
    {
        $idResep = Resep::query()->where('IdProduk', $idProduk)->orderByDesc('Versi')->value('Id');

        if ($idResep === null) {
            return [];
        }

        /** @var list<int> */
        return ResepDetail::query()->where('IdResep', $idResep)->distinct()->pluck('IdProdukBahan')->map(fn (mixed $id): int => (int) $id)->values()->all();
    }
}

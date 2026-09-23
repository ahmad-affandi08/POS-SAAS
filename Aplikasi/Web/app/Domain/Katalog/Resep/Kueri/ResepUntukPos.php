<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Resep\Kueri;

use App\Domain\Katalog\Data\KonteksKatalogPos;
use App\Domain\Katalog\Kontrak\BagianKatalogPos;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Resep\Model\Resep;
use App\Domain\Katalog\Resep\Model\ResepDetail;
use Illuminate\Database\Query\JoinClause;

/**
 * Bagian katalog POS `Resep` (F-03 D.3): hanya versi terbaru per produk, dengan `Bahan` tertanam (jumlah satuan
 * dasar dan persen susut) untuk pengurangan bahan di POS (F-07). Tanpa `sejak`: versi terbaru produk yang belum
 * dihapus; dengan `sejak`: versi terbaru yang dibuat sejak itu. Versi resep tidak pernah dihapus (BR-03.4).
 */
final class ResepUntukPos implements BagianKatalogPos
{
    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function AmbilBagian(KonteksKatalogPos $konteks): array
    {
        $terbaru = Resep::query()->where('Resep.IdTenant', $konteks->idTenant)->selectRaw('IdProduk, MAX(Versi) AS VersiTerbaru')->groupBy('IdProduk');
        $resep = Resep::query()
            ->where('Resep.IdTenant', $konteks->idTenant)
            ->joinSub($terbaru, 'Terbaru', fn (JoinClause $gabung) => $gabung->on('Terbaru.IdProduk', '=', 'Resep.IdProduk')->on('Terbaru.VersiTerbaru', '=', 'Resep.Versi'))
            ->when($konteks->sejak === null, fn ($kueri) => $kueri->whereIn('Resep.IdProduk', Produk::query()->select('Id')))
            ->when($konteks->sejak !== null, fn ($kueri) => $kueri->where('Resep.DiubahPada', '>=', $konteks->sejak))
            ->orderBy('Resep.IdProduk')
            ->get(['Resep.Id', 'Resep.Uuid', 'Resep.IdProduk', 'Resep.Versi', 'Resep.JumlahHasil']);

        if ($resep->isEmpty()) {
            return ['Resep' => []];
        }

        $detail = ResepDetail::query()->whereIn('IdResep', $resep->pluck('Id')->all())->orderBy('IdResep')->orderBy('Urutan')->get()->groupBy('IdResep');
        $idProduk = $resep->pluck('IdProduk')->merge($detail->flatten()->pluck('IdProdukBahan'))->unique()->values()->all();
        $uuidProduk = Produk::query()->withTrashed()->whereKey($idProduk)->pluck('Uuid', 'Id');

        return ['Resep' => array_values($resep->map(fn (Resep $baris): array => [
            'Uuid' => $baris->Uuid,
            'UuidProduk' => $uuidProduk->get($baris->IdProduk),
            'Versi' => $baris->Versi,
            'JumlahHasil' => $baris->JumlahHasil,
            'Bahan' => array_values(collect($detail->get($baris->Id, []))->map(fn (ResepDetail $bahan): array => [
                'UuidProdukBahan' => $uuidProduk->get($bahan->IdProdukBahan),
                'JumlahDasar' => $bahan->JumlahDasar,
                'PersenSusut' => $bahan->PersenSusut,
            ])->all()),
        ])->all())];
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Layanan;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Penjualan\Enum\JenisMetodePembayaran;
use App\Domain\Penjualan\Enum\StatusPenjualan;
use App\Domain\Penjualan\Model\MetodePembayaran;
use App\Domain\Penjualan\Model\Penjualan;
use App\Domain\Penjualan\Model\PenjualanPembayaran;
use App\Domain\Penjualan\Model\ReturPenjualan;
use App\Domain\Penjualan\Model\ReturPenjualanPembayaran;

/**
 * K-11 tukar barang: penjualan pengganti yang dibayar metode `Tukar` merujuk retur asalnya (`UuidReturTukar`). Nilai
 * tukar yang dipakai semua penjualan pengganti (yang tidak di-void) tidak boleh melebihi refund `Tukar` retur itu.
 * Pelanggaran tidak menolak penjualan (barang sudah diserahkan, §18.3) melainkan menjadi tinjauan `TukarBermasalah`;
 * saldo akun `KliringTukarBarang` yang tidak nol memperlihatkan selisihnya di neraca.
 */
final class PemeriksaTukarBarang
{
    /**
     * Retur tukar (dikunci) beserta masalahnya. Retur belum diterima server = null + masalah.
     *
     * @return array{0: ReturPenjualan|null, 1: list<string>}
     */
    public function Periksa(?string $uuidRetur, Uang $jumlahTukar, int $idOutlet): array
    {
        if ($uuidRetur === null) {
            return [null, []];
        }

        $retur = ReturPenjualan::query()->where('Uuid', $uuidRetur)->lockForUpdate()->first();

        if (! $retur instanceof ReturPenjualan) {
            return [null, ['retur tukar belum diterima server']];
        }

        $masalah = [];

        if ($retur->IdOutlet !== $idOutlet) {
            $masalah[] = 'retur tukar berasal dari outlet lain';
        }

        $idMetodeTukar = MetodePembayaran::query()->where('Jenis', JenisMetodePembayaran::Tukar->value)->pluck('Id')->all();
        $tersedia = Uang::Dari((string) ReturPenjualanPembayaran::query()
            ->where('IdReturPenjualan', $retur->Id)
            ->whereIn('IdMetodePembayaran', $idMetodeTukar)
            ->sum('Jumlah'));
        $idPenjualanLain = Penjualan::query()
            ->where('IdReturTukar', $retur->Id)
            ->where('Status', '!=', StatusPenjualan::Void->value)
            ->pluck('Id')
            ->all();
        $dipakai = Uang::Dari((string) PenjualanPembayaran::query()
            ->whereIn('IdPenjualan', $idPenjualanLain)
            ->whereIn('IdMetodePembayaran', $idMetodeTukar)
            ->sum('Jumlah'));

        if ($dipakai->Tambah($jumlahTukar)->Bandingkan($tersedia) > 0) {
            $masalah[] = "nilai tukar {$dipakai->Tambah($jumlahTukar)->FormatRupiah()} melebihi nilai retur {$tersedia->FormatRupiah()}";
        }

        return [$retur, $masalah];
    }
}

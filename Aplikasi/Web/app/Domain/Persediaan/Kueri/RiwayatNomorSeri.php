<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Kueri;

use App\Domain\Katalog\Kueri\InfoProdukStok;
use App\Domain\Organisasi\Kueri\InfoGudang;
use App\Domain\Persediaan\Enum\JenisReferensiMutasi;
use App\Domain\Persediaan\Model\MutasiStok;
use App\Domain\Persediaan\Model\NomorSeri;
use Illuminate\Database\Eloquent\Builder;

/**
 * F-05h: pencarian nomor seri/IMEI dan riwayat satu nomor seri dari masuk sampai terjual/diretur, dibaca dari buku stok
 * (`MutasiStok`, hanya yang menyebut nomor itu). Dibatasi lokasi stok di outlet yang boleh diakses: nomor seri yang tidak
 * pernah bergerak di lokasi itu tidak terlihat (tenant lain tidak dikenal lewat scope `MilikTenant`).
 */
final class RiwayatNomorSeri
{
    public const BATAS_HASIL = 50;

    public function __construct(
        private readonly InfoGudang $infoGudang,
        private readonly InfoProdukStok $infoProduk,
    ) {}

    /**
     * @param  list<int>|null  $idOutletBoleh
     * @return list<array{Uuid: string, Nomor: string, UuidProduk: string, NamaProduk: string, Sku: string|null, Status: string, LabelStatus: string, NamaGudang: string|null, NomorPenjualan: string|null, TanggalJual: string|null}>
     */
    public function Cari(string $cari, ?array $idOutletBoleh): array
    {
        $cari = trim($cari);

        if ($cari === '') {
            return [];
        }

        $seri = $this->KueriBoleh($idOutletBoleh)
            ->where('Nomor', 'like', '%'.addcslashes($cari, '%_\\').'%')
            ->orderBy('Nomor')
            ->limit(self::BATAS_HASIL)
            ->get();

        return $this->Petakan($seri->all());
    }

    /**
     * @param  list<int>|null  $idOutletBoleh
     * @return array{Unit: array<string, mixed>, Riwayat: list<array{Tanggal: string, Jenis: string, Arah: string, NomorDokumen: string|null, NamaGudang: string|null}>}|null
     */
    public function Ambil(string $uuid, ?array $idOutletBoleh): ?array
    {
        $seri = $this->KueriBoleh($idOutletBoleh)->where('Uuid', $uuid)->first();

        if ($seri === null) {
            return null;
        }

        $semuaGudang = $this->infoGudang->AmbilBanyak(array_values(array_unique(MutasiStok::query()->where('IdNomorSeri', $seri->Id)->pluck('IdGudang')->all())));
        $riwayat = array_values(MutasiStok::query()->where('IdNomorSeri', $seri->Id)->orderBy('Id')->get()->map(fn (MutasiStok $m): array => [
            'Tanggal' => $m->TanggalBisnis->toDateString(),
            'Jenis' => $m->JenisMutasi->AmbilLabel(),
            'Arah' => $m->Jumlah !== null && str_starts_with((string) $m->Jumlah, '-') ? 'Keluar' : 'Masuk',
            'NomorDokumen' => $m->NomorReferensi,
            'NamaGudang' => ($semuaGudang[$m->IdGudang] ?? null)?->nama,
        ])->all());

        return ['Unit' => $this->Petakan([$seri])[0], 'Riwayat' => $riwayat];
    }

    /**
     * @param  list<int>|null  $idOutletBoleh
     * @return Builder<NomorSeri>
     */
    private function KueriBoleh(?array $idOutletBoleh): Builder
    {
        $kueri = NomorSeri::query();

        if ($idOutletBoleh !== null) {
            $idGudang = array_map(fn ($g): int => $g->id, $this->infoGudang->AmbilBoleh($idOutletBoleh, false));
            $kueri->whereIn('Id', MutasiStok::query()->whereIn('IdGudang', $idGudang)->whereNotNull('IdNomorSeri')->select('IdNomorSeri'));
        }

        return $kueri;
    }

    /**
     * @param  list<NomorSeri>  $seri
     * @return list<array<string, mixed>>
     */
    private function Petakan(array $seri): array
    {
        if ($seri === []) {
            return [];
        }

        $idSeri = array_map(fn (NomorSeri $s): int => $s->Id, $seri);
        $produk = $this->infoProduk->AmbilBanyak(array_values(array_unique(array_map(fn (NomorSeri $s): int => $s->IdProduk, $seri))));
        $gudangSeri = $this->infoGudang->AmbilBanyak(array_values(array_filter(array_unique(array_map(fn (NomorSeri $s): ?int => $s->IdGudang, $seri)))));
        $jual = [];

        foreach (MutasiStok::query()->whereIn('IdNomorSeri', $idSeri)->where('JenisReferensi', JenisReferensiMutasi::Penjualan->value)->orderBy('Id')->get(['IdNomorSeri', 'NomorReferensi', 'TanggalBisnis']) as $m) {
            $jual[$m->IdNomorSeri] = $m;
        }

        return array_values(array_map(function (NomorSeri $s) use ($produk, $gudangSeri, $jual): array {
            $p = $produk[$s->IdProduk] ?? null;
            $terjual = $s->Status->value === 'Terjual' ? ($jual[$s->Id] ?? null) : null;

            return [
                'Uuid' => $s->Uuid,
                'Nomor' => $s->Nomor,
                'UuidProduk' => $p->uuid ?? '',
                'NamaProduk' => $p->nama ?? 'Produk tidak dikenal',
                'Sku' => $p?->sku,
                'Status' => $s->Status->value,
                'LabelStatus' => $s->Status->AmbilLabel(),
                'NamaGudang' => $s->IdGudang === null ? null : ($gudangSeri[$s->IdGudang] ?? null)?->nama,
                'NomorPenjualan' => $terjual?->NomorReferensi,
                'TanggalJual' => $terjual?->TanggalBisnis->toDateString(),
            ];
        }, $seri));
    }
}

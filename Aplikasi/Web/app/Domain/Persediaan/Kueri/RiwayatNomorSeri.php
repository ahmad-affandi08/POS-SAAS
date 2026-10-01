<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Kueri;

use App\Domain\Katalog\Kueri\InfoProdukStok;
use App\Domain\Organisasi\Kueri\InfoGudang;
use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use App\Domain\Penjualan\Kueri\InfoPenjualanNomorSeri;
use App\Domain\Persediaan\Enum\JenisReferensiMutasi;
use App\Domain\Persediaan\Enum\StatusNomorSeri;
use App\Domain\Persediaan\Model\MutasiStok;
use App\Domain\Persediaan\Model\NomorSeri;
use Illuminate\Database\Eloquent\Builder;

/**
 * F-05h: pencarian nomor seri/IMEI dan riwayat satu nomor seri dari masuk sampai terjual/diretur, dibaca dari buku stok
 * (`MutasiStok`, hanya yang menyebut nomor itu). Dibatasi lokasi stok di outlet yang boleh diakses: nomor seri yang tidak
 * pernah bergerak di lokasi itu tidak terlihat (tenant lain tidak dikenal lewat scope `MilikTenant`).
 *
 * Pencarian mencocokkan potongan nomor **atau** nama/SKU/barcode produk (PRD v3.13), bisa disaring status dan produk,
 * sehingga pertanyaan "IMEI apa saja yang tersedia untuk produk ini" dan "unit ini milik siapa, garansinya sampai kapan"
 * terjawab dari satu tempat. Unit yang terjual membawa penjualan, pembeli, dan garansinya dari `InfoPenjualanNomorSeri`.
 */
final class RiwayatNomorSeri
{
    public const BATAS_HASIL = 50;

    public const BATAS_EKSPOR = 5000;

    public function __construct(
        private readonly InfoGudang $infoGudang,
        private readonly InfoProdukStok $infoProduk,
        private readonly InfoPenjualanNomorSeri $infoPenjualan,
        private readonly TanggalBisnisOutlet $tanggalBisnis,
    ) {}

    /**
     * @param  list<int>|null  $idOutletBoleh
     * @param  bool  $termasukProduk  false = hanya mencocokkan nomor (pencarian cepat Ctrl+K, supaya nama produk tidak membanjiri hasil)
     * @return array{Baris: list<array<string, mixed>>, Total: int}
     */
    public function Cari(string $cari, ?array $idOutletBoleh, ?string $status = null, ?string $uuidProduk = null, int $batas = self::BATAS_HASIL, bool $termasukProduk = true): array
    {
        $cari = trim($cari);
        $uuidProduk = $uuidProduk === null || trim($uuidProduk) === '' ? null : trim($uuidProduk);

        if ($cari === '' && $uuidProduk === null) {
            return ['Baris' => [], 'Total' => 0];
        }

        $kueri = $this->KueriBoleh($idOutletBoleh);

        if ($cari !== '') {
            $pola = '%'.addcslashes($cari, '%_\\').'%';
            $kueri->where(fn (Builder $dalam) => $termasukProduk
                ? $dalam->where('Nomor', 'like', $pola)->orWhereIn('IdProduk', $this->infoProduk->KueriIdNama($cari)->select('Id'))
                : $dalam->where('Nomor', 'like', $pola));
        }

        if ($uuidProduk !== null) {
            $produk = $this->infoProduk->AmbilDariUuid([$uuidProduk])[$uuidProduk] ?? null;
            $kueri->where('IdProduk', $produk === null ? 0 : $produk->id);
        }

        $statusSah = $status === null ? null : StatusNomorSeri::tryFrom($status);

        if ($statusSah !== null) {
            $kueri->where('Status', $statusSah->value);
        }

        $total = (clone $kueri)->count();
        $seri = $kueri->orderBy('Nomor')->orderBy('Id')->limit(max(1, $batas))->get();

        return ['Baris' => $this->Petakan(array_values($seri->all())), 'Total' => $total];
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
        $produk = $this->infoProduk->AmbilBanyak(array_values(array_unique(array_map(fn (NomorSeri $s): int => $s->IdProduk, $seri))), true);
        $gudangSeri = $this->infoGudang->AmbilBanyak(array_values(array_filter(array_unique(array_map(fn (NomorSeri $s): ?int => $s->IdGudang, $seri)))));
        $penjualan = $this->infoPenjualan->AmbilBanyak(array_values(array_filter(array_map(fn (NomorSeri $s): ?int => $s->IdPenjualanDetail, $seri))));
        $hariIni = $this->tanggalBisnis->Hitung(null)->toDateString();
        $jual = [];

        // Cadangan untuk unit terjual yang belum tertaut ke baris penjualan (data sebelum penautan): nomor & tanggal dari buku stok.
        foreach (MutasiStok::query()->whereIn('IdNomorSeri', $idSeri)->where('JenisReferensi', JenisReferensiMutasi::Penjualan->value)->orderBy('Id')->get(['IdNomorSeri', 'NomorReferensi', 'TanggalBisnis']) as $m) {
            $jual[$m->IdNomorSeri] = $m;
        }

        return array_values(array_map(function (NomorSeri $s) use ($produk, $gudangSeri, $penjualan, $jual, $hariIni): array {
            $p = $produk[$s->IdProduk] ?? null;
            $terjual = $s->Status === StatusNomorSeri::Terjual;
            $info = $terjual && $s->IdPenjualanDetail !== null ? ($penjualan[$s->IdPenjualanDetail] ?? null) : null;
            $buku = $terjual ? ($jual[$s->Id] ?? null) : null;
            $garansiSampai = $info['GaransiSampai'] ?? null;
            $gudang = $s->IdGudang === null ? null : ($gudangSeri[$s->IdGudang] ?? null);

            return [
                'Uuid' => $s->Uuid,
                'Nomor' => $s->Nomor,
                'UuidProduk' => $p->uuid ?? '',
                'NamaProduk' => $p->nama ?? 'Produk tidak dikenal',
                'Sku' => $p?->sku,
                'Status' => $s->Status->value,
                'LabelStatus' => $s->Status->AmbilLabel(),
                'UuidGudang' => $gudang?->uuid,
                'NamaGudang' => $gudang?->nama,
                'UuidPenjualan' => $info['UuidPenjualan'] ?? null,
                'NomorPenjualan' => $info['NomorPenjualan'] ?? $buku?->NomorReferensi,
                'TanggalJual' => $info['TanggalJual'] ?? $buku?->TanggalBisnis->toDateString(),
                'UuidPelanggan' => $info['UuidPelanggan'] ?? null,
                'NamaPelanggan' => $info['NamaPelanggan'] ?? null,
                'MasaGaransiBulan' => $info['MasaGaransiBulan'] ?? null,
                'GaransiSampai' => $garansiSampai,
                // Aktif selama tanggal bisnis hari ini belum melewati tanggal garansi; dibandingkan sebagai teks Y-m-d.
                'StatusGaransi' => $garansiSampai === null ? null : ($hariIni <= $garansiSampai ? 'Aktif' : 'Berakhir'),
            ];
        }, $seri));
    }
}

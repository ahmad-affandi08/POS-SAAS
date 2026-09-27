<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Kueri;

use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Bersama\Tabel\Layanan\PenerapKueriTabel;
use App\Domain\Organisasi\Kueri\InfoGudang;
use App\Domain\Persediaan\Enum\StatusOrderProduksi;
use App\Domain\Persediaan\Model\OrderProduksi;
use Illuminate\Support\Collection;

/**
 * Daftar order produksi untuk `TabelData` (F-05e), bawaan terbaru dulu. Hanya lokasi di outlet yang boleh diakses.
 * Cari: nomor, nama/SKU produk hasil, nomor batch. Saring: Status, Gudang.
 */
final class DaftarOrderProduksi
{
    public const KOLOM_URUT = ['Tanggal', 'Nomor', 'NilaiHasil'];

    public const KOLOM_SARING = ['Status', 'Gudang'];

    public const URUT_BAWAAN = '-Tanggal';

    public function __construct(private readonly InfoGudang $infoGudang) {}

    /**
     * @param  list<int>|null  $idOutletBoleh
     * @return array{Data: list<array<string, mixed>>, Meta: array{Halaman: int, PerHalaman: int, Total: int, JumlahHalaman: int}}
     */
    public function AmbilTabel(DataPermintaanTabel $permintaan, ?array $idOutletBoleh): array
    {
        $kata = $permintaan->cari;
        $status = $permintaan->AmbilDaftar('Status', array_map(fn (StatusOrderProduksi $s): string => $s->value, StatusOrderProduksi::cases()));
        $uuidGudang = $permintaan->saring['Gudang'] ?? null;
        $idGudang = $uuidGudang === null ? null : (($this->infoGudang->AmbilDariUuid([$uuidGudang])[$uuidGudang] ?? null)->id ?? 0);
        $pola = PenerapKueriTabel::PolaCari($kata);

        $kueri = OrderProduksi::query()
            ->when($idOutletBoleh !== null, fn ($k) => $k->whereIn('IdOutlet', $idOutletBoleh ?? []))
            ->when($status !== [], fn ($k) => $k->whereIn('Status', $status))
            ->when($idGudang !== null, fn ($k) => $k->where('IdGudang', $idGudang))
            ->when($kata !== '', fn ($k) => $k->where(fn ($d) => $d
                ->where('Nomor', 'like', $pola)
                ->orWhere('NamaProduk', 'like', $pola)
                ->orWhere('Sku', 'like', $pola)
                ->orWhere('NomorBatch', 'like', $pola)));

        return PenerapKueriTabel::Terapkan($kueri, $permintaan, ['Tanggal' => 'Tanggal', 'Nomor' => 'Nomor', 'NilaiHasil' => 'NilaiHasil'], fn (Collection $dokumen): array => $this->Petakan(array_values($dokumen->all())));
    }

    /**
     * @param  list<OrderProduksi>  $dokumen
     * @return list<array<string, mixed>>
     */
    private function Petakan(array $dokumen): array
    {
        $gudang = $this->infoGudang->AmbilBanyak(array_values(array_unique(array_map(fn (OrderProduksi $o): int => $o->IdGudang, $dokumen))));

        return array_map(fn (OrderProduksi $o): array => [
            'Uuid' => $o->Uuid,
            'Nomor' => $o->Nomor,
            'Tanggal' => $o->Tanggal->format('Y-m-d'),
            'NamaGudang' => $gudang[$o->IdGudang]->nama ?? '',
            'NamaOutlet' => $gudang[$o->IdGudang]->namaOutlet ?? null,
            'NamaProduk' => $o->NamaProduk,
            'Sku' => $o->Sku,
            'JumlahHasil' => $o->JumlahHasil,
            'NomorBatch' => $o->NomorBatch,
            'Status' => $o->Status->value,
            'LabelStatus' => $o->Status->AmbilLabel(),
            'NilaiHasil' => $o->NilaiHasil,
            'HppSatuanHasil' => $o->HppSatuanHasil,
            'DiubahPada' => $o->DiubahPada?->toIso8601String() ?? '',
        ], $dokumen);
    }
}

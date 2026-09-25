<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Kueri;

use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Bersama\Tabel\Layanan\PenerapKueriTabel;
use App\Domain\Organisasi\Kueri\InfoGudang;
use App\Domain\Persediaan\Enum\AlasanPenyesuaian;
use App\Domain\Persediaan\Enum\StatusPenyesuaianStok;
use App\Domain\Persediaan\Model\PenyesuaianStok;
use App\Domain\Persediaan\Model\PenyesuaianStokDetail;
use Illuminate\Support\Collection;

/**
 * Daftar penyesuaian stok untuk `TabelData` (D-16; tipe FE `BarisDaftarPenyesuaianStok`), bawaan terbaru dulu. Hanya
 * lokasi di outlet yang boleh diakses. Cari: nomor, keterangan, nama/SKU produk. Saring: Status, Alasan, Gudang.
 */
final class DaftarPenyesuaianStok
{
    public const KOLOM_URUT = ['Tanggal', 'Nomor', 'NilaiPerkiraan'];

    public const KOLOM_SARING = ['Status', 'Alasan', 'Gudang'];

    public const URUT_BAWAAN = '-Tanggal';

    public function __construct(private readonly InfoGudang $infoGudang) {}

    /**
     * @param  list<int>|null  $idOutletBoleh
     * @return array{Data: list<array<string, mixed>>, Meta: array{Halaman: int, PerHalaman: int, Total: int, JumlahHalaman: int}}
     */
    public function AmbilTabel(DataPermintaanTabel $permintaan, ?array $idOutletBoleh): array
    {
        $kata = $permintaan->cari;
        $status = $permintaan->AmbilDaftar('Status', array_map(fn (StatusPenyesuaianStok $s): string => $s->value, StatusPenyesuaianStok::cases()));
        $alasan = $permintaan->AmbilDaftar('Alasan', array_map(fn (AlasanPenyesuaian $a): string => $a->value, AlasanPenyesuaian::cases()));
        $uuidGudang = $permintaan->saring['Gudang'] ?? null;
        $idGudang = $uuidGudang === null ? null : (($this->infoGudang->AmbilDariUuid([$uuidGudang])[$uuidGudang] ?? null)->id ?? 0);
        $pola = PenerapKueriTabel::PolaCari($kata);

        $kueri = PenyesuaianStok::query()
            ->when($idOutletBoleh !== null, fn ($k) => $k->whereIn('IdOutlet', $idOutletBoleh ?? []))
            ->when($status !== [], fn ($k) => $k->whereIn('Status', $status))
            ->when($alasan !== [], fn ($k) => $k->whereIn('KodeAlasan', $alasan))
            ->when($idGudang !== null, fn ($k) => $k->where('IdGudang', $idGudang))
            ->when($kata !== '', fn ($k) => $k->where(fn ($d) => $d
                ->where('Nomor', 'like', $pola)
                ->orWhere('Keterangan', 'like', $pola)
                ->orWhereIn('Id', PenyesuaianStokDetail::query()->select('IdPenyesuaianStok')->where(fn ($x) => $x->where('NamaProduk', 'like', $pola)->orWhere('Sku', 'like', $pola)))));

        return PenerapKueriTabel::Terapkan($kueri, $permintaan, ['Tanggal' => 'Tanggal', 'Nomor' => 'Nomor', 'NilaiPerkiraan' => 'NilaiPerkiraan'], fn (Collection $dokumen): array => $this->Petakan(array_values($dokumen->all())));
    }

    /**
     * @param  list<PenyesuaianStok>  $dokumen
     * @return list<array<string, mixed>>
     */
    private function Petakan(array $dokumen): array
    {
        $gudang = $this->infoGudang->AmbilBanyak(array_values(array_unique(array_map(fn (PenyesuaianStok $p): int => $p->IdGudang, $dokumen))));

        return array_map(fn (PenyesuaianStok $p): array => [
            'Uuid' => $p->Uuid,
            'Nomor' => $p->Nomor,
            'Tanggal' => $p->Tanggal->format('Y-m-d'),
            'NamaGudang' => $gudang[$p->IdGudang]->nama ?? '',
            'NamaOutlet' => $gudang[$p->IdGudang]->namaOutlet ?? null,
            'KodeAlasan' => $p->KodeAlasan->value,
            'LabelAlasan' => $p->KodeAlasan->AmbilLabel(),
            'Status' => $p->Status->value,
            'LabelStatus' => $p->Status->AmbilLabel(),
            'JumlahBaris' => $p->JumlahBaris,
            'NilaiPerkiraan' => $p->NilaiPerkiraan,
            'DiubahPada' => $p->DiubahPada?->toIso8601String() ?? '',
        ], $dokumen);
    }
}

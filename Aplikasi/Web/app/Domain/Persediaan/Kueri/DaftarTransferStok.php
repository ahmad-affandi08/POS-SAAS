<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Kueri;

use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Bersama\Tabel\Layanan\PenerapKueriTabel;
use App\Domain\Organisasi\Kueri\InfoGudang;
use App\Domain\Persediaan\Enum\StatusTransferStok;
use App\Domain\Persediaan\Model\TransferStok;
use App\Domain\Persediaan\Model\TransferStokDetail;
use Illuminate\Support\Collection;

/**
 * Daftar transfer stok untuk `TabelData` (D-16; tipe FE `BarisDaftarTransferStok`), bawaan terbaru dulu. Hanya transfer
 * yang lokasi asal atau tujuannya di outlet yang boleh diakses (null = semua). Cari: nomor, catatan, nama/SKU produk.
 * Saring: Status (pilihan banyak), Gudang (Uuid lokasi asal atau tujuan).
 */
final class DaftarTransferStok
{
    public const KOLOM_URUT = ['Tanggal', 'Nomor', 'TotalNilaiKirim'];

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
        $status = $permintaan->AmbilDaftar('Status', array_map(fn (StatusTransferStok $s): string => $s->value, StatusTransferStok::cases()));
        $uuidGudang = $permintaan->saring['Gudang'] ?? null;
        $idGudang = $uuidGudang === null ? null : (($this->infoGudang->AmbilDariUuid([$uuidGudang])[$uuidGudang] ?? null)->id ?? 0);
        $pola = PenerapKueriTabel::PolaCari($kata);

        $kueri = TransferStok::query()
            ->when($idOutletBoleh !== null, fn ($k) => $k->where(fn ($d) => $d->whereIn('IdOutletAsal', $idOutletBoleh ?? [])->orWhereIn('IdOutletTujuan', $idOutletBoleh ?? [])))
            ->when($status !== [], fn ($k) => $k->whereIn('Status', $status))
            ->when($idGudang !== null, fn ($k) => $k->where(fn ($d) => $d->where('IdGudangAsal', $idGudang)->orWhere('IdGudangTujuan', $idGudang)))
            ->when($kata !== '', fn ($k) => $k->where(fn ($d) => $d
                ->where('Nomor', 'like', $pola)
                ->orWhere('Catatan', 'like', $pola)
                ->orWhereIn('Id', TransferStokDetail::query()->select('IdTransferStok')->where(fn ($x) => $x->where('NamaProduk', 'like', $pola)->orWhere('Sku', 'like', $pola)))));

        return PenerapKueriTabel::Terapkan($kueri, $permintaan, ['Tanggal' => 'Tanggal', 'Nomor' => 'Nomor', 'TotalNilaiKirim' => 'TotalNilaiKirim'], fn (Collection $dokumen): array => $this->Petakan(array_values($dokumen->all())));
    }

    /**
     * @param  list<TransferStok>  $dokumen
     * @return list<array<string, mixed>>
     */
    private function Petakan(array $dokumen): array
    {
        $gudang = $this->infoGudang->AmbilBanyak(array_values(array_unique([...array_map(fn (TransferStok $t): int => $t->IdGudangAsal, $dokumen), ...array_map(fn (TransferStok $t): int => $t->IdGudangTujuan, $dokumen)])));

        return array_map(fn (TransferStok $t): array => [
            'Uuid' => $t->Uuid,
            'Nomor' => $t->Nomor,
            'Tanggal' => $t->Tanggal->format('Y-m-d'),
            'NamaGudangAsal' => $gudang[$t->IdGudangAsal]->nama ?? '',
            'NamaOutletAsal' => $gudang[$t->IdGudangAsal]->namaOutlet ?? null,
            'NamaGudangTujuan' => $gudang[$t->IdGudangTujuan]->nama ?? '',
            'NamaOutletTujuan' => $gudang[$t->IdGudangTujuan]->namaOutlet ?? null,
            'Status' => $t->Status->value,
            'LabelStatus' => $t->Status->AmbilLabel(),
            'JumlahBaris' => $t->JumlahBaris,
            'TotalNilaiKirim' => $t->TotalNilaiKirim,
            'DiubahPada' => $t->DiubahPada?->toIso8601String() ?? '',
        ], $dokumen);
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Kueri;

use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Bersama\Tabel\Layanan\PenerapKueriTabel;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Organisasi\Kueri\DaftarAnggota;
use App\Domain\Organisasi\Kueri\InfoGudang;
use App\Domain\Persediaan\Enum\StatusStokAwal;
use App\Domain\Persediaan\Model\StokAwal;
use App\Domain\Persediaan\Model\StokAwalDetail;
use Illuminate\Support\Collection;

/**
 * Daftar dokumen stok awal untuk `TabelData` (D-16; tipe FE `PropsDaftarStokAwal['StokAwal']`, DesainF05a C.6.6),
 * bawaan terbaru dulu. Hanya lokasi stok di outlet yang boleh diakses (null = semua). Cari: nomor, catatan, atau
 * nama/SKU produk di barisnya. Saring: Status (pilihan banyak), Gudang (Uuid lokasi stok).
 */
final class DaftarStokAwal
{
    public const KOLOM_URUT = ['Tanggal', 'Nomor', 'TotalNilai'];

    public const KOLOM_SARING = ['Status', 'Gudang'];

    public const URUT_BAWAAN = '-Tanggal';

    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly InfoGudang $infoGudang,
        private readonly DaftarAnggota $anggota,
    ) {}

    /**
     * @param  list<int>|null  $idOutletBoleh
     * @return array{Data: list<array<string, mixed>>, Meta: array{Halaman: int, PerHalaman: int, Total: int, JumlahHalaman: int}}
     */
    public function AmbilTabel(DataPermintaanTabel $permintaan, ?array $idOutletBoleh): array
    {
        $kata = $permintaan->cari;
        $status = $permintaan->AmbilDaftar('Status', array_map(fn (StatusStokAwal $s): string => $s->value, StatusStokAwal::cases()));
        $uuidGudang = $permintaan->saring['Gudang'] ?? null;
        $idGudang = $uuidGudang === null ? null : (($this->infoGudang->AmbilDariUuid([$uuidGudang])[$uuidGudang] ?? null)->id ?? 0);
        $pola = PenerapKueriTabel::PolaCari($kata);

        $kueri = StokAwal::query()
            ->when($idOutletBoleh !== null, fn ($kueri) => $kueri->whereIn('IdOutlet', $idOutletBoleh ?? []))
            ->when($status !== [], fn ($kueri) => $kueri->whereIn('Status', $status))
            ->when($idGudang !== null, fn ($kueri) => $kueri->where('IdGudang', $idGudang))
            ->when($kata !== '', fn ($kueri) => $kueri->where(fn ($dalam) => $dalam
                ->where('Nomor', 'like', $pola)
                ->orWhere('Catatan', 'like', $pola)
                ->orWhereIn('Id', StokAwalDetail::query()->select('IdStokAwal')->where(fn ($detail) => $detail
                    ->where('NamaProduk', 'like', $pola)
                    ->orWhere('Sku', 'like', $pola)))));

        return PenerapKueriTabel::Terapkan($kueri, $permintaan, ['Tanggal' => 'Tanggal', 'Nomor' => 'Nomor', 'TotalNilai' => 'TotalNilai'], fn (Collection $dokumen): array => $this->Petakan(array_values($dokumen->all())));
    }

    /**
     * @param  list<StokAwal>  $dokumen
     * @return list<array<string, mixed>>
     */
    private function Petakan(array $dokumen): array
    {
        $gudang = $this->infoGudang->AmbilBanyak(array_values(array_unique(array_map(fn (StokAwal $s): int => $s->IdGudang, $dokumen))));
        $nama = $this->anggota->AmbilNamaPengguna($this->konteks->Wajib(), array_values(array_unique(array_filter(array_map(fn (StokAwal $s): ?int => $s->DibuatOleh, $dokumen), 'is_int'))));

        return array_map(fn (StokAwal $s): array => [
            'Uuid' => $s->Uuid,
            'Nomor' => $s->Nomor,
            'Tanggal' => $s->Tanggal->format('Y-m-d'),
            'NamaGudang' => $gudang[$s->IdGudang]->nama ?? '',
            'NamaOutlet' => $gudang[$s->IdGudang]->namaOutlet ?? null,
            'Status' => $s->Status->value,
            'LabelStatus' => $s->Status->AmbilLabel(),
            'Sumber' => $s->Sumber->value,
            'JumlahBaris' => $s->JumlahBaris,
            'TotalNilai' => $s->TotalNilai,
            'DibuatOleh' => $s->DibuatOleh === null ? null : ($nama[$s->DibuatOleh] ?? null),
            'DiubahPada' => $s->DiubahPada?->toIso8601String() ?? '',
        ], $dokumen);
    }
}

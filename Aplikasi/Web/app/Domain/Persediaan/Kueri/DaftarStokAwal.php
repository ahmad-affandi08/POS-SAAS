<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Kueri;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Organisasi\Kueri\DaftarAnggota;
use App\Domain\Organisasi\Kueri\InfoGudang;
use App\Domain\Persediaan\Enum\StatusStokAwal;
use App\Domain\Persediaan\Model\StokAwal;
use App\Domain\Persediaan\Model\StokAwalDetail;

/**
 * Daftar dokumen stok awal berhalaman (tipe FE `PropsDaftarStokAwal['StokAwal']`, DesainF05a C.6.6), terbaru dulu
 * (Tanggal, lalu Id). Hanya lokasi stok di outlet yang boleh diakses (null = semua). Saringan: Kata (nomor, catatan,
 * atau nama/SKU produk di barisnya), Status (`Semua` = semua status), lokasi stok (Uuid).
 */
final class DaftarStokAwal
{
    public const PER_HALAMAN = 20;

    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly InfoGudang $infoGudang,
        private readonly DaftarAnggota $anggota,
    ) {}

    /**
     * @param  array{Kata: string, Status: string, UuidGudang: string|null}  $saring
     * @param  list<int>|null  $idOutletBoleh
     * @return array{Data: list<array<string, mixed>>, HalamanSaatIni: int, HalamanTerakhir: int, Total: int}
     */
    public function Ambil(array $saring, ?array $idOutletBoleh, int $halaman): array
    {
        $kata = trim($saring['Kata']);
        $status = StatusStokAwal::tryFrom($saring['Status']);
        $idGudang = null;

        if ($saring['UuidGudang'] !== null) {
            $idGudang = ($this->infoGudang->AmbilDariUuid([$saring['UuidGudang']])[$saring['UuidGudang']] ?? null)->id ?? 0;
        }

        $pola = '%'.addcslashes($kata, '%_\\').'%';
        $halamanData = StokAwal::query()
            ->when($idOutletBoleh !== null, fn ($kueri) => $kueri->whereIn('IdOutlet', $idOutletBoleh ?? []))
            ->when($status !== null, fn ($kueri) => $kueri->where('Status', $status?->value))
            ->when($idGudang !== null, fn ($kueri) => $kueri->where('IdGudang', $idGudang))
            ->when($kata !== '', fn ($kueri) => $kueri->where(fn ($dalam) => $dalam
                ->where('Nomor', 'like', $pola)
                ->orWhere('Catatan', 'like', $pola)
                ->orWhereIn('Id', StokAwalDetail::query()->select('IdStokAwal')->where(fn ($detail) => $detail
                    ->where('NamaProduk', 'like', $pola)
                    ->orWhere('Sku', 'like', $pola)))))
            ->orderByDesc('Tanggal')
            ->orderByDesc('Id')
            ->paginate(self::PER_HALAMAN, ['*'], 'halaman', max(1, $halaman));

        /** @var list<StokAwal> $dokumen */
        $dokumen = array_values($halamanData->items());
        $gudang = $this->infoGudang->AmbilBanyak(array_values(array_unique(array_map(fn (StokAwal $s): int => $s->IdGudang, $dokumen))));
        $nama = $this->anggota->AmbilNamaPengguna($this->konteks->Wajib(), array_values(array_unique(array_filter(array_map(fn (StokAwal $s): ?int => $s->DibuatOleh, $dokumen), 'is_int'))));

        $data = array_map(fn (StokAwal $s): array => [
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

        return [
            'Data' => $data,
            'HalamanSaatIni' => $halamanData->currentPage(),
            'HalamanTerakhir' => $halamanData->lastPage(),
            'Total' => $halamanData->total(),
        ];
    }
}

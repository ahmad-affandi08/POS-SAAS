<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kueri;

use App\Domain\Bersama\Dokumen\Enum\StatusDokumenTerposting;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Bersama\Tabel\Layanan\PenerapKueriTabel;
use App\Domain\Organisasi\Kueri\PetaUuidOutlet;
use App\Domain\Penjualan\Model\MetodePembayaran;
use App\Domain\Penjualan\Model\Pencairan;
use Illuminate\Support\Collection;

/**
 * Daftar pencairan untuk `TabelData` (F-08, BR-08.4). Cari: nomor dokumen & referensi setoran. Saring: `Status`,
 * `Metode` (Uuid), rentang `Tanggal`. Dibatasi outlet yang boleh diakses pelaku (null = semua).
 *
 * `SelisihBiaya` = `Biaya − BiayaDiharapkan`, dikirim supaya daftarnya bisa langsung menunjukkan setoran yang
 * potongannya menyimpang dari pengaturan metode — itulah baris yang perlu ditanyakan ke platform, dan alasan utama
 * daftar ini ada.
 */
final class DaftarPencairan
{
    public const KOLOM_URUT = ['Tanggal', 'Nomor', 'JumlahKotor', 'JumlahBersih', 'Biaya'];

    public const KOLOM_SARING = ['Status', 'Metode', 'Tanggal'];

    public const URUT_BAWAAN = '-Tanggal';

    public function __construct(private readonly PetaUuidOutlet $petaOutlet) {}

    /**
     * @param  list<int>|null  $idOutletBoleh
     * @return array{Data: list<array<string, mixed>>, Meta: array{Halaman: int, PerHalaman: int, Total: int, JumlahHalaman: int}}
     */
    public function Ambil(DataPermintaanTabel $p, ?array $idOutletBoleh): array
    {
        $status = $p->AmbilDaftar('Status', array_map(fn (StatusDokumenTerposting $s): string => $s->value, StatusDokumenTerposting::cases()));
        $uuidMetode = $p->saring['Metode'] ?? null;
        $idMetode = $uuidMetode === null ? null : (MetodePembayaran::query()->where('Uuid', (string) $uuidMetode)->value('Id') ?? 0);
        ['Dari' => $dari, 'Sampai' => $sampai] = $p->AmbilRentangTanggal('Tanggal');
        $pola = PenerapKueriTabel::PolaCari($p->cari);

        $kueri = Pencairan::query()
            ->when($idOutletBoleh !== null, fn ($q) => $q->whereIn('IdOutlet', $idOutletBoleh ?? []))
            ->when($status !== [], fn ($q) => $q->whereIn('Status', $status))
            ->when($idMetode !== null, fn ($q) => $q->where('IdMetodePembayaran', $idMetode))
            ->when($dari !== null, fn ($q) => $q->whereDate('Tanggal', '>=', (string) $dari))
            ->when($sampai !== null, fn ($q) => $q->whereDate('Tanggal', '<=', (string) $sampai))
            ->when($p->cari !== '', fn ($q) => $q->where(fn ($dalam) => $dalam
                ->where('Nomor', 'like', $pola)
                ->orWhere('Referensi', 'like', $pola)));

        $urut = ['Tanggal' => 'Tanggal', 'Nomor' => 'Nomor', 'JumlahKotor' => 'JumlahKotor', 'JumlahBersih' => 'JumlahBersih', 'Biaya' => 'Biaya'];

        return PenerapKueriTabel::Terapkan($kueri, $p, $urut, function (Collection $baris): array {
            $outlet = $this->petaOutlet->AmbilKode(array_values(array_unique(array_filter($baris->pluck('IdOutlet')->all(), 'is_int'))));
            $metode = MetodePembayaran::query()->whereIn('Id', $baris->pluck('IdMetodePembayaran')->all())->pluck('Nama', 'Id');

            return array_values($baris->map(fn (Pencairan $d): array => [
                'Uuid' => $d->Uuid,
                'Nomor' => $d->Nomor,
                'Tanggal' => $d->Tanggal->format('Y-m-d'),
                'NamaMetode' => $metode->get($d->IdMetodePembayaran) ?? '',
                'KodeOutlet' => $outlet[$d->IdOutlet] ?? '',
                'Status' => $d->Status->value,
                'LabelStatus' => $d->Status->AmbilLabel(),
                'JumlahKotor' => $d->JumlahKotor,
                'JumlahBersih' => $d->JumlahBersih,
                'Biaya' => $d->Biaya,
                'BiayaDiharapkan' => $d->BiayaDiharapkan,
                'SelisihBiaya' => $d->AmbilBiaya()->Kurangi(Uang::Dari($d->BiayaDiharapkan))->KeString(),
                'Referensi' => $d->Referensi,
            ])->all());
        });
    }
}

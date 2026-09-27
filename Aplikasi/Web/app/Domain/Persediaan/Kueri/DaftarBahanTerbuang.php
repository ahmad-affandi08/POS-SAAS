<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Kueri;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Bersama\Tabel\Layanan\PenerapKueriTabel;
use App\Domain\Organisasi\Kueri\AnggotaOutlet;
use App\Domain\Organisasi\Kueri\InfoGudang;
use App\Domain\Persediaan\Enum\AlasanBahanTerbuang;
use App\Domain\Persediaan\Enum\JenisMutasi;
use App\Domain\Persediaan\Enum\StatusBahanTerbuang;
use App\Domain\Persediaan\Model\BahanTerbuang;
use App\Domain\Persediaan\Model\MutasiStok;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * F-05f: daftar bahan terbuang (`TabelData`, bawaan terbaru dulu, hanya outlet yang boleh diakses) dan ringkasan food
 * cost periode: nilai terbuang (tercatat) vs HPP penjualan periode yang sama (Σ mutasi Penjualan − ReturPenjualan di
 * buku stok) dan persentasenya. Saring: Tanggal (rentang), Alasan, Status, Gudang; cari: nama produk, catatan, Uuid.
 */
final class DaftarBahanTerbuang
{
    public const KOLOM_URUT = ['TanggalBisnis', 'Nilai'];

    public const KOLOM_SARING = ['Tanggal', 'Alasan', 'Status', 'Gudang'];

    public const URUT_BAWAAN = '-TanggalBisnis';

    public function __construct(
        private readonly InfoGudang $infoGudang,
        private readonly AnggotaOutlet $anggota,
    ) {}

    /**
     * @param  list<int>|null  $idOutletBoleh
     * @return array{Data: list<array<string, mixed>>, Meta: array{Halaman: int, PerHalaman: int, Total: int, JumlahHalaman: int}}
     */
    public function AmbilTabel(DataPermintaanTabel $permintaan, ?array $idOutletBoleh): array
    {
        $kueri = $this->BangunKueri($permintaan, $idOutletBoleh);

        if ($permintaan->urut === []) {
            $kueri->orderByDesc('TanggalBisnis')->orderByDesc('Id');
        }

        return PenerapKueriTabel::Terapkan($kueri, $permintaan, ['TanggalBisnis' => 'TanggalBisnis', 'Nilai' => 'Nilai'], fn (Collection $baris): array => $this->Petakan(array_values($baris->all())));
    }

    /**
     * Ringkasan food cost untuk saringan yang sama (status Tercatat saja).
     *
     * @param  list<int>|null  $idOutletBoleh
     * @return array{NilaiTerbuang: string, HppPenjualan: string, Persen: string|null, Jumlah: int}
     */
    public function AmbilRingkasan(DataPermintaanTabel $permintaan, ?array $idOutletBoleh): array
    {
        $kueri = $this->BangunKueri($permintaan, $idOutletBoleh)->where('Status', StatusBahanTerbuang::Tercatat->value);
        $nilai = Uang::Dari((string) ((clone $kueri)->sum('Nilai') ?: '0'));
        $jumlah = (clone $kueri)->count();
        $rentang = $permintaan->AmbilRentangTanggal('Tanggal');
        $idGudang = $this->AmbilIdGudangBoleh($permintaan, $idOutletBoleh);

        $hpp = MutasiStok::query()
            ->whereIn('JenisMutasi', [JenisMutasi::Penjualan->value, JenisMutasi::ReturPenjualan->value])
            ->when($rentang['Dari'] !== null, fn ($k) => $k->where('TanggalBisnis', '>=', $rentang['Dari']))
            ->when($rentang['Sampai'] !== null, fn ($k) => $k->where('TanggalBisnis', '<=', $rentang['Sampai']))
            ->when($idGudang !== null, fn ($k) => $k->whereIn('IdGudang', $idGudang))
            ->sum('TotalHpp');
        $hppPenjualan = Uang::Nol()->Kurangi(Uang::Dari((string) ($hpp ?: '0')));
        $persen = $hppPenjualan->KeString() === '0.00' || $hppPenjualan->BernilaiNegatif()
            ? null
            : (string) BigDecimal::of($nilai->KeString())->multipliedBy(100)->dividedBy($hppPenjualan->KeString(), 2, RoundingMode::HalfUp);

        return ['NilaiTerbuang' => $nilai->KeString(), 'HppPenjualan' => $hppPenjualan->KeString(), 'Persen' => $persen, 'Jumlah' => $jumlah];
    }

    /**
     * @param  list<int>|null  $idOutletBoleh
     * @return Builder<BahanTerbuang>
     */
    private function BangunKueri(DataPermintaanTabel $permintaan, ?array $idOutletBoleh): Builder
    {
        $rentang = $permintaan->AmbilRentangTanggal('Tanggal');
        $alasan = $permintaan->AmbilDaftar('Alasan', array_map(fn (AlasanBahanTerbuang $a): string => $a->value, AlasanBahanTerbuang::cases()));
        $status = $permintaan->AmbilDaftar('Status', array_map(fn (StatusBahanTerbuang $s): string => $s->value, StatusBahanTerbuang::cases()));
        $uuidGudang = $permintaan->saring['Gudang'] ?? null;
        $idGudang = $uuidGudang === null ? null : (($this->infoGudang->AmbilDariUuid([$uuidGudang])[$uuidGudang] ?? null)->id ?? 0);
        $pola = PenerapKueriTabel::PolaCari($permintaan->cari);

        return BahanTerbuang::query()
            ->when($idOutletBoleh !== null, fn ($k) => $k->whereIn('IdOutlet', $idOutletBoleh ?? []))
            ->when($rentang['Dari'] !== null, fn ($k) => $k->where('TanggalBisnis', '>=', $rentang['Dari']))
            ->when($rentang['Sampai'] !== null, fn ($k) => $k->where('TanggalBisnis', '<=', $rentang['Sampai']))
            ->when($alasan !== [], fn ($k) => $k->whereIn('Alasan', $alasan))
            ->when($status !== [], fn ($k) => $k->whereIn('Status', $status))
            ->when($idGudang !== null, fn ($k) => $k->where('IdGudang', $idGudang))
            ->when($permintaan->cari !== '', fn ($k) => $k->where(fn ($d) => $d
                ->where('NamaProduk', 'like', $pola)
                ->orWhere('Catatan', 'like', $pola)
                ->orWhere('Uuid', strtoupper(trim($permintaan->cari)))));
    }

    /**
     * Lokasi yang dipakai untuk HPP penjualan: saring Gudang, atau semua lokasi di outlet yang boleh; null = semua.
     *
     * @param  list<int>|null  $idOutletBoleh
     * @return list<int>|null
     */
    private function AmbilIdGudangBoleh(DataPermintaanTabel $permintaan, ?array $idOutletBoleh): ?array
    {
        $uuidGudang = $permintaan->saring['Gudang'] ?? null;

        if ($uuidGudang !== null) {
            return [($this->infoGudang->AmbilDariUuid([$uuidGudang])[$uuidGudang] ?? null)->id ?? 0];
        }

        if ($idOutletBoleh === null) {
            return null;
        }

        return array_values(array_map(fn ($g): int => $g->id, $this->infoGudang->AmbilBoleh($idOutletBoleh, false)));
    }

    /**
     * @param  list<BahanTerbuang>  $baris
     * @return list<array<string, mixed>>
     */
    private function Petakan(array $baris): array
    {
        $gudang = $this->infoGudang->AmbilBanyak(array_values(array_unique(array_map(fn (BahanTerbuang $b): int => $b->IdGudang, $baris))));
        $nama = $this->anggota->AmbilNama(array_values(array_unique(array_filter(array_map(fn (BahanTerbuang $b): ?int => $b->IdPengguna, $baris)))));

        return array_map(fn (BahanTerbuang $b): array => [
            'Uuid' => $b->Uuid,
            'TanggalBisnis' => $b->TanggalBisnis->format('Y-m-d'),
            'NamaProduk' => $b->NamaProduk,
            'Jumlah' => $b->Jumlah,
            'Alasan' => $b->Alasan->value,
            'LabelAlasan' => $b->Alasan->AmbilLabel(),
            'Catatan' => $b->Catatan,
            'Nilai' => $b->Nilai,
            'Status' => $b->Status->value,
            'LabelStatus' => $b->Status->AmbilLabel(),
            'Sumber' => $b->Sumber,
            'NamaGudang' => $gudang[$b->IdGudang]->nama ?? '',
            'NamaOutlet' => $gudang[$b->IdGudang]->namaOutlet ?? null,
            'NamaPencatat' => $b->IdPengguna === null ? null : ($nama[$b->IdPengguna]['Nama'] ?? null),
            'PerluTinjauan' => $b->PerluTinjauan,
            'AlasanTinjauan' => $b->AlasanTinjauan,
            'AlasanBatal' => $b->AlasanBatal,
        ], $baris);
    }
}

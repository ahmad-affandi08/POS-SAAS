<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kueri;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Bersama\Tabel\Layanan\PenerapKueriTabel;
use App\Domain\Katalog\Enum\GolonganObat;
use App\Domain\Katalog\Kueri\InfoProdukStok;
use App\Domain\Organisasi\Kueri\AnggotaOutlet;
use App\Domain\Penjualan\Model\Penjualan;
use App\Domain\Penjualan\Model\PenjualanDetail;
use App\Domain\Penjualan\Model\ResepPenjualan;
use App\Domain\Persediaan\Kueri\MutasiObatUntukLaporan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Laporan penjualan obat keras, psikotropika, dan narkotika (Sektor Apotek bagian 1, PRD §9.5 "laporan penjualan obat
 * keras") untuk `TabelData` & ekspor CSV. Sumber: snapshot golongan di `PenjualanDetail` (golongan saat dijual), resep di
 * `ResepPenjualan`, batch dari ledger stok (kueri publik Persediaan). Dibatasi outlet yang boleh diakses.
 *
 * Cari: nomor penjualan, nomor resep, atau nama dokter. Saring: `Golongan` (banyak), `Tanggal` (rentang tanggal bisnis),
 * `Resep` (1 = dengan resep, 0 = tanpa). Urut: `Tanggal`.
 *
 * Data pasien (nama, alamat) hanya utuh bila `$lihatPasien` (izin `apotek.resep.lihat`); selain itu nama disamarkan
 * (huruf pertama tiap kata) dan alamat tidak dikirim.
 */
final class DaftarPenjualanObatResep
{
    public const KOLOM_URUT = ['Tanggal'];

    public const KOLOM_SARING = ['Golongan', 'Tanggal', 'Resep'];

    public const URUT_BAWAAN = '-Tanggal';

    public const MAKS_EKSPOR = 5000;

    public function __construct(
        private readonly InfoProdukStok $produk,
        private readonly AnggotaOutlet $anggota,
        private readonly MutasiObatUntukLaporan $mutasi,
    ) {}

    /**
     * @param  list<int>|null  $idOutletBoleh
     * @return array{Data: list<array<string, mixed>>, Meta: array{Halaman: int, PerHalaman: int, Total: int, JumlahHalaman: int}}
     */
    public function Ambil(DataPermintaanTabel $p, ?array $idOutletBoleh, bool $lihatPasien): array
    {
        return PenerapKueriTabel::Terapkan(
            $this->Saring($p, $idOutletBoleh),
            $p,
            ['Tanggal' => fn (Builder $q, bool $turun) => $q->orderBy('Penjualan.TanggalBisnis', $turun ? 'desc' : 'asc')->orderBy('Penjualan.Id', $turun ? 'desc' : 'asc')],
            fn (Collection $baris): array => $this->Petakan($baris, $lihatPasien),
        );
    }

    /**
     * @param  list<int>|null  $idOutletBoleh
     * @return list<array<string, mixed>>
     */
    public function AmbilSemua(DataPermintaanTabel $p, ?array $idOutletBoleh, bool $lihatPasien): array
    {
        $baris = $this->Saring($p, $idOutletBoleh)
            ->orderByDesc('Penjualan.TanggalBisnis')->orderByDesc('Penjualan.Id')->orderBy('PenjualanDetail.Urutan')
            ->limit(self::MAKS_EKSPOR)
            ->get();

        return $this->Petakan($baris, $lihatPasien);
    }

    /**
     * @return list<array{Nilai: string, Label: string}>
     */
    public static function OpsiGolongan(): array
    {
        return array_values(array_map(
            fn (GolonganObat $g): array => ['Nilai' => $g->value, 'Label' => $g->AmbilLabel()],
            array_filter(GolonganObat::cases(), fn (GolonganObat $g): bool => $g->CekWajibApoteker()),
        ));
    }

    /** Nama disamarkan: huruf pertama tiap kata + bintang ("Budi Santoso" → "B*** S***"). */
    public static function SamarkanNama(string $nama): string
    {
        $kata = preg_split('/\s+/u', trim($nama)) ?: [];

        return implode(' ', array_map(fn (string $k): string => mb_substr($k, 0, 1).'***', array_filter($kata, fn (string $k): bool => $k !== '')));
    }

    /**
     * @param  list<int>|null  $idOutletBoleh
     * @return Builder<PenjualanDetail>
     */
    private function Saring(DataPermintaanTabel $p, ?array $idOutletBoleh): Builder
    {
        $wajibApoteker = array_column(self::OpsiGolongan(), 'Nilai');
        $golongan = $p->AmbilDaftar('Golongan', $wajibApoteker);
        ['Dari' => $dari, 'Sampai' => $sampai] = $p->AmbilRentangTanggal('Tanggal');
        $resep = $p->AmbilBoolean('Resep');
        $kata = $p->cari;

        return PenjualanDetail::query()
            ->join('Penjualan', fn ($j) => $j->on('Penjualan.Id', '=', 'PenjualanDetail.IdPenjualan')->on('Penjualan.IdTenant', '=', 'PenjualanDetail.IdTenant'))
            ->select('PenjualanDetail.*')
            ->whereIn('PenjualanDetail.GolonganObat', $golongan === [] ? $wajibApoteker : $golongan)
            ->when($idOutletBoleh !== null, fn ($q) => $q->whereIn('Penjualan.IdOutlet', $idOutletBoleh ?? []))
            ->when($dari !== null, fn ($q) => $q->where('Penjualan.TanggalBisnis', '>=', (string) $dari))
            ->when($sampai !== null, fn ($q) => $q->where('Penjualan.TanggalBisnis', '<=', (string) $sampai))
            ->when($resep !== null, fn ($q) => $q->where('PenjualanDetail.DenganResep', $resep))
            ->when($kata !== '', function ($q) use ($kata): void {
                $pola = PenerapKueriTabel::PolaCari($kata);
                $idResep = ResepPenjualan::query()->where(fn ($r) => $r->where('NomorResep', 'like', $pola)->orWhere('NamaDokter', 'like', $pola))->pluck('IdPenjualan')->all();
                $q->where(fn ($w) => $w->where('Penjualan.Nomor', 'like', $pola)->orWhereIn('Penjualan.Id', $idResep === [] ? [0] : $idResep));
            });
    }

    /**
     * @param  Collection<int, PenjualanDetail>  $baris
     * @return list<array<string, mixed>>
     */
    private function Petakan(Collection $baris, bool $lihatPasien): array
    {
        $idPenjualan = $baris->pluck('IdPenjualan')->unique()->values()->all();
        $penjualan = Penjualan::query()->whereIn('Id', $idPenjualan)->get(['Id', 'Uuid', 'Nomor', 'TanggalBisnis', 'IdPengguna', 'IdApoteker', 'Status'])->keyBy('Id');
        $resep = ResepPenjualan::query()->whereIn('IdPenjualan', $idPenjualan)->get()->keyBy('IdPenjualan');
        $produk = $this->produk->AmbilBanyak(array_values(array_map('intval', $baris->pluck('IdProduk')->unique()->all())), denganTerhapus: true);
        $orang = $this->anggota->AmbilNama(array_values(array_unique(array_filter([
            ...array_map('intval', $penjualan->pluck('IdPengguna')->all()),
            ...array_map('intval', $penjualan->pluck('IdApoteker')->filter()->all()),
        ]))));
        $batch = $this->mutasi->AmbilBatchPenjualan(array_values(array_map('intval', $baris->pluck('Id')->all())));

        return array_values($baris->map(function (PenjualanDetail $d) use ($penjualan, $resep, $produk, $orang, $batch, $lihatPasien): array {
            $pj = $penjualan->get($d->IdPenjualan);
            $r = $resep->get($d->IdPenjualan);
            $golongan = GolonganObat::tryFrom((string) $d->GolonganObat);

            return [
                'Uuid' => $d->Uuid,
                'UuidPenjualan' => $pj?->Uuid,
                'Tanggal' => $pj?->TanggalBisnis->toDateString(),
                'Nomor' => $pj?->Nomor,
                'Status' => $pj?->Status->value,
                'NamaProduk' => $d->NamaProduk,
                'Golongan' => $d->GolonganObat,
                'LabelGolongan' => $golongan?->AmbilLabel() ?? '',
                'ObatWajibApotek' => $d->ObatWajibApotek,
                'Jumlah' => Kuantitas::Dari((string) $d->JumlahDasar)->KeString(),
                'SimbolSatuan' => isset($produk[$d->IdProduk]) ? $produk[$d->IdProduk]->simbolSatuan : '',
                'Batch' => implode(', ', $batch[$d->Id] ?? []),
                'DenganResep' => $d->DenganResep,
                'NomorResep' => $r?->NomorResep,
                'TanggalResep' => $r?->TanggalResep->toDateString(),
                'NamaDokter' => $r?->NamaDokter,
                'NoSipDokter' => $r?->NoSipDokter,
                'NamaPasien' => $r === null ? null : ($lihatPasien ? $r->NamaPasien : self::SamarkanNama($r->NamaPasien)),
                'UmurPasien' => $r?->UmurPasien,
                'AlamatPasien' => $r !== null && $lihatPasien ? $r->AlamatPasien : null,
                'PasienTersamar' => $r !== null && ! $lihatPasien,
                'NamaApoteker' => $pj?->IdApoteker === null ? null : ($orang[$pj->IdApoteker]['Nama'] ?? null),
                'NamaKasir' => $pj === null ? '' : ($orang[$pj->IdPengguna]['Nama'] ?? ''),
            ];
        })->all());
    }
}

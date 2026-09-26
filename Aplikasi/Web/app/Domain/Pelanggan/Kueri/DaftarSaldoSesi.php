<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Kueri;

use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Bersama\Tabel\Layanan\PenerapKueriTabel;
use App\Domain\Pelanggan\Enum\StatusPemakaianSesi;
use App\Domain\Pelanggan\Enum\StatusSaldoSesi;
use App\Domain\Pelanggan\Model\MutasiSesi;
use App\Domain\Pelanggan\Model\PemakaianSesi;
use App\Domain\Pelanggan\Model\SaldoSesi;
use Illuminate\Support\Collection;

/**
 * Saldo paket sesi pelanggan (F-16d bagian 2) untuk back-office: `TabelData` (cari nama paket/nomor penjualan, saring
 * status & tanpa pelanggan, urut tanggal beli/sisa/nilai/berlaku), detail satu saldo (mutasi & pemakaian), ringkasan per
 * pelanggan.
 */
final class DaftarSaldoSesi
{
    public const KOLOM_URUT = ['TanggalBeli', 'SisaSesi', 'NilaiTersisa', 'BerlakuSampai'];

    public const KOLOM_SARING = ['Status', 'TanpaPelanggan'];

    public const URUT_BAWAAN = '-TanggalBeli';

    public function __construct(private readonly IdentitasPelanggan $identitas) {}

    /**
     * @return array{Data: list<array<string, mixed>>, Meta: array<string, mixed>}
     */
    public function AmbilTabel(DataPermintaanTabel $permintaan): array
    {
        $status = $permintaan->AmbilDaftar('Status', array_map(fn (StatusSaldoSesi $s): string => $s->value, StatusSaldoSesi::cases()));
        $tanpa = $permintaan->AmbilDaftar('TanpaPelanggan', ['Ya', 'Tidak']);

        $kueri = SaldoSesi::query()
            ->when($status !== [], fn ($k) => $k->whereIn('Status', $status))
            ->when(count($tanpa) === 1, fn ($k) => $tanpa[0] === 'Ya' ? $k->whereNull('IdPelanggan') : $k->whereNotNull('IdPelanggan'))
            ->when($permintaan->cari !== '', function ($k) use ($permintaan): void {
                $pola = PenerapKueriTabel::PolaCari($permintaan->cari);
                $k->where(fn ($q) => $q->where('NamaPaket', 'like', $pola)->orWhere('NomorPenjualan', 'like', $pola));
            });

        return PenerapKueriTabel::Terapkan($kueri, $permintaan, [
            'TanggalBeli' => 'TanggalBeli',
            'SisaSesi' => 'SisaSesi',
            'NilaiTersisa' => 'NilaiTersisa',
            'BerlakuSampai' => 'BerlakuSampai',
        ], function (Collection $baris): array {
            /** @var Collection<int, SaldoSesi> $baris */
            $nama = [];

            foreach ($baris as $s) {
                if ($s->IdPelanggan !== null && ! array_key_exists($s->IdPelanggan, $nama)) {
                    $nama[$s->IdPelanggan] = $this->identitas->AmbilRingkas($s->IdPelanggan);
                }
            }

            return array_values($baris->map(fn (SaldoSesi $s): array => self::Petakan($s) + [
                'Pelanggan' => $s->IdPelanggan === null ? null : ($nama[$s->IdPelanggan] ?? null),
            ])->all());
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function AmbilDetail(SaldoSesi $saldo): array
    {
        $mutasi = MutasiSesi::query()->where('IdSaldoSesi', $saldo->Id)->orderBy('Id')->get();
        $pemakaian = PemakaianSesi::query()->where('IdSaldoSesi', $saldo->Id)->orderByDesc('Id')->limit(200)->get();

        return self::Petakan($saldo) + [
            'Pelanggan' => $this->identitas->AmbilRingkas($saldo->IdPelanggan),
            'Mutasi' => array_values($mutasi->map(fn (MutasiSesi $m): array => [
                'Uuid' => $m->Uuid,
                'Jenis' => $m->Jenis->value,
                'LabelJenis' => $m->Jenis->AmbilLabel(),
                'JumlahSesi' => $m->JumlahSesi,
                'Nilai' => $m->Nilai,
                'SisaSetelah' => $m->SisaSetelah,
                'Tanggal' => $m->Tanggal->toDateString(),
                'Keterangan' => $m->Keterangan,
            ])->all()),
            'Pemakaian' => array_values($pemakaian->map(fn (PemakaianSesi $p): array => [
                'Uuid' => $p->Uuid,
                'NamaProduk' => $p->NamaProduk,
                'Jumlah' => $p->Jumlah,
                'NilaiDiakui' => $p->NilaiDiakui,
                'TanggalBisnis' => $p->TanggalBisnis->toDateString(),
                'Status' => $p->Status->value,
                'Dibatalkan' => $p->Status === StatusPemakaianSesi::Dibatalkan,
                'PerluTinjauan' => $p->PerluTinjauan,
                'AlasanTinjauan' => $p->AlasanTinjauan,
            ])->all()),
        ];
    }

    /**
     * Paket sesi pelanggan untuk halaman detail pelanggan (aktif lebih dulu, maks. 50).
     *
     * @return list<array<string, mixed>>
     */
    public function AmbilPerPelanggan(int $idPelanggan): array
    {
        return array_values(SaldoSesi::query()
            ->where('IdPelanggan', $idPelanggan)
            ->orderByRaw("Status <> '".StatusSaldoSesi::Aktif->value."'")
            ->orderByDesc('Id')
            ->limit(50)
            ->get()
            ->map(fn (SaldoSesi $s): array => self::Petakan($s))
            ->all());
    }

    /**
     * @return array<string, mixed>
     */
    private static function Petakan(SaldoSesi $s): array
    {
        return [
            'Uuid' => $s->Uuid,
            'NamaPaket' => $s->NamaPaket,
            'NomorPenjualan' => $s->NomorPenjualan,
            'JumlahSesi' => $s->JumlahSesi,
            'SisaSesi' => $s->SisaSesi,
            'NilaiAwal' => $s->NilaiAwal,
            'NilaiTersisa' => $s->NilaiTersisa,
            'TanggalBeli' => $s->TanggalBeli->toDateString(),
            'BerlakuSampai' => $s->BerlakuSampai?->toDateString(),
            'Status' => $s->Status->value,
            'LabelStatus' => $s->Status->AmbilLabel(),
        ];
    }
}

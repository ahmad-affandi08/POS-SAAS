<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kueri;

use App\Domain\Bersama\Tabel\Layanan\PenerapKueriTabel;
use App\Domain\Pelanggan\Kueri\IdentitasPelanggan;
use App\Domain\Penjualan\Enum\JenisMetodePembayaran;
use App\Domain\Penjualan\Enum\StatusPesananPenjualan;
use App\Domain\Penjualan\Model\MetodePembayaran;
use App\Domain\Penjualan\Model\PesananPenjualan;
use App\Domain\Penjualan\Model\PesananPenjualanDetail;
use Illuminate\Database\Eloquent\Builder;

/**
 * Cari pre-order yang siap diambil di outlet perangkat (F-12 bagian 2, `GET /api/pos/v1/pesanan-penjualan?kata=`,
 * perlu online): nomor atau nama/nomor HP pelanggan (min. 3 karakter), status `Dipesan`/`Siap`, maks. 20 terurut
 * tanggal ambil. Membawa baris (harga saat dipesan), sisa uang muka, pelanggan, dan metode sistem "Uang muka (DP)".
 */
final class PesananPenjualanPos
{
    public const BATAS = 20;

    public function __construct(private readonly IdentitasPelanggan $pelanggan) {}

    /**
     * @return array{Pesanan: list<array<string, mixed>>, MetodeUangMuka: array{Uuid: string, Nama: string}|null}
     */
    public function Cari(int $idOutlet, string $kata): array
    {
        $kata = trim($kata);
        $metode = MetodePembayaran::query()->where('Jenis', JenisMetodePembayaran::UangMuka->value)->first(['Uuid', 'Nama']);
        $metodeUangMuka = $metode === null ? null : ['Uuid' => $metode->Uuid, 'Nama' => $metode->Nama];

        if (mb_strlen($kata) < 3) {
            return ['Pesanan' => [], 'MetodeUangMuka' => $metodeUangMuka];
        }

        $idPelanggan = $this->pelanggan->CariIdPos($kata);
        $pesanan = PesananPenjualan::query()
            ->where('IdOutlet', $idOutlet)
            ->whereIn('Status', [StatusPesananPenjualan::Dipesan->value, StatusPesananPenjualan::Siap->value])
            ->where(fn (Builder $k) => $k->where('Nomor', 'like', PenerapKueriTabel::PolaCari(strtoupper($kata)))
                ->when($idPelanggan !== [], fn (Builder $s) => $s->orWhereIn('IdPelanggan', $idPelanggan)))
            ->orderBy('TanggalAmbil')
            ->orderBy('Id')
            ->limit(self::BATAS)
            ->get();
        $detail = PesananPenjualanDetail::query()->whereIn('IdPesananPenjualan', $pesanan->pluck('Id')->all())->orderBy('Id')->get()->groupBy('IdPesananPenjualan');
        $identitas = $this->pelanggan->AmbilUntukPos(array_values($pesanan->pluck('IdPelanggan')->all()));

        return [
            'Pesanan' => array_values($pesanan->map(fn (PesananPenjualan $p): array => [
                'Uuid' => $p->Uuid,
                'Nomor' => $p->Nomor,
                'Status' => $p->Status->value,
                'TanggalAmbil' => $p->TanggalAmbil->toDateString(),
                'Catatan' => $p->Catatan,
                'TotalPesanan' => $p->TotalPesanan,
                'UangMuka' => $p->UangMuka,
                'SisaUangMuka' => $p->AmbilSisaUangMuka()->KeString(),
                'Pelanggan' => $identitas[$p->IdPelanggan] ?? null,
                'Baris' => array_values(($detail->get($p->Id) ?? collect())->map(fn (PesananPenjualanDetail $d): array => [
                    'Uuid' => $d->Uuid,
                    'UuidProduk' => $d->UuidProduk,
                    'UuidProdukSatuan' => $d->UuidProdukSatuan,
                    'NamaProduk' => $d->NamaProduk,
                    'Jumlah' => $d->Jumlah,
                    'HargaSatuan' => $d->HargaSatuan,
                    'HargaPilihan' => $d->HargaPilihan,
                    'Pilihan' => $d->Pilihan ?? [],
                    'Catatan' => $d->Catatan,
                ])->all()),
            ])->all()),
            'MetodeUangMuka' => $metodeUangMuka,
        ];
    }
}

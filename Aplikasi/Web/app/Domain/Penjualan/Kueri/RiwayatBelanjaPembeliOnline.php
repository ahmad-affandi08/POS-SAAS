<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kueri;

use App\Domain\Organisasi\Kueri\PetaUuidOutlet;
use App\Domain\Penjualan\Enum\JenisPemenuhanOnline;
use App\Domain\Penjualan\Layanan\KodeStrukDigital;
use App\Domain\Penjualan\Model\Penjualan;
use App\Domain\Penjualan\Model\PesananOnline;

/**
 * F-17 bagian 3: riwayat belanja pembeli yang sudah masuk di toko online — pesanan online miliknya **dan** belanjanya
 * di kasir (penjualan dengan pelanggan yang sama, F-16a). Satu pelanggan, satu riwayat, di mana pun ia belanja.
 * Maks. 20 terbaru per jenis. Penjualan dibuka lewat struk digitalnya (`/s/{kodeStruk}`), pesanan lewat halaman
 * statusnya; keduanya tautan yang memang sudah publik bagi pemegangnya.
 */
final class RiwayatBelanjaPembeliOnline
{
    public const BATAS = 20;

    public function __construct(private readonly PetaUuidOutlet $outlet) {}

    /**
     * @return array{Pesanan: list<array<string, mixed>>, Belanja: list<array<string, mixed>>}
     */
    public function Ambil(int $idTenant, int $idPelanggan, string $slugTenant): array
    {
        $pesanan = PesananOnline::query()->where('IdPelanggan', $idPelanggan)->orderByDesc('Id')->limit(self::BATAS)
            ->get(['Id', 'Nomor', 'KodeAkses', 'IdOutlet', 'JenisPemenuhan', 'Status', 'Total', 'DibuatPada']);
        $belanja = Penjualan::query()->where('IdPelanggan', $idPelanggan)->orderByDesc('Id')->limit(self::BATAS)
            ->get(['Id', 'Uuid', 'Nomor', 'IdOutlet', 'Status', 'TanggalBisnis', 'TotalAkhir']);
        $outlet = $this->outlet->AmbilIdentitas(array_values(array_map('intval', [...$pesanan->pluck('IdOutlet')->all(), ...$belanja->pluck('IdOutlet')->all()])));

        return [
            'Pesanan' => array_values($pesanan->map(fn (PesananOnline $p): array => [
                'Nomor' => $p->Nomor,
                'Outlet' => $outlet[$p->IdOutlet]['Nama'] ?? null,
                'JenisPemenuhan' => $p->JenisPemenuhan === JenisPemenuhanOnline::Kirim ? 'Dikirim' : 'Ambil sendiri',
                'Status' => $p->Status->value,
                'LabelStatus' => $p->Status->AmbilLabel(),
                'Total' => $p->Total,
                'DibuatPada' => $p->DibuatPada?->toIso8601String(),
                'UrlStatus' => url("/{$slugTenant}/pesanan/{$p->KodeAkses}"),
            ])->all()),
            'Belanja' => array_values($belanja->map(fn (Penjualan $p): array => [
                'Nomor' => $p->Nomor,
                'Outlet' => $outlet[$p->IdOutlet]['Nama'] ?? null,
                'Tanggal' => $p->TanggalBisnis->toDateString(),
                'Status' => $p->Status->value,
                'LabelStatus' => $p->Status->AmbilLabel(),
                'Total' => $p->TotalAkhir,
                'UrlStruk' => url('/s/'.KodeStrukDigital::Buat($idTenant, $p->Uuid)),
            ])->all()),
        ];
    }

    /**
     * Alamat kirim terakhir pembeli ini (untuk mengisi formulir checkout); null bila belum pernah dikirim.
     *
     * @return array{Alamat: string, Kelurahan: string|null, Kecamatan: string|null, Kota: string|null, Provinsi: string|null, KodePos: string|null}|null
     */
    public function AmbilAlamatTerakhir(int $idPelanggan): ?array
    {
        $p = PesananOnline::query()->where('IdPelanggan', $idPelanggan)->where('JenisPemenuhan', JenisPemenuhanOnline::Kirim->value)
            ->whereNotNull('Alamat')->orderByDesc('Id')->first();

        return $p === null || $p->Alamat === null ? null : [
            'Alamat' => $p->Alamat, 'Kelurahan' => $p->Kelurahan, 'Kecamatan' => $p->Kecamatan,
            'Kota' => $p->Kota, 'Provinsi' => $p->Provinsi, 'KodePos' => $p->KodePos,
        ];
    }
}

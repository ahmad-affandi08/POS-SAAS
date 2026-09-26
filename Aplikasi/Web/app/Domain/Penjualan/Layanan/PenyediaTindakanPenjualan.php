<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Layanan;

use App\Domain\Bersama\Tindakan\Data\DataKonteksTindakan;
use App\Domain\Bersama\Tindakan\Data\DataRincianTindakan;
use App\Domain\Bersama\Tindakan\Kontrak\PenyediaTindakan;
use App\Domain\Bersama\Tindakan\Layanan\PembuatButirTinjauan;
use App\Domain\Penjualan\Model\IsiDeposit;
use App\Domain\Penjualan\Model\Penjualan;
use App\Domain\Penjualan\Model\ReturPenjualan;
use Illuminate\Database\Eloquent\Builder;

/**
 * Kotak Tindakan domain Penjualan (D-23 C): penjualan, retur, dan isi deposit dari kasir yang diterima dengan
 * `PerluTinjauan` dan belum ditandai dicek (dibatasi outlet akses). Lihat: `laporan.penjualan.lihat` (isi deposit:
 * `pelanggan.lihat`).
 */
final class PenyediaTindakanPenjualan implements PenyediaTindakan
{
    public function Kumpulkan(DataKonteksTindakan $konteks): array
    {
        $butir = [];

        if ($konteks->CekIzin('laporan.penjualan.lihat')) {
            $butir[] = PembuatButirTinjauan::Buat(
                $this->KueriPenjualan($konteks->idOutletBoleh),
                'Penjualan',
                'Penjualan',
                'penjualan.tinjauan',
                'Penjualan',
                'Penjualan perlu dicek',
                'Diterima dari kasir dengan catatan (misal stok minus, diskon/izin berubah, dikirim setelah shift ditutup).',
                '/kelola/penjualan?saring[PerluTinjauan]=Ya',
                fn (Penjualan $p): DataRincianTindakan => new DataRincianTindakan($p->Uuid, $p->Nomor, $p->AlasanTinjauan, $p->TanggalBisnis->toDateString(), '/kelola/penjualan/'.$p->Uuid),
            );
            $butir[] = PembuatButirTinjauan::Buat(
                $this->KueriRetur($konteks->idOutletBoleh),
                'ReturPenjualan',
                'ReturPenjualan',
                'retur-penjualan.tinjauan',
                'Penjualan',
                'Retur penjualan perlu dicek',
                'Retur dari kasir yang diterima dengan catatan.',
                '/kelola/penjualan/retur',
                fn (ReturPenjualan $r): DataRincianTindakan => new DataRincianTindakan($r->Uuid, $r->Nomor, $r->AlasanTinjauan, $r->TanggalBisnis->toDateString(), '/kelola/penjualan/retur/'.$r->Uuid),
            );
        }

        if ($konteks->CekIzin('pelanggan.lihat')) {
            $butir[] = PembuatButirTinjauan::Buat(
                $this->KueriIsiDeposit($konteks->idOutletBoleh),
                'IsiDeposit',
                'IsiDeposit',
                'isi-deposit.tinjauan',
                'Pelanggan',
                'Isi deposit perlu dicek',
                'Isi deposit dari kasir dengan catatan (misal pelanggan belum dikenal server).',
                '/kelola/pelanggan/isi-deposit?saring[PerluTinjauan]=Ya',
                fn (IsiDeposit $i): DataRincianTindakan => new DataRincianTindakan($i->Uuid, $i->Nomor, $i->AlasanTinjauan, $i->TanggalBisnis->toDateString(), '/kelola/pelanggan/isi-deposit?cari='.rawurlencode($i->Nomor)),
            );
        }

        return $butir;
    }

    public function AmbilJenisDokumen(): array
    {
        return ['Penjualan', 'ReturPenjualan', 'IsiDeposit'];
    }

    public function SaringDokumen(string $jenisDokumen, array $uuid): array
    {
        return match ($jenisDokumen) {
            'Penjualan' => PembuatButirTinjauan::Saring($this->KueriPenjualan(null), 'Penjualan', $uuid),
            'ReturPenjualan' => PembuatButirTinjauan::Saring($this->KueriRetur(null), 'ReturPenjualan', $uuid),
            'IsiDeposit' => PembuatButirTinjauan::Saring($this->KueriIsiDeposit(null), 'IsiDeposit', $uuid),
            default => [],
        };
    }

    /**
     * @param  list<int>|null  $idOutlet
     * @return Builder<Penjualan>
     */
    private function KueriPenjualan(?array $idOutlet): Builder
    {
        return Penjualan::query()->where('Penjualan.PerluTinjauan', true)->when($idOutlet !== null, fn ($k) => $k->whereIn('Penjualan.IdOutlet', $idOutlet));
    }

    /**
     * @param  list<int>|null  $idOutlet
     * @return Builder<ReturPenjualan>
     */
    private function KueriRetur(?array $idOutlet): Builder
    {
        return ReturPenjualan::query()->where('ReturPenjualan.PerluTinjauan', true)->when($idOutlet !== null, fn ($k) => $k->whereIn('ReturPenjualan.IdOutlet', $idOutlet));
    }

    /**
     * @param  list<int>|null  $idOutlet
     * @return Builder<IsiDeposit>
     */
    private function KueriIsiDeposit(?array $idOutlet): Builder
    {
        return IsiDeposit::query()->where('IsiDeposit.PerluTinjauan', true)->when($idOutlet !== null, fn ($k) => $k->whereIn('IsiDeposit.IdOutlet', $idOutlet));
    }
}

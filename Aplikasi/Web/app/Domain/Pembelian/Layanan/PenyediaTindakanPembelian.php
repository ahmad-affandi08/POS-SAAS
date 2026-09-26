<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Layanan;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Tindakan\Data\DataButirTindakan;
use App\Domain\Bersama\Tindakan\Data\DataKonteksTindakan;
use App\Domain\Bersama\Tindakan\Data\DataRincianTindakan;
use App\Domain\Bersama\Tindakan\Enum\TingkatTindakan;
use App\Domain\Bersama\Tindakan\Kontrak\PenyediaTindakan;
use App\Domain\Pembelian\Enum\StatusFakturPembelian;
use App\Domain\Pembelian\Enum\StatusPesananPembelian;
use App\Domain\Pembelian\Model\FakturPembelian;
use App\Domain\Pembelian\Model\PesananPembelian;

/**
 * Kotak Tindakan domain Pembelian (D-23 C, dibatasi outlet akses):
 * - hutang pemasok (faktur belum lunas) yang jatuh tempo dalam [HARI_HUTANG] hari atau sudah lewat (izin
 *   `pembelian.kelola`);
 * - pesanan pembelian yang menunggu persetujuan (izin `pembelian.po.setujui`).
 */
final class PenyediaTindakanPembelian implements PenyediaTindakan
{
    public const HARI_HUTANG = 7;

    public function Kumpulkan(DataKonteksTindakan $konteks): array
    {
        $idOutlet = $konteks->idOutletBoleh;
        $butir = [];

        if ($konteks->CekIzin('pembelian.kelola')) {
            $faktur = FakturPembelian::query()
                ->whereIn('Status', [StatusFakturPembelian::BelumDibayar->value, StatusFakturPembelian::DibayarSebagian->value])
                ->where('JatuhTempo', '<=', $konteks->hariIni->addDays(self::HARI_HUTANG)->toDateString())
                ->when($idOutlet !== null, fn ($k) => $k->where(fn ($q) => $q->whereNull('IdOutlet')->orWhereIn('IdOutlet', $idOutlet)))
                ->orderBy('JatuhTempo')
                ->get();
            $total = Uang::Nol();

            foreach ($faktur as $f) {
                $total = $total->Tambah($f->AmbilSisa());
            }

            $hari = $konteks->hariIni->toDateString();
            $lewat = $faktur->filter(fn (FakturPembelian $f): bool => $f->JatuhTempo->toDateString() < $hari)->count();
            $butir[] = new DataButirTindakan(
                'hutang.jatuh-tempo',
                'Pembelian',
                $lewat > 0 ? TingkatTindakan::Penting : TingkatTindakan::Perhatian,
                'Hutang pemasok jatuh tempo',
                ($lewat > 0 ? "{$lewat} sudah lewat jatuh tempo. " : '').'Total sisa '.$total->FormatRupiah().' jatuh tempo dalam '.self::HARI_HUTANG.' hari.',
                $faktur->count(),
                '/kelola/pembelian/hutang',
                'Bayar hutang',
                array_values($faktur->take(DataButirTindakan::BATAS_RINCIAN)->map(fn (FakturPembelian $f): DataRincianTindakan => new DataRincianTindakan(
                    $f->Uuid,
                    $f->Nomor,
                    'Sisa '.$f->AmbilSisa()->FormatRupiah().', jatuh tempo '.$f->JatuhTempo->toDateString(),
                    $f->JatuhTempo->toDateString(),
                    '/kelola/pembelian/faktur/'.$f->Uuid,
                ))->all()),
            );
        }

        if ($konteks->CekIzin('pembelian.po.setujui')) {
            $po = PesananPembelian::query()
                ->where('Status', StatusPesananPembelian::MenungguPersetujuan->value)
                ->when($idOutlet !== null, fn ($k) => $k->where(fn ($q) => $q->whereNull('IdOutlet')->orWhereIn('IdOutlet', $idOutlet)));
            $jumlah = (clone $po)->count();
            $butir[] = new DataButirTindakan(
                'pesanan-pembelian.menunggu-persetujuan',
                'Pembelian',
                TingkatTindakan::Perhatian,
                'Pesanan pembelian menunggu persetujuan',
                'Setujui atau tolak agar bisa dikirim ke pemasok.',
                $jumlah,
                '/kelola/pembelian/pesanan?saring[Status]=MenungguPersetujuan',
                'Lihat pesanan',
                $jumlah === 0 ? [] : array_values($po->orderBy('Id')->limit(DataButirTindakan::BATAS_RINCIAN)->get()->map(
                    fn (PesananPembelian $p): DataRincianTindakan => new DataRincianTindakan($p->Uuid, $p->Nomor, null, null, '/kelola/pembelian/pesanan/'.$p->Uuid),
                )->all()),
            );
        }

        return $butir;
    }

    public function AmbilJenisDokumen(): array
    {
        return [];
    }

    public function SaringDokumen(string $jenisDokumen, array $uuid): array
    {
        return [];
    }
}

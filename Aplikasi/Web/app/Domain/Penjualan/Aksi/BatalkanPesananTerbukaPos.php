<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Sinkron\Enum\StatusItemSinkron;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Pemenuhan\Aksi\BatalkanBarisTiketDapur;
use App\Domain\Penjualan\Data\DataPesananTerbukaPos;
use App\Domain\Penjualan\Enum\StatusBarisPesanan;
use App\Domain\Penjualan\Enum\StatusPesananTerbuka;
use App\Domain\Penjualan\Layanan\PenjagaPesananTerbuka;
use App\Domain\Penjualan\Model\PesananTerbukaDetail;
use Illuminate\Support\Facades\DB;

/**
 * Item outbox `PesananTerbuka.Batal` (F-07 mode meja fase 1): membatalkan pesanan terbuka sebelum dibayar. Alasan
 * wajib. Pesanan yang punya item aktif terkirim ke dapur butuh izin `penjualan.void` pada pelaku atau penyetuju
 * (BR-07.5) dan baris tiket dapurnya dibatalkan. Sudah dibatalkan → `Duplikat`; sudah dibayar → `PesananSudahDitutup`.
 */
final class BatalkanPesananTerbukaPos
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenjagaPesananTerbuka $penjaga,
        private readonly BatalkanBarisTiketDapur $batalkanTiket,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(DataPesananTerbukaPos $data): StatusItemSinkron
    {
        $this->penjaga->PastikanWaktuWajar($data->waktu, 'DibatalkanPada');

        return DB::transaction(function () use ($data): StatusItemSinkron {
            $idTenant = $this->konteks->Wajib();
            $pesanan = $this->penjaga->CariUntukDiubah($data->uuidPesanan, $data->idOutlet);

            if ($pesanan->Status === StatusPesananTerbuka::Dibatalkan) {
                return StatusItemSinkron::Duplikat;
            }

            $this->penjaga->PastikanTerbuka($pesanan);
            $pelaku = $this->penjaga->CariPelaku($idTenant, $data->uuidPengguna, $data->idOutlet);

            if ($data->alasan === null || mb_strlen(trim($data->alasan)) < 3) {
                throw new PelanggaranAturanBisnis('AlasanWajib', 'Tulis alasan pembatalan pesanan.', 'Alasan');
            }

            $aktif = PesananTerbukaDetail::query()->where('IdPesananTerbuka', $pesanan->Id)->where('Status', StatusBarisPesanan::Aktif->value)->lockForUpdate()->get();
            $terkirim = $aktif->filter(fn (PesananTerbukaDetail $b): bool => $b->DikirimKeDapurPada !== null);
            $penyetuju = $terkirim->isEmpty() ? null : $this->penjaga->CariPenyetujuVoid($idTenant, $pelaku, $data->uuidPenyetuju, $data->idOutlet);

            foreach ($aktif as $b) {
                $b->update([
                    'Status' => StatusBarisPesanan::Dibatalkan,
                    'DibatalkanPada' => $data->waktu,
                    'AlasanBatal' => $data->alasan,
                    'IdPembatal' => $pelaku->id,
                    'IdPenyetujuBatal' => $b->DikirimKeDapurPada !== null ? $penyetuju?->id : null,
                ]);
            }

            $pesanan->update([
                'Status' => StatusPesananTerbuka::Dibatalkan,
                'DitutupPada' => $data->waktu,
                'AlasanBatal' => $data->alasan,
                'IdPembatal' => $pelaku->id,
                'IdPenyetujuBatal' => $penyetuju?->id,
                'IdPerangkatKunciBayar' => null,
                'KunciBayarSampai' => null,
            ]);
            $this->batalkanTiket->Jalankan(array_values($terkirim->pluck('Uuid')->all()));
            $this->audit->Catat('pesanan-terbuka.batal', $pesanan, nilaiBaru: [
                'Nomor' => $pesanan->Nomor,
                'Alasan' => $data->alasan,
                'ItemTerkirim' => $terkirim->count(),
                'IdPenyetuju' => $penyetuju?->id,
            ], idPengguna: $pelaku->id);

            return StatusItemSinkron::Diterima;
        });
    }
}

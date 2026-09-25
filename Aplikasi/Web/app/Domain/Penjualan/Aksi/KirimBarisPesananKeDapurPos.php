<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Sinkron\Enum\StatusItemSinkron;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Organisasi\Kueri\MejaOutlet;
use App\Domain\Pemenuhan\Aksi\KirimKeDapur;
use App\Domain\Pemenuhan\Data\DataKirimDapur;
use App\Domain\Penjualan\Data\DataPesananTerbukaPos;
use App\Domain\Penjualan\Enum\StatusBarisPesanan;
use App\Domain\Penjualan\Layanan\PenjagaPesananTerbuka;
use App\Domain\Penjualan\Model\PesananTerbukaDetail;
use Illuminate\Support\Facades\DB;

/**
 * Item outbox `PesananTerbuka.KirimDapur` (F-07 mode meja fase 1): mengirim baris aktif yang sebelumnya disimpan
 * tanpa dikirim ke dapur (tahan) sebagai satu ronde. Baris yang sudah terkirim atau dibatalkan dilewati; tidak ada
 * yang perlu dikirim → `Duplikat`.
 */
final class KirimBarisPesananKeDapurPos
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenjagaPesananTerbuka $penjaga,
        private readonly MejaOutlet $meja,
        private readonly KirimKeDapur $kirimDapur,
    ) {}

    public function Jalankan(DataPesananTerbukaPos $data): StatusItemSinkron
    {
        $this->penjaga->PastikanWaktuWajar($data->waktu, 'DikirimPada');

        return DB::transaction(function () use ($data): StatusItemSinkron {
            $pesanan = $this->penjaga->CariUntukDiubah($data->uuidPesanan, $data->idOutlet);
            $baris = PesananTerbukaDetail::query()->where('IdPesananTerbuka', $pesanan->Id)->whereIn('Uuid', $data->uuidBaris)->lockForUpdate()->get();

            if ($baris->count() !== count(array_unique($data->uuidBaris))) {
                throw new PelanggaranAturanBisnis('BarisTidakDitemukan', 'Sebagian item yang dikirim tidak ada di pesanan ini.', 'UuidBaris', 404);
            }

            $kirim = $baris->filter(fn (PesananTerbukaDetail $b): bool => $b->Status === StatusBarisPesanan::Aktif && $b->DikirimKeDapurPada === null)->values();

            if ($kirim->isEmpty()) {
                return StatusItemSinkron::Duplikat;
            }

            $this->penjaga->PastikanTerbuka($pesanan);
            $this->penjaga->CariPelaku($this->konteks->Wajib(), $data->uuidPengguna, $data->idOutlet);

            foreach ($kirim as $b) {
                $b->update(['DikirimKeDapurPada' => $data->waktu, 'Ronde' => $data->ronde]);
            }

            $this->kirimDapur->Jalankan(new DataKirimDapur(
                idOutlet: $pesanan->IdOutlet,
                idPesananTerbuka: $pesanan->Id,
                idPenjualan: null,
                nomorDokumen: $pesanan->Nomor,
                namaMeja: $pesanan->IdMeja === null ? null : ($this->meja->AmbilPerId([$pesanan->IdMeja])[$pesanan->IdMeja]['Nama'] ?? null),
                label: $pesanan->Label,
                ronde: $data->ronde,
                dikirimPada: $data->waktu,
                baris: PenjagaPesananTerbuka::KeBarisDapur($kirim),
            ));
            $pesanan->touch();

            return StatusItemSinkron::Diterima;
        });
    }
}

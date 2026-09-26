<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Sinkron\Enum\StatusItemSinkron;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Penjualan\Data\DataPesananTerbukaPos;
use App\Domain\Penjualan\Enum\StatusBarisPesanan;
use App\Domain\Penjualan\Enum\StatusPesananTerbuka;
use App\Domain\Penjualan\Layanan\PenjagaPesananTerbuka;
use App\Domain\Penjualan\Model\PesananTerbuka;
use App\Domain\Penjualan\Model\PesananTerbukaDetail;
use Illuminate\Support\Facades\DB;

/**
 * Item outbox `PesananTerbuka.PindahBaris` (v1.99, pisah tagihan & gabung meja): memindahkan baris aktif dari pesanan
 * asal ke pesanan tujuan di outlet yang sama (keduanya `Terbuka`). Baris tidak diubah selain pesanannya (harga, ronde,
 * status dapur tetap), sehingga tiket dapur yang sudah terkirim tidak berubah.
 *
 * - **Pisah tagihan:** perangkat membuka pesanan baru (`PesananTerbuka.Buka`) lalu memindahkan item yang dibayar
 *   terpisah; masing-masing dibayar lewat `Penjualan.Buat` seperti biasa.
 * - **Gabung meja/tagihan:** semua item dipindah dengan `TutupAsal`; asal yang tidak lagi punya baris aktif ditutup
 *   berstatus `Digabung`.
 *
 * Idempoten: baris yang sudah berada di pesanan tujuan dilewati; semua sudah di tujuan → `Duplikat`. Audit
 * `pesanan-terbuka.pindah-item`.
 */
final class PindahBarisPesananTerbukaPos
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenjagaPesananTerbuka $penjaga,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(DataPesananTerbukaPos $data): StatusItemSinkron
    {
        $this->penjaga->PastikanWaktuWajar($data->waktu, 'DipindahPada');
        $uuidTujuan = $data->uuidTujuan ?? throw new PelanggaranAturanBisnis('TujuanWajib', 'Pilih pesanan tujuan.', 'UuidTujuan');

        if ($uuidTujuan === $data->uuidPesanan) {
            throw new PelanggaranAturanBisnis('TujuanSama', 'Pesanan tujuan harus berbeda dari pesanan asal.', 'UuidTujuan');
        }

        return DB::transaction(function () use ($data, $uuidTujuan): StatusItemSinkron {
            // Kunci kedua pesanan berurutan Id agar dua perangkat yang saling memindah tidak saling mengunci.
            $pesanan = PesananTerbuka::query()->whereIn('Uuid', [$data->uuidPesanan, $uuidTujuan])->orderBy('Id')->lockForUpdate()->get()->keyBy('Uuid');
            $asal = $pesanan->get($data->uuidPesanan) ?? $this->penjaga->CariUntukDiubah($data->uuidPesanan, $data->idOutlet);
            $tujuan = $pesanan->get($uuidTujuan) ?? $this->penjaga->CariUntukDiubah($uuidTujuan, $data->idOutlet);

            foreach ([$asal, $tujuan] as $p) {
                if ($p->IdOutlet !== $data->idOutlet) {
                    throw new PelanggaranAturanBisnis('PesananTidakDitemukan', 'Pesanan terbuka tidak ditemukan di outlet ini.', 'UuidPesanan', 404);
                }
            }

            $baris = PesananTerbukaDetail::query()->whereIn('Uuid', $data->uuidBaris)->lockForUpdate()->get();

            if ($baris->count() !== count(array_unique($data->uuidBaris))
                || $baris->contains(fn (PesananTerbukaDetail $b): bool => $b->IdPesananTerbuka !== $asal->Id && $b->IdPesananTerbuka !== $tujuan->Id)) {
                throw new PelanggaranAturanBisnis('BarisTidakDitemukan', 'Sebagian item yang dipindah tidak ada di pesanan asal.', 'UuidBaris', 404);
            }

            $dipindah = $baris->filter(fn (PesananTerbukaDetail $b): bool => $b->IdPesananTerbuka === $asal->Id && $b->Status === StatusBarisPesanan::Aktif);

            if ($dipindah->isEmpty()) {
                return StatusItemSinkron::Duplikat;
            }

            $this->penjaga->PastikanTerbuka($asal);
            $this->penjaga->PastikanTerbuka($tujuan);
            $pelaku = $this->penjaga->CariPelaku($this->konteks->Wajib(), $data->uuidPengguna, $data->idOutlet);

            PesananTerbukaDetail::query()->whereKey($dipindah->pluck('Id')->all())->update(['IdPesananTerbuka' => $tujuan->Id]);

            $sisaAktif = PesananTerbukaDetail::query()->where('IdPesananTerbuka', $asal->Id)->where('Status', StatusBarisPesanan::Aktif->value)->exists();

            if ($data->tutupAsal && ! $sisaAktif) {
                $asal->update([
                    'Status' => StatusPesananTerbuka::Digabung,
                    'DitutupPada' => $data->waktu,
                    'IdPerangkatKunciBayar' => null,
                    'KunciBayarSampai' => null,
                ]);
            } else {
                $asal->touch();
            }

            $tujuan->touch();
            $this->audit->Catat('pesanan-terbuka.pindah-item', $tujuan, nilaiBaru: [
                'Asal' => $asal->Nomor,
                'Tujuan' => $tujuan->Nomor,
                'Item' => $dipindah->map(fn (PesananTerbukaDetail $b): array => ['Nama' => $b->NamaProduk, 'Jumlah' => (string) $b->Jumlah])->values()->all(),
                'AsalDitutup' => $asal->Status === StatusPesananTerbuka::Digabung,
            ], idPengguna: $pelaku->id);

            return StatusItemSinkron::Diterima;
        });
    }
}

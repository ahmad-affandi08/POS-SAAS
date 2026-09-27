<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Dokumen\Layanan\PencatatRiwayatStatus;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Enum\PelacakanProduk;
use App\Domain\Persediaan\Data\DataOrderProduksi;
use App\Domain\Persediaan\Enum\StatusOrderProduksi;
use App\Domain\Persediaan\Layanan\PemeriksaLokasiDokumen;
use App\Domain\Persediaan\Layanan\PenjagaVersiDokumen;
use App\Domain\Persediaan\Layanan\PenyusunBahanProduksi;
use App\Domain\Persediaan\Model\OrderProduksi;
use App\Domain\Persediaan\Model\OrderProduksiBahan;
use Illuminate\Support\Facades\DB;

/**
 * F-05e: membuat atau mengubah draf order produksi. Buat idempoten per Uuid klien; ubah hanya Draf dengan versi
 * optimistis. Lokasi aktif (bukan dalam perjalanan), tanggal tidak di masa depan, overhead ≥ 0; produk hasil & bahan
 * diperiksa `PenyusunBahanProduksi` (bahan kosong = dari resep). Hasil ber-batch wajib nomor batch (dan kedaluwarsa bila
 * diwajibkan). Draf belum mengubah stok. Audit `order-produksi.buat`/`.ubah`.
 */
final class SimpanOrderProduksi
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PemeriksaLokasiDokumen $pemeriksaLokasi,
        private readonly PenyusunBahanProduksi $penyusunBahan,
        private readonly PencatatRiwayatStatus $riwayat,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(DataOrderProduksi $data, ?OrderProduksi $ada): OrderProduksi
    {
        if ($ada === null && $data->uuid !== null) {
            $lama = OrderProduksi::query()->where('Uuid', $data->uuid)->first();

            if ($lama !== null) {
                return $lama;
            }
        }

        return DB::transaction(function () use ($data, $ada): OrderProduksi {
            $oleh = $this->audit->AmbilIdPengguna();

            if ($ada !== null) {
                $dokumen = OrderProduksi::query()->whereKey($ada->Id)->lockForUpdate()->firstOrFail();

                if ($dokumen->Status !== StatusOrderProduksi::Draf) {
                    throw new PelanggaranAturanBisnis('StatusTidakSesuai', "Order produksi berstatus {$dokumen->Status->AmbilLabel()} tidak bisa diubah. Hanya draf yang bisa diubah.");
                }

                PenjagaVersiDokumen::Pastikan($data->versiDiubahPada, $dokumen->DiubahPada);
            } else {
                $dokumen = new OrderProduksi;

                if ($data->uuid !== null) {
                    $dokumen->Uuid = $data->uuid;
                }
            }

            $gudang = $this->pemeriksaLokasi->AmbilLokasi($data->idGudang);
            $this->pemeriksaLokasi->PastikanBukanMasaDepan($data->tanggal, $gudang->idOutlet);

            if ($data->biayaOverhead->BernilaiNegatif()) {
                throw new PelanggaranAturanBisnis('OverheadTidakValid', 'Biaya overhead tidak boleh minus.', 'BiayaOverhead');
            }

            $susunan = $this->penyusunBahan->Susun($data->idProduk, $data->jumlahHasil, $data->bahan);
            $hasil = $susunan['hasil'];
            $nomorBatch = $data->nomorBatch === null ? null : trim($data->nomorBatch);
            $nomorBatch = $nomorBatch === '' ? null : $nomorBatch;
            $kedaluwarsa = $data->tanggalKedaluwarsa;

            if ($hasil->pelacakan === PelacakanProduk::Batch) {
                if ($nomorBatch === null) {
                    throw new PelanggaranAturanBisnis('NomorBatchWajib', "{$hasil->nama} memakai batch. Isi nomor batch hasil produksi.", 'NomorBatch');
                }

                if ($kedaluwarsa === null && (bool) config('persediaan.StokAwal.WajibKedaluwarsaBatch', true)) {
                    throw new PelanggaranAturanBisnis('KedaluwarsaWajib', 'Isi tanggal kedaluwarsa batch hasil produksi.', 'TanggalKedaluwarsa');
                }
            } else {
                $nomorBatch = null;
                $kedaluwarsa = null;
            }

            $keterangan = $data->keterangan === null ? null : trim($data->keterangan);
            $lama = $ada === null ? null : ['IdProduk' => $dokumen->IdProduk, 'JumlahHasil' => $dokumen->JumlahHasil, 'BiayaOverhead' => $dokumen->BiayaOverhead];

            $dokumen->fill([
                'IdGudang' => $gudang->id,
                'IdOutlet' => $gudang->idOutlet,
                'Tanggal' => $data->tanggal->format('Y-m-d'),
                'IdProduk' => $hasil->id,
                'NamaProduk' => mb_substr($hasil->nama, 0, 150),
                'Sku' => $hasil->sku,
                'JumlahHasil' => $data->jumlahHasil->KeString(),
                'IdResep' => $susunan['resep']?->idResep,
                'VersiResep' => $susunan['resep']?->versi,
                'BiayaOverhead' => $data->biayaOverhead->KeString(),
                'NomorBatch' => $nomorBatch,
                'TanggalKedaluwarsa' => $kedaluwarsa?->format('Y-m-d'),
                'Keterangan' => $keterangan === '' ? null : $keterangan,
                'DiubahOleh' => $oleh,
            ]);

            if ($ada === null) {
                $dokumen->DibuatOleh = $oleh;
            }

            $dokumen->DiubahPada = now();
            $dokumen->save();

            OrderProduksiBahan::query()->where('IdOrderProduksi', $dokumen->Id)->delete();
            $idTenant = $this->konteks->Wajib();
            $waktu = now();
            OrderProduksiBahan::query()->insert(array_map(fn (array $b, int $i): array => [
                'IdTenant' => $idTenant,
                'IdOrderProduksi' => $dokumen->Id,
                'Urutan' => $i + 1,
                'IdProduk' => $b['info']->id,
                'NamaProduk' => mb_substr($b['info']->nama, 0, 150),
                'Sku' => $b['info']->sku,
                'JumlahStandar' => $b['standar']->KeString(),
                'Jumlah' => $b['jumlah']->KeString(),
                'Nilai' => null,
                'DibuatPada' => $waktu,
                'DiubahPada' => $waktu,
            ], $susunan['bahan'], array_keys($susunan['bahan'])));

            $ringkas = ['IdGudang' => $gudang->id, 'NamaGudang' => $gudang->nama, 'IdProduk' => $hasil->id, 'JumlahHasil' => $dokumen->JumlahHasil, 'JumlahBahan' => count($susunan['bahan']), 'BiayaOverhead' => $dokumen->BiayaOverhead];

            if ($ada === null) {
                $this->riwayat->Catat(OrderProduksi::JENIS_DOKUMEN, $dokumen->Id, null, StatusOrderProduksi::Draf->value, $oleh);
                $this->audit->Catat('order-produksi.buat', $dokumen, null, $ringkas);
            } else {
                $this->audit->Catat('order-produksi.ubah', $dokumen, $lama, $ringkas);
            }

            return $dokumen;
        }, max(1, (int) config('persediaan.PercobaanTransaksi', 3)));
    }
}

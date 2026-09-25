<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Sinkron\Enum\StatusItemSinkron;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Organisasi\Kueri\MejaOutlet;
use App\Domain\Penjualan\Data\DataPesananTerbukaPos;
use App\Domain\Penjualan\Layanan\PenjagaPesananTerbuka;
use Illuminate\Support\Facades\DB;

/**
 * Item outbox `PesananTerbuka.Ubah` (F-07 mode meja fase 1, §18.3): pindah meja, ubah label, dan jumlah tamu.
 * Last-writer-wins menurut `DiubahPada` perangkat: perubahan yang lebih lama dari `HeaderDiubahPada` diterima tanpa
 * mengubah apa pun (tetap `Diterima` agar outbox perangkat lama tidak macet). Pindah meja dicatat di log audit.
 */
final class UbahPesananTerbukaPos
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenjagaPesananTerbuka $penjaga,
        private readonly MejaOutlet $meja,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(DataPesananTerbukaPos $data): StatusItemSinkron
    {
        $this->penjaga->PastikanWaktuWajar($data->waktu, 'DiubahPada');

        return DB::transaction(function () use ($data): StatusItemSinkron {
            $pesanan = $this->penjaga->CariUntukDiubah($data->uuidPesanan, $data->idOutlet);
            $this->penjaga->PastikanTerbuka($pesanan);
            $pelaku = $this->penjaga->CariPelaku($this->konteks->Wajib(), $data->uuidPengguna, $data->idOutlet);

            if ($data->waktu->lessThanOrEqualTo($pesanan->HeaderDiubahPada)) {
                return StatusItemSinkron::Diterima;
            }

            $isian = ['HeaderDiubahPada' => $data->waktu];

            if ($data->ubahMeja) {
                $isian['IdMeja'] = $data->uuidMeja === null ? null : ($this->meja->CariAktif($data->idOutlet, $data->uuidMeja)['Id']
                    ?? throw new PelanggaranAturanBisnis('MejaTidakDitemukan', 'Meja tujuan tidak ditemukan atau sudah diarsipkan.', 'UuidMeja', 404));
            }

            if ($data->ubahLabel) {
                $isian['Label'] = $data->label;
            }

            if ($data->jumlahTamu !== null) {
                $isian['JumlahTamu'] = $data->jumlahTamu;
            }

            $mejaLama = $pesanan->IdMeja;
            $pesanan->update($isian);

            if ($data->ubahMeja && $mejaLama !== $pesanan->IdMeja) {
                $nama = $this->meja->AmbilPerId(array_values(array_filter([$mejaLama, $pesanan->IdMeja])));
                $this->audit->Catat('pesanan-terbuka.pindah-meja', $pesanan,
                    nilaiLama: ['Meja' => $mejaLama === null ? null : ($nama[$mejaLama]['Nama'] ?? null)],
                    nilaiBaru: ['Meja' => $pesanan->IdMeja === null ? null : ($nama[$pesanan->IdMeja]['Nama'] ?? null)],
                    idPengguna: $pelaku->id);
            }

            return StatusItemSinkron::Diterima;
        });
    }
}

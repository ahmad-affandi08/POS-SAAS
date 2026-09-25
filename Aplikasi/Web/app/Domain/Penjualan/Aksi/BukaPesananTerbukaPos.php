<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Sinkron\Enum\StatusItemSinkron;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Organisasi\Kueri\MejaOutlet;
use App\Domain\Penjualan\Data\DataPesananTerbukaPos;
use App\Domain\Penjualan\Enum\StatusPesananTerbuka;
use App\Domain\Penjualan\Layanan\PenjagaPesananTerbuka;
use App\Domain\Penjualan\Model\PesananTerbuka;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Item outbox `PesananTerbuka.Buka` (F-07 mode meja fase 1): membuka pesanan terbuka di outlet perangkat, opsional di
 * satu meja aktif. Idempoten per Uuid pesanan (`Duplikat`); nomor dipakai dokumen lain → `NomorSudahDipakai`. Meja
 * yang sudah punya pesanan terbuka lain tetap diterima (satu meja bisa punya beberapa bill, gabung/pisah bill di
 * perangkat) karena pesanan dibuat offline di perangkat yang berbeda.
 */
final class BukaPesananTerbukaPos
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenjagaPesananTerbuka $penjaga,
        private readonly MejaOutlet $meja,
    ) {}

    public function Jalankan(DataPesananTerbukaPos $data): StatusItemSinkron
    {
        $this->penjaga->PastikanWaktuWajar($data->waktu, 'DibukaPada');

        try {
            return DB::transaction(function () use ($data): StatusItemSinkron {
                $lama = PesananTerbuka::query()->where('Uuid', $data->uuidPesanan)->first();

                if ($lama !== null) {
                    if ($lama->IdOutlet !== $data->idOutlet) {
                        throw new PelanggaranAturanBisnis('UuidSudahDipakai', 'Kode unik pesanan sudah dipakai dokumen lain.', 'Uuid', 409);
                    }

                    return StatusItemSinkron::Duplikat;
                }

                $pelaku = $this->penjaga->CariPelaku($this->konteks->Wajib(), $data->uuidPengguna, $data->idOutlet);
                $idMeja = null;

                if ($data->uuidMeja !== null) {
                    $idMeja = $this->meja->CariAktif($data->idOutlet, $data->uuidMeja)['Id']
                        ?? throw new PelanggaranAturanBisnis('MejaTidakDitemukan', 'Meja tidak ditemukan atau sudah diarsipkan di outlet ini.', 'UuidMeja', 404);
                }

                PesananTerbuka::query()->create([
                    'Uuid' => $data->uuidPesanan,
                    'IdOutlet' => $data->idOutlet,
                    'IdPerangkat' => $data->idPerangkat,
                    'IdMeja' => $idMeja,
                    'Nomor' => (string) $data->nomor,
                    'Label' => $data->label,
                    'JumlahTamu' => $data->jumlahTamu ?? 1,
                    'Status' => StatusPesananTerbuka::Terbuka,
                    'IdPengguna' => $pelaku->id,
                    'DibukaPada' => $data->waktu,
                    'HeaderDiubahPada' => $data->waktu,
                ]);

                return StatusItemSinkron::Diterima;
            });
        } catch (QueryException $galat) {
            if (($galat->errorInfo[1] ?? null) === 1062) {
                if (str_contains($galat->getMessage(), 'UniqPesananTerbukaIdTenantNomor')) {
                    throw new PelanggaranAturanBisnis('NomorSudahDipakai', "Nomor pesanan {$data->nomor} sudah dipakai dokumen lain.", 'Nomor', 409);
                }

                throw new PelanggaranAturanBisnis('UuidSudahDipakai', 'Kode unik pesanan sudah dipakai dokumen lain.', 'Uuid', 409);
            }

            throw $galat;
        }
    }
}

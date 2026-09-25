<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Sinkron\Enum\StatusItemSinkron;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Kueri\AnggotaOutlet;
use App\Domain\Pelanggan\Data\DataPelangganPos;
use App\Domain\Pelanggan\Layanan\NomorHp;
use App\Domain\Pelanggan\Model\Pelanggan;
use App\Domain\Pelanggan\Model\PelangganAlias;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Pelanggan baru dari POS (item outbox `Pelanggan.Buat`, F-16a), berlaku offline. Idempoten per Uuid (Uuid yang sudah
 * ada di tenant = `Duplikat`). Pelaku anggota outlet ber-izin `penjualan.buat`. Nomor HP yang sudah terdaftar (dibuat
 * perangkat lain/back-office selagi offline) tidak ditolak: Uuid perangkat dicatat sebagai alias pelanggan lama agar
 * penjualan yang merujuknya tetap tertaut. Audit `pelanggan.tambah` (nomor HP tersamar).
 */
final class TerimaPelangganPos
{
    public function __construct(
        private readonly AnggotaOutlet $anggota,
        private readonly PencatatAudit $audit,
    ) {}

    /**
     * @throws PelanggaranAturanBisnis KasirTidakDitemukan, TanpaIzin, NoHpTidakValid, UuidSudahDipakai
     */
    public function Jalankan(DataPelangganPos $data): StatusItemSinkron
    {
        $pelaku = $this->anggota->Cari($data->idTenant, $data->uuidPengguna, $data->idOutlet);

        if ($pelaku === null) {
            throw new PelanggaranAturanBisnis('KasirTidakDitemukan', 'Pengguna ini tidak terdaftar di outlet perangkat.', 'UuidPengguna');
        }

        if (! $pelaku->CekIzin(IzinTenant::PenjualanBuat->value)) {
            throw new PelanggaranAturanBisnis('TanpaIzin', "{$pelaku->nama} tidak punya izin mencatat pelanggan di POS.", 'UuidPengguna', 403);
        }

        $noHp = NomorHp::Normalisasi($data->noHp);

        if ($noHp === null) {
            throw new PelanggaranAturanBisnis('NoHpTidakValid', 'Nomor HP pelanggan tidak valid.', 'NoHp');
        }

        try {
            return DB::transaction(function () use ($data, $noHp, $pelaku): StatusItemSinkron {
                if (Pelanggan::query()->where('Uuid', $data->uuid)->exists() || PelangganAlias::query()->where('Uuid', $data->uuid)->exists()) {
                    return StatusItemSinkron::Duplikat;
                }

                $lama = Pelanggan::query()->where('NoHp', $noHp)->lockForUpdate()->first();

                if ($lama !== null) {
                    PelangganAlias::query()->create(['Uuid' => $data->uuid, 'IdPelanggan' => $lama->Id]);

                    return StatusItemSinkron::Diterima;
                }

                $isian = ['Nama' => trim($data->nama), 'NoHp' => $noHp, 'Email' => $data->email];
                $baru = Pelanggan::query()->create([
                    ...$isian,
                    'Uuid' => $data->uuid,
                    'DibuatOleh' => $pelaku->id,
                    'IdPerangkatPembuat' => $data->idPerangkat,
                ]);
                $this->audit->Catat('pelanggan.tambah', $baru, nilaiBaru: [...$isian, 'NoHp' => NomorHp::Samarkan($noHp), 'Sumber' => 'POS'], idPengguna: $pelaku->id);

                return StatusItemSinkron::Diterima;
            }, 3);
        } catch (QueryException $galat) {
            // Uuid sudah dipakai tenant lain (tidak terlihat lewat MilikTenant).
            if (str_contains($galat->getMessage(), 'UniqPelangganUuid')) {
                throw new PelanggaranAturanBisnis('UuidSudahDipakai', 'Uuid pelanggan sudah dipakai. Buat ulang pelanggan di perangkat.', 'Uuid');
            }

            throw $galat;
        }
    }
}

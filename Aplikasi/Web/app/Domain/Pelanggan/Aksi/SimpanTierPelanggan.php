<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pelanggan\Data\DataTierPelanggan;
use App\Domain\Pelanggan\Model\TierPelanggan;
use Illuminate\Support\Facades\DB;

/**
 * Tambah/ubah tier pelanggan (F-16b, izin `pelanggan.kelola`). Kode unik per tenant, huruf besar; kode tidak bisa
 * diubah setelah dibuat karena dirujuk daftar harga (`DaftarHarga.TierPelanggan`). Pengali poin 0,1–10. Audit
 * `tier-pelanggan.tambah`/`tier-pelanggan.ubah`.
 */
final class SimpanTierPelanggan
{
    public const BATAS_AKTIF = 10;

    public function __construct(private readonly PencatatAudit $audit) {}

    /**
     * @throws PelanggaranAturanBisnis KodeSudahDipakai, BatasTier, PengaliTidakValid
     */
    public function Jalankan(DataTierPelanggan $data, ?TierPelanggan $tier = null): TierPelanggan
    {
        if ($data->pengaliPoin->isLessThan('0.1') || $data->pengaliPoin->isGreaterThan(10)) {
            throw new PelanggaranAturanBisnis('PengaliTidakValid', 'Pengali poin antara 0,1 dan 10.', 'PengaliPoin');
        }

        return DB::transaction(function () use ($data, $tier): TierPelanggan {
            $isian = [
                'Nama' => trim($data->nama),
                'MinimalBelanja' => $data->minimalBelanja->KeString(),
                'PengaliPoin' => (string) $data->pengaliPoin->toScale(2),
                'Urutan' => $data->urutan,
            ];

            if ($tier === null) {
                $kode = mb_strtoupper(trim($data->kode));

                if (TierPelanggan::query()->where('Kode', $kode)->lockForUpdate()->exists()) {
                    throw new PelanggaranAturanBisnis('KodeSudahDipakai', "Kode tier {$kode} sudah dipakai.", 'Kode');
                }

                if (TierPelanggan::query()->where('Status', 'Aktif')->count() >= self::BATAS_AKTIF) {
                    throw new PelanggaranAturanBisnis('BatasTier', 'Paling banyak '.self::BATAS_AKTIF.' tier aktif.', 'Umum');
                }

                $baru = TierPelanggan::query()->create([...$isian, 'Kode' => $kode]);
                $this->audit->Catat('tier-pelanggan.tambah', $baru, nilaiBaru: [...$isian, 'Kode' => $kode], idPengguna: $data->idPengguna);

                return $baru;
            }

            $terkunci = TierPelanggan::query()->whereKey($tier->Id)->lockForUpdate()->firstOrFail();
            $lama = $terkunci->only(array_keys($isian));
            $terkunci->fill($isian);
            $berubah = array_keys($terkunci->getDirty());
            $terkunci->save();

            if ($berubah !== []) {
                $kunci = array_flip($berubah);
                $this->audit->Catat('tier-pelanggan.ubah', $terkunci, array_intersect_key($lama, $kunci), array_intersect_key($isian, $kunci), idPengguna: $data->idPengguna);
            }

            return $terkunci;
        }, 3);
    }
}

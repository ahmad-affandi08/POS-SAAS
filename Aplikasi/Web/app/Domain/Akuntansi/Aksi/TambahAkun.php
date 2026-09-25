<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Aksi;

use App\Domain\Akuntansi\Data\DataAkun;
use App\Domain\Akuntansi\Layanan\AturanAkun;
use App\Domain\Akuntansi\Model\Akun;
use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Tenant\Layanan\PenguncianTenant;
use Illuminate\Support\Facades\DB;

/**
 * F-13a bagan akun: tambah akun (anak) tenant. Akun anak mewarisi tipe induknya; kode unik per tenant (baris Tenant
 * dikunci agar kirim ganda aman). Saldo normal dari tipe (kebalikannya untuk akun kontra). LogAudit `akun.tambah`.
 */
final class TambahAkun
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenguncianTenant $penguncian,
        private readonly PencatatAudit $audit,
    ) {}

    /**
     * @throws PelanggaranAturanBisnis IndukTidakDikenal, TipeAkunBedaInduk, KodeAkunTidakSesuaiTipe, KodeAkunSudahAda, KasBankHanyaAset
     */
    public function Jalankan(DataAkun $data): Akun
    {
        return DB::transaction(function () use ($data): Akun {
            $this->penguncian->Kunci($this->konteks->Wajib());
            $induk = null;

            if ($data->uuidInduk !== null) {
                $induk = Akun::query()->where('Uuid', $data->uuidInduk)->first();

                if (! $induk instanceof Akun || ! $induk->Aktif) {
                    throw new PelanggaranAturanBisnis('IndukTidakDikenal', 'Akun induk tidak ditemukan atau nonaktif.', 'UuidInduk');
                }

                if ($induk->Jenis !== $data->tipe) {
                    throw new PelanggaranAturanBisnis('TipeAkunBedaInduk', "Akun anak harus bertipe sama dengan induknya ({$induk->Jenis->AmbilLabel()}).", 'Jenis');
                }
            }

            AturanAkun::Periksa($data);

            $akun = Akun::query()->create([
                'Kode' => $data->kode,
                'Nama' => trim($data->nama),
                'Jenis' => $data->tipe,
                'SaldoNormal' => $data->tipe->AmbilSaldoNormal($data->kontra),
                'IdInduk' => $induk?->Id,
                'Sistem' => false,
                'Aktif' => true,
                'KasBank' => $data->kasBank,
            ]);

            $this->audit->Catat('akun.tambah', $akun, nilaiBaru: [
                'Kode' => $akun->Kode,
                'Nama' => $akun->Nama,
                'Jenis' => $data->tipe->value,
                'SaldoNormal' => $akun->SaldoNormal->value,
                'KodeInduk' => $induk?->Kode,
                'KasBank' => $data->kasBank,
            ]);

            return $akun;
        });
    }
}

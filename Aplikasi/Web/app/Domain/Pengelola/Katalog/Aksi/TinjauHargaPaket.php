<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Katalog\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Status\StatusDataMaster;
use App\Domain\Pengelola\Referensi\Enum\KeputusanTinjauan;
use App\Domain\Pengelola\Referensi\Layanan\TinjauanDataMaster;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Tenant\Model\HargaPaket;
use App\Domain\Tenant\Model\Paket;
use Illuminate\Support\Facades\DB;

/**
 * Tinjauan harga paket (BR-P04.5): 1 penyetuju (Super Admin) yang bukan penyusun/pengaju. Saat terbit, harga terbit
 * sebelumnya diakhiri sehari sebelum harga baru berlaku. BR-P04.1: harga baru hanya berlaku untuk tagihan berikutnya.
 */
final class TinjauHargaPaket
{
    public const JENIS_DATA = 'HargaPaket';

    public const PENYETUJU = 1;

    public function __construct(
        private readonly TinjauanDataMaster $tinjauan,
        private readonly PencatatAuditPengelola $audit,
    ) {}

    public static function PastikanBelumLewat(HargaPaket $harga): void
    {
        if ($harga->BerlakuMulai->toDateString() < now('Asia/Jakarta')->toDateString()) {
            throw new PelanggaranAturanBisnis(
                'BR-P04.5',
                'Tanggal berlaku harga sudah lewat. Ubah draf dengan tanggal hari ini atau sesudahnya.',
                'BerlakuMulai',
            );
        }
    }

    public function Jalankan(PenggunaPengelola $peninjau, HargaPaket $harga, KeputusanTinjauan $keputusan, ?string $catatan = null): StatusDataMaster
    {
        return DB::transaction(function () use ($peninjau, $harga, $keputusan, $catatan): StatusDataMaster {
            $harga = HargaPaket::query()->lockForUpdate()->findOrFail($harga->Id);
            // Kunci per paket: penerbitan harga paket yang sama berjalan berurutan.
            Paket::query()->whereKey($harga->IdPaket)->lockForUpdate()->firstOrFail();

            if ($harga->Status !== StatusDataMaster::MenungguTinjauan) {
                throw new PelanggaranAturanBisnis('StatusTidakSesuai', 'Harga ini tidak sedang menunggu tinjauan.');
            }

            $jumlahSetuju = $this->tinjauan->CatatKeputusan(
                self::JENIS_DATA,
                $harga->Id,
                $harga->PutaranTinjauan,
                TinjauanDataMaster::GabungTerlarang([[$harga->DaftarIdPenyusun, $harga->IdPenggunaPengelolaPengaju]]),
                $peninjau,
                $keputusan,
                $catatan,
            );

            if ($keputusan === KeputusanTinjauan::Tolak) {
                $harga->update(['Status' => StatusDataMaster::Draf]);
                $this->audit->Catat(
                    'katalog.harga.tolak',
                    $harga,
                    nilaiLama: ['Status' => StatusDataMaster::MenungguTinjauan->value],
                    nilaiBaru: ['Status' => StatusDataMaster::Draf->value],
                    alasan: $catatan,
                    idPelaku: $peninjau->Id,
                );

                return StatusDataMaster::Draf;
            }

            if ($jumlahSetuju < self::PENYETUJU) {
                return StatusDataMaster::MenungguTinjauan;
            }

            $this->Terbitkan($harga, $peninjau);

            return StatusDataMaster::Terbit;
        });
    }

    private function Terbitkan(HargaPaket $harga, PenggunaPengelola $peninjau): void
    {
        self::PastikanBelumLewat($harga);

        $terbit = HargaPaket::query()
            ->where('IdPaket', $harga->IdPaket)
            ->where('Status', StatusDataMaster::Terbit->value)
            ->lockForUpdate()
            ->get();

        foreach ($terbit as $lama) {
            if ($lama->BerlakuMulai->greaterThanOrEqualTo($harga->BerlakuMulai)) {
                throw new PelanggaranAturanBisnis(
                    'BR-P04.5',
                    'Sudah ada harga terbit yang berlaku mulai '.$lama->BerlakuMulai->toDateString().'. Harga baru harus berlaku setelahnya.',
                );
            }
        }

        $sampai = $harga->BerlakuMulai->copy()->subDay();

        foreach ($terbit as $lama) {
            if ($lama->BerlakuSampai === null || $lama->BerlakuSampai->greaterThan($sampai)) {
                $sampaiLama = $lama->BerlakuSampai?->toDateString();
                $lama->update(['BerlakuSampai' => $sampai->toDateString()]);
                $this->audit->Catat(
                    'katalog.harga.akhiri',
                    $lama,
                    nilaiLama: ['BerlakuSampai' => $sampaiLama],
                    nilaiBaru: ['BerlakuSampai' => $sampai->toDateString()],
                    alasan: 'Digantikan harga '.$harga->Uuid,
                    idPelaku: $peninjau->Id,
                );
            }
        }

        $harga->update(['Status' => StatusDataMaster::Terbit]);
        $this->audit->Catat(
            'katalog.harga.terbit',
            $harga,
            nilaiLama: ['Status' => StatusDataMaster::MenungguTinjauan->value],
            nilaiBaru: ['Status' => StatusDataMaster::Terbit->value, 'HargaBulanan' => $harga->HargaBulanan, 'HargaTahunan' => $harga->HargaTahunan],
            idPelaku: $peninjau->Id,
        );
    }
}

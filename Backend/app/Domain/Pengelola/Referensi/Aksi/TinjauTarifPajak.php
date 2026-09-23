<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Referensi\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Status\StatusDataMaster;
use App\Domain\Pajak\Model\JenisPajak;
use App\Domain\Pajak\Model\TarifPajak;
use App\Domain\Pajak\Peristiwa\TarifPajakTerbit;
use App\Domain\Pengelola\Referensi\Enum\KeputusanTinjauan;
use App\Domain\Pengelola\Referensi\Layanan\TinjauanDataMaster;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use Illuminate\Support\Facades\DB;

/**
 * P-02 langkah 3–4: peninjau menyetujui atau menolak. BR-P02.2: tarif nasional terbit setelah 2 penyetuju berbeda,
 * tarif daerah setelah 1; pengaju tidak boleh meninjau; satu penolakan mengembalikan ke Draf.
 * Saat terbit, tarif terbit sebelumnya (jenis & wilayah sama) diberi BerlakuSampai = sehari sebelum tarif baru.
 */
final class TinjauTarifPajak
{
    public const JENIS_DATA = 'TarifPajak';

    public const PENYETUJU_NASIONAL = 2;

    public const PENYETUJU_DAERAH = 1;

    public function __construct(
        private readonly TinjauanDataMaster $tinjauan,
        private readonly PencatatAuditPengelola $audit,
    ) {}

    public function Jalankan(PenggunaPengelola $peninjau, TarifPajak $tarif, KeputusanTinjauan $keputusan, ?string $catatan = null): StatusDataMaster
    {
        $hasil = DB::transaction(function () use ($peninjau, $tarif, $keputusan, $catatan): StatusDataMaster {
            $tarif = TarifPajak::query()->lockForUpdate()->findOrFail($tarif->Id);
            // Kunci per jenis pajak: penerbitan tarif jenis yang sama berjalan berurutan, sehingga dua tarif
            // tidak bisa terbit bersamaan tanpa saling mengakhiri.
            JenisPajak::query()->whereKey($tarif->IdJenisPajak)->lockForUpdate()->firstOrFail();

            if ($tarif->Status !== StatusDataMaster::MenungguTinjauan) {
                throw new PelanggaranAturanBisnis('StatusTidakSesuai', 'Tarif ini tidak sedang menunggu tinjauan.');
            }

            $jumlahSetuju = $this->tinjauan->CatatKeputusan(
                self::JENIS_DATA,
                $tarif->Id,
                $tarif->PutaranTinjauan,
                array_values(array_filter([$tarif->IdPenggunaPengelolaPengaju])),
                $peninjau,
                $keputusan,
                $catatan,
            );

            if ($keputusan === KeputusanTinjauan::Tolak) {
                $tarif->update(['Status' => StatusDataMaster::Draf]);
                $this->audit->Catat(
                    'referensi.tarif-pajak.tolak',
                    $tarif,
                    nilaiLama: ['Status' => StatusDataMaster::MenungguTinjauan->value],
                    nilaiBaru: ['Status' => StatusDataMaster::Draf->value],
                    alasan: $catatan,
                    idPelaku: $peninjau->Id,
                );

                return StatusDataMaster::Draf;
            }

            $this->audit->Catat('referensi.tarif-pajak.setujui', $tarif, alasan: $catatan, idPelaku: $peninjau->Id);
            $dibutuhkan = $tarif->CekNasional() ? self::PENYETUJU_NASIONAL : self::PENYETUJU_DAERAH;

            if ($jumlahSetuju < $dibutuhkan) {
                return StatusDataMaster::MenungguTinjauan;
            }

            $this->Terbitkan($tarif, $peninjau);

            return StatusDataMaster::Terbit;
        });

        if ($hasil === StatusDataMaster::Terbit) {
            TarifPajakTerbit::dispatch($tarif->Id);
        }

        return $hasil;
    }

    private function Terbitkan(TarifPajak $tarif, PenggunaPengelola $peninjau): void
    {
        $terbitSebelumnya = TarifPajak::query()
            ->where('IdJenisPajak', $tarif->IdJenisPajak)
            ->where('KodeWilayah', $tarif->KodeWilayah)
            ->where('Status', StatusDataMaster::Terbit->value)
            ->lockForUpdate()
            ->get();

        foreach ($terbitSebelumnya as $lama) {
            if ($lama->BerlakuMulai->greaterThanOrEqualTo($tarif->BerlakuMulai)) {
                throw new PelanggaranAturanBisnis(
                    'BR-P02.1',
                    'Sudah ada tarif terbit yang berlaku mulai '.$lama->BerlakuMulai->toDateString().'. Tarif baru harus berlaku setelahnya.',
                );
            }
        }

        $sampai = $tarif->BerlakuMulai->copy()->subDay();

        foreach ($terbitSebelumnya as $lama) {
            if ($lama->BerlakuSampai === null || $lama->BerlakuSampai->greaterThan($sampai)) {
                $berlakuSampaiLama = $lama->BerlakuSampai?->toDateString();
                // Satu-satunya perubahan yang diizinkan pada tarif terbit (P-02 langkah 1, BR-P02.1).
                $lama->update(['BerlakuSampai' => $sampai->toDateString()]);
                $this->audit->Catat(
                    'referensi.tarif-pajak.akhiri',
                    $lama,
                    nilaiLama: ['BerlakuSampai' => $berlakuSampaiLama],
                    nilaiBaru: ['BerlakuSampai' => $sampai->toDateString()],
                    alasan: 'Digantikan tarif '.$tarif->Uuid,
                    idPelaku: $peninjau->Id,
                );
            }
        }

        $tarif->update(['Status' => StatusDataMaster::Terbit]);
        $this->audit->Catat(
            'referensi.tarif-pajak.terbit',
            $tarif,
            nilaiLama: ['Status' => StatusDataMaster::MenungguTinjauan->value],
            nilaiBaru: ['Status' => StatusDataMaster::Terbit->value],
            idPelaku: $peninjau->Id,
        );
    }
}

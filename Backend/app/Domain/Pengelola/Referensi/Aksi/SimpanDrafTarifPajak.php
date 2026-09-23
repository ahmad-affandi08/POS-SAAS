<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Referensi\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Status\StatusDataMaster;
use App\Domain\Pajak\Enum\CakupanPajak;
use App\Domain\Pajak\Model\JenisPajak;
use App\Domain\Pajak\Model\TarifPajak;
use App\Domain\Pengelola\Referensi\Data\DataTarifPajak;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Referensi\Enum\TingkatWilayah;
use App\Domain\Referensi\Model\Wilayah;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Illuminate\Support\Facades\DB;

/**
 * P-02 langkah 1–2: membuat atau mengubah DRAF tarif pajak. Tarif yang sudah diajukan atau terbit tidak bisa diubah
 * (BR-P02.1); koreksi tarif terbit = draf baru dengan BerlakuMulai baru.
 */
final class SimpanDrafTarifPajak
{
    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    public function Jalankan(PenggunaPengelola $pelaku, DataTarifPajak $data, ?TarifPajak $tarif = null): TarifPajak
    {
        return DB::transaction(function () use ($pelaku, $data, $tarif): TarifPajak {
            if ($tarif !== null) {
                $tarif = TarifPajak::query()->lockForUpdate()->findOrFail($tarif->Id);

                if ($tarif->Status !== StatusDataMaster::Draf) {
                    throw new PelanggaranAturanBisnis(
                        'BR-P02.1',
                        'Hanya draf yang bisa diubah. Tarif yang sudah terbit dikoreksi dengan tarif baru bertanggal berlaku baru.',
                    );
                }
            }

            $jenis = JenisPajak::query()->where('Kode', $data->kodeJenisPajak)->first()
                ?? throw new PelanggaranAturanBisnis('JenisPajakTidakDikenal', 'Jenis pajak tidak dikenal.', 'KodeJenisPajak');

            self::PastikanValid($jenis, $data);

            $nilaiLama = $tarif?->only(['Tarif', 'PengaliDppPembilang', 'PengaliDppPenyebut', 'KodeWilayah', 'BiayaLayananMasukDpp', 'NomorDasarHukum']);
            $tarif ??= new TarifPajak(['Status' => StatusDataMaster::Draf, 'IdPenggunaPengelolaPengaju' => $pelaku->Id]);
            $tarif->fill([
                'IdJenisPajak' => $jenis->Id,
                'Tarif' => (string) BigDecimal::of($data->tarif)->toScale(4),
                'PengaliDppPembilang' => $data->pengaliDppPembilang,
                'PengaliDppPenyebut' => $data->pengaliDppPenyebut,
                'KodeWilayah' => $data->kodeWilayah,
                'BiayaLayananMasukDpp' => $data->biayaLayananMasukDpp,
                'BerlakuMulai' => $data->berlakuMulai->toDateString(),
                'NomorDasarHukum' => $data->nomorDasarHukum,
                'TautanDasarHukum' => $data->tautanDasarHukum,
            ])->save();

            $this->audit->Catat(
                $nilaiLama === null ? 'referensi.tarif-pajak.buat-draf' : 'referensi.tarif-pajak.ubah-draf',
                $tarif,
                nilaiLama: $nilaiLama,
                nilaiBaru: [
                    'JenisPajak' => $jenis->Kode,
                    'Tarif' => $tarif->Tarif,
                    'PengaliDpp' => "{$data->pengaliDppPembilang}/{$data->pengaliDppPenyebut}",
                    'KodeWilayah' => $data->kodeWilayah,
                    'BerlakuMulai' => $data->berlakuMulai->toDateString(),
                ],
                idPelaku: $pelaku->Id,
            );

            return $tarif;
        });
    }

    private static function PastikanValid(JenisPajak $jenis, DataTarifPajak $data): void
    {
        try {
            $tarif = BigDecimal::of($data->tarif);
        } catch (MathException) {
            throw new PelanggaranAturanBisnis('TarifTidakValid', 'Tarif harus berupa angka desimal, misal 10 atau 10.5.', 'Tarif');
        }

        if ($tarif->getScale() > 4 || $tarif->isNegativeOrZero() || $tarif->isGreaterThan(100)) {
            throw new PelanggaranAturanBisnis('TarifTidakValid', 'Tarif harus lebih dari 0 dan paling tinggi 100 persen, maksimal 4 desimal.', 'Tarif');
        }

        if ($data->pengaliDppPembilang < 1 || $data->pengaliDppPenyebut < 1 || $data->pengaliDppPembilang > $data->pengaliDppPenyebut) {
            throw new PelanggaranAturanBisnis('PengaliDppTidakValid', 'Pengali DPP harus pecahan antara 0 dan 1, misal 11/12 atau 1/1.', 'PengaliDppPembilang');
        }

        if ($jenis->Cakupan === CakupanPajak::Nasional && $data->kodeWilayah !== null) {
            throw new PelanggaranAturanBisnis('WilayahTidakBerlaku', 'Tarif pajak nasional tidak memakai wilayah.', 'KodeWilayah');
        }

        if ($jenis->Cakupan === CakupanPajak::Daerah) {
            $kabKotaAda = $data->kodeWilayah !== null && Wilayah::query()
                ->where('Kode', $data->kodeWilayah)
                ->where('Tingkat', TingkatWilayah::KabupatenKota->value)
                ->exists();

            if (! $kabKotaAda) {
                throw new PelanggaranAturanBisnis('WilayahWajib', 'Tarif pajak daerah wajib memilih kabupaten/kota yang terdaftar.', 'KodeWilayah');
            }
        }
    }
}

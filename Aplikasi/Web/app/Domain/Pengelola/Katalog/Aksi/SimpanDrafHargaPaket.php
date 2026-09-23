<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Katalog\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Status\StatusDataMaster;
use App\Domain\Pengelola\Katalog\Data\DataHargaPaket;
use App\Domain\Pengelola\Referensi\Layanan\TinjauanDataMaster;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Tenant\Model\HargaPaket;
use App\Domain\Tenant\Model\Paket;
use Brick\Math\Exception\MathException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Membuat atau mengubah DRAF harga paket (P-04, BR-P04.1, BR-P04.5). Harga terbit tidak diubah; perubahan harga
 * = versi baru dengan tanggal berlaku baru.
 */
final class SimpanDrafHargaPaket
{
    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    public function Jalankan(PenggunaPengelola $pelaku, Paket $paket, DataHargaPaket $data, ?HargaPaket $harga = null): HargaPaket
    {
        return DB::transaction(function () use ($pelaku, $paket, $data, $harga): HargaPaket {
            if ($paket->HargaNegosiasi) {
                throw new PelanggaranAturanBisnis('HargaNegosiasi', 'Paket ini memakai harga negosiasi dan tidak punya harga tetap.');
            }

            $nilaiLama = null;

            if ($harga !== null) {
                $harga = HargaPaket::query()->lockForUpdate()->findOrFail($harga->Id);

                if ($harga->IdPaket !== $paket->Id || $harga->Status !== StatusDataMaster::Draf) {
                    throw new PelanggaranAturanBisnis('BR-P04.5', 'Hanya draf harga yang bisa diubah. Harga terbit diganti dengan versi baru.');
                }

                $nilaiLama = self::AmbilNilai($harga);
            }

            $bulanan = self::PastikanUang($data->hargaBulanan, 'HargaBulanan');
            $tahunan = self::PastikanUang($data->hargaTahunan, 'HargaTahunan');

            $harga ??= new HargaPaket(['IdPaket' => $paket->Id, 'Status' => StatusDataMaster::Draf]);
            $harga->fill([
                'HargaBulanan' => $bulanan->KeString(),
                'HargaTahunan' => $tahunan->KeString(),
                'BerlakuMulai' => $data->berlakuMulai->toDateString(),
                'TerapkanKePelangganLama' => $data->terapkanKePelangganLama,
                'DaftarIdPenyusun' => TinjauanDataMaster::TambahPenyusun($harga->DaftarIdPenyusun, $pelaku->Id),
            ])->save();

            $this->audit->Catat(
                $nilaiLama === null ? 'katalog.harga.buat-draf' : 'katalog.harga.ubah-draf',
                $harga,
                nilaiLama: $nilaiLama,
                nilaiBaru: ['Paket' => $paket->Kode, ...self::AmbilNilai($harga)],
                idPelaku: $pelaku->Id,
            );

            return $harga;
        });
    }

    private static function PastikanUang(string $nilai, string $bidang): Uang
    {
        try {
            $uang = Uang::Dari($nilai);
        } catch (InvalidArgumentException|MathException) {
            throw new PelanggaranAturanBisnis('HargaTidakValid', 'Harga berupa angka Rupiah, maksimal 2 desimal.', $bidang);
        }

        if ($uang->BernilaiNegatif()) {
            throw new PelanggaranAturanBisnis('HargaTidakValid', 'Harga tidak boleh negatif.', $bidang);
        }

        return $uang;
    }

    /**
     * @return array<string, mixed>
     */
    private static function AmbilNilai(HargaPaket $harga): array
    {
        return [
            'HargaBulanan' => $harga->HargaBulanan,
            'HargaTahunan' => $harga->HargaTahunan,
            'BerlakuMulai' => $harga->BerlakuMulai->toDateString(),
            'TerapkanKePelangganLama' => $harga->TerapkanKePelangganLama,
        ];
    }
}

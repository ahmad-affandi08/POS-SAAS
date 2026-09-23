<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Katalog\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Pengelola\Katalog\Data\DataKupon;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Tenant\Enum\JenisKupon;
use App\Domain\Tenant\Model\KuponLangganan;
use App\Domain\Tenant\Model\Paket;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Membuat atau mengubah kupon langganan (P-04). Pemakaian kupon dicatat penagihan (P-08); kupon yang sudah dipakai
 * cukup dinonaktifkan, tidak dihapus.
 */
final class SimpanKupon
{
    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    public function Jalankan(PenggunaPengelola $pelaku, DataKupon $data, ?KuponLangganan $kupon = null): KuponLangganan
    {
        try {
            return DB::transaction(function () use ($pelaku, $data, $kupon): KuponLangganan {
                if ($kupon !== null && $kupon->Kode !== $data->kode) {
                    throw new PelanggaranAturanBisnis('KodeTidakBisaDiubah', 'Kode kupon tidak bisa diubah.', 'Kode');
                }

                if (preg_match('/^[A-Z0-9-]{3,30}$/', $data->kode) !== 1) {
                    throw new PelanggaranAturanBisnis('KodeTidakValid', 'Kode kupon huruf besar/angka/tanda hubung, 3–30 karakter.', 'Kode');
                }

                if ($kupon === null && KuponLangganan::query()->where('Kode', $data->kode)->exists()) {
                    throw new PelanggaranAturanBisnis('KodeSudahAda', "Kode kupon {$data->kode} sudah ada.", 'Kode');
                }

                $nilai = self::PastikanNilai($data);

                if ($data->durasiBulan < 1 || ($data->kuota !== null && $data->kuota < 1)) {
                    throw new PelanggaranAturanBisnis('DurasiTidakValid', 'Durasi minimal 1 bulan dan kuota minimal 1 (atau kosong = tanpa batas).', 'DurasiBulan');
                }

                if ($data->daftarKodePaket !== null) {
                    $kode = array_values(array_unique($data->daftarKodePaket));

                    if ($kode === [] || Paket::query()->whereIn('Kode', $kode)->count() !== count($kode)) {
                        throw new PelanggaranAturanBisnis('PaketTidakDikenal', 'Pilih paket yang terdaftar, atau kosongkan untuk semua paket.', 'DaftarKodePaket');
                    }
                }

                $berlakuSampaiBerubah = $kupon === null || $kupon->BerlakuSampai?->toDateString() !== $data->berlakuSampai?->toDateString();

                // Kupon yang sudah kedaluwarsa tetap bisa diubah/dinonaktifkan selama tanggalnya tidak diubah ke masa lalu.
                if ($berlakuSampaiBerubah && $data->berlakuSampai !== null && $data->berlakuSampai->toDateString() < now('Asia/Jakarta')->toDateString()) {
                    throw new PelanggaranAturanBisnis('TanggalLewat', 'Tanggal berlaku kupon sudah lewat.', 'BerlakuSampai');
                }

                $nilaiLama = $kupon === null ? null : self::AmbilNilai($kupon);
                $kupon ??= new KuponLangganan;
                $kupon->fill([
                    'Kode' => $data->kode,
                    'Jenis' => $data->jenis,
                    'Nilai' => $nilai,
                    'DurasiBulan' => $data->durasiBulan,
                    'Kuota' => $data->kuota,
                    'DaftarKodePaket' => $data->daftarKodePaket,
                    'BerlakuSampai' => $data->berlakuSampai?->toDateString(),
                    'Aktif' => $data->aktif,
                ])->save();

                $this->audit->Catat(
                    $nilaiLama === null ? 'katalog.kupon.buat' : 'katalog.kupon.ubah',
                    $kupon,
                    nilaiLama: $nilaiLama,
                    nilaiBaru: self::AmbilNilai($kupon),
                    idPelaku: $pelaku->Id,
                );

                return $kupon;
            });
        } catch (UniqueConstraintViolationException) {
            throw new PelanggaranAturanBisnis('KodeSudahAda', 'Kode kupon ini baru saja dipakai. Muat ulang halaman lalu pakai kode lain.', 'Kode');
        }
    }

    private static function PastikanNilai(DataKupon $data): string
    {
        try {
            if ($data->jenis === JenisKupon::Nominal) {
                $uang = Uang::Dari($data->nilai);

                if ($uang->BernilaiNegatif() || $uang->BernilaiNol()) {
                    throw new InvalidArgumentException('Nominal harus lebih dari 0.');
                }

                return $uang->KeString();
            }

            $persen = BigDecimal::of($data->nilai);

            if ($persen->getScale() > 2 || $persen->isNegativeOrZero() || $persen->isGreaterThan(100)) {
                throw new InvalidArgumentException('Persen di luar rentang.');
            }

            return (string) $persen->toScale(2);
        } catch (InvalidArgumentException|MathException) {
            throw new PelanggaranAturanBisnis(
                'NilaiTidakValid',
                $data->jenis === JenisKupon::Persen ? 'Diskon persen harus lebih dari 0 dan paling tinggi 100.' : 'Diskon nominal harus Rupiah lebih dari 0.',
                'Nilai',
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function AmbilNilai(KuponLangganan $kupon): array
    {
        return [
            'Jenis' => $kupon->Jenis->value,
            'Nilai' => $kupon->Nilai,
            'DurasiBulan' => $kupon->DurasiBulan,
            'Kuota' => $kupon->Kuota,
            'DaftarKodePaket' => $kupon->DaftarKodePaket,
            'BerlakuSampai' => $kupon->BerlakuSampai?->toDateString(),
            'Aktif' => $kupon->Aktif,
        ];
    }
}

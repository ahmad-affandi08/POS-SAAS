<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Referensi\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pengelola\Referensi\Data\DataWilayah;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Referensi\Enum\TingkatWilayah;
use App\Domain\Referensi\Model\Wilayah;
use Illuminate\Support\Facades\DB;

/**
 * Membuat atau mengubah satu wilayah (P-02). Kode wilayah tidak bisa diubah setelah dibuat.
 */
final class SimpanWilayah
{
    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    public function Jalankan(PenggunaPengelola $pelaku, DataWilayah $data, ?Wilayah $wilayah = null): Wilayah
    {
        return DB::transaction(function () use ($pelaku, $data, $wilayah): Wilayah {
            if ($wilayah !== null && $wilayah->Kode !== $data->kode) {
                throw new PelanggaranAturanBisnis('KodeTidakBisaDiubah', 'Kode wilayah tidak bisa diubah.', 'Kode');
            }

            self::PastikanValid($data);

            if ($wilayah === null && Wilayah::query()->where('Kode', $data->kode)->exists()) {
                throw new PelanggaranAturanBisnis('KodeSudahAda', "Kode wilayah {$data->kode} sudah ada.", 'Kode');
            }

            $nilaiLama = $wilayah === null ? null : self::AmbilNilai($wilayah);
            $wilayah ??= new Wilayah;
            $wilayah->fill($data->KeLarik())->save();

            $this->audit->Catat(
                $nilaiLama === null ? 'referensi.wilayah.buat' : 'referensi.wilayah.ubah',
                $wilayah,
                nilaiLama: $nilaiLama,
                nilaiBaru: $data->KeLarik(),
                idPelaku: $pelaku->Id,
            );

            return $wilayah;
        });
    }

    /** Aturan kode resmi: provinsi "33" tanpa induk; kabupaten/kota "33.74" dengan induk provinsi "33". */
    public static function PastikanValid(DataWilayah $data, bool $indukBoleh = false): void
    {
        if (preg_match($data->tingkat->AmbilPolaKode(), $data->kode) !== 1) {
            throw new PelanggaranAturanBisnis(
                'KodeWilayahTidakValid',
                $data->tingkat === TingkatWilayah::Provinsi
                    ? 'Kode provinsi harus 2 digit, misal 33.'
                    : 'Kode kabupaten/kota harus berformat 33.74.',
                'Kode',
            );
        }

        if ($data->tingkat === TingkatWilayah::Provinsi) {
            if ($data->kodeInduk !== null) {
                throw new PelanggaranAturanBisnis('IndukTidakValid', 'Provinsi tidak punya wilayah induk.', 'KodeInduk');
            }

            return;
        }

        if ($data->kodeInduk === null || ! str_starts_with($data->kode, $data->kodeInduk.'.')) {
            throw new PelanggaranAturanBisnis('IndukTidakValid', 'Induk kabupaten/kota harus provinsi dengan awalan kode yang sama.', 'KodeInduk');
        }

        $indukAda = $indukBoleh || Wilayah::query()
            ->where('Kode', $data->kodeInduk)
            ->where('Tingkat', TingkatWilayah::Provinsi->value)
            ->exists();

        if (! $indukAda) {
            throw new PelanggaranAturanBisnis('IndukTidakValid', "Provinsi {$data->kodeInduk} belum ada.", 'KodeInduk');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function AmbilNilai(Wilayah $wilayah): array
    {
        return [
            'Kode' => $wilayah->Kode,
            'Nama' => $wilayah->Nama,
            'Tingkat' => $wilayah->Tingkat->value,
            'KodeInduk' => $wilayah->KodeInduk,
            'ZonaWaktu' => $wilayah->ZonaWaktu->value,
        ];
    }
}

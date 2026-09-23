<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Katalog\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pengelola\Katalog\Data\DataFitur;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Tenant\Model\Fitur;
use Illuminate\Support\Facades\DB;

/**
 * Membuat atau mengubah fitur di katalog (P-04). Kunci fitur dipakai kode aplikasi, jadi tidak bisa diubah.
 */
final class SimpanFitur
{
    /** Format kunci D-06: huruf kecil, dipisah titik, tiap bagian kebab-case, misal `pos.mode-meja`. */
    public const POLA_KUNCI = '/^[a-z0-9]+(-[a-z0-9]+)*(\.[a-z0-9]+(-[a-z0-9]+)*)+$/';

    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    public function Jalankan(PenggunaPengelola $pelaku, DataFitur $data, ?Fitur $fitur = null): Fitur
    {
        return DB::transaction(function () use ($pelaku, $data, $fitur): Fitur {
            if ($fitur !== null && $fitur->Kunci !== $data->kunci) {
                throw new PelanggaranAturanBisnis('KunciTidakBisaDiubah', 'Kunci fitur tidak bisa diubah karena dipakai kode aplikasi.', 'Kunci');
            }

            if (preg_match(self::POLA_KUNCI, $data->kunci) !== 1) {
                throw new PelanggaranAturanBisnis('KunciTidakValid', 'Kunci fitur huruf kecil dipisah titik, misal pos.mode-meja.', 'Kunci');
            }

            if ($fitur === null && Fitur::query()->where('Kunci', $data->kunci)->exists()) {
                throw new PelanggaranAturanBisnis('KunciSudahAda', "Kunci {$data->kunci} sudah ada.", 'Kunci');
            }

            $nilaiLama = $fitur?->only(['Kunci', 'Nama', 'Modul', 'Keterangan']);
            $fitur ??= new Fitur;
            $fitur->fill(['Kunci' => $data->kunci, 'Nama' => $data->nama, 'Modul' => $data->modul, 'Keterangan' => $data->keterangan])->save();

            $this->audit->Catat(
                $nilaiLama === null ? 'katalog.fitur.buat' : 'katalog.fitur.ubah',
                $fitur,
                nilaiLama: $nilaiLama,
                nilaiBaru: $fitur->only(['Kunci', 'Nama', 'Modul', 'Keterangan']),
                idPelaku: $pelaku->Id,
            );

            return $fitur;
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Bersama\Tindakan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Tindakan\Kueri\KotakTindakan;
use App\Domain\Bersama\Tindakan\Model\TinjauanDokumen;
use Illuminate\Support\Facades\DB;

/**
 * Tandai dokumen yang perlu ditinjau "sudah dicek" dari Kotak Tindakan (D-23 C, izin `tindakan.tinjau`). Hanya Uuid
 * yang dikenali penyedia jenis itu di tenant aktif yang dicatat; yang sudah ditandai dilewati (idempoten). Dokumen
 * aslinya tidak diubah. Audit `tindakan.tinjau` per panggilan. Hasil: jumlah dokumen yang baru ditandai.
 */
final class TandaiDokumenDitinjau
{
    public const MAKS = 200;

    public function __construct(
        private readonly KotakTindakan $kotak,
        private readonly PencatatAudit $audit,
    ) {}

    /**
     * @param  list<string>  $uuid
     */
    public function Jalankan(string $jenisDokumen, array $uuid, int $idPengguna, ?string $catatan = null): int
    {
        $uuid = array_values(array_unique(array_map('strtoupper', $uuid)));

        if ($uuid === [] || count($uuid) > self::MAKS) {
            throw new PelanggaranAturanBisnis('PilihanTidakValid', 'Pilih 1 sampai '.self::MAKS.' dokumen.', 'Uuid');
        }

        $penyedia = $this->kotak->CariPenyedia($jenisDokumen)
            ?? throw new PelanggaranAturanBisnis('JenisTidakDikenal', 'Jenis dokumen tidak dikenal.', 'Jenis');
        $valid = $penyedia->SaringDokumen($jenisDokumen, $uuid);

        if ($valid === []) {
            throw new PelanggaranAturanBisnis('DokumenTidakDitemukan', 'Dokumen yang dipilih tidak ditemukan atau tidak perlu ditinjau.', 'Uuid');
        }

        $catatan = $catatan === null || trim($catatan) === '' ? null : mb_substr(trim($catatan), 0, 255);

        return DB::transaction(function () use ($jenisDokumen, $valid, $idPengguna, $catatan): int {
            $sudah = TinjauanDokumen::query()->where('JenisDokumen', $jenisDokumen)->whereIn('UuidDokumen', $valid)->pluck('UuidDokumen')->all();
            $baru = array_values(array_diff($valid, $sudah));

            foreach ($baru as $u) {
                TinjauanDokumen::query()->create([
                    'JenisDokumen' => $jenisDokumen,
                    'UuidDokumen' => $u,
                    'IdPengguna' => $idPengguna,
                    'Catatan' => $catatan,
                ]);
            }

            if ($baru !== []) {
                $this->audit->Catat('tindakan.tinjau', nilaiBaru: [
                    'JenisDokumen' => $jenisDokumen,
                    'Jumlah' => count($baru),
                    'Uuid' => array_slice($baru, 0, 50),
                    'Catatan' => $catatan,
                ], idPengguna: $idPengguna);
            }

            return count($baru);
        });
    }
}

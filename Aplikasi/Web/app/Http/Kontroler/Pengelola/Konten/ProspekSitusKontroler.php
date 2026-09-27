<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pengelola\Konten;

use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Pengelola\Konten\Aksi\UbahProspekSitus;
use App\Domain\Pengelola\Konten\Kueri\DaftarProspekSitus;
use App\Domain\Pengelola\TimInternal\Enum\IzinPengelola;
use App\Domain\Situs\Enum\JenisProspek;
use App\Domain\Situs\Enum\StatusProspek;
use App\Domain\Situs\Model\ProspekSitus;
use App\Http\Kontroler\Kontroler;
use App\Http\Kontroler\Pengelola\PelakuPengelola;
use App\Http\Respons\ResponsTabel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Response;

/**
 * Situs pemasaran bagian B: prospek dari formulir kontak/minta demo. Lihat: `situs.lihat` (kontak disamarkan);
 * kontak utuh & ubah status: `situs.kelola`.
 */
final class ProspekSitusKontroler extends Kontroler
{
    use PelakuPengelola;

    public function Daftar(Request $permintaan, DaftarProspekSitus $daftar): JsonResponse|Response
    {
        $kelola = $this->AmbilPelaku()->PunyaIzin(IzinPengelola::SitusKelola);
        $tabel = DataPermintaanTabel::Dari($permintaan->query(), DaftarProspekSitus::KOLOM_URUT, '', DaftarProspekSitus::KOLOM_SARING);

        return ResponsTabel::Kirim($permintaan, 'Pengelola/Situs/Prospek', 'Prospek', fn (): array => $daftar->AmbilTabel($tabel, $kelola), fn (): array => [
            'PilihanStatus' => array_map(fn (StatusProspek $s): array => ['Nilai' => $s->value, 'Label' => $s->AmbilLabel()], StatusProspek::cases()),
            'PilihanJenis' => array_map(fn (JenisProspek $j): array => ['Nilai' => $j->value, 'Label' => $j->AmbilLabel()], JenisProspek::cases()),
            'Izin' => ['Kelola' => $kelola],
        ]);
    }

    public function Ubah(Request $permintaan, string $prospekSitus, UbahProspekSitus $ubah): RedirectResponse
    {
        $isian = $permintaan->validate([
            'Status' => ['required', Rule::enum(StatusProspek::class)],
            'Catatan' => ['nullable', 'string', 'max:1000'],
        ]);
        $prospek = ProspekSitus::query()->where('Uuid', $prospekSitus)->firstOrFail();
        $catatan = trim((string) ($isian['Catatan'] ?? ''));

        $ubah->Jalankan($this->AmbilPelaku(), $prospek, StatusProspek::from((string) $isian['Status']), $catatan === '' ? null : $catatan);

        return back()->with('Kilat', 'Prospek diperbarui.');
    }
}

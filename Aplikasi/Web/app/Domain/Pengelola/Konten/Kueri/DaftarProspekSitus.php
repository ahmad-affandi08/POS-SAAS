<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Konten\Kueri;

use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Bersama\Tabel\Layanan\PenerapKueriTabel;
use App\Domain\Pelanggan\Layanan\NomorHp;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Situs\Enum\JenisProspek;
use App\Domain\Situs\Enum\StatusProspek;
use App\Domain\Situs\Model\ProspekSitus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Situs pemasaran bagian B: daftar prospek di konsol (`TabelData`). Saring `Status` (bawaan: selain Spam) & `Jenis`;
 * cari nama/nama usaha/kota, atau nomor HP utuh (dicocokkan lewat sidik karena nomor terenkripsi). Nomor & email
 * utuh hanya untuk pemegang `situs.kelola`; selain itu disamarkan.
 */
final class DaftarProspekSitus
{
    public const KOLOM_URUT = ['DibuatPada', 'Nama', 'Status'];

    public const KOLOM_SARING = ['Status', 'Jenis'];

    /**
     * @return array{Data: list<array<string, mixed>>, Meta: array{Halaman: int, PerHalaman: int, Total: int, JumlahHalaman: int}}
     */
    public function AmbilTabel(DataPermintaanTabel $permintaan, bool $lihatKontak): array
    {
        $kueri = ProspekSitus::query();
        $status = $permintaan->AmbilDaftar('Status', array_map(fn (StatusProspek $s): string => $s->value, StatusProspek::cases()));
        $jenis = $permintaan->AmbilDaftar('Jenis', array_map(fn (JenisProspek $j): string => $j->value, JenisProspek::cases()));

        $status === [] ? $kueri->where('Status', '!=', StatusProspek::Spam->value) : $kueri->whereIn('Status', $status);

        if ($jenis !== []) {
            $kueri->whereIn('Jenis', $jenis);
        }

        if ($permintaan->cari !== '') {
            $kata = PenerapKueriTabel::PolaCari($permintaan->cari);
            $noHp = NomorHp::Normalisasi($permintaan->cari);
            $kueri->where(function (Builder $bagian) use ($kata, $noHp): void {
                $bagian->where('Nama', 'like', $kata)->orWhere('NamaUsaha', 'like', $kata)->orWhere('Kota', 'like', $kata);

                if ($noHp !== null) {
                    $bagian->orWhere('SidikNoHp', ProspekSitus::BuatSidik($noHp));
                }
            });
        }

        if ($permintaan->urut === []) {
            $kueri->orderByDesc('Id');
        }

        return PenerapKueriTabel::Terapkan($kueri, $permintaan, ['DibuatPada' => 'Id', 'Nama' => 'Nama', 'Status' => 'Status'], fn (Collection $baris): array => $this->Petakan(array_values($baris->all()), $lihatKontak));
    }

    /** Jumlah prospek berstatus Baru (lencana menu konsol). */
    public function HitungBaru(): int
    {
        return ProspekSitus::query()->where('Status', StatusProspek::Baru->value)->count();
    }

    /**
     * @param  list<ProspekSitus>  $prospek
     * @return list<array<string, mixed>>
     */
    private function Petakan(array $prospek, bool $lihatKontak): array
    {
        $idPenangan = array_values(array_filter(array_unique(array_map(fn (ProspekSitus $p) => $p->IdPenggunaPengelolaPenangan, $prospek))));
        $namaPenangan = $idPenangan === [] ? collect() : PenggunaPengelola::query()->whereKey($idPenangan)->pluck('Nama', 'Id');

        return array_map(fn (ProspekSitus $p): array => [
            'Uuid' => $p->Uuid,
            'Jenis' => $p->Jenis->value,
            'LabelJenis' => $p->Jenis->AmbilLabel(),
            'Nama' => $p->Nama,
            'NamaUsaha' => $p->NamaUsaha,
            'JenisUsaha' => $p->JenisUsaha,
            'Kota' => $p->Kota,
            'NoHp' => $lihatKontak ? $p->NoHp : NomorHp::Samarkan($p->NoHp),
            'Email' => $p->Email === null ? null : ($lihatKontak ? $p->Email : self::SamarkanEmail($p->Email)),
            'Pesan' => $p->Pesan,
            'HalamanAsal' => $p->HalamanAsal,
            'Status' => $p->Status->value,
            'LabelStatus' => $p->Status->AmbilLabel(),
            'Catatan' => $p->Catatan,
            'Penangan' => $p->IdPenggunaPengelolaPenangan === null ? null : (string) ($namaPenangan[$p->IdPenggunaPengelolaPenangan] ?? '-'),
            'DitanganiPada' => $p->DitanganiPada?->toIso8601String(),
            'DibuatPada' => $p->DibuatPada?->toIso8601String(),
        ], $prospek);
    }

    private static function SamarkanEmail(string $email): string
    {
        [$nama, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($nama, 0, 1).'***@'.$domain;
    }
}

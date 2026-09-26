<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Kueri;

use App\Domain\Akuntansi\Model\Akun;
use App\Domain\Akuntansi\Model\JadwalKasBank;
use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Bersama\Tabel\Layanan\PenerapKueriTabel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * D-23 D: daftar transaksi kas & bank berulang untuk `TabelData`: cari keterangan, saring status (Aktif/Berhenti),
 * urut jatuh tempo berikutnya & jumlah. Pengguna berbatas outlet hanya melihat jadwal di outlet aksesnya.
 */
final class DaftarJadwalKasBank
{
    public const KOLOM_URUT = ['TanggalBerikutnya', 'Jumlah'];

    public const KOLOM_SARING = ['Status'];

    public const URUT_BAWAAN = 'TanggalBerikutnya';

    /**
     * @param  list<int>|null  $idOutletBoleh
     * @return array{Data: list<array<string, mixed>>, Meta: array{Halaman: int, PerHalaman: int, Total: int, JumlahHalaman: int}}
     */
    public function AmbilTabel(DataPermintaanTabel $permintaan, ?array $idOutletBoleh): array
    {
        $kata = $permintaan->cari;
        $status = $permintaan->AmbilDaftar('Status', ['Aktif', 'Berhenti']);
        $kueri = JadwalKasBank::query()
            ->when($kata !== '', fn (Builder $k) => $k->where('Keterangan', 'like', PenerapKueriTabel::PolaCari($kata)))
            ->when(count($status) === 1, fn (Builder $k) => $k->where('Aktif', $status[0] === 'Aktif'))
            ->when($idOutletBoleh !== null, fn (Builder $k) => $k->whereIn('IdOutlet', $idOutletBoleh === [] ? [0] : $idOutletBoleh));

        return PenerapKueriTabel::Terapkan($kueri, $permintaan, ['TanggalBerikutnya' => 'TanggalBerikutnya', 'Jumlah' => 'Jumlah'], fn (Collection $jadwal): array => $this->Petakan($jadwal));
    }

    /**
     * Jadwal milik tenant aktif dalam batas outlet pelaku, atau null.
     *
     * @param  list<int>|null  $idOutletBoleh
     */
    public function Cari(string $uuid, ?array $idOutletBoleh): ?JadwalKasBank
    {
        return JadwalKasBank::query()
            ->where('Uuid', $uuid)
            ->when($idOutletBoleh !== null, fn (Builder $k) => $k->whereIn('IdOutlet', $idOutletBoleh === [] ? [0] : $idOutletBoleh))
            ->first();
    }

    /**
     * @param  Collection<int, JadwalKasBank>  $jadwal
     * @return list<array<string, mixed>>
     */
    private function Petakan(Collection $jadwal): array
    {
        $idAkun = array_values(array_unique([...$jadwal->pluck('IdAkunSumber')->all(), ...$jadwal->pluck('IdAkunTujuan')->all()]));
        $akun = Akun::query()->whereKey($idAkun)->get(['Id', 'Kode', 'Nama'])->keyBy('Id');

        return array_values($jadwal->map(fn (JadwalKasBank $j): array => [
            'Uuid' => $j->Uuid,
            'Keterangan' => $j->Keterangan,
            'Jenis' => $j->Jenis->value,
            'LabelJenis' => $j->Jenis->AmbilLabel(),
            'AkunSumber' => $akun->get($j->IdAkunSumber)?->AmbilLabel() ?? '',
            'AkunTujuan' => $akun->get($j->IdAkunTujuan)?->AmbilLabel() ?? '',
            'Jumlah' => $j->Jumlah,
            'Frekuensi' => $j->Frekuensi->value,
            'LabelFrekuensi' => $j->Frekuensi->AmbilLabel(),
            'TanggalBerikutnya' => $j->TanggalBerikutnya->toDateString(),
            'Aktif' => $j->Aktif,
            'JumlahDicatat' => $j->JumlahDicatat,
            'GalatTerakhir' => $j->GalatTerakhir,
        ])->all());
    }
}

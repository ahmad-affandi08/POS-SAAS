<?php

declare(strict_types=1);

namespace App\Domain\Pemenuhan\Kueri;

use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Bersama\Tabel\Layanan\PenerapKueriTabel;
use App\Domain\Karyawan\Kueri\JadwalStafReservasi;
use App\Domain\Katalog\Kueri\LayananReservasi;
use App\Domain\Organisasi\Kueri\PetaUuidOutlet;
use App\Domain\Pelanggan\Layanan\NomorHp;
use App\Domain\Pemenuhan\Enum\StatusReservasi;
use App\Domain\Pemenuhan\Model\Reservasi;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * F-07 mode service: daftar reservasi untuk `TabelData` (cari nomor/nama/HP, saring tanggal dalam zona waktu tenant,
 * status, staf, outlet; urut jam mulai). Pengguna berbatas outlet hanya melihat outlet aksesnya.
 */
final class DaftarReservasi
{
    public const KOLOM_URUT = ['MulaiPada', 'Nomor'];

    public const KOLOM_SARING = ['Tanggal', 'Status', 'Staf', 'Outlet'];

    public const URUT_BAWAAN = 'MulaiPada';

    public function __construct(
        private readonly LayananReservasi $layanan,
        private readonly JadwalStafReservasi $staf,
        private readonly PetaUuidOutlet $outlet,
    ) {}

    /**
     * @param  list<int>|null  $idOutletBoleh
     * @return array{Data: list<array<string, mixed>>, Meta: array{Halaman: int, PerHalaman: int, Total: int, JumlahHalaman: int}}
     */
    public function AmbilTabel(DataPermintaanTabel $p, ?array $idOutletBoleh, string $zona): array
    {
        $tanggal = $p->AmbilRentangTanggal('Tanggal');
        $status = $p->AmbilDaftar('Status', array_map(fn (StatusReservasi $s): string => $s->value, StatusReservasi::cases()));
        $uuidStaf = $p->AmbilDaftar('Staf');
        $uuidOutlet = $p->AmbilDaftar('Outlet');
        $kata = $p->cari;
        $kueri = $this->Dasar($idOutletBoleh)
            ->when($tanggal['Dari'] !== null, fn (Builder $k) => $k->where('MulaiPada', '>=', CarbonImmutable::parse((string) $tanggal['Dari'], $zona)->startOfDay()->utc()))
            ->when($tanggal['Sampai'] !== null, fn (Builder $k) => $k->where('MulaiPada', '<', CarbonImmutable::parse((string) $tanggal['Sampai'], $zona)->startOfDay()->addDay()->utc()))
            ->when($status !== [], fn (Builder $k) => $k->whereIn('Status', $status))
            ->when($uuidOutlet !== [], fn (Builder $k) => $k->whereIn('IdOutlet', array_values($this->outlet->AmbilIdDariUuid($uuidOutlet)) ?: [0]))
            ->when($uuidStaf !== [], function (Builder $k) use ($uuidStaf): void {
                $id = array_values(array_filter(array_map(fn (string $u): ?int => $this->staf->CariStaf($u)['Id'] ?? null, $uuidStaf)));
                $k->whereIn('IdKaryawan', $id === [] ? [0] : $id);
            })
            ->when($kata !== '', function (Builder $k) use ($kata): void {
                $pola = PenerapKueriTabel::PolaCari($kata);
                $hp = NomorHp::Normalisasi($kata);
                $k->where(fn (Builder $q) => $q->where('Nomor', 'like', $pola)->orWhere('NamaPelanggan', 'like', $pola)
                    ->when($hp !== null, fn (Builder $q2) => $q2->orWhere('NoHp', $hp)));
            });

        return PenerapKueriTabel::Terapkan($kueri, $p, ['MulaiPada' => 'MulaiPada', 'Nomor' => 'Nomor'], fn (Collection $baris): array => $this->Petakan($baris));
    }

    /**
     * @param  list<int>|null  $idOutletBoleh
     */
    public function Cari(string $uuid, ?array $idOutletBoleh): ?Reservasi
    {
        return $this->Dasar($idOutletBoleh)->where('Uuid', $uuid)->first();
    }

    /**
     * @param  Collection<int, Reservasi>  $baris
     * @return list<array<string, mixed>>
     */
    public function Petakan(Collection $baris): array
    {
        $layanan = $this->layanan->AmbilNama(array_values(array_unique($baris->pluck('IdProduk')->all())));
        $staf = $this->staf->AmbilRingkas(array_values(array_filter(array_unique($baris->pluck('IdKaryawan')->all()))));
        $outlet = [];

        foreach ($this->outlet->AmbilRingkas(array_values(array_unique($baris->pluck('IdOutlet')->all()))) as $o) {
            $outlet[$o['Id']] = $o;
        }

        return array_values($baris->map(fn (Reservasi $r): array => [
            'Uuid' => $r->Uuid,
            'Nomor' => $r->Nomor,
            'MulaiPada' => $r->MulaiPada->toIso8601ZuluString(),
            'SelesaiPada' => $r->SelesaiPada->toIso8601ZuluString(),
            'NamaPelanggan' => $r->NamaPelanggan,
            'NoHp' => NomorHp::Format($r->NoHp),
            'Layanan' => $layanan[$r->IdProduk] ?? '',
            'Staf' => $r->IdKaryawan === null ? null : ($staf[$r->IdKaryawan] ?? null),
            'Outlet' => ['Uuid' => $outlet[$r->IdOutlet]['Uuid'] ?? null, 'Nama' => $outlet[$r->IdOutlet]['Nama'] ?? ''],
            'Status' => $r->Status->value,
            'LabelStatus' => $r->Status->AmbilLabel(),
            'Sumber' => $r->Sumber->value,
            'LabelSumber' => $r->Sumber->AmbilLabel(),
            'Catatan' => $r->Catatan,
            'AlasanBatal' => $r->AlasanBatal,
            'StatusBerikutnya' => array_values(array_map(
                fn (StatusReservasi $s): string => $s->value,
                array_filter(StatusReservasi::cases(), fn (StatusReservasi $s): bool => $r->Status->BisaBerubahKe($s)),
            )),
        ])->all());
    }

    /**
     * @param  list<int>|null  $idOutletBoleh
     * @return Builder<Reservasi>
     */
    private function Dasar(?array $idOutletBoleh): Builder
    {
        return Reservasi::query()->when($idOutletBoleh !== null, fn (Builder $k) => $k->whereIn('IdOutlet', $idOutletBoleh === [] ? [0] : $idOutletBoleh));
    }
}

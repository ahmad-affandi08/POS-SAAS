<?php

declare(strict_types=1);

namespace App\Domain\Pemenuhan\Kueri;

use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Bersama\Tabel\Layanan\PenerapKueriTabel;
use App\Domain\Organisasi\Kueri\PetaUuidOutlet;
use App\Domain\Pelanggan\Layanan\NomorHp;
use App\Domain\Pemenuhan\Enum\StatusLaundry;
use App\Domain\Pemenuhan\Model\TiketLaundry;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Laundry (§9.9): daftar tiket untuk `TabelData` (cari nomor/nama/HP, saring tanggal terima dalam zona tenant, status,
 * outlet, dan `Terlambat`: `BelumSiap` = lewat estimasi tetapi belum siap, `BelumDiambil` = siap lebih dari N hari
 * pengaturan). Pengguna berbatas outlet hanya melihat outlet aksesnya.
 */
final class DaftarLaundry
{
    public const KOLOM_URUT = ['DibuatPada', 'EstimasiSelesaiPada', 'Nomor'];

    public const KOLOM_SARING = ['Tanggal', 'Status', 'Outlet', 'Terlambat'];

    public const URUT_BAWAAN = '-DibuatPada';

    public function __construct(
        private readonly PetaUuidOutlet $outlet,
        private readonly PengaturanLaundryTenant $pengaturan,
    ) {}

    /**
     * @param  list<int>|null  $idOutletBoleh
     * @return array{Data: list<array<string, mixed>>, Meta: array{Halaman: int, PerHalaman: int, Total: int, JumlahHalaman: int}}
     */
    public function AmbilTabel(DataPermintaanTabel $p, ?array $idOutletBoleh, string $zona): array
    {
        $tanggal = $p->AmbilRentangTanggal('Tanggal');
        $status = $p->AmbilDaftar('Status', array_map(fn (StatusLaundry $s): string => $s->value, StatusLaundry::cases()));
        $uuidOutlet = $p->AmbilDaftar('Outlet');
        $terlambat = $p->AmbilDaftar('Terlambat', ['BelumSiap', 'BelumDiambil']);
        $hari = $this->pengaturan->Ambil()->HariBelumDiambil;
        $kata = $p->cari;
        $kueri = $this->Dasar($idOutletBoleh)
            ->when($tanggal['Dari'] !== null, fn (Builder $k) => $k->where('DibuatPada', '>=', CarbonImmutable::parse((string) $tanggal['Dari'], $zona)->startOfDay()->utc()))
            ->when($tanggal['Sampai'] !== null, fn (Builder $k) => $k->where('DibuatPada', '<', CarbonImmutable::parse((string) $tanggal['Sampai'], $zona)->startOfDay()->addDay()->utc()))
            ->when($status !== [], fn (Builder $k) => $k->whereIn('Status', $status))
            ->when($uuidOutlet !== [], fn (Builder $k) => $k->whereIn('IdOutlet', array_values($this->outlet->AmbilIdDariUuid($uuidOutlet)) ?: [0]))
            ->when($terlambat !== [], fn (Builder $k) => $k->where(function (Builder $q) use ($terlambat, $hari): void {
                if (in_array('BelumSiap', $terlambat, true)) {
                    $q->orWhere(fn (Builder $a) => self::TerapkanBelumSiap($a));
                }

                if (in_array('BelumDiambil', $terlambat, true)) {
                    $q->orWhere(fn (Builder $a) => self::TerapkanBelumDiambil($a, $hari));
                }
            }))
            ->when($kata !== '', function (Builder $k) use ($kata): void {
                $pola = PenerapKueriTabel::PolaCari($kata);
                $hp = NomorHp::Normalisasi($kata);
                $k->where(fn (Builder $q) => $q->where('Nomor', 'like', $pola)->orWhere('NamaPelanggan', 'like', $pola)
                    ->when($hp !== null, fn (Builder $q2) => $q2->orWhere('NoHp', $hp)));
            });

        return PenerapKueriTabel::Terapkan(
            $kueri,
            $p,
            ['DibuatPada' => 'DibuatPada', 'EstimasiSelesaiPada' => 'EstimasiSelesaiPada', 'Nomor' => 'Nomor'],
            fn (Collection $baris): array => $this->Petakan($baris, $hari),
        );
    }

    /**
     * @param  list<int>|null  $idOutletBoleh
     */
    public function Cari(string $uuid, ?array $idOutletBoleh): ?TiketLaundry
    {
        return $this->Dasar($idOutletBoleh)->where('Uuid', strtoupper($uuid))->first();
    }

    /** @param  Builder<TiketLaundry>  $k */
    public static function TerapkanBelumSiap(Builder $k): void
    {
        $k->whereIn('Status', StatusLaundry::AmbilNilaiDiproses())->where('EstimasiSelesaiPada', '<', now());
    }

    /** @param  Builder<TiketLaundry>  $k */
    public static function TerapkanBelumDiambil(Builder $k, int $hari): void
    {
        $k->where('Status', StatusLaundry::Siap->value)->where('SiapPada', '<', now()->subDays($hari));
    }

    /**
     * @param  Collection<int, TiketLaundry>  $baris
     * @return list<array<string, mixed>>
     */
    public function Petakan(Collection $baris, ?int $hariBelumDiambil = null): array
    {
        $hari = $hariBelumDiambil ?? $this->pengaturan->Ambil()->HariBelumDiambil;
        $outlet = [];

        foreach ($this->outlet->AmbilRingkas(array_values(array_unique($baris->pluck('IdOutlet')->all()))) as $o) {
            $outlet[$o['Id']] = $o;
        }

        return array_values($baris->map(fn (TiketLaundry $t): array => [
            'Uuid' => $t->Uuid,
            'Nomor' => $t->Nomor,
            'DibuatPada' => $t->DibuatPada?->toIso8601ZuluString(),
            'EstimasiSelesaiPada' => $t->EstimasiSelesaiPada->toIso8601ZuluString(),
            'SiapPada' => $t->SiapPada?->toIso8601ZuluString(),
            'DiambilPada' => $t->DiambilPada?->toIso8601ZuluString(),
            'NamaPelanggan' => $t->NamaPelanggan,
            'NoHp' => $t->NoHp === null ? null : NomorHp::Format($t->NoHp),
            'JenisLayanan' => $t->JenisLayanan->value,
            'Berat' => $t->Berat,
            'Item' => $t->Item ?? [],
            'Parfum' => $t->Parfum,
            'Catatan' => $t->Catatan,
            'Outlet' => ['Uuid' => $outlet[$t->IdOutlet]['Uuid'] ?? null, 'Nama' => $outlet[$t->IdOutlet]['Nama'] ?? ''],
            'Status' => $t->Status->value,
            'LabelStatus' => $t->Status->AmbilLabel(),
            'LewatEstimasi' => $t->Status->CekDiproses() && $t->EstimasiSelesaiPada->isPast(),
            'TerlambatDiambil' => $t->Status === StatusLaundry::Siap && $t->SiapPada !== null && $t->SiapPada->lt(now()->subDays($hari)),
            'NotifikasiTerkirim' => $t->NotifikasiSiapPada !== null,
            'StatusBerikutnya' => array_values(array_map(
                fn (StatusLaundry $s): string => $s->value,
                array_filter(StatusLaundry::cases(), fn (StatusLaundry $s): bool => $s !== StatusLaundry::Dibatalkan && $t->Status->BisaBerubahKe($s)),
            )),
        ])->all());
    }

    /**
     * @param  list<int>|null  $idOutletBoleh
     * @return Builder<TiketLaundry>
     */
    private function Dasar(?array $idOutletBoleh): Builder
    {
        return TiketLaundry::query()->when($idOutletBoleh !== null, fn (Builder $k) => $k->whereIn('IdOutlet', $idOutletBoleh === [] ? [0] : $idOutletBoleh));
    }
}

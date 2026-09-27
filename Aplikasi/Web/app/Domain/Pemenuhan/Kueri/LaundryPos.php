<?php

declare(strict_types=1);

namespace App\Domain\Pemenuhan\Kueri;

use App\Domain\Pelanggan\Layanan\NomorHp;
use App\Domain\Pemenuhan\Enum\StatusLaundry;
use App\Domain\Pemenuhan\Model\TiketLaundry;
use Illuminate\Database\Eloquent\Builder;

/**
 * Laundry di aplikasi kasir (online): tiket outlet perangkat yang masih aktif (belum diambil/dibatalkan), dicari lewat
 * nomor nota, nama, atau nomor HP; tanpa kata = semua yang siap diambil. Maks. 50, siap dulu lalu estimasi terdekat.
 */
final class LaundryPos
{
    public function __construct(private readonly DaftarLaundry $daftar) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function Cari(int $idOutlet, string $kata): array
    {
        $kata = trim($kata);
        $baris = TiketLaundry::query()
            ->where('IdOutlet', $idOutlet)
            ->whereNotIn('Status', [StatusLaundry::Diambil->value, StatusLaundry::Dibatalkan->value])
            ->when($kata === '', fn (Builder $k) => $k->where('Status', StatusLaundry::Siap->value))
            ->when($kata !== '', function (Builder $k) use ($kata): void {
                $hp = NomorHp::Normalisasi($kata);
                $pola = '%'.addcslashes($kata, '%_\\').'%';
                $k->where(fn (Builder $q) => $q->where('Nomor', 'like', $pola)->orWhere('NamaPelanggan', 'like', $pola)
                    ->when($hp !== null, fn (Builder $q2) => $q2->orWhere('NoHp', $hp)));
            })
            ->orderByRaw("CASE WHEN `Status` = 'Siap' THEN 0 ELSE 1 END")
            ->orderBy('EstimasiSelesaiPada')
            ->limit(50)
            ->get();

        return $this->daftar->Petakan($baris);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function Ambil(int $idOutlet, string $uuid): ?array
    {
        $t = TiketLaundry::query()->where('IdOutlet', $idOutlet)->where('Uuid', strtoupper($uuid))->first();

        return $t === null ? null : $this->daftar->Petakan(collect([$t]))[0];
    }
}

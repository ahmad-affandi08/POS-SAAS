<?php

declare(strict_types=1);

namespace App\Domain\Pemenuhan\Kueri;

use App\Domain\Pemenuhan\Enum\StatusBarisTiket;
use App\Domain\Pemenuhan\Enum\StatusTiketDapur;
use App\Domain\Pemenuhan\Model\TiketDapur;
use App\Domain\Pemenuhan\Model\TiketDapurDetail;
use Carbon\CarbonImmutable;

/**
 * Kueri tiket dapur (F-10b fase 1): tiket aktif outlet untuk layar KDS, dan status tiket per baris dokumen untuk
 * tampilan pesanan di kasir/pelayan (dibaca domain Penjualan lewat kueri publik ini).
 */
final class TiketDapurOutlet
{
    /** Tiket yang lebih tua dari ini tidak tampil di KDS (pesanan kemarin yang lupa disajikan). */
    public const JAM_TAMPIL = 12;

    /** Tiket yang baru disajikan tetap tampil sebentar agar salah ketuk bisa dikoreksi. */
    public const MENIT_SETELAH_DISAJIKAN = 5;

    /**
     * @param  list<int>|null  $idStasiun  null = semua stasiun
     * @return list<array<string, mixed>>
     */
    public function AmbilUntukKds(int $idOutlet, ?array $idStasiun): array
    {
        $sekarang = CarbonImmutable::now();
        $tiket = TiketDapur::query()
            ->where('IdOutlet', $idOutlet)
            ->where('DikirimPada', '>=', $sekarang->subHours(self::JAM_TAMPIL))
            ->when($idStasiun !== null, fn ($k) => $k->whereIn('IdStasiunDapur', $idStasiun === [] ? [0] : $idStasiun))
            ->where(fn ($k) => $k->where('Status', '!=', StatusTiketDapur::Disajikan->value)
                ->orWhere('DisajikanPada', '>=', $sekarang->subMinutes(self::MENIT_SETELAH_DISAJIKAN)))
            ->orderBy('DikirimPada')
            ->orderBy('Id')
            ->limit(200)
            ->get();
        $detail = TiketDapurDetail::query()->whereIn('IdTiketDapur', $tiket->pluck('Id')->all())->orderBy('Id')->get()->groupBy('IdTiketDapur');

        return array_values($tiket->map(fn (TiketDapur $t): array => [
            'Uuid' => $t->Uuid,
            'IdStasiunDapur' => $t->IdStasiunDapur,
            'NomorDokumen' => $t->NomorDokumen,
            'NamaMeja' => $t->NamaMeja,
            'Label' => $t->Label,
            'Ronde' => $t->Ronde,
            'Status' => $t->Status->value,
            'DikirimPada' => $t->DikirimPada->toIso8601ZuluString(),
            'MulaiPada' => $t->MulaiPada?->toIso8601ZuluString(),
            'SiapPada' => $t->SiapPada?->toIso8601ZuluString(),
            'Baris' => array_values(collect($detail->get($t->Id, []))->map(fn (TiketDapurDetail $d): array => [
                'UuidBaris' => $d->UuidBaris,
                'NamaProduk' => $d->NamaProduk,
                'Jumlah' => (string) $d->Jumlah,
                'Pilihan' => $d->Pilihan ?? [],
                'Catatan' => $d->Catatan,
                'Dibatalkan' => $d->Status === StatusBarisTiket::Dibatalkan,
            ])->all()),
        ])->all());
    }

    /**
     * Status tiket (Antre/Dimasak/Siap/Disajikan) per Uuid baris dokumen.
     *
     * @param  list<string>  $uuidBaris
     * @return array<string, string>
     */
    public function AmbilStatusPerBaris(array $uuidBaris): array
    {
        if ($uuidBaris === []) {
            return [];
        }

        $hasil = [];
        $baris = TiketDapurDetail::query()->whereIn('UuidBaris', $uuidBaris)->get(['UuidBaris', 'IdTiketDapur']);
        $status = TiketDapur::query()->whereKey($baris->pluck('IdTiketDapur')->unique()->values()->all())->get(['Id', 'Status'])->keyBy('Id');

        foreach ($baris as $b) {
            $t = $status->get($b->IdTiketDapur);

            if ($t instanceof TiketDapur) {
                $hasil[$b->UuidBaris] = $t->Status->value;
            }
        }

        return $hasil;
    }

    public function CariDiOutlet(string $uuid, int $idOutlet): ?TiketDapur
    {
        $tiket = TiketDapur::query()->where('Uuid', $uuid)->first();

        return $tiket !== null && $tiket->IdOutlet === $idOutlet ? $tiket : null;
    }
}

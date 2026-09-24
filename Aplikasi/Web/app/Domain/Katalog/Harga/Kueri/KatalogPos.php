<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Harga\Kueri;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Data\KonteksKatalogPos;
use App\Domain\Katalog\Harga\Layanan\PenyusunKursorKatalog;
use App\Domain\Katalog\Kontrak\BagianKatalogPos;
use App\Domain\Katalog\Model\PenghapusanKatalog;
use Carbon\CarbonImmutable;

/**
 * Isi `GET /api/pos/v1/katalog?sejak=` (F-03 D.3) untuk tenant aktif (dari token perangkat).
 * - Tanpa kursor, atau kursor lebih tua dari `katalog.Pos.UmurKursorMaksimalHari` → **lengkap** (`Lengkap: true`,
 *   `Terhapus` kosong; POS mengganti katalognya).
 * - Dengan kursor → **delta**: setiap bagian memuat baris `DiubahPada ≥ sejak`, `Terhapus` dari `PenghapusanKatalog`
 *   dengan `DihapusPada ≥ sejak`.
 * - Bagian digabung dari semua `BagianKatalogPos` yang ditandai (Tim 1, 2, 3). `Kursor` baru = awal kueri dikurangi
 *   `katalog.Pos.TumpangTindihDetik`.
 * Kursor tidak valid → `KursorTidakValid` (422).
 */
final class KatalogPos
{
    public const SKEMA = 1;

    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenyusunKursorKatalog $kursor,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function Ambil(int $idOutlet, ?string $kursor): array
    {
        $mulai = CarbonImmutable::now()->utc();
        $sejak = $kursor === null || $kursor === '' ? null : $this->kursor->Baca($kursor);

        if ($sejak !== null && $sejak->lessThan($mulai->subDays((int) config('katalog.Pos.UmurKursorMaksimalHari', 90)))) {
            $sejak = null;
        }

        $konteks = new KonteksKatalogPos($this->konteks->Wajib(), $idOutlet, $sejak);
        /** @var array<string, list<array<string, mixed>>> $bagianGabungan */
        $bagianGabungan = [];

        /** @var iterable<BagianKatalogPos> $bagianTertanda */
        $bagianTertanda = app()->tagged(BagianKatalogPos::TAG);

        foreach ($bagianTertanda as $bagian) {
            foreach ($bagian->AmbilBagian($konteks) as $nama => $baris) {
                $bagianGabungan[$nama] = [...($bagianGabungan[$nama] ?? []), ...$baris];
            }
        }

        $hasil = [
            'Skema' => self::SKEMA,
            'Lengkap' => $sejak === null,
            'Kursor' => $this->kursor->Buat($mulai->subSeconds((int) config('katalog.Pos.TumpangTindihDetik', 120))),
            'WaktuServer' => $mulai->toIso8601ZuluString(),
            ...$bagianGabungan,
        ];
        $hasil['Terhapus'] = $sejak === null ? [] : array_values(PenghapusanKatalog::query()
            ->where('DihapusPada', '>=', $sejak)
            ->orderBy('Id')
            ->get()
            ->map(fn (PenghapusanKatalog $p): array => ['Entitas' => $p->Entitas->value, 'Uuid' => $p->UuidEntitas])
            ->all());

        return $hasil;
    }
}

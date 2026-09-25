<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Kueri;

use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Bersama\Tabel\Layanan\PenerapKueriTabel;
use App\Domain\Pelanggan\Enum\StatusPelanggan;
use App\Domain\Pelanggan\Layanan\BukuPoin;
use App\Domain\Pelanggan\Layanan\NomorHp;
use App\Domain\Pelanggan\Model\Pelanggan;
use App\Domain\Pelanggan\Model\TierPelanggan;
use App\Domain\Penjualan\Kueri\BelanjaPelanggan;
use Illuminate\Support\Collection;

/**
 * Daftar pelanggan untuk `TabelData` (F-16a; tipe FE `BarisPelanggan`), bawaan urut nama. Cari: nama, nomor HP (angka
 * saja atau berawalan 0), email. Saring: `Status`, `Tag`. Ringkasan belanja (jumlah transaksi, total, terakhir) dari
 * domain Penjualan untuk baris di halaman itu.
 */
final class DaftarPelanggan
{
    public const KOLOM_URUT = ['Nama', 'DibuatPada'];

    public const KOLOM_SARING = ['Status', 'Tag', 'Tier'];

    public const URUT_BAWAAN = 'Nama';

    public function __construct(
        private readonly BelanjaPelanggan $belanja,
        private readonly DaftarTierPelanggan $tier,
        private readonly BukuPoin $buku,
    ) {}

    /**
     * @return array{Data: list<array<string, mixed>>, Meta: array{Halaman: int, PerHalaman: int, Total: int, JumlahHalaman: int}}
     */
    public function AmbilTabel(DataPermintaanTabel $permintaan): array
    {
        $status = $permintaan->AmbilDaftar('Status', array_map(fn (StatusPelanggan $s): string => $s->value, StatusPelanggan::cases()));
        $tag = $permintaan->AmbilDaftar('Tag');
        $kodeTier = $permintaan->AmbilDaftar('Tier');
        $pola = PenerapKueriTabel::PolaCari($permintaan->cari);
        $hp = NomorHp::Normalisasi($permintaan->cari);
        $angka = (string) preg_replace('/\D+/', '', $permintaan->cari);

        $kueri = Pelanggan::query()
            ->when($status !== [], fn ($kueri) => $kueri->whereIn('Status', $status))
            ->when($tag !== [], fn ($kueri) => $kueri->where(function ($dalam) use ($tag): void {
                foreach ($tag as $t) {
                    $dalam->orWhereJsonContains('Tag', $t);
                }
            }))
            ->when($kodeTier !== [], fn ($kueri) => $kueri->whereIn('IdTier', TierPelanggan::query()->whereIn('Kode', $kodeTier)->select('Id')))
            ->when($permintaan->cari !== '', fn ($kueri) => $kueri->where(fn ($dalam) => $dalam
                ->where('Nama', 'like', $pola)
                ->orWhere('Email', 'like', $pola)
                ->when(strlen($angka) >= 4, fn ($k) => $k->orWhere('NoHp', 'like', PenerapKueriTabel::PolaCari($hp ?? ltrim($angka, '0'))))));

        return PenerapKueriTabel::Terapkan($kueri, $permintaan, ['Nama' => 'Nama', 'DibuatPada' => 'DibuatPada'], function (Collection $baris): array {
            /** @var Collection<int, Pelanggan> $baris */
            $id = array_values($baris->map(fn (Pelanggan $p): int => $p->Id)->all());
            $ringkasan = $this->belanja->AmbilRingkasan($id);
            $tier = $this->tier->AmbilPeta(array_values(array_filter($baris->map(fn (Pelanggan $p): ?int => $p->IdTier)->all(), 'is_int')));
            $saldo = $this->buku->AmbilSaldoBanyak($id);

            return array_values($baris->map(fn (Pelanggan $p): array => [
                ...self::Petakan($p, $p->IdTier === null ? null : ($tier[$p->IdTier] ?? null), $saldo[$p->Id] ?? 0),
                ...($ringkasan[$p->Id] ?? ['JumlahTransaksi' => 0, 'TotalBelanja' => '0.00', 'TerakhirPada' => null]),
            ])->all());
        });
    }

    /**
     * Semua tag yang pernah dipakai tenant (opsi saringan & isian), urut abjad.
     *
     * @return list<string>
     */
    public function AmbilSemuaTag(): array
    {
        $tag = [];

        foreach (Pelanggan::query()->whereNotNull('Tag')->pluck('Tag') as $daftar) {
            foreach ((array) $daftar as $t) {
                if (is_string($t)) {
                    $tag[$t] = true;
                }
            }
        }

        $hasil = array_keys($tag);
        sort($hasil, SORT_NATURAL | SORT_FLAG_CASE);

        return $hasil;
    }

    /**
     * @param  array{Kode: string, Nama: string}|null  $tier
     * @return array<string, mixed>
     */
    public static function Petakan(Pelanggan $p, ?array $tier = null, int $saldoPoin = 0): array
    {
        return [
            'Uuid' => $p->Uuid,
            'Nama' => $p->Nama,
            'NoHp' => NomorHp::Format($p->NoHp),
            'Email' => $p->Email,
            'TanggalLahir' => $p->TanggalLahir?->toDateString(),
            'Alamat' => $p->Alamat,
            'Tag' => array_values($p->Tag ?? []),
            'Catatan' => $p->Catatan,
            'SetujuPemasaran' => $p->SetujuPemasaran,
            'Status' => $p->Status->value,
            'Tier' => $tier,
            'TierTetap' => $p->TierTetap,
            'SaldoPoin' => $saldoPoin,
            'LimitKredit' => $p->LimitKredit === null ? null : (string) $p->LimitKredit,
            'TerminHari' => $p->TerminHari,
            'DibuatPada' => $p->DibuatPada?->toIso8601ZuluString(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Kueri;

use App\Domain\Bersama\Tabel\Layanan\PenerapKueriTabel;
use App\Domain\Pelanggan\Enum\StatusPelanggan;
use App\Domain\Pelanggan\Layanan\BukuPoin;
use App\Domain\Pelanggan\Layanan\NomorHp;
use App\Domain\Pelanggan\Model\Pelanggan;
use App\Domain\Penjualan\Kueri\BelanjaPelanggan;
use App\Domain\Promo\Kueri\PemakaianPromo;
use Carbon\CarbonImmutable;

/**
 * Cari pelanggan aktif dari POS (F-16a, `GET /api/pos/v1/pelanggan?kata=`): minimal 3 karakter; cocok nama atau nomor
 * HP (ketik sebagian angka). Maks. 20 hasil. Nomor HP dikirim tersamar (data pribadi tidak disimpan di perangkat).
 */
final class CariPelangganPos
{
    public function __construct(
        private readonly DaftarTierPelanggan $tier,
        private readonly BukuPoin $buku,
        private readonly KreditPelanggan $kredit,
        private readonly BelanjaPelanggan $belanja,
        private readonly PemakaianPromo $pemakaian,
    ) {}

    public const BATAS = 20;

    public const PANJANG_MINIMAL = 3;

    /**
     * F-16b: `KodeTier`/`NamaTier` (harga per tier di POS) dan `SaldoPoin`. F-12: `LimitKredit`, `SisaPiutang`,
     * `HariLewatJatuhTempo` (disimpan perangkat untuk cek tempo offline, BR-12.1). F-16c bagian 3 (promo): `HariLahir`
     * `MM-DD` (tanpa tahun lahir, data minimal), `JumlahTransaksi` (tanpa void), dan `PemakaianPromo` `{UuidPromo:
     * {Hari, Promo}}` pada tanggal bisnis [tanggalBisnis].
     *
     * @return list<array{Uuid: string, Nama: string, NoHp: string, KodeTier: string|null, NamaTier: string|null, SaldoPoin: int, LimitKredit: string|null, SisaPiutang: string, HariLewatJatuhTempo: int, HariLahir: string|null, JumlahTransaksi: int, PemakaianPromo: array<string, array{Hari: int, Promo: int}>|object}>
     */
    public function Cari(string $kata, ?string $tanggalBisnis = null): array
    {
        $kata = trim($kata);

        if (mb_strlen($kata) < self::PANJANG_MINIMAL) {
            return [];
        }

        $angka = (string) preg_replace('/\D+/', '', $kata);
        $cariHp = $angka !== '' && strlen($angka) >= self::PANJANG_MINIMAL && preg_match('/^[0-9+() .-]+$/', $kata) === 1;
        $pola = $cariHp ? PenerapKueriTabel::PolaCari(NomorHp::Normalisasi($kata) ?? ltrim($angka, '0')) : PenerapKueriTabel::PolaCari($kata);

        $daftar = Pelanggan::query()
            ->where('Status', StatusPelanggan::Aktif->value)
            ->where($cariHp ? 'NoHp' : 'Nama', 'like', $pola)
            ->orderBy('Nama')
            ->orderBy('Id')
            ->limit(self::BATAS)
            ->get(['Id', 'Uuid', 'Nama', 'NoHp', 'IdTier', 'TanggalLahir']);
        $tier = $this->tier->AmbilPeta(array_values(array_filter($daftar->pluck('IdTier')->all(), 'is_int')));
        $saldo = $this->buku->AmbilSaldoBanyak(array_values($daftar->pluck('Id')->all()));
        $kredit = $this->kredit->AmbilRingkas(array_values($daftar->pluck('Id')->all()), CarbonImmutable::today());
        $id = array_values(array_map('intval', $daftar->pluck('Id')->all()));
        $belanja = $this->belanja->AmbilRingkasan($id);
        $pemakaian = $this->pemakaian->HitungBanyakPelanggan($id, $tanggalBisnis ?? CarbonImmutable::today()->toDateString());

        return array_values($daftar->map(fn (Pelanggan $p): array => [
            'Uuid' => $p->Uuid,
            'Nama' => $p->Nama,
            'NoHp' => NomorHp::Samarkan($p->NoHp),
            'KodeTier' => $p->IdTier === null ? null : ($tier[$p->IdTier]['Kode'] ?? null),
            'NamaTier' => $p->IdTier === null ? null : ($tier[$p->IdTier]['Nama'] ?? null),
            'SaldoPoin' => $saldo[$p->Id] ?? 0,
            'LimitKredit' => $kredit[$p->Id]['LimitKredit'] ?? null,
            'SisaPiutang' => $kredit[$p->Id]['SisaPiutang'] ?? '0.00',
            'HariLewatJatuhTempo' => $kredit[$p->Id]['HariLewatJatuhTempo'] ?? 0,
            'HariLahir' => $p->TanggalLahir?->format('m-d'),
            'JumlahTransaksi' => $belanja[$p->Id]['JumlahTransaksi'] ?? 0,
            // Objek JSON walau kosong (`{}`), bukan larik.
            'PemakaianPromo' => ($pemakaian[$p->Id] ?? []) === [] ? (object) [] : $pemakaian[$p->Id],
        ])->all());
    }
}

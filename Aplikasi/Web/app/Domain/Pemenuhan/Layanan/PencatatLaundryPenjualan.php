<?php

declare(strict_types=1);

namespace App\Domain\Pemenuhan\Layanan;

use App\Domain\Bersama\Dokumen\Layanan\PencatatRiwayatStatus;
use App\Domain\Pelanggan\Kueri\IdentitasPelanggan;
use App\Domain\Pelanggan\Layanan\NomorHp;
use App\Domain\Pemenuhan\Enum\JenisLayananLaundry;
use App\Domain\Pemenuhan\Enum\StatusLaundry;
use App\Domain\Pemenuhan\Kueri\PengaturanLaundryTenant;
use App\Domain\Pemenuhan\Model\TiketLaundry;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Laundry (§9.9, SLS-09) di jalur `Penjualan.Buat` (transaksi DB yang sama, offline-first):
 * - **Buat**: blok `Laundry` penjualan menjadi satu tiket berstatus Diterima (`Uuid`/`Nomor` = penjualan). Nama & nomor
 *   HP dari pelanggan tertaut (bila ada), selain itu dari blok. Estimasi selesai dari perangkat; kosong/tidak valid =
 *   waktu penjualan + durasi pengaturan (reguler/express). Masalah (tanpa nama/berat/item) dilaporkan untuk tinjauan,
 *   tiket tetap dibuat.
 * - **Batalkan**: void penjualan membatalkan tiket yang belum diambil.
 *
 * @phpstan-type BlokLaundry array{JenisLayanan: string, Berat?: string|null, Item?: list<array{Nama: string, Jumlah: int}>|null, Parfum?: string|null, Catatan?: string|null, EstimasiSelesaiPada?: string|null, NamaPelanggan?: string|null, NoHp?: string|null}
 */
final class PencatatLaundryPenjualan
{
    public function __construct(
        private readonly IdentitasPelanggan $pelanggan,
        private readonly PengaturanLaundryTenant $pengaturan,
        private readonly PencatatRiwayatStatus $riwayat,
    ) {}

    /**
     * @param  BlokLaundry  $blok
     * @return list<string> masalah untuk tinjauan penjualan
     */
    public function Buat(array $blok, int $idPenjualan, string $uuidPenjualan, string $nomor, int $idOutlet, ?int $idPelanggan, CarbonImmutable $dibuatPada, int $idPengguna): array
    {
        if (TiketLaundry::query()->where('IdPenjualan', $idPenjualan)->exists()) {
            return [];
        }

        $masalah = [];
        $jenis = JenisLayananLaundry::tryFrom($blok['JenisLayanan']) ?? JenisLayananLaundry::Reguler;
        $kontak = $idPelanggan === null ? null : $this->pelanggan->AmbilKontak($idPelanggan);
        $nama = trim((string) ($kontak['Nama'] ?? $blok['NamaPelanggan'] ?? ''));
        $noHp = $kontak['NoHp'] ?? NomorHp::Normalisasi($blok['NoHp'] ?? null);
        $item = array_values(array_filter($blok['Item'] ?? [], fn (array $i): bool => trim($i['Nama']) !== '' && $i['Jumlah'] > 0));
        $berat = isset($blok['Berat']) && $blok['Berat'] !== '' && BigDecimal::of($blok['Berat'])->isPositive() ? $blok['Berat'] : null;

        if ($nama === '') {
            $nama = 'Tanpa nama';
            $masalah[] = 'tiket tanpa nama pelanggan';
        }

        if ($berat === null && $item === []) {
            $masalah[] = 'tiket tanpa berat maupun item';
        }

        $t = new TiketLaundry;
        $t->Uuid = $uuidPenjualan;
        $t->IdOutlet = $idOutlet;
        $t->IdPenjualan = $idPenjualan;
        $t->Nomor = $nomor;
        $t->IdPelanggan = $idPelanggan;
        $t->NamaPelanggan = mb_substr($nama, 0, 100);
        $t->NoHp = $noHp;
        $t->JenisLayanan = $jenis;
        $t->Berat = $berat;
        $t->Item = $item === [] ? null : array_map(fn (array $i): array => ['Nama' => mb_substr(trim($i['Nama']), 0, 60), 'Jumlah' => $i['Jumlah']], $item);
        $t->Parfum = isset($blok['Parfum']) && trim($blok['Parfum']) !== '' ? mb_substr(trim($blok['Parfum']), 0, 50) : null;
        $t->Catatan = isset($blok['Catatan']) && trim($blok['Catatan']) !== '' ? mb_substr(trim($blok['Catatan']), 0, 255) : null;
        $t->Status = StatusLaundry::Diterima;
        $t->EstimasiSelesaiPada = Carbon::instance($this->HitungEstimasi($blok['EstimasiSelesaiPada'] ?? null, $jenis, $dibuatPada));
        $t->save();
        $this->riwayat->Catat(TiketLaundry::JENIS_DOKUMEN, $t->Id, null, StatusLaundry::Diterima->value, $idPengguna);

        return $masalah;
    }

    public function Batalkan(int $idPenjualan, int $idPengguna): void
    {
        $t = TiketLaundry::query()->where('IdPenjualan', $idPenjualan)->lockForUpdate()->first();

        if ($t === null || $t->Status->CekAkhir()) {
            return;
        }

        $dari = $t->Status;
        $t->UbahStatus(StatusLaundry::Dibatalkan);
        $t->save();
        $this->riwayat->Catat(TiketLaundry::JENIS_DOKUMEN, $t->Id, $dari->value, StatusLaundry::Dibatalkan->value, $idPengguna, 'Penjualan di-void');
    }

    private function HitungEstimasi(?string $dariPerangkat, JenisLayananLaundry $jenis, CarbonImmutable $dibuatPada): CarbonImmutable
    {
        if ($dariPerangkat !== null && $dariPerangkat !== '') {
            try {
                $waktu = CarbonImmutable::parse($dariPerangkat)->utc();

                // Estimasi wajar: tidak sebelum transaksi dan tidak lebih dari 60 hari.
                if ($waktu->greaterThanOrEqualTo($dibuatPada) && $waktu->lessThanOrEqualTo($dibuatPada->addDays(60))) {
                    return $waktu;
                }
            } catch (Throwable) {
                // Format tidak dikenal: pakai durasi pengaturan.
            }
        }

        $p = $this->pengaturan->Ambil();

        return $dibuatPada->utc()->addHours($jenis === JenisLayananLaundry::Express ? $p->JamExpress : $p->JamReguler);
    }
}

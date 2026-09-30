<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Layanan;

use App\Domain\Bersama\Dokumen\Layanan\PencatatRiwayatStatus;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Penjualan\Enum\JenisMetodePembayaran;
use App\Domain\Penjualan\Enum\JenisPemenuhanOnline;
use App\Domain\Penjualan\Enum\StatusPesananOnline;
use App\Domain\Penjualan\Model\PenjualanPembayaran;
use App\Domain\Penjualan\Model\PesananOnline;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/**
 * F-17: penagihan pesanan toko online di transaksi DB penjualan. Cermin `PenutupPesananPenjualan` (pre-order F-12),
 * karena peristiwanya sama: uang muka yang sudah dibukukan (J-17.1) dipakai penjualan lewat metode Uang Muka, dan
 * void penjualan mengembalikannya ke pesanan.
 *
 * Berlaku untuk **semua** pesanan online, bukan hanya yang dibayar di muka: pesanan bayar saat ambil/COD ditagih
 * lewat jalur yang sama dan hanya tidak punya baris Uang Muka. Karena itu "belum dibayar" bukan masalah di sini —
 * yang dijaga adalah pesanan yang sudah ditagihkan penjualan lain, sudah berstatus akhir, atau uangnya sudah
 * dikembalikan.
 *
 * Keadaan yang berubah setelah kasir menagih (bisa offline) **tidak menolak penjualan** (§18.3): masalahnya
 * dikembalikan sebagai teks tinjauan `UangMukaBermasalah`, seperti pre-order. Penjualan yang sudah masuk tidak
 * dibatalkan hanya karena statusnya bergerak di server; yang salah ditandai supaya orang memeriksanya.
 *
 * `Selesai` hanya dipasang untuk pesanan **ambil sendiri**: pesanan kirim baru selesai setelah kurirnya menyerahkan
 * barang (dijaga `UbahStatusPesananOnline`), jadi menagihnya di kasir tidak boleh mendahului itu.
 */
final class PenutupUangMukaPesananOnline
{
    public function __construct(private readonly PencatatRiwayatStatus $riwayat) {}

    /**
     * @return array{0: PesananOnline|null, 1: list<string>} [pesanan terkunci, masalah]
     */
    public function Cari(?string $uuid, int $idOutlet): array
    {
        if ($uuid === null) {
            return [null, []];
        }

        $pesanan = PesananOnline::query()->where('Uuid', $uuid)->lockForUpdate()->first();

        if (! $pesanan instanceof PesananOnline) {
            return [null, ['pesanan online tidak dikenal server']];
        }

        $masalah = [];

        if ($pesanan->IdOutlet !== $idOutlet) {
            $masalah[] = "pesanan online {$pesanan->Nomor} milik outlet lain";
        }

        if ($pesanan->DikembalikanPada !== null) {
            $masalah[] = "uang muka pesanan online {$pesanan->Nomor} sudah dikembalikan";
        }

        if (in_array($pesanan->Status, [StatusPesananOnline::Ditolak, StatusPesananOnline::Dibatalkan, StatusPesananOnline::Kedaluwarsa], true)) {
            $masalah[] = "pesanan online {$pesanan->Nomor} sudah {$pesanan->Status->AmbilLabel()}";
        }

        if ($pesanan->IdPenjualan !== null) {
            $masalah[] = "pesanan online {$pesanan->Nomor} sudah ditagihkan lewat penjualan lain";
        }

        return [$pesanan, $masalah];
    }

    /**
     * @return list<string> masalah
     */
    public function Tandai(PesananOnline $pesanan, int $idPenjualan, Uang $uangMukaDipakai, CarbonImmutable $waktu, int $idPengguna): array
    {
        $masalah = [];
        $sisa = $pesanan->AmbilSisaUangMuka();

        if ($uangMukaDipakai->Bandingkan($sisa) > 0) {
            $masalah[] = "uang muka dipakai {$uangMukaDipakai->FormatRupiah()} melebihi sisa {$pesanan->Nomor} {$sisa->FormatRupiah()}";
        }

        $asal = $pesanan->Status;
        $pesanan->UangMukaTerpakai = Uang::Dari($pesanan->UangMukaTerpakai)->Tambah($uangMukaDipakai)->KeString();
        $pesanan->IdPenjualan ??= $idPenjualan;

        if ($pesanan->JenisPemenuhan === JenisPemenuhanOnline::AmbilSendiri && $asal->BisaBerubahKe(StatusPesananOnline::Selesai)) {
            $pesanan->UbahStatus(StatusPesananOnline::Selesai);
            $pesanan->SelesaiPada = Carbon::instance($waktu->toDateTime());
        }

        $pesanan->save();

        if ($asal !== $pesanan->Status) {
            $this->riwayat->Catat(PesananOnline::JENIS_DOKUMEN, $pesanan->Id, $asal->value, $pesanan->Status->value, $idPengguna, 'Ditagihkan di kasir');
        }

        return $masalah;
    }

    /** Void penjualan penagihan: uang muka kembali menjadi sisa pesanan, pesanan bisa ditagihkan ulang. */
    public function Batalkan(int $idPenjualan, int $idPengguna): void
    {
        $pesanan = PesananOnline::query()->where('IdPenjualan', $idPenjualan)->lockForUpdate()->first();

        if (! $pesanan instanceof PesananOnline) {
            return;
        }

        $dipakai = Uang::Dari((string) PenjualanPembayaran::query()
            ->where('IdPenjualan', $idPenjualan)
            ->where('JenisMetode', JenisMetodePembayaran::UangMuka->value)
            ->sum('Jumlah'));
        $asal = $pesanan->Status;
        $pesanan->UangMukaTerpakai = Uang::Dari($pesanan->UangMukaTerpakai)->Kurangi($dipakai)->KeString();
        $pesanan->IdPenjualan = null;

        if ($asal === StatusPesananOnline::Selesai) {
            $pesanan->forceFill(['Status' => StatusPesananOnline::Siap, 'SelesaiPada' => null]);
        }

        $pesanan->save();

        if ($asal !== $pesanan->Status) {
            $this->riwayat->Catat(PesananOnline::JENIS_DOKUMEN, $pesanan->Id, $asal->value, $pesanan->Status->value, $idPengguna, 'Penjualan penagihan di-void');
        }
    }
}

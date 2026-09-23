<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Tagihan\Aksi;

use App\Domain\Pengelola\Tagihan\Kueri\DaftarTagihanPlatform;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Tenant\Enum\StatusLangganan;
use App\Domain\Tenant\Enum\StatusPembayaranLangganan;
use App\Domain\Tenant\Enum\StatusTagihanLangganan;
use App\Domain\Tenant\Model\Langganan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Penjadwal tunggakan sederhana (P-08 langkah 4 tanpa pengingat, F-00 state machine), dijalankan tiap jam:
 * 1. Tagihan Terbit yang lewat jatuh tempo → JatuhTempo (tetap bisa dibayar).
 * 2. Langganan Aktif yang periodenya habis → Tertunggak (semua fitur jalan, banner tampil).
 * 3. Tertunggak lewat masa tenggang (config `tagihan.HariMasaTenggang`, default 7 hari) → Ditangguhkan, kecuali ada
 *    bukti transfer yang sedang menunggu verifikasi (tenant sudah membayar, jangan dihukum karena antrean kami).
 * Tenant berpenanda Uji/Demo/Internal dilewati (dikecualikan dari tagihan, P-07).
 * Tiap baris diproses dengan kunci dan status diperiksa ulang, sehingga aman berjalan bersamaan dengan verifikasi
 * pembayaran. Idempoten. Setiap perubahan tercatat di audit pengelola dengan pelaku Sistem.
 *
 * @phpstan-type Hasil array{TagihanJatuhTempo: int, Tertunggak: int, Ditangguhkan: int}
 */
final class ProsesTunggakanLangganan
{
    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    /**
     * @return Hasil
     */
    public function Jalankan(): array
    {
        $sekarang = CarbonImmutable::now();

        return [
            'TagihanJatuhTempo' => $this->TandaiTagihanJatuhTempo($sekarang),
            'Tertunggak' => $this->UbahLangganan(StatusLangganan::Aktif, StatusLangganan::Tertunggak, $sekarang),
            'Ditangguhkan' => $this->UbahLangganan(
                StatusLangganan::Tertunggak,
                StatusLangganan::Ditangguhkan,
                $sekarang->subDays((int) config('tagihan.HariMasaTenggang')),
            ),
        ];
    }

    private function TandaiTagihanJatuhTempo(CarbonImmutable $sekarang): int
    {
        $jumlah = 0;
        $daftarId = DaftarTagihanPlatform::KueriTagihan()
            ->where('Status', StatusTagihanLangganan::Terbit->value)
            ->where('JatuhTempoPada', '<=', $sekarang)
            ->orderBy('Id')
            ->pluck('Id');

        foreach ($daftarId as $id) {
            $jumlah += DB::transaction(function () use ($id, $sekarang): int {
                $tagihan = DaftarTagihanPlatform::KueriTagihan()->whereKey($id)->lockForUpdate()->first();

                if ($tagihan === null || $tagihan->Status !== StatusTagihanLangganan::Terbit || $tagihan->JatuhTempoPada->greaterThan($sekarang)) {
                    return 0;
                }

                $tagihan->update(['Status' => StatusTagihanLangganan::JatuhTempo]);
                $this->audit->Catat(
                    'tagihan.jatuh-tempo',
                    $tagihan,
                    nilaiLama: ['Status' => StatusTagihanLangganan::Terbit->value],
                    nilaiBaru: ['Status' => StatusTagihanLangganan::JatuhTempo->value, 'Nomor' => $tagihan->Nomor],
                    idTenant: $tagihan->IdTenant,
                );

                return 1;
            });
        }

        return $jumlah;
    }

    /**
     * @param  CarbonImmutable  $batasPeriodeSelesai  langganan yang `PeriodeSelesai`-nya ≤ batas ini diproses
     */
    private function UbahLangganan(StatusLangganan $asal, StatusLangganan $tujuan, CarbonImmutable $batasPeriodeSelesai): int
    {
        $jumlah = 0;
        // Tenant berpenanda Uji/Demo/Internal dikecualikan dari tagihan (P-07, BR-P07.8): tidak pernah tertunggak.
        $daftarId = Langganan::query()
            ->whereHas('Tenant', fn ($kueri) => $kueri->whereNull('Penanda'))
            ->where('Status', $asal->value)
            ->whereNotNull('PeriodeSelesai')
            ->where('PeriodeSelesai', '<=', $batasPeriodeSelesai)
            ->orderBy('Id')
            ->pluck('Id');

        foreach ($daftarId as $id) {
            $jumlah += DB::transaction(function () use ($id, $asal, $tujuan, $batasPeriodeSelesai): int {
                $langganan = Langganan::query()->whereKey($id)->lockForUpdate()->first();

                if ($langganan === null || $langganan->Status !== $asal || $langganan->PeriodeSelesai === null
                    || $langganan->PeriodeSelesai->greaterThan($batasPeriodeSelesai)) {
                    return 0;
                }

                if ($tujuan === StatusLangganan::Ditangguhkan && $this->CekAdaPembayaranMenunggu($langganan->IdTenant)) {
                    return 0;
                }

                $langganan->update(['Status' => $tujuan]);
                $this->audit->Catat(
                    'langganan.status.otomatis',
                    $langganan,
                    nilaiLama: ['Status' => $asal->value],
                    nilaiBaru: ['Status' => $tujuan->value],
                    alasan: $tujuan === StatusLangganan::Tertunggak ? 'Periode langganan berakhir tanpa pembayaran.' : 'Masa tenggang tunggakan habis.',
                    idTenant: $langganan->IdTenant,
                );

                return 1;
            });
        }

        return $jumlah;
    }

    private function CekAdaPembayaranMenunggu(int $idTenant): bool
    {
        return DaftarTagihanPlatform::KueriPembayaran()
            ->where('IdTenant', $idTenant)
            ->where('Status', StatusPembayaranLangganan::Menunggu->value)
            ->exists();
    }
}

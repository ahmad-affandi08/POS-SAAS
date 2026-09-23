<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Tagihan\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Pengelola\Tagihan\Kueri\DaftarTagihanPlatform;
use App\Domain\Pengelola\Tagihan\Surel\PembayaranLanggananDiterima;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Tenant\Enum\JenisTagihanLangganan;
use App\Domain\Tenant\Enum\StatusLangganan;
use App\Domain\Tenant\Enum\StatusPembayaranLangganan;
use App\Domain\Tenant\Enum\StatusTagihanLangganan;
use App\Domain\Tenant\Layanan\PenghitungPeriodeLangganan;
use App\Domain\Tenant\Model\Langganan;
use App\Domain\Tenant\Model\PembayaranLangganan;
use App\Domain\Tenant\Model\TagihanLangganan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Keuangan/Super Admin menerima bukti transfer (P-08 langkah 3): pembayaran Diterima → tagihan Lunas → langganan
 * Aktif dengan paket, siklus, dan periode baru (Trial/Gratis/Tertunggak/Ditangguhkan → Aktif, BR-00.7).
 *
 * - Jumlah yang benar-benar masuk ke rekening wajib diisi dan harus sama dengan total tagihan.
 * - Kunci berurutan Langganan → Tagihan → Pembayaran (sama dengan aksi tenant) lalu status diperiksa ulang, sehingga
 *   dua verifikator bersamaan atau klik ganda hanya menghasilkan satu penerimaan.
 * - Tanpa four-eyes: verifikasi berbasis mutasi rekening dan tercatat di audit; persetujuan kedua P-08 hanya untuk
 *   refund > Rp 1.000.000 (BR-P08.2).
 * - Email pemberitahuan ke Owner dikirim setelah transaksi tersimpan; kegagalan email tidak membatalkan verifikasi.
 */
final class TerimaPembayaranLangganan
{
    public function __construct(
        private readonly DaftarTagihanPlatform $daftar,
        private readonly PenghitungPeriodeLangganan $periode,
        private readonly PencatatAuditPengelola $audit,
    ) {}

    public function Jalankan(PenggunaPengelola $verifikator, string $uuidPembayaran, string $jumlahDiterima, ?string $catatan = null): PembayaranLangganan
    {
        $awal = $this->daftar->CariPembayaran($uuidPembayaran)
            ?? throw new PelanggaranAturanBisnis('PembayaranTidakDitemukan', 'Pembayaran tidak ditemukan.');

        [$pembayaran, $tagihan] = DB::transaction(function () use ($verifikator, $awal, $jumlahDiterima, $catatan): array {
            $langganan = Langganan::query()->where('IdTenant', $awal->IdTenant)->lockForUpdate()->first()
                ?? throw new PelanggaranAturanBisnis('LanggananTidakAda', 'Langganan tenant ini tidak ditemukan.');
            $tagihan = DaftarTagihanPlatform::KueriTagihan()->with('Paket')->whereKey($awal->IdTagihanLangganan)->lockForUpdate()->firstOrFail();
            $pembayaran = DaftarTagihanPlatform::KueriPembayaran()->whereKey($awal->Id)->lockForUpdate()->firstOrFail();

            if ($pembayaran->Status !== StatusPembayaranLangganan::Menunggu) {
                throw new PelanggaranAturanBisnis('SudahDiverifikasi', "Pembayaran ini sudah {$pembayaran->Status->AmbilLabel()}.");
            }

            if (! $tagihan->Status->CekTerbuka()) {
                throw new PelanggaranAturanBisnis('TagihanTidakTerbuka', "Tagihan {$tagihan->Nomor} sudah {$tagihan->Status->AmbilLabel()}.");
            }

            $diterima = Uang::Dari($jumlahDiterima);

            if (! $diterima->SamaDengan($tagihan->AmbilTotal()) || ! $diterima->SamaDengan($pembayaran->AmbilJumlah())) {
                throw new PelanggaranAturanBisnis(
                    'JumlahTidakCocok',
                    'Jumlah diterima harus sama dengan total tagihan '.$tagihan->AmbilTotal()->FormatRupiah().'. Bila berbeda, tolak dengan alasan.',
                    'JumlahDiterima',
                );
            }

            if ($langganan->Status !== StatusLangganan::Aktif && ! $langganan->Status->BisaBerubahKe(StatusLangganan::Aktif)) {
                throw new PelanggaranAturanBisnis('LanggananTidakBisaAktif', "Langganan berstatus {$langganan->Status->AmbilLabel()} tidak bisa diaktifkan dari tagihan.");
            }

            $sekarang = CarbonImmutable::now();
            $lanjutan = $tagihan->Jenis === JenisTagihanLangganan::Perpanjangan
                && in_array($langganan->Status, [StatusLangganan::Aktif, StatusLangganan::Tertunggak], true)
                && $langganan->IdPaket === $tagihan->IdPaket;
            $periode = $this->periode->Hitung(
                $lanjutan ? JenisTagihanLangganan::Perpanjangan : JenisTagihanLangganan::Aktivasi,
                $tagihan->Siklus,
                $sekarang,
                $langganan->PeriodeSelesai,
            );
            $mulaiPaket = $lanjutan ? $this->AmbilMulaiPaketSebelumnya($tagihan) : null;

            $statusTagihanLama = $tagihan->Status->value;
            $pembayaran->update([
                'Status' => StatusPembayaranLangganan::Diterima,
                'IdPenggunaPengelolaVerifikator' => $verifikator->Id,
                'DiverifikasiPada' => $sekarang,
                'JumlahDiterima' => $diterima->KeString(),
            ]);

            $tagihan->update([
                'Status' => StatusTagihanLangganan::Lunas,
                'DibayarPada' => $sekarang,
                'PeriodeMulai' => $periode['Mulai'],
                'PeriodeSelesai' => $periode['Selesai'],
                'MulaiLanggananPaket' => ($mulaiPaket ?? $periode['Mulai'])->toDateString(),
            ]);

            $lama = [
                'Status' => $langganan->Status->value,
                'IdPaket' => $langganan->IdPaket,
                'SiklusTagihan' => $langganan->SiklusTagihan->value,
                'PeriodeMulai' => $langganan->PeriodeMulai?->toIso8601ZuluString(),
                'PeriodeSelesai' => $langganan->PeriodeSelesai?->toIso8601ZuluString(),
            ];
            $langganan->update([
                'Status' => StatusLangganan::Aktif,
                'IdPaket' => $tagihan->IdPaket,
                'SiklusTagihan' => $tagihan->Siklus,
                'PeriodeMulai' => $periode['Mulai'],
                'PeriodeSelesai' => $periode['Selesai'],
            ]);

            $this->audit->Catat(
                'tagihan.pembayaran.terima',
                $pembayaran,
                nilaiLama: ['Pembayaran' => StatusPembayaranLangganan::Menunggu->value, 'Tagihan' => $statusTagihanLama, 'Langganan' => $lama],
                nilaiBaru: [
                    'Pembayaran' => StatusPembayaranLangganan::Diterima->value,
                    'NomorTagihan' => $tagihan->Nomor,
                    'JumlahDiterima' => $diterima->KeString(),
                    'Langganan' => [
                        'Status' => StatusLangganan::Aktif->value,
                        'IdPaket' => $tagihan->IdPaket,
                        'SiklusTagihan' => $tagihan->Siklus->value,
                        'PeriodeMulai' => $periode['Mulai']->toIso8601ZuluString(),
                        'PeriodeSelesai' => $periode['Selesai']->toIso8601ZuluString(),
                    ],
                ],
                alasan: $catatan,
                idPelaku: $verifikator->Id,
                idTenant: $tagihan->IdTenant,
            );

            return [$pembayaran, $tagihan];
        });

        $this->KirimPemberitahuan($pembayaran, $tagihan);

        return $pembayaran;
    }

    /** Jangkar grandfathering (BR-P04.1) diteruskan dari tagihan lunas sebelumnya untuk paket yang sama. */
    private function AmbilMulaiPaketSebelumnya(TagihanLangganan $tagihan): ?CarbonImmutable
    {
        $sebelumnya = DaftarTagihanPlatform::KueriTagihan()
            ->where('IdTenant', $tagihan->IdTenant)
            ->where('Status', StatusTagihanLangganan::Lunas->value)
            ->where('Id', '!=', $tagihan->Id)
            ->orderByDesc('DibayarPada')
            ->orderByDesc('Id')
            ->first();

        return $sebelumnya !== null && $sebelumnya->IdPaket === $tagihan->IdPaket && $sebelumnya->MulaiLanggananPaket !== null
            ? CarbonImmutable::instance($sebelumnya->MulaiLanggananPaket)
            : null;
    }

    private function KirimPemberitahuan(PembayaranLangganan $pembayaran, TagihanLangganan $tagihan): void
    {
        if ($pembayaran->EmailPemberitahuan === null) {
            return;
        }

        try {
            Mail::to($pembayaran->EmailPemberitahuan)->send(new PembayaranLanggananDiterima(
                nama: $pembayaran->NamaPemberitahuan ?? 'Pemilik usaha',
                nomorTagihan: $tagihan->Nomor,
                total: $tagihan->AmbilTotal()->FormatRupiah(),
                namaPaket: $tagihan->Paket->Nama,
                periodeSelesai: $tagihan->PeriodeSelesai?->copy()->setTimezone('Asia/Jakarta')->translatedFormat('j F Y') ?? '—',
            ));
        } catch (Throwable $galat) {
            Log::warning('Email penerimaan pembayaran langganan gagal dikirim.', ['IdTenant' => $tagihan->IdTenant, 'Nomor' => $tagihan->Nomor, 'Galat' => $galat->getMessage()]);
        }
    }
}

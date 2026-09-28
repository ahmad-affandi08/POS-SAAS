<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Tagihan\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Integrasi\Billing\NotifikasiBilling;
use App\Domain\Integrasi\GerbangPembayaran\StatusPembayaranGerbang;
use App\Domain\Pengelola\Tagihan\Kueri\DaftarTagihanPlatform;
use App\Domain\Pengelola\Tagihan\Surel\PembayaranLanggananDiterima;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Tenant\Enum\MetodePembayaranLangganan;
use App\Domain\Tenant\Enum\StatusPembayaranLangganan;
use App\Domain\Tenant\Enum\StatusTagihanLangganan;
use App\Domain\Tenant\Layanan\PelunasTagihanLangganan;
use App\Domain\Tenant\Model\Langganan;
use App\Domain\Tenant\Model\PembayaranLangganan;
use App\Domain\Tenant\Model\TagihanLangganan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Notifikasi gerbang billing platform (P-08 langkah 3, BR-P08.11): satu-satunya jalan tagihan langganan menjadi
 * `Lunas` lewat pembayaran online. Tanda tangannya sudah diverifikasi pemanggil.
 *
 * - Pelunasannya memakai `PelunasTagihanLangganan`, layanan yang sama dengan verifikasi transfer manual, sehingga
 *   periode & status yang dihasilkan kedua jalur tidak bisa berbeda.
 * - **Idempoten:** Midtrans mengirim notifikasi berulang sampai dijawab 200. Pembayaran yang statusnya bukan lagi
 *   `Menunggu` dijawab "sudah diproses" tanpa menyentuh apa pun, jadi periode langganan tidak pernah diperpanjang
 *   dua kali untuk satu pembayaran.
 * - Kunci berurutan Langganan → Tagihan → Pembayaran, sama dengan jalur manual dan penjadwal tunggakan.
 * - Data tagihan adalah data platform ke tenant, dibaca lintas tenant lewat `DaftarTagihanPlatform`
 *   (`KonteksPengelola::KueriDataPlatform`, CLAUDE.md #11). Pelaku audit dikosongkan: pelakunya sistem, bukan orang.
 */
final class TerimaNotifikasiBillingLangganan
{
    public function __construct(
        private readonly PelunasTagihanLangganan $pelunas,
        private readonly PencatatAuditPengelola $audit,
    ) {}

    /** @return bool `false` = notifikasi tidak dikenal atau tidak bisa diproses; tetap dijawab 200 agar tidak diulang. */
    public function Jalankan(NotifikasiBilling $notifikasi): bool
    {
        $awal = DaftarTagihanPlatform::KueriPembayaran()->where('RefGateway', $notifikasi->nomorPesanan)->first();

        // `IdTenant` di nomor pesanan hanya penanda; yang menentukan tetap baris pembayarannya sendiri.
        if ($awal === null || $awal->IdTenant !== $notifikasi->idTenant || $awal->Metode !== MetodePembayaranLangganan::Gateway) {
            Log::warning('Notifikasi gerbang billing tanpa pembayaran yang cocok.', ['NomorPesanan' => $notifikasi->nomorPesanan, 'Status' => $notifikasi->statusAsli]);

            return false;
        }

        // `pending`, `authorize`, dan `capture` yang masih ditinjau: belum ada uang yang pasti masuk.
        if ($notifikasi->status === StatusPembayaranGerbang::Menunggu) {
            return true;
        }

        try {
            return DB::transaction(fn (): bool => $this->Proses($awal, $notifikasi));
        } catch (PelanggaranAturanBisnis $galat) {
            // Misal jumlah yang dibayar tidak sama dengan total tagihan: tidak boleh dilunasi otomatis, dan tidak ada
            // gunanya diulang. Pembayaran dibiarkan `Menunggu` supaya muncul di antrean verifikasi manual.
            Log::error('Notifikasi gerbang billing tidak bisa diproses otomatis.', [
                'NomorPesanan' => $notifikasi->nomorPesanan,
                'IdTenant' => $awal->IdTenant,
                'Status' => $notifikasi->statusAsli,
                'Jumlah' => $notifikasi->jumlah,
                'Kode' => $galat->kode,
                'Pesan' => $galat->getMessage(),
            ]);

            return false;
        }
    }

    private function Proses(PembayaranLangganan $awal, NotifikasiBilling $notifikasi): bool
    {
        $langganan = Langganan::query()->where('IdTenant', $awal->IdTenant)->lockForUpdate()->first();
        $tagihan = DaftarTagihanPlatform::KueriTagihan()->with('Paket')->whereKey($awal->IdTagihanLangganan)->lockForUpdate()->firstOrFail();
        $pembayaran = DaftarTagihanPlatform::KueriPembayaran()->whereKey($awal->Id)->lockForUpdate()->firstOrFail();

        // Notifikasi ulang untuk pembayaran yang sudah selesai: sudah diproses, tidak ada yang perlu diubah.
        if ($pembayaran->Status !== StatusPembayaranLangganan::Menunggu) {
            return true;
        }

        if ($notifikasi->status !== StatusPembayaranGerbang::Lunas) {
            $pembayaran->update([
                'Status' => StatusPembayaranLangganan::Ditolak,
                'AlasanTolak' => "Pembayaran online tidak selesai (status gerbang: {$notifikasi->statusAsli}).",
            ]);
            $this->audit->Catat(
                'tagihan.pembayaran-gerbang.gagal',
                $pembayaran,
                nilaiLama: ['Pembayaran' => StatusPembayaranLangganan::Menunggu->value],
                nilaiBaru: ['Pembayaran' => StatusPembayaranLangganan::Ditolak->value, 'StatusGerbang' => $notifikasi->statusAsli, 'IdTransaksiGerbang' => $notifikasi->idTransaksi],
                idTenant: $pembayaran->IdTenant,
            );

            return true;
        }

        if ($langganan === null) {
            throw new PelanggaranAturanBisnis('LanggananTidakAda', 'Langganan tenant ini tidak ditemukan.');
        }

        $diterima = Uang::Dari($notifikasi->jumlah);
        $hasil = $this->pelunas->Lunasi(
            $langganan,
            $tagihan,
            $pembayaran,
            $diterima,
            CarbonImmutable::now(),
            idVerifikatorPengelola: null,
            mulaiPaketSebelumnya: $this->AmbilMulaiPaketSebelumnya($tagihan),
        );

        $this->audit->Catat(
            'tagihan.pembayaran-gerbang.lunas',
            $pembayaran,
            nilaiLama: ['Pembayaran' => StatusPembayaranLangganan::Menunggu->value, 'Tagihan' => $hasil->statusTagihanLama, 'Langganan' => $hasil->langgananLama],
            nilaiBaru: [
                'Pembayaran' => StatusPembayaranLangganan::Diterima->value,
                'NomorTagihan' => $tagihan->Nomor,
                'JumlahDiterima' => $diterima->KeString(),
                'IdTransaksiGerbang' => $notifikasi->idTransaksi,
                'Langganan' => $hasil->LanggananBaru($tagihan->IdPaket, $tagihan->Siklus),
            ],
            idTenant: $tagihan->IdTenant,
        );

        DB::afterCommit(fn () => $this->KirimPemberitahuan($pembayaran, $tagihan));

        return true;
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
            Log::warning('Email pelunasan langganan lewat gerbang gagal dikirim.', ['IdTenant' => $tagihan->IdTenant, 'Nomor' => $tagihan->Nomor, 'Galat' => $galat->getMessage()]);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Layanan;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Dokumen\Layanan\PencatatRiwayatStatus;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Integrasi\GerbangPembayaran\StatusPembayaranGerbang;
use App\Domain\Penjualan\Enum\StatusTagihanQris;
use App\Domain\Penjualan\Model\TagihanQris;
use Brick\Math\Exception\MathException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Menerapkan status dari gerbang (webhook atau cek status, F-08 BR-08.5) ke satu tagihan QRIS di transaksi DB dengan
 * kunci baris, sehingga notifikasi berulang/bersamaan idempoten:
 * - `Lunas` hanya bila jumlah yang dilaporkan gerbang (bila ada) sama dengan `Jumlah` tagihan; beda jumlah = status
 *   tidak berubah, dicatat sebagai peringatan log + `LogAudit` `tagihan-qris.jumlah-berbeda` untuk ditelusuri.
 * - `Kedaluwarsa`/`Gagal` hanya dari `Menunggu`; tagihan `Lunas` tidak pernah berubah lagi.
 * - Tagihan kedaluwarsa/gagal/dibatalkan yang ternyata dibayar tetap menjadi `Lunas` (uang nyata diterima).
 * Setiap perubahan dicatat di `RiwayatStatusDokumen` (tanpa pengguna; sumber di alasan).
 */
final class PenerapStatusTagihanQris
{
    public function __construct(
        private readonly PencatatRiwayatStatus $riwayat,
        private readonly PencatatAudit $audit,
    ) {}

    public function Terapkan(int $idTagihan, StatusPembayaranGerbang $statusGerbang, ?string $jumlahGerbang, string $sumber): TagihanQris
    {
        return DB::transaction(function () use ($idTagihan, $statusGerbang, $jumlahGerbang, $sumber): TagihanQris {
            $tagihan = TagihanQris::query()->whereKey($idTagihan)->lockForUpdate()->firstOrFail();
            $tujuan = match ($statusGerbang) {
                StatusPembayaranGerbang::Lunas => StatusTagihanQris::Lunas,
                StatusPembayaranGerbang::Kedaluwarsa => StatusTagihanQris::Kedaluwarsa,
                StatusPembayaranGerbang::Gagal => StatusTagihanQris::Gagal,
                StatusPembayaranGerbang::Menunggu => null,
            };

            if ($tujuan === null || ! $tagihan->Status->BisaBerubahKe($tujuan)) {
                return $tagihan;
            }

            $jumlah = Uang::Dari($tagihan->Jumlah);
            $diterima = $jumlah;

            if ($tujuan === StatusTagihanQris::Lunas && $jumlahGerbang !== null && trim($jumlahGerbang) !== '') {
                $dilaporkan = self::UraiJumlah($jumlahGerbang);

                if ($dilaporkan === null || ! $dilaporkan->SamaDengan($jumlah)) {
                    Log::warning('Jumlah QRIS dinamis dari gerbang berbeda dengan tagihan; status tidak diubah.', [
                        'NomorPesanan' => $tagihan->NomorPesanan,
                        'Jumlah' => $tagihan->Jumlah,
                        'JumlahGerbang' => mb_substr($jumlahGerbang, 0, 30),
                        'Sumber' => $sumber,
                    ]);
                    $this->audit->Catat('tagihan-qris.jumlah-berbeda', $tagihan, nilaiBaru: [
                        'NomorPesanan' => $tagihan->NomorPesanan,
                        'Jumlah' => $tagihan->Jumlah,
                        'JumlahGerbang' => mb_substr($jumlahGerbang, 0, 30),
                        'Sumber' => $sumber,
                    ], idTenant: $tagihan->IdTenant);

                    return $tagihan;
                }

                $diterima = $dilaporkan;
            }

            $asal = $tagihan->Status;
            $tagihan->Status = $tujuan;

            if ($tujuan === StatusTagihanQris::Lunas) {
                $tagihan->LunasPada = now();
                $tagihan->JumlahDiterima = $diterima->KeString();
            }

            $tagihan->save();
            $this->riwayat->Catat(TagihanQris::JENIS_DOKUMEN, $tagihan->Id, $asal->value, $tujuan->value, null, $sumber);

            if ($asal !== StatusTagihanQris::Menunggu) {
                Log::warning('Tagihan QRIS dinamis dibayar setelah berstatus akhir; periksa penjualan terkait.', [
                    'NomorPesanan' => $tagihan->NomorPesanan,
                    'StatusAsal' => $asal->value,
                ]);
            }

            $this->audit->Catat('tagihan-qris.status', $tagihan, ['Status' => $asal->value], ['Status' => $tujuan->value, 'Sumber' => $sumber], idTenant: $tagihan->IdTenant);

            return $tagihan;
        });
    }

    /** Menandai `Kedaluwarsa` tagihan `Menunggu` yang lewat batas + tenggang (tanpa kabar gerbang). */
    public function TandaiKedaluwarsa(int $idTagihan): TagihanQris
    {
        return DB::transaction(function () use ($idTagihan): TagihanQris {
            $tagihan = TagihanQris::query()->whereKey($idTagihan)->lockForUpdate()->firstOrFail();

            if ($tagihan->Status !== StatusTagihanQris::Menunggu) {
                return $tagihan;
            }

            $tagihan->Status = StatusTagihanQris::Kedaluwarsa;
            $tagihan->save();
            $this->riwayat->Catat(TagihanQris::JENIS_DOKUMEN, $tagihan->Id, StatusTagihanQris::Menunggu->value, StatusTagihanQris::Kedaluwarsa->value, null, 'Batas waktu lewat');

            return $tagihan;
        });
    }

    private static function UraiJumlah(string $jumlah): ?Uang
    {
        try {
            return Uang::Dari(trim($jumlah));
        } catch (MathException|InvalidArgumentException) {
            return null;
        }
    }
}

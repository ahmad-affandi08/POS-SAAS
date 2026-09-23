<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Tenant\Data\DataBuktiTransfer;
use App\Domain\Tenant\Enum\MetodePembayaranLangganan;
use App\Domain\Tenant\Enum\StatusPembayaranLangganan;
use App\Domain\Tenant\Kueri\RekeningTujuanPlatform;
use App\Domain\Tenant\Model\Langganan;
use App\Domain\Tenant\Model\PembayaranLangganan;
use App\Domain\Tenant\Model\TagihanLangganan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Owner mengunggah bukti transfer untuk tagihan terbuka (P-08 langkah 3, transfer manual Fase 0–1).
 *
 * - Langganan lalu tagihan dikunci; satu tagihan hanya punya satu pembayaran `Menunggu` (klik ganda tidak menggandakan antrean).
 * - Pembayaran sebagian belum didukung: jumlah transfer harus sama dengan total tagihan.
 * - Berkas disimpan di disk privat dengan nama acak per tenant; tidak pernah di folder publik. Bila transaksi gagal,
 *   berkas yang terlanjur tersimpan dihapus lagi.
 */
final class UnggahBuktiTransfer
{
    public function __construct(
        private readonly RekeningTujuanPlatform $rekening,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(string $uuidTagihan, DataBuktiTransfer $data, int $idPengguna, string $namaPengguna, string $emailPengguna): PembayaranLangganan
    {
        $rekening = $this->rekening->Cari($data->kodeRekeningTujuan)
            ?? throw new PelanggaranAturanBisnis('RekeningTidakDikenal', 'Pilih rekening tujuan dari daftar.', 'KodeRekeningTujuan');

        if ($data->tanggalTransfer->toDateString() > now('Asia/Jakarta')->toDateString()) {
            throw new PelanggaranAturanBisnis('TanggalTransferDiMasaDepan', 'Tanggal transfer tidak boleh di masa depan.', 'TanggalTransfer');
        }

        $disk = (string) config('tagihan.DiskBukti');
        $path = null;

        try {
            return DB::transaction(function () use ($uuidTagihan, $data, $idPengguna, $namaPengguna, $emailPengguna, $rekening, $disk, &$path): PembayaranLangganan {
                $awal = TagihanLangganan::query()->where('Uuid', $uuidTagihan)->first()
                    ?? throw new PelanggaranAturanBisnis('TagihanTidakDitemukan', 'Tagihan tidak ditemukan.');
                // Urutan kunci Langganan → Tagihan sama dengan verifikasi & penjadwal tunggakan: bukti yang masuk
                // bersamaan dengan proses penangguhan pasti terlihat olehnya (atau menunggu sampai proses itu selesai).
                Langganan::query()->where('IdTenant', $awal->IdTenant)->lockForUpdate()->first();
                $tagihan = TagihanLangganan::query()->whereKey($awal->Id)->lockForUpdate()->firstOrFail();

                if (! $tagihan->Status->CekTerbuka()) {
                    throw new PelanggaranAturanBisnis('TagihanTidakTerbuka', "Tagihan {$tagihan->Nomor} sudah {$tagihan->Status->AmbilLabel()}.");
                }

                $menunggu = PembayaranLangganan::query()
                    ->where('IdTagihanLangganan', $tagihan->Id)
                    ->where('Status', StatusPembayaranLangganan::Menunggu->value)
                    ->exists();

                if ($menunggu) {
                    throw new PelanggaranAturanBisnis('PembayaranMasihDiverifikasi', 'Bukti transfer tagihan ini sedang diverifikasi. Tunggu hasilnya sebelum mengunggah lagi.');
                }

                if (! Uang::Dari($data->jumlah)->SamaDengan($tagihan->AmbilTotal())) {
                    throw new PelanggaranAturanBisnis(
                        'JumlahTidakSesuai',
                        'Jumlah transfer harus sama dengan total tagihan '.$tagihan->AmbilTotal()->FormatRupiah().'. Bila berbeda, hubungi tim kami.',
                        'Jumlah',
                    );
                }

                $folder = trim((string) config('tagihan.FolderBukti'), '/').'/'.$tagihan->IdTenant;
                $namaBerkas = Str::lower((string) Str::ulid()).'.'.($data->berkas->extension() ?: 'bin');
                $path = $data->berkas->storeAs($folder, $namaBerkas, ['disk' => $disk]) ?: null;

                if ($path === null) {
                    throw new PelanggaranAturanBisnis('BuktiGagalDisimpan', 'Bukti transfer gagal disimpan. Coba unggah lagi.', 'Bukti');
                }

                $pembayaran = PembayaranLangganan::query()->create([
                    'IdTenant' => $tagihan->IdTenant,
                    'IdTagihanLangganan' => $tagihan->Id,
                    'Metode' => MetodePembayaranLangganan::TransferManual,
                    'Status' => StatusPembayaranLangganan::Menunggu,
                    'Jumlah' => $tagihan->Total,
                    'TanggalTransfer' => $data->tanggalTransfer->toDateString(),
                    'BankPengirim' => $data->bankPengirim,
                    'NamaPengirim' => $data->namaPengirim,
                    'KodeRekeningTujuan' => $rekening['Kode'],
                    'BankTujuan' => $rekening['NamaBank'],
                    'NomorRekeningTujuan' => $rekening['NomorRekening'],
                    'PathBukti' => $path,
                    'NamaFileBukti' => Str::limit($data->berkas->getClientOriginalName(), 250, ''),
                    'MimeBukti' => (string) $data->berkas->getMimeType(),
                    'UkuranBukti' => (int) $data->berkas->getSize(),
                    'IdPenggunaPengunggah' => $idPengguna,
                    'EmailPemberitahuan' => $emailPengguna,
                    'NamaPemberitahuan' => $namaPengguna,
                ]);
                $this->audit->Catat('langganan.bukti-transfer-unggah', $pembayaran, nilaiBaru: [
                    'NomorTagihan' => $tagihan->Nomor, 'Jumlah' => $tagihan->Total, 'KodeRekeningTujuan' => $rekening['Kode'],
                ]);

                return $pembayaran;
            });
        } catch (Throwable $galat) {
            if ($path !== null) {
                Storage::disk($disk)->delete($path);
            }

            throw $galat;
        }
    }
}

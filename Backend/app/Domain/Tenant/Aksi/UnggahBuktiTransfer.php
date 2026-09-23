<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Tenant\Data\DataBuktiTransfer;
use App\Domain\Tenant\Enum\MetodePembayaranLangganan;
use App\Domain\Tenant\Enum\StatusPembayaranLangganan;
use App\Domain\Tenant\Kueri\RekeningTujuanPlatform;
use App\Domain\Tenant\Model\PembayaranLangganan;
use App\Domain\Tenant\Model\TagihanLangganan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Owner mengunggah bukti transfer untuk tagihan terbuka (P-08 langkah 3, transfer manual Fase 0–1).
 *
 * - Tagihan dikunci; satu tagihan hanya punya satu pembayaran `Menunggu` (klik ganda tidak menggandakan antrean).
 * - Pembayaran sebagian belum didukung: jumlah transfer harus sama dengan total tagihan.
 * - Berkas disimpan di disk privat dengan nama acak per tenant; tidak pernah di folder publik. Bila transaksi gagal,
 *   berkas yang terlanjur tersimpan dihapus lagi.
 */
final class UnggahBuktiTransfer
{
    public function __construct(private readonly RekeningTujuanPlatform $rekening) {}

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
                $tagihan = TagihanLangganan::query()->where('Uuid', $uuidTagihan)->lockForUpdate()->first()
                    ?? throw new PelanggaranAturanBisnis('TagihanTidakDitemukan', 'Tagihan tidak ditemukan.');

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

                return PembayaranLangganan::query()->create([
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
            });
        } catch (Throwable $galat) {
            if ($path !== null) {
                Storage::disk($disk)->delete($path);
            }

            throw $galat;
        }
    }
}

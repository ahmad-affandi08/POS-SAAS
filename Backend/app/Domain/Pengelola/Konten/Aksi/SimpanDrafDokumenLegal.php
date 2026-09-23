<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Konten\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pengelola\Konten\Data\DataDokumenLegal;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Tenant\Enum\StatusDokumenLegal;
use App\Domain\Tenant\Model\DokumenLegal;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Membuat atau mengubah draf dokumen legal (P-06). Satu jenis hanya punya satu draf (BR-P06.4); versi terbit tidak
 * pernah diubah (BR-P06.1). Nomor versi diberikan saat draf dibuat. Baris-baris jenis yang sama dikunci agar dua
 * permintaan bersamaan tidak membuat dua draf.
 */
final class SimpanDrafDokumenLegal
{
    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    public function Jalankan(PenggunaPengelola $pelaku, DataDokumenLegal $data, ?DokumenLegal $draf = null): DokumenLegal
    {
        try {
            return DB::transaction(function () use ($pelaku, $data, $draf): DokumenLegal {
                $sejenis = DokumenLegal::query()->where('Jenis', $data->jenis->value)->lockForUpdate()->get();

                if ($draf !== null) {
                    $draf = $sejenis->firstWhere('Id', $draf->Id) ?? throw new PelanggaranAturanBisnis('TidakDitemukan', 'Draf tidak ditemukan.');

                    if ($draf->Status !== StatusDokumenLegal::Draf) {
                        throw new PelanggaranAturanBisnis('BR-P06.1', 'Versi yang sudah terbit tidak bisa diubah. Buat draf versi baru.');
                    }
                } elseif ($sejenis->contains(fn (DokumenLegal $dokumen) => $dokumen->Status === StatusDokumenLegal::Draf)) {
                    throw new PelanggaranAturanBisnis('BR-P06.4', "{$data->jenis->AmbilLabel()} sudah punya draf. Ubah draf itu atau hapus dulu.", 'Jenis');
                }

                $nilaiLama = $draf?->only(['Judul', 'RingkasanPerubahan', 'Materiil', 'BerlakuMulai']);
                $draf ??= new DokumenLegal([
                    'Jenis' => $data->jenis,
                    'Versi' => (int) $sejenis->max('Versi') + 1,
                    'Status' => StatusDokumenLegal::Draf,
                ]);
                $isiBerubah = $draf->Isi !== $data->isi;
                $draf->fill([
                    'Judul' => $data->judul,
                    'Isi' => $data->isi,
                    'RingkasanPerubahan' => $data->ringkasanPerubahan,
                    'Materiil' => $data->materiil,
                    'BerlakuMulai' => $data->berlakuMulai,
                ])->save();

                $this->audit->Catat(
                    $nilaiLama === null ? 'legal.draf.buat' : 'legal.draf.ubah',
                    $draf,
                    nilaiLama: $nilaiLama,
                    // Isi dokumen bisa panjang; log mencatat bahwa isinya berubah, versi utuh ada di tabelnya.
                    nilaiBaru: [
                        'Jenis' => $data->jenis->value,
                        'Versi' => $draf->Versi,
                        ...$draf->only(['Judul', 'RingkasanPerubahan', 'Materiil']),
                        'BerlakuMulai' => $data->berlakuMulai,
                        'IsiBerubah' => $isiBerubah,
                    ],
                    idPelaku: $pelaku->Id,
                );

                return $draf;
            });
        } catch (UniqueConstraintViolationException) {
            throw new PelanggaranAturanBisnis('BR-P06.4', 'Draf jenis ini baru saja dibuat anggota lain. Muat ulang halaman.', 'Jenis');
        }
    }
}

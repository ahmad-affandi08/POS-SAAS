<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Integrasi\Penguji;

use App\Domain\Pengelola\Integrasi\Data\HasilUjiKoneksi;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Menulis, membaca, lalu menghapus satu berkas kecil di bucket (P-05). Membuktikan izin tulis & hapus, bukan hanya
 * koneksi.
 */
final class PengujiS3 implements PengujiKoneksi
{
    public function Uji(array $pengaturan, array $kredensial): HasilUjiKoneksi
    {
        try {
            $disk = Storage::build(PenyusunKonfigurasiLaravel::DiskS3($pengaturan, $kredensial) + ['throw' => true]);
            $path = 'uji-koneksi/'.Str::ulid().'.txt';
            $disk->put($path, 'uji koneksi pengelola');
            $isi = $disk->get($path);
            $disk->delete($path);

            return $isi === 'uji koneksi pengelola'
                ? HasilUjiKoneksi::Berhasil('Tulis, baca, dan hapus berkas uji berhasil.')
                : HasilUjiKoneksi::Gagal('Berkas uji terbaca tetapi isinya berbeda.');
        } catch (Throwable $galat) {
            return HasilUjiKoneksi::Gagal('Tidak bisa memakai bucket: '.PenyaringPesan::Saring($galat->getMessage(), $kredensial));
        }
    }
}

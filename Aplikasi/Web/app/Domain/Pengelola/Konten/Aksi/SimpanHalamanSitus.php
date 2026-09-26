<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Konten\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Situs\Layanan\AturanSlugSitus;
use App\Domain\Situs\Layanan\KontenSitusBawaan;
use App\Domain\Situs\Layanan\ValidatorBagianSitus;
use App\Domain\Situs\Model\GambarSitus;
use App\Domain\Situs\Model\HalamanSitus;
use Illuminate\Support\Facades\DB;

/**
 * D-21: membuat halaman situs baru (draf) atau menyimpan draf halaman. Blok diperiksa `ValidatorBagianSitus`; halaman
 * publik tidak berubah sampai diterbitkan. Slug unik, bukan jalur sistem; slug halaman bawaan tidak bisa diganti.
 */
final class SimpanHalamanSitus
{
    public function __construct(
        private readonly ValidatorBagianSitus $validator,
        private readonly PencatatAuditPengelola $audit,
    ) {}

    /**
     * @param  array{Slug: string, Judul: string, JudulSeo?: string|null, DeskripsiSeo?: string|null, UuidGambarOg?: string|null, TampilDiSitemap?: bool, Bagian: mixed}  $data
     */
    public function Jalankan(PenggunaPengelola $pelaku, array $data, ?HalamanSitus $halaman = null): HalamanSitus
    {
        $slug = strtolower(trim($data['Slug']));
        $bagian = $this->validator->Periksa($data['Bagian']);

        if ($halaman !== null && $slug !== $halaman->Slug && array_key_exists($halaman->Slug, KontenSitusBawaan::AmbilHalaman())) {
            throw new PelanggaranAturanBisnis('SlugBawaanTetap', 'Slug halaman bawaan tidak bisa diganti.', 'Slug');
        }

        if ($slug !== HalamanSitus::SLUG_BERANDA && ($galat = AturanSlugSitus::Periksa($slug)) !== null) {
            throw new PelanggaranAturanBisnis('SlugTidakValid', $galat, 'Slug');
        }

        $og = isset($data['UuidGambarOg']) && is_string($data['UuidGambarOg']) && $data['UuidGambarOg'] !== '' ? strtoupper($data['UuidGambarOg']) : null;

        if ($og !== null && ! GambarSitus::query()->where('Uuid', $og)->exists()) {
            throw new PelanggaranAturanBisnis('GambarTidakDikenal', 'Gambar pratinjau tautan tidak ditemukan.', 'UuidGambarOg');
        }

        return DB::transaction(function () use ($pelaku, $data, $halaman, $slug, $bagian, $og): HalamanSitus {
            $kueriSlug = HalamanSitus::query()->where('Slug', $slug);

            if ($halaman !== null) {
                $kueriSlug->whereKeyNot($halaman->Id);
            }

            if ($kueriSlug->exists()) {
                throw new PelanggaranAturanBisnis('SlugSudahDipakai', 'Slug ini sudah dipakai halaman lain.', 'Slug');
            }

            $baru = $halaman === null;
            $halaman ??= new HalamanSitus;
            $lama = $baru ? null : $halaman->only(['Slug', 'Judul', 'JudulSeo', 'DeskripsiSeo']);
            $halaman->fill([
                'Slug' => $slug,
                'Judul' => trim($data['Judul']),
                'JudulSeo' => self::Rapikan($data['JudulSeo'] ?? null),
                'DeskripsiSeo' => self::Rapikan($data['DeskripsiSeo'] ?? null),
                'UuidGambarOg' => $og,
                'TampilDiSitemap' => (bool) ($data['TampilDiSitemap'] ?? true),
                'BagianDraf' => $bagian,
                'IdPenggunaPengelolaPengubah' => $pelaku->Id,
            ])->save();

            $this->audit->Catat($baru ? 'situs.halaman.buat' : 'situs.halaman.ubah', $halaman, nilaiLama: $lama, nilaiBaru: [
                'Slug' => $halaman->Slug,
                'Judul' => $halaman->Judul,
                'JumlahBlok' => count($bagian),
            ]);

            return $halaman;
        });
    }

    private static function Rapikan(mixed $teks): ?string
    {
        $teks = is_string($teks) ? trim($teks) : '';

        return $teks === '' ? null : $teks;
    }
}

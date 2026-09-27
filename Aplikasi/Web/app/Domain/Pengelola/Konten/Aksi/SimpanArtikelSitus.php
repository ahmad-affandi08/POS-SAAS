<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Konten\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Situs\Model\ArtikelSitus;
use App\Domain\Situs\Model\GambarSitus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Situs bagian B2: membuat (draf) atau menyimpan artikel blog. Slug satu segmen & unik (kosong = dari judul); gambar
 * sampul harus ada di pustaka situs. Artikel terbit langsung berubah di situs setelah disimpan.
 */
final class SimpanArtikelSitus
{
    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    /**
     * @param  array{Slug: string|null, Judul: string, Ringkasan: string|null, Isi: string, Kategori: string|null, NamaPenulis: string|null, UuidGambarSampul: string|null, JudulSeo: string|null, DeskripsiSeo: string|null}  $data
     */
    public function Jalankan(PenggunaPengelola $pelaku, array $data, ?ArtikelSitus $artikel = null): ArtikelSitus
    {
        $slug = strtolower(trim((string) $data['Slug']));
        $slug = $slug === '' ? Str::slug($data['Judul']) : $slug;
        $slug = substr($slug, 0, 120);

        if (preg_match('#^'.ArtikelSitus::POLA_SLUG.'$#', $slug) !== 1) {
            throw new PelanggaranAturanBisnis('SlugTidakValid', 'Slug hanya huruf kecil, angka, dan tanda hubung (misal "tips-kasir-kafe").', 'Slug');
        }

        $sampul = $data['UuidGambarSampul'] !== null && $data['UuidGambarSampul'] !== '' ? strtoupper($data['UuidGambarSampul']) : null;

        if ($sampul !== null && ! GambarSitus::query()->where('Uuid', $sampul)->exists()) {
            throw new PelanggaranAturanBisnis('GambarTidakDikenal', 'Gambar sampul tidak ditemukan di pustaka.', 'UuidGambarSampul');
        }

        return DB::transaction(function () use ($pelaku, $data, $artikel, $slug, $sampul): ArtikelSitus {
            $kueriSlug = ArtikelSitus::query()->where('Slug', $slug);

            if ($artikel !== null) {
                $kueriSlug->whereKeyNot($artikel->Id);
            }

            if ($kueriSlug->exists()) {
                throw new PelanggaranAturanBisnis('SlugSudahDipakai', 'Slug ini sudah dipakai artikel lain.', 'Slug');
            }

            $baru = $artikel === null;
            $artikel ??= new ArtikelSitus;
            $lama = $baru ? null : $artikel->only(['Slug', 'Judul', 'Kategori']);
            $artikel->fill([
                'Slug' => $slug,
                'Judul' => trim($data['Judul']),
                'Ringkasan' => self::Rapikan($data['Ringkasan']),
                'Isi' => trim($data['Isi']),
                'Kategori' => self::Rapikan($data['Kategori']),
                'NamaPenulis' => self::Rapikan($data['NamaPenulis']),
                'UuidGambarSampul' => $sampul,
                'JudulSeo' => self::Rapikan($data['JudulSeo']),
                'DeskripsiSeo' => self::Rapikan($data['DeskripsiSeo']),
                'IdPenggunaPengelolaPengubah' => $pelaku->Id,
            ])->save();

            $this->audit->Catat($baru ? 'situs.artikel.buat' : 'situs.artikel.ubah', $artikel, nilaiLama: $lama, nilaiBaru: [
                'Slug' => $artikel->Slug,
                'Judul' => $artikel->Judul,
                'Kategori' => $artikel->Kategori,
            ]);

            return $artikel;
        });
    }

    private static function Rapikan(?string $teks): ?string
    {
        $teks = trim((string) $teks);

        return $teks === '' ? null : $teks;
    }
}

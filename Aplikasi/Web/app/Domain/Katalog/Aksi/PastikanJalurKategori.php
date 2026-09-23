<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Model\Kategori;
use App\Domain\Tenant\Layanan\PenguncianTenant;
use Illuminate\Support\Facades\DB;

/**
 * F-03 impor: kategori untuk jalur "Minuman > Kopi" (sudah dipecah per tingkat). Tiap tingkat dicocokkan dengan nama
 * sub-kategori tanpa beda huruf besar/kecil; tingkat yang belum ada dibuat bila `bolehBuat`, selain itu hasilnya
 * null. Maks. `katalog.Kategori.MaksimalKedalaman` tingkat. Jalur kosong = null.
 */
final class PastikanJalurKategori
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenguncianTenant $penguncian,
        private readonly PencatatAudit $audit,
    ) {}

    /**
     * @param  list<string>  $jalur
     */
    public function Jalankan(array $jalur, bool $bolehBuat): ?Kategori
    {
        $jalur = array_values(array_filter(array_map(fn (string $nama): string => trim($nama), $jalur), fn (string $nama): bool => $nama !== ''));

        if ($jalur === []) {
            return null;
        }

        $maksimal = (int) config('katalog.Kategori.MaksimalKedalaman', 3);

        if (count($jalur) > $maksimal) {
            throw new PelanggaranAturanBisnis('KategoriTerlaluDalam', "Kategori maksimal {$maksimal} tingkat, misal Minuman > Kopi > Kopi Susu.", 'Kategori');
        }

        return DB::transaction(function () use ($jalur, $bolehBuat): ?Kategori {
            $this->penguncian->Kunci($this->konteks->Wajib());
            $induk = null;

            foreach ($jalur as $nama) {
                if (mb_strlen($nama) > 60) {
                    throw new PelanggaranAturanBisnis('NamaTidakValid', 'Nama kategori maksimal 60 karakter.', 'Kategori');
                }

                $kategori = Kategori::query()
                    ->when($induk === null, fn ($kueri) => $kueri->whereNull('IdInduk'), fn ($kueri) => $kueri->where('IdInduk', $induk?->Id))
                    ->whereRaw('LOWER(Nama) = ?', [mb_strtolower($nama)])
                    ->orderBy('Id')
                    ->first();

                if ($kategori === null) {
                    if (! $bolehBuat) {
                        return null;
                    }

                    $kategori = Kategori::query()->create(['Nama' => $nama, 'IdInduk' => $induk?->Id, 'Urutan' => 0]);
                    $this->audit->Catat('kategori.buat', $kategori, null, $kategori->only(['Nama', 'IdInduk', 'Urutan']));
                }

                $induk = $kategori;
            }

            return $induk;
        });
    }
}

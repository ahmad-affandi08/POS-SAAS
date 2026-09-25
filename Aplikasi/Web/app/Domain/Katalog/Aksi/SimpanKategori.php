<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Data\DataKategori;
use App\Domain\Katalog\Layanan\PohonKategoriTenant;
use App\Domain\Katalog\Model\Kategori;
use App\Domain\Tenant\Layanan\PenguncianTenant;
use Illuminate\Support\Facades\DB;

/**
 * F-03: buat atau ubah kategori bertingkat (maks. `katalog.Kategori.MaksimalKedalaman` = 3 tingkat).
 * - Tanpa siklus (induk bukan dirinya atau turunannya), nama unik di antara saudara tanpa beda huruf besar/kecil.
 * - Stasiun dapur (F-10a) opsional: item kategori tanpa stasiun dirutekan ke stasiun bawaan.
 * - Urutan kunci: Tenant → baris kategori. Kirim ganda form buat: kiriman kedua ditolak `KategoriGanda`.
 */
final class SimpanKategori
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenguncianTenant $penguncian,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(?Kategori $kategori, DataKategori $data): Kategori
    {
        return DB::transaction(function () use ($kategori, $data): Kategori {
            $this->penguncian->Kunci($this->konteks->Wajib());
            $kategori = $kategori === null ? null : Kategori::query()->whereKey($kategori->Id)->lockForUpdate()->firstOrFail();
            $nama = trim($data->nama);

            if ($nama === '' || mb_strlen($nama) > 60) {
                throw new PelanggaranAturanBisnis('NamaTidakValid', 'Nama kategori wajib diisi, maksimal 60 karakter.', 'Nama');
            }

            $this->PastikanPosisi($kategori, $data->idInduk);
            $this->PastikanNamaUnik($kategori, $data->idInduk, $nama);

            $kolom = ['Nama', 'IdInduk', 'Urutan', 'IdStasiunDapur'];
            $lama = $kategori?->only($kolom);
            $kategori ??= new Kategori;
            $kategori->fill(['Nama' => $nama, 'IdInduk' => $data->idInduk, 'Urutan' => max(0, $data->urutan)]);

            if ($data->aturStasiun) {
                $kategori->IdStasiunDapur = $data->idStasiunDapur;
            }

            $kategori->save();

            $this->audit->Catat($lama === null ? 'kategori.buat' : 'kategori.ubah', $kategori, $lama, $kategori->only($kolom));

            return $kategori;
        });
    }

    private function PastikanPosisi(?Kategori $kategori, ?int $idInduk): void
    {
        if ($idInduk === null) {
            return;
        }

        if (! Kategori::query()->whereKey($idInduk)->exists()) {
            throw new PelanggaranAturanBisnis('KategoriSiklus', 'Kategori induk tidak ditemukan.', 'UuidInduk');
        }

        $pohon = PohonKategoriTenant::Muat();

        if ($kategori !== null && in_array($idInduk, $pohon->AmbilIdDenganTurunan($kategori->Id), true)) {
            throw new PelanggaranAturanBisnis('KategoriSiklus', 'Kategori tidak bisa dipindah ke bawah dirinya sendiri atau sub-kategorinya.', 'UuidInduk');
        }

        $maksimal = (int) config('katalog.Kategori.MaksimalKedalaman', 3);
        $tinggi = $kategori === null ? 1 : $pohon->HitungTinggi($kategori->Id);

        if ($pohon->HitungKedalaman($idInduk) + $tinggi > $maksimal) {
            throw new PelanggaranAturanBisnis('KategoriTerlaluDalam', "Kategori maksimal {$maksimal} tingkat, misal Minuman › Kopi › Kopi Susu.", 'UuidInduk');
        }
    }

    private function PastikanNamaUnik(?Kategori $kategori, ?int $idInduk, string $nama): void
    {
        $ganda = Kategori::query()
            ->when($idInduk === null, fn ($kueri) => $kueri->whereNull('IdInduk'), fn ($kueri) => $kueri->where('IdInduk', $idInduk))
            ->when($kategori !== null, fn ($kueri) => $kueri->whereKeyNot($kategori?->Id))
            ->whereRaw('LOWER(Nama) = ?', [mb_strtolower($nama)])
            ->exists();

        if ($ganda) {
            throw new PelanggaranAturanBisnis('KategoriGanda', "Kategori {$nama} sudah ada di tingkat ini.", 'Nama');
        }
    }
}

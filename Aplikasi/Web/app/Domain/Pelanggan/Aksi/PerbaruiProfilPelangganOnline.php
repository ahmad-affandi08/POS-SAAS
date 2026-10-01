<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pelanggan\Model\Pelanggan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * F-17 bagian 3: pembeli yang sudah masuk mengubah profilnya sendiri (nama, email, persetujuan pemasaran). Nomor HP
 * tidak bisa diubah di sini karena nomor itulah identitas akunnya. Tanggal lahir hanya bisa **diisi sekali** bila
 * masih kosong: promo ulang tahun (F-16c) memakainya, jadi mengubahnya sendiri setiap hari tidak boleh; koreksi lewat
 * toko (back-office). Audit `pelanggan.ubah-online` hanya mencatat kolom yang berubah.
 */
final class PerbaruiProfilPelangganOnline
{
    public function __construct(private readonly PencatatAudit $audit) {}

    public function Jalankan(Pelanggan $pelanggan, string $nama, ?string $email, ?CarbonImmutable $tanggalLahir, bool $setujuPemasaran): Pelanggan
    {
        return DB::transaction(function () use ($pelanggan, $nama, $email, $tanggalLahir, $setujuPemasaran): Pelanggan {
            $terkunci = Pelanggan::query()->whereKey($pelanggan->Id)->lockForUpdate()->firstOrFail();
            $lahirLama = $terkunci->TanggalLahir?->toDateString();
            $lahirBaru = $tanggalLahir?->toDateString();

            if ($lahirLama !== null && $lahirBaru !== $lahirLama) {
                throw new PelanggaranAturanBisnis('TanggalLahirTerkunci', 'Tanggal lahir yang sudah tersimpan hanya bisa diubah oleh toko.', 'TanggalLahir');
            }

            $email = $email === null || trim($email) === '' ? null : trim($email);
            $isian = ['Nama' => trim($nama), 'Email' => $email, 'TanggalLahir' => $lahirLama ?? $lahirBaru, 'SetujuPemasaran' => $setujuPemasaran];
            $lama = ['Nama' => $terkunci->Nama, 'Email' => $terkunci->Email, 'TanggalLahir' => $lahirLama, 'SetujuPemasaran' => $terkunci->SetujuPemasaran];
            $berubah = array_keys(array_filter($isian, fn (mixed $nilai, string $kolom): bool => $nilai !== $lama[$kolom], ARRAY_FILTER_USE_BOTH));

            if ($berubah === []) {
                return $terkunci;
            }

            $terkunci->fill($isian)->save();
            $kunci = array_flip($berubah);
            $this->audit->Catat('pelanggan.ubah-online', $terkunci, array_intersect_key($lama, $kunci), array_intersect_key($isian, $kunci));

            return $terkunci;
        });
    }
}

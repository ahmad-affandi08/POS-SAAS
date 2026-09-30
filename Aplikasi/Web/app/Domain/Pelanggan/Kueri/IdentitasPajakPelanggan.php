<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Kueri;

use App\Domain\Pelanggan\Model\Pelanggan;

/**
 * Identitas pajak pembeli untuk dokumen pajak (Faktur Pajak Coretax, PRD v3.11–3.12): NPWP/NIK (didekripsi di sini, hanya
 * dipakai untuk membuat berkas ekspor) beserta nama & alamat sesuai DJP. Kueri publik domain Pelanggan; domain lain tidak
 * membaca tabel `Pelanggan` langsung.
 */
final class IdentitasPajakPelanggan
{
    /**
     * @param  list<int>  $idPelanggan
     * @return array<int, array{Nama: string, Alamat: string|null, Email: string|null, Npwp: string|null, Nik: string|null, NamaNpwp: string|null, AlamatNpwp: string|null}>
     */
    public function AmbilBanyak(array $idPelanggan): array
    {
        $idPelanggan = array_values(array_unique($idPelanggan));

        if ($idPelanggan === []) {
            return [];
        }

        $hasil = [];

        foreach (Pelanggan::query()->whereIn('Id', $idPelanggan)->get() as $p) {
            $hasil[$p->Id] = [
                'Nama' => $p->Nama,
                'Alamat' => $p->Alamat,
                'Email' => $p->Email,
                'Npwp' => $p->Npwp,
                'Nik' => $p->Nik,
                'NamaNpwp' => $p->NamaNpwp,
                'AlamatNpwp' => $p->AlamatNpwp,
            ];
        }

        return $hasil;
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Kasir\Kueri;

use App\Domain\Akuntansi\Kueri\DaftarAkunPilihan;
use App\Domain\Kasir\Aksi\SimpanKategoriKas;
use App\Domain\Kasir\Enum\JenisKategoriKas;
use App\Domain\Kasir\Model\KategoriKas;

/**
 * Kategori kas tenant untuk halaman back-office (F-06): per jenis, urut `Urutan` lalu nama, dengan akun lawannya,
 * plus opsi akun per jenis untuk form.
 */
final class DaftarKategoriKas
{
    public function __construct(private readonly DaftarAkunPilihan $akun) {}

    /**
     * @return array{Kategori: list<array<string, mixed>>, OpsiAkun: array<string, list<array{Uuid: string, Kode: string, Nama: string, Jenis: string}>>}
     */
    public function Ambil(): array
    {
        $kategori = KategoriKas::query()->orderBy('Jenis')->orderBy('Urutan')->orderBy('Nama')->get();
        $akun = $this->akun->AmbilBanyak(array_values($kategori->pluck('IdAkun')->all()));
        $opsi = [];

        foreach (JenisKategoriKas::cases() as $jenis) {
            $opsi[$jenis->value] = array_map(
                fn (array $a): array => ['Uuid' => $a['Uuid'], 'Kode' => $a['Kode'], 'Nama' => $a['Nama'], 'Jenis' => $a['Jenis']],
                $this->akun->Ambil(SimpanKategoriKas::AmbilTipeAkunBoleh($jenis)),
            );
        }

        return [
            'Kategori' => array_values($kategori->map(fn (KategoriKas $k): array => [
                'Uuid' => $k->Uuid,
                'Nama' => $k->Nama,
                'Jenis' => $k->Jenis->value,
                'LabelJenis' => $k->Jenis->AmbilLabel(),
                'UuidAkun' => $akun[$k->IdAkun]['Uuid'] ?? null,
                'Akun' => isset($akun[$k->IdAkun]) ? "{$akun[$k->IdAkun]['Kode']} {$akun[$k->IdAkun]['Nama']}" : null,
                'Aktif' => $k->Aktif,
                'Urutan' => $k->Urutan,
            ])->all()),
            'OpsiAkun' => $opsi,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Katalog;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Katalog\Data\DataBatasStok;
use App\Domain\Organisasi\Kueri\GudangTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/**
 * Batas stok produk F-03 (E.4): `{ Baris: {UuidGudang, StokMinimum, StokMaksimum}[] }`; "" = tidak diatur.
 */
final class SimpanBatasStokPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Baris' => ['present', 'array', 'max:200'],
            'Baris.*.UuidGudang' => ['required', 'ulid'],
            'Baris.*.StokMinimum' => ['nullable', 'string', SimpanProdukPermintaan::POLA_KUANTITAS],
            'Baris.*.StokMaksimum' => ['nullable', 'string', SimpanProdukPermintaan::POLA_KUANTITAS],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'Baris.*.StokMinimum.regex' => 'Stok minimum berupa angka, maksimal 4 angka desimal.',
            'Baris.*.StokMaksimum.regex' => 'Stok maksimum berupa angka, maksimal 4 angka desimal.',
        ];
    }

    /**
     * @return list<DataBatasStok>
     *
     * @throws ValidationException
     */
    public function AmbilData(): array
    {
        $idGudang = array_column(app(GudangTenant::class)->AmbilAktif(), 'Id', 'Uuid');
        $hasil = [];

        foreach (array_values((array) $this->input('Baris', [])) as $i => $baris) {
            $uuid = (string) ($baris['UuidGudang'] ?? '');

            if (! isset($idGudang[$uuid])) {
                throw ValidationException::withMessages(["Baris.{$i}.UuidGudang" => 'Lokasi stok tidak ditemukan atau sudah diarsipkan.']);
            }

            $hasil[] = new DataBatasStok(
                $idGudang[$uuid],
                self::KeKuantitas($baris['StokMinimum'] ?? null),
                self::KeKuantitas($baris['StokMaksimum'] ?? null),
            );
        }

        return $hasil;
    }

    private static function KeKuantitas(mixed $nilai): ?Kuantitas
    {
        return is_string($nilai) && trim($nilai) !== '' ? Kuantitas::Dari(trim($nilai)) : null;
    }
}

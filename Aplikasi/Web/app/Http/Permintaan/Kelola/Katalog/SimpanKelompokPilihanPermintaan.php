<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Katalog;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Pilihan\Data\DataKelompokPilihan;
use App\Domain\Katalog\Pilihan\Data\DataPilihan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Isian kelompok pilihan (tipe FE `FormKelompokPilihan`, F-03 E.9). Uang & jumlah berupa string desimal. Bahan
 * pilihan dicari lewat Uuid di tenant aktif; bahan tenant lain = tidak ditemukan.
 */
final class SimpanKelompokPilihanPermintaan extends FormRequest
{
    /** @var array<string, int> */
    private array $idProduk = [];

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'Nama' => ['required', 'string', 'max:60'],
            'MinimalPilih' => ['required', 'integer', 'min:0', 'max:255'],
            'MaksimalPilih' => ['required', 'integer', 'min:0', 'max:255'],
            'Urutan' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'Pilihan' => ['present', 'array'],
            'Pilihan.*.Uuid' => ['nullable', 'ulid'],
            'Pilihan.*.Nama' => ['required', 'string', 'max:60'],
            'Pilihan.*.Harga' => ['required', 'regex:/^\d{1,16}(\.\d{1,2})?$/'],
            'Pilihan.*.Aktif' => ['required', 'boolean'],
            'Pilihan.*.UuidProdukBahan' => ['nullable', 'ulid'],
            'Pilihan.*.Jumlah' => ['nullable', 'regex:/^\d{1,14}(\.\d{1,4})?$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'Pilihan.*.Harga.regex' => 'Harga berupa angka, maksimal 2 desimal.',
            'Pilihan.*.Jumlah.regex' => 'Jumlah berupa angka, maksimal 4 desimal.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $uuid = [];

            foreach ($this->AmbilBaris() as $i => $baris) {
                if (is_string($baris['UuidProdukBahan'] ?? null)) {
                    $uuid[$i] = $baris['UuidProdukBahan'];
                }
            }

            /** @var array<string, int> $peta */
            $peta = Produk::query()->whereIn('Uuid', array_values($uuid))->pluck('Id', 'Uuid')->all();
            $this->idProduk = $peta;

            foreach ($uuid as $i => $isi) {
                if (! isset($peta[$isi])) {
                    $validator->errors()->add("Pilihan.{$i}.UuidProdukBahan", 'Bahan tidak ditemukan.');
                }
            }
        });
    }

    public function AmbilData(bool $bolehUbahHarga): DataKelompokPilihan
    {
        $pilihan = [];

        foreach ($this->AmbilBaris() as $baris) {
            $uuidBahan = $baris['UuidProdukBahan'] ?? null;
            $jumlah = $baris['Jumlah'] ?? null;
            $pilihan[] = new DataPilihan(
                uuid: is_string($baris['Uuid'] ?? null) ? $baris['Uuid'] : null,
                nama: (string) ($baris['Nama'] ?? ''),
                harga: Uang::Dari((string) ($baris['Harga'] ?? '0')),
                aktif: filter_var($baris['Aktif'] ?? false, FILTER_VALIDATE_BOOLEAN),
                idProdukBahan: is_string($uuidBahan) ? ($this->idProduk[$uuidBahan] ?? null) : null,
                jumlah: is_scalar($jumlah) && (string) $jumlah !== '' ? Kuantitas::Dari((string) $jumlah) : null,
            );
        }

        return new DataKelompokPilihan(
            nama: $this->string('Nama')->toString(),
            minimalPilih: $this->integer('MinimalPilih'),
            maksimalPilih: $this->integer('MaksimalPilih'),
            urutan: $this->integer('Urutan'),
            pilihan: $pilihan,
            bolehUbahHarga: $bolehUbahHarga,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function AmbilBaris(): array
    {
        $baris = $this->input('Pilihan', []);

        return is_array($baris) ? array_values(array_filter($baris, 'is_array')) : [];
    }
}

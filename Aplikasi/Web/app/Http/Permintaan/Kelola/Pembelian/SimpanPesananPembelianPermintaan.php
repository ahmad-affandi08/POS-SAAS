<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Pembelian;

use App\Domain\Pembelian\Aksi\SimpanPesananPembelian;
use App\Domain\Pembelian\Data\DataBarisPesananPembelian;
use App\Domain\Pembelian\Data\DataPesananPembelian;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/** Isian draf pesanan pembelian (F-04 fase 1); lokasi stok diubah ke Id oleh kontroler (batas outlet). */
final class SimpanPesananPembelianPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'UuidPemasok' => ['required', 'string', 'ulid'],
            'UuidGudang' => ['required', 'string', 'ulid'],
            'Tanggal' => ['required', 'date_format:Y-m-d'],
            'PerkiraanTiba' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:Tanggal'],
            'TerminHari' => ['nullable', 'integer', 'min:0', 'max:365'],
            'Ongkir' => ['nullable', 'string', AturanPembelian::UANG],
            'Catatan' => ['nullable', 'string', 'max:500'],
            'Baris' => ['required', 'array', 'min:1', 'max:'.SimpanPesananPembelian::MAKS_BARIS],
            'Baris.*.UuidProduk' => ['required', 'string', 'ulid'],
            'Baris.*.UuidProdukSatuan' => ['nullable', 'string', 'ulid'],
            'Baris.*.Jumlah' => ['required', 'string', AturanPembelian::JUMLAH],
            'Baris.*.Harga' => ['required', 'string', AturanPembelian::UANG],
            'Baris.*.Diskon' => ['nullable', 'string', AturanPembelian::UANG],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return AturanPembelian::Pesan();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['UuidPemasok' => 'pemasok', 'UuidGudang' => 'lokasi stok', 'Tanggal' => 'tanggal', 'PerkiraanTiba' => 'perkiraan tiba', 'Baris' => 'barang', 'Baris.*.Jumlah' => 'jumlah', 'Baris.*.Harga' => 'harga', 'Baris.*.Diskon' => 'diskon', 'Ongkir' => 'ongkir'];
    }

    public function AmbilData(int $idGudang, int $idPengguna): DataPesananPembelian
    {
        /** @var list<array<string, mixed>> $baris */
        $baris = array_values((array) $this->validated('Baris'));

        return new DataPesananPembelian(
            (string) $this->validated('UuidPemasok'),
            $idGudang,
            AturanPembelian::Tanggal($this->validated('Tanggal')) ?? CarbonImmutable::today(),
            AturanPembelian::Tanggal($this->validated('PerkiraanTiba')),
            $this->validated('TerminHari') === null ? null : (int) $this->validated('TerminHari'),
            AturanPembelian::Uang($this->validated('Ongkir')),
            AturanPembelian::Teks($this->validated('Catatan')),
            array_map(fn (array $b): DataBarisPesananPembelian => new DataBarisPesananPembelian(
                (string) $b['UuidProduk'],
                AturanPembelian::Teks($b['UuidProdukSatuan'] ?? null),
                AturanPembelian::Jumlah($b['Jumlah']),
                AturanPembelian::Uang($b['Harga']),
                AturanPembelian::Uang($b['Diskon'] ?? null),
            ), $baris),
            $idPengguna,
        );
    }
}

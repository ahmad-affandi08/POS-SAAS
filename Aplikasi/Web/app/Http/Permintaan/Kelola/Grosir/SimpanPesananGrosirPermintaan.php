<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Grosir;

use App\Domain\Penjualan\Data\DataBarisPesananGrosir;
use App\Domain\Penjualan\Data\DataPesananGrosir;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Isian draf SO grosir (F-12, §9.7). **Harga tidak ada di sini**: server mengambilnya dari price engine, sehingga
 * harga di dokumen tidak bisa dikarang dari peramban. Outlet diubah ke Id oleh kontroler (batas outlet pelaku).
 */
final class SimpanPesananGrosirPermintaan extends FormRequest
{
    public const MAKS_BARIS = 200;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'UuidPelanggan' => ['required', 'string', 'ulid'],
            'UuidOutlet' => ['required', 'string', 'ulid'],
            'Tanggal' => ['required', 'date_format:Y-m-d'],
            'TanggalKirimDiminta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:Tanggal'],
            'Catatan' => ['nullable', 'string', 'max:500'],
            'Baris' => ['required', 'array', 'min:1', 'max:'.self::MAKS_BARIS],
            'Baris.*.UuidProduk' => ['required', 'string', 'ulid'],
            'Baris.*.UuidProdukSatuan' => ['required', 'string', 'ulid'],
            'Baris.*.Jumlah' => ['required', 'string', AturanGrosir::JUMLAH],
            'Baris.*.Diskon' => ['nullable', 'string', AturanGrosir::UANG],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return AturanGrosir::Pesan();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'UuidPelanggan' => 'pelanggan',
            'UuidOutlet' => 'outlet',
            'Tanggal' => 'tanggal',
            'TanggalKirimDiminta' => 'tanggal kirim diminta',
            'Baris' => 'barang',
            'Baris.*.Jumlah' => 'jumlah',
            'Baris.*.Diskon' => 'diskon',
        ];
    }

    public function AmbilData(int $idOutlet): DataPesananGrosir
    {
        /** @var list<array<string, mixed>> $baris */
        $baris = array_values((array) $this->validated('Baris'));

        return new DataPesananGrosir(
            uuidPelanggan: (string) $this->validated('UuidPelanggan'),
            idOutlet: $idOutlet,
            tanggal: AturanGrosir::Tanggal($this->validated('Tanggal')) ?? CarbonImmutable::today(),
            baris: array_map(fn (array $b): DataBarisPesananGrosir => new DataBarisPesananGrosir(
                (string) $b['UuidProduk'],
                (string) $b['UuidProdukSatuan'],
                AturanGrosir::Jumlah($b['Jumlah']),
                AturanGrosir::Uang($b['Diskon'] ?? null),
            ), $baris),
            tanggalKirimDiminta: AturanGrosir::Tanggal($this->validated('TanggalKirimDiminta')),
            catatan: AturanGrosir::Teks($this->validated('Catatan')),
        );
    }
}

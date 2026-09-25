<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Pembelian;

use App\Domain\Pembelian\Data\DataBarisFakturPembelian;
use App\Domain\Pembelian\Data\DataFakturPembelian;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/** Isian faktur pembelian (F-04 fase 1): GRN terpilih, harga & diskon faktur per baris GRN, ongkir, jatuh tempo. */
final class SimpanFakturPembelianPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'UuidPemasok' => ['required', 'string', 'ulid'],
            'NomorFakturPemasok' => ['required', 'string', 'max:60'],
            'Tanggal' => ['required', 'date_format:Y-m-d'],
            'JatuhTempo' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:Tanggal'],
            'UuidPenerimaan' => ['required', 'array', 'min:1', 'max:50'],
            'UuidPenerimaan.*' => ['string', 'ulid'],
            'Ongkir' => ['nullable', 'string', AturanPembelian::UANG],
            'Catatan' => ['nullable', 'string', 'max:500'],
            'Lampiran' => AturanPembelian::Lampiran(),
            'Baris' => ['nullable', 'array', 'max:1000'],
            'Baris.*.IdBarisPenerimaan' => ['required', 'integer', 'min:1'],
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
        return ['UuidPemasok' => 'pemasok', 'NomorFakturPemasok' => 'nomor faktur pemasok', 'Tanggal' => 'tanggal faktur', 'JatuhTempo' => 'jatuh tempo', 'UuidPenerimaan' => 'penerimaan barang', 'Ongkir' => 'ongkir', 'Lampiran' => 'lampiran'];
    }

    public function AmbilData(int $idPengguna): DataFakturPembelian
    {
        /** @var list<array<string, mixed>> $baris */
        $baris = array_values((array) ($this->validated('Baris') ?? []));
        $berkas = $this->file('Lampiran');

        return new DataFakturPembelian(
            (string) $this->validated('UuidPemasok'),
            (string) $this->validated('NomorFakturPemasok'),
            AturanPembelian::Tanggal($this->validated('Tanggal')) ?? CarbonImmutable::today(),
            AturanPembelian::Tanggal($this->validated('JatuhTempo')),
            array_values(array_map('strval', (array) $this->validated('UuidPenerimaan'))),
            array_map(fn (array $b): DataBarisFakturPembelian => new DataBarisFakturPembelian(
                (int) $b['IdBarisPenerimaan'],
                AturanPembelian::Uang($b['Harga']),
                AturanPembelian::Uang($b['Diskon'] ?? null),
            ), $baris),
            AturanPembelian::UangAtauNull($this->validated('Ongkir')),
            AturanPembelian::Teks($this->validated('Catatan')),
            $berkas instanceof UploadedFile ? $berkas : null,
            $idPengguna,
        );
    }
}

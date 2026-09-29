<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Grosir;

use App\Domain\Penjualan\Data\DataFakturPenjualan;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Isian faktur penjualan grosir (BR-12.4). Angkanya tidak dikirim klien: seluruhnya dijumlahkan dari surat jalan yang
 * dipilih. Batas satu pelanggan/outlet/bulan kalender & tarif seragam diputuskan Aksinya, bukan formulir ini.
 */
final class BuatFakturPenjualanPermintaan extends FormRequest
{
    public const MAKS_SURAT_JALAN = 100;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'UuidSuratJalan' => ['required', 'array', 'min:1', 'max:'.self::MAKS_SURAT_JALAN],
            'UuidSuratJalan.*' => ['required', 'string', 'ulid'],
            'Tanggal' => ['required', 'date_format:Y-m-d'],
            'NomorFakturPajak' => ['nullable', 'string', 'max:30'],
            'Catatan' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['UuidSuratJalan' => 'surat jalan', 'Tanggal' => 'tanggal', 'NomorFakturPajak' => 'nomor Faktur Pajak'];
    }

    public function AmbilData(): DataFakturPenjualan
    {
        /** @var list<string> $uuid */
        $uuid = array_values(array_map('strval', (array) $this->validated('UuidSuratJalan')));

        return new DataFakturPenjualan(
            uuidSuratJalan: $uuid,
            tanggal: AturanGrosir::Tanggal($this->validated('Tanggal')) ?? CarbonImmutable::today(),
            nomorFakturPajak: AturanGrosir::Teks($this->validated('NomorFakturPajak')),
            catatan: AturanGrosir::Teks($this->validated('Catatan')),
        );
    }
}

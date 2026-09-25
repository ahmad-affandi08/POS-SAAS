<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Persediaan;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Persediaan\Data\DataTerimaTransfer;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi penerimaan transfer stok (F-05b): tanggal terima dan jumlah diterima per baris (`Urutan`), satuan dasar.
 * Jumlah 0 = baris itu tidak diterima pada penerimaan ini.
 */
final class TerimaTransferStokPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Tanggal' => ['required', 'date_format:Y-m-d'],
            'Baris' => ['required', 'array', 'min:1', 'max:'.(int) config('persediaan.Dokumen.MaksimalBaris', 500)],
            'Baris.*.Urutan' => ['required', 'integer', 'min:1'],
            'Baris.*.Jumlah' => ['required', SimpanStokAwalPermintaan::POLA_JUMLAH],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'Baris.required' => 'Isi jumlah diterima minimal satu barang.',
            'Baris.*.Jumlah.regex' => 'Jumlah diterima berupa angka tanpa titik ribuan, maksimal 4 angka desimal.',
        ];
    }

    public function AmbilTanggal(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', (string) $this->validated('Tanggal')) ?: CarbonImmutable::today();
    }

    /**
     * @return list<DataTerimaTransfer>
     */
    public function AmbilBaris(): array
    {
        /** @var list<array<string, mixed>> $baris */
        $baris = array_values((array) $this->validated('Baris'));

        return array_map(fn (array $b): DataTerimaTransfer => new DataTerimaTransfer((int) $b['Urutan'], Kuantitas::Dari((string) $b['Jumlah'])), $baris);
    }
}

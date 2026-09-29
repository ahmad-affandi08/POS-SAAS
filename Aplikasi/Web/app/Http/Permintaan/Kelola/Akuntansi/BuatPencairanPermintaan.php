<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Akuntansi;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Penjualan\Data\DataPencairan;
use App\Domain\Penjualan\Kueri\PembayaranBelumDicairkan;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Isian pencairan dana non-tunai (F-08, BR-08.4). Yang dikirim klien hanya **jumlah yang masuk rekening** dan daftar
 * pembayaran yang dicairkan; potongan platformnya selalu dihitung server sebagai selisih, sehingga tidak ada dokumen
 * yang angkanya tidak menjelaskan dirinya sendiri.
 */
final class BuatPencairanPermintaan extends FormRequest
{
    private const UANG = 'regex:/^\d{1,16}(\.\d{1,2})?$/';

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'UuidMetodePembayaran' => ['required', 'string', 'ulid'],
            'UuidOutlet' => ['required', 'string', 'ulid'],
            'UuidAkunTujuan' => ['required', 'string', 'ulid'],
            'Tanggal' => ['required', 'date_format:Y-m-d'],
            'JumlahBersih' => ['required', 'string', self::UANG],
            'Referensi' => ['nullable', 'string', 'max:100'],
            'Catatan' => ['nullable', 'string', 'max:500'],
            'UuidPembayaran' => ['required', 'array', 'min:1', 'max:'.PembayaranBelumDicairkan::MAKS_BARIS],
            'UuidPembayaran.*' => ['required', 'string', 'ulid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['regex' => ':Attribute harus angka dengan pemisah desimal titik, maksimal 2 desimal.'];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'UuidMetodePembayaran' => 'metode pembayaran',
            'UuidOutlet' => 'outlet',
            'UuidAkunTujuan' => 'akun penerima',
            'Tanggal' => 'tanggal masuk rekening',
            'JumlahBersih' => 'jumlah yang masuk rekening',
            'UuidPembayaran' => 'pembayaran yang dicairkan',
        ];
    }

    public function AmbilData(): DataPencairan
    {
        /** @var list<string> $uuid */
        $uuid = array_values(array_map('strval', (array) $this->validated('UuidPembayaran')));
        $tanggal = CarbonImmutable::createFromFormat('!Y-m-d', (string) $this->validated('Tanggal'));

        return new DataPencairan(
            uuidMetodePembayaran: (string) $this->validated('UuidMetodePembayaran'),
            uuidOutlet: (string) $this->validated('UuidOutlet'),
            uuidAkunTujuan: (string) $this->validated('UuidAkunTujuan'),
            tanggal: $tanggal instanceof CarbonImmutable ? $tanggal : CarbonImmutable::today(),
            jumlahBersih: Uang::Dari((string) $this->validated('JumlahBersih')),
            uuidPembayaran: $uuid,
            referensi: self::Teks($this->validated('Referensi')),
            catatan: self::Teks($this->validated('Catatan')),
        );
    }

    private static function Teks(mixed $nilai): ?string
    {
        return is_string($nilai) && trim($nilai) !== '' ? trim($nilai) : null;
    }
}

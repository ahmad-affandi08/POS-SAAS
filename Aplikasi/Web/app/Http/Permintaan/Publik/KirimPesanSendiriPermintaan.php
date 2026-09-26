<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Publik;

use App\Domain\Penjualan\Data\DataBarisPesanSendiri;
use App\Domain\Penjualan\Data\DataPesanSendiri;
use Illuminate\Foundation\Http\FormRequest;

/**
 * F-17 `POST /{slugTenant}/meja/{tokenMeja}/pesan`: `{Uuid, NamaPemesan?, Catatan?, Baris: [{Uuid, UuidProduk, Jumlah,
 * Pilihan: [UuidPilihan], Catatan?}]}`. Jumlah bilangan bulat 1–50, paling banyak 30 baris. Harga dihitung server.
 */
final class KirimPesanSendiriPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Uuid' => ['required', 'ulid'],
            'NamaPemesan' => ['nullable', 'string', 'max:60'],
            'Catatan' => ['nullable', 'string', 'max:200'],
            'Baris' => ['required', 'array', 'min:1', 'max:'.HitungPesanSendiriPermintaan::BATAS_BARIS],
            'Baris.*' => ['array'],
            'Baris.*.Uuid' => ['required', 'ulid', 'distinct:ignore_case'],
            'Baris.*.UuidProduk' => ['required', 'ulid'],
            'Baris.*.Jumlah' => ['required', 'integer', 'min:1', 'max:'.HitungPesanSendiriPermintaan::BATAS_JUMLAH],
            'Baris.*.Pilihan' => ['sometimes', 'array', 'max:20'],
            'Baris.*.Pilihan.*' => ['ulid'],
            'Baris.*.Catatan' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'Baris.required' => 'Keranjang masih kosong.',
            'Baris.min' => 'Keranjang masih kosong.',
            'Baris.max' => 'Paling banyak '.HitungPesanSendiriPermintaan::BATAS_BARIS.' menu dalam satu pesanan.',
            'Baris.*.Jumlah.min' => 'Jumlah minimal 1.',
            'Baris.*.Jumlah.max' => 'Jumlah paling banyak '.HitungPesanSendiriPermintaan::BATAS_JUMLAH.' per menu.',
            'NamaPemesan.max' => 'Nama paling banyak 60 karakter.',
            'Catatan.max' => 'Catatan paling banyak 200 karakter.',
        ];
    }

    public function AmbilData(): DataPesanSendiri
    {
        /** @var list<array<string, mixed>> $baris */
        $baris = array_values((array) $this->validated('Baris'));

        return new DataPesanSendiri(
            uuid: strtoupper($this->string('Uuid')->toString()),
            namaPemesan: self::Bersihkan($this->input('NamaPemesan')),
            catatan: self::Bersihkan($this->input('Catatan')),
            baris: array_map(fn (array $b): DataBarisPesanSendiri => new DataBarisPesanSendiri(
                uuid: strtoupper((string) $b['Uuid']),
                uuidProduk: strtoupper((string) $b['UuidProduk']),
                jumlah: (int) $b['Jumlah'],
                pilihan: array_values(array_map(fn (mixed $u): string => strtoupper((string) $u), (array) ($b['Pilihan'] ?? []))),
                catatan: self::Bersihkan($b['Catatan'] ?? null),
            ), $baris),
        );
    }

    private static function Bersihkan(mixed $teks): ?string
    {
        $teks = is_string($teks) ? trim($teks) : '';

        return $teks === '' ? null : $teks;
    }
}

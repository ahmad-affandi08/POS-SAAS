<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Publik;

use Illuminate\Foundation\Http\FormRequest;

final class TokoOnlinePermintaan extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $checkout = $this->routeIs('publik.toko-online.pesan');

        return [
            'Uuid' => [$checkout ? 'required' : 'sometimes', 'ulid'],
            'Outlet' => ['required', 'ulid'],
            'JenisPemenuhan' => ['required', 'in:AmbilSendiri,Kirim'],
            'MetodePembayaran' => [$checkout ? 'required' : 'sometimes', 'in:BayarSaatAmbil,Cod,QrisOnline'],
            'NamaPelanggan' => [$checkout ? 'required' : 'sometimes', 'string', 'max:100'],
            'NoHp' => [$checkout ? 'required' : 'sometimes', 'string', 'max:30'],
            'Email' => ['nullable', 'email', 'max:150'],
            'Alamat' => ['nullable', 'required_if:JenisPemenuhan,Kirim', 'string', 'max:500'],
            'Kelurahan' => ['nullable', 'required_if:JenisPemenuhan,Kirim', 'string', 'max:100'],
            'Kecamatan' => ['nullable', 'required_if:JenisPemenuhan,Kirim', 'string', 'max:100'],
            'Kota' => ['nullable', 'required_if:JenisPemenuhan,Kirim', 'string', 'max:100'],
            'Provinsi' => ['nullable', 'required_if:JenisPemenuhan,Kirim', 'string', 'max:100'],
            'KodePos' => ['nullable', 'required_if:JenisPemenuhan,Kirim', 'digits:5'],
            'Catatan' => ['nullable', 'string', 'max:500'],
            // v3.46: voucher berkode (opsional) di perkiraan & checkout.
            'KodeVoucher' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9-]+$/'],
            'SetujuDataPribadi' => [$checkout ? 'required' : 'sometimes', 'accepted'],
            'Baris' => ['required', 'array', 'min:1', 'max:50'],
            'Baris.*.Uuid' => ['sometimes', 'ulid', 'distinct:ignore_case'],
            'Baris.*.UuidProduk' => ['required', 'ulid'],
            'Baris.*.UuidVarian' => ['sometimes', 'nullable', 'ulid'],
            'Baris.*.Jumlah' => ['required', 'integer', 'min:1', 'max:999'],
            'Baris.*.Pilihan' => ['sometimes', 'array', 'max:20'],
            'Baris.*.Pilihan.*' => ['ulid'],
            'Baris.*.Catatan' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return list<array{UuidProduk: string, Jumlah: int, Pilihan: list<string>, UuidVarian: string|null}> */
    public function AmbilBaris(): array
    {
        return array_values(array_map(fn (array $b): array => [
            'UuidProduk' => strtoupper((string) $b['UuidProduk']),
            'Jumlah' => (int) $b['Jumlah'],
            'Pilihan' => array_values(array_map(fn (mixed $u): string => strtoupper((string) $u), (array) ($b['Pilihan'] ?? []))),
            'UuidVarian' => is_string($b['UuidVarian'] ?? null) ? strtoupper($b['UuidVarian']) : null,
        ], (array) $this->validated('Baris')));
    }
}

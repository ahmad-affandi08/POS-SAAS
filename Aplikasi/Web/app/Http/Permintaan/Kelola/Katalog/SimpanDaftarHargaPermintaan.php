<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Katalog;

use App\Domain\Katalog\Harga\Data\DataDaftarHarga;
use App\Domain\Katalog\Harga\Layanan\WaktuLokalDaftarHarga;
use App\Domain\Organisasi\Kueri\PetaUuidOutlet;
use App\Domain\Penjualan\Enum\KanalPenjualan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Form daftar harga (tipe FE `FormDaftarHarga`, E.7). `UuidOutlet` kosong = semua outlet; `Kanal`/`TierPelanggan`
 * kosong = semua; waktu `YYYY-MM-DDTHH:mm` di zona waktu tenant (kosong = tanpa batas), disimpan UTC.
 */
final class SimpanDaftarHargaPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Nama' => ['required', 'string', 'max:100'],
            'UuidOutlet' => ['present', 'array', 'max:200'],
            'UuidOutlet.*' => ['required', 'ulid', 'distinct'],
            'Kanal' => ['nullable', Rule::enum(KanalPenjualan::class)],
            'TierPelanggan' => ['nullable', 'string', 'max:30'],
            'MulaiPada' => ['nullable', 'date_format:'.WaktuLokalDaftarHarga::FORMAT],
            'SelesaiPada' => ['nullable', 'date_format:'.WaktuLokalDaftarHarga::FORMAT],
            'Prioritas' => ['required', 'integer', 'min:0', 'max:65535'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'MulaiPada.date_format' => 'Isi waktu mulai dengan tanggal dan jam.',
            'SelesaiPada.date_format' => 'Isi waktu selesai dengan tanggal dan jam.',
            'Prioritas.*' => 'Prioritas berupa angka bulat 0 sampai 65535.',
        ];
    }

    /**
     * @throws ValidationException
     */
    public function AmbilData(string $zonaWaktu): DataDaftarHarga
    {
        /** @var list<string> $uuidOutlet */
        $uuidOutlet = array_values(array_map('strval', (array) $this->input('UuidOutlet', [])));
        $idOutlet = app(PetaUuidOutlet::class)->AmbilIdDariUuid($uuidOutlet);

        foreach ($uuidOutlet as $i => $uuid) {
            if (! isset($idOutlet[$uuid])) {
                throw ValidationException::withMessages(["UuidOutlet.{$i}" => 'Outlet tidak ditemukan.', 'UuidOutlet' => 'Outlet tidak ditemukan.']);
            }
        }

        return new DataDaftarHarga(
            nama: $this->string('Nama')->toString(),
            idOutlet: $uuidOutlet === [] ? null : array_values(array_map(fn (string $uuid): int => $idOutlet[$uuid], $uuidOutlet)),
            kanal: $this->filled('Kanal') ? KanalPenjualan::from($this->string('Kanal')->toString()) : null,
            tierPelanggan: $this->filled('TierPelanggan') ? $this->string('TierPelanggan')->toString() : null,
            mulaiPada: WaktuLokalDaftarHarga::DariTeks($this->filled('MulaiPada') ? $this->string('MulaiPada')->toString() : null, $zonaWaktu),
            selesaiPada: WaktuLokalDaftarHarga::DariTeks($this->filled('SelesaiPada') ? $this->string('SelesaiPada')->toString() : null, $zonaWaktu),
            prioritas: $this->integer('Prioritas'),
        );
    }
}

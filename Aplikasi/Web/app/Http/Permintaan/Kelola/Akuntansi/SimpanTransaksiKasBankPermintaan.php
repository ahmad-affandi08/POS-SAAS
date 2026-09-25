<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Akuntansi;

use App\Domain\Akuntansi\Data\DataTransaksiKasBank;
use App\Domain\Akuntansi\Enum\JenisTransaksiKasBank;
use App\Domain\Bersama\Nilai\Uang;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * Isian transaksi kas & bank (F-13a). Uang sebagai string desimal (bukan float). Outlet diubah ke Id oleh kontroler
 * (batas outlet pelaku). Lampiran opsional sesuai `config('akuntansi.*Lampiran*')`.
 */
final class SimpanTransaksiKasBankPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'Jenis' => ['required', 'string', Rule::enum(JenisTransaksiKasBank::class)],
            'Tanggal' => ['required', 'date_format:Y-m-d'],
            'UuidOutlet' => ['nullable', 'string', 'ulid'],
            'UuidAkunSumber' => ['required', 'string', 'ulid'],
            'UuidAkunTujuan' => ['required', 'string', 'ulid'],
            'Jumlah' => ['required', 'string', 'regex:/^\d{1,16}(\.\d{1,2})?$/'],
            'Keterangan' => ['required', 'string', 'max:255'],
            'Lampiran' => [
                'nullable',
                'file',
                'mimes:'.implode(',', (array) config('akuntansi.EkstensiLampiran')),
                'max:'.(int) config('akuntansi.UkuranMaksimalLampiranKb'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['Jumlah.regex' => 'Jumlah harus angka Rupiah, maksimal 2 angka di belakang koma.'];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'Jenis' => 'jenis transaksi',
            'Tanggal' => 'tanggal',
            'UuidOutlet' => 'outlet',
            'UuidAkunSumber' => 'akun asal',
            'UuidAkunTujuan' => 'akun tujuan',
            'Jumlah' => 'jumlah',
            'Keterangan' => 'keterangan',
            'Lampiran' => 'lampiran',
        ];
    }

    public function AmbilUuidOutlet(): ?string
    {
        $uuid = $this->validated('UuidOutlet');

        return is_string($uuid) && $uuid !== '' ? $uuid : null;
    }

    public function AmbilData(?int $idOutlet, ?int $idPengguna): DataTransaksiKasBank
    {
        $lampiran = $this->file('Lampiran');

        return new DataTransaksiKasBank(
            JenisTransaksiKasBank::from((string) $this->validated('Jenis')),
            CarbonImmutable::createFromFormat('!Y-m-d', (string) $this->validated('Tanggal')) ?: CarbonImmutable::today(),
            $idOutlet,
            (string) $this->validated('UuidAkunSumber'),
            (string) $this->validated('UuidAkunTujuan'),
            Uang::Dari((string) $this->validated('Jumlah')),
            (string) $this->validated('Keterangan'),
            $lampiran instanceof UploadedFile ? $lampiran : null,
            $idPengguna,
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Piutang;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Pelanggan\Aksi\SimpanPembayaranPiutang;
use App\Domain\Pelanggan\Data\DataPembayaranPiutang;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/** Isian pelunasan piutang (F-12): pelanggan, akun kas/bank, tanggal, dan alokasi per piutang (Uuid → jumlah). */
final class SimpanPembayaranPiutangPermintaan extends FormRequest
{
    private const UANG = 'regex:/^\d{1,16}(\.\d{1,2})?$/';

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'UuidPelanggan' => ['required', 'string', 'ulid'],
            'UuidAkun' => ['required', 'string', 'ulid'],
            'Tanggal' => ['required', 'date_format:Y-m-d'],
            'Catatan' => ['nullable', 'string', 'max:500'],
            'Alokasi' => ['required', 'array', 'min:1', 'max:'.SimpanPembayaranPiutang::MAKS_PIUTANG],
            'Alokasi.*.UuidPiutang' => ['required', 'string', 'ulid', 'distinct'],
            'Alokasi.*.Jumlah' => ['required', 'string', self::UANG],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['regex' => ':Attribute harus angka dengan pemisah desimal titik (maks. 2 desimal).'];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['UuidPelanggan' => 'pelanggan', 'UuidAkun' => 'akun kas/bank', 'Tanggal' => 'tanggal', 'Alokasi' => 'piutang yang dilunasi', 'Alokasi.*.Jumlah' => 'jumlah pelunasan'];
    }

    public function AmbilData(int $idPengguna): DataPembayaranPiutang
    {
        $alokasi = [];

        foreach ((array) $this->validated('Alokasi') as $a) {
            if (is_array($a)) {
                $alokasi[(string) $a['UuidPiutang']] = Uang::Dari((string) $a['Jumlah']);
            }
        }

        $catatan = $this->validated('Catatan');

        return new DataPembayaranPiutang(
            (string) $this->validated('UuidPelanggan'),
            (string) $this->validated('UuidAkun'),
            CarbonImmutable::createFromFormat('!Y-m-d', (string) $this->validated('Tanggal')) ?: CarbonImmutable::today(),
            $alokasi,
            is_string($catatan) && trim($catatan) !== '' ? trim($catatan) : null,
            $idPengguna,
        );
    }
}

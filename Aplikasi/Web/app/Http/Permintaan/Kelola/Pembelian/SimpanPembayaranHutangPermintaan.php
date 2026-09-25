<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Pembelian;

use App\Domain\Pembelian\Aksi\SimpanPembayaranHutang;
use App\Domain\Pembelian\Data\DataPembayaranHutang;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/** Isian pembayaran hutang (F-04 fase 1): akun kas/bank dan alokasi per faktur (Uuid → jumlah). */
final class SimpanPembayaranHutangPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'UuidPemasok' => ['required', 'string', 'ulid'],
            'UuidAkun' => ['required', 'string', 'ulid'],
            'Tanggal' => ['required', 'date_format:Y-m-d'],
            'Catatan' => ['nullable', 'string', 'max:500'],
            'Lampiran' => AturanPembelian::Lampiran(),
            'Alokasi' => ['required', 'array', 'min:1', 'max:'.SimpanPembayaranHutang::MAKS_FAKTUR],
            'Alokasi.*.UuidFaktur' => ['required', 'string', 'ulid', 'distinct'],
            'Alokasi.*.Jumlah' => ['required', 'string', AturanPembelian::UANG],
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
        return ['UuidPemasok' => 'pemasok', 'UuidAkun' => 'akun kas/bank', 'Tanggal' => 'tanggal', 'Alokasi' => 'faktur yang dibayar', 'Alokasi.*.Jumlah' => 'jumlah bayar', 'Lampiran' => 'lampiran'];
    }

    public function AmbilData(int $idPengguna): DataPembayaranHutang
    {
        $alokasi = [];

        foreach ((array) $this->validated('Alokasi') as $a) {
            if (is_array($a)) {
                $alokasi[(string) $a['UuidFaktur']] = AturanPembelian::Uang($a['Jumlah']);
            }
        }

        $berkas = $this->file('Lampiran');

        return new DataPembayaranHutang(
            (string) $this->validated('UuidPemasok'),
            (string) $this->validated('UuidAkun'),
            AturanPembelian::Tanggal($this->validated('Tanggal')) ?? CarbonImmutable::today(),
            $alokasi,
            AturanPembelian::Teks($this->validated('Catatan')),
            $berkas instanceof UploadedFile ? $berkas : null,
            $idPengguna,
        );
    }
}

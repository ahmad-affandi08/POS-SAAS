<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola\Langganan;

use App\Domain\Tenant\Data\DataBuktiTransfer;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\File;

/**
 * Bukti transfer: gambar (JPG/PNG/WEBP) atau PDF, jenis diperiksa dari isi berkas (bukan nama), ukuran dibatasi
 * `tagihan.UkuranBuktiMaksimalKb`.
 */
final class UnggahBuktiTransferPermintaan extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        /** @var list<string> $ekstensi */
        $ekstensi = (array) config('tagihan.EkstensiBukti');

        return [
            'Bukti' => ['required', File::types($ekstensi)->max((int) config('tagihan.UkuranBuktiMaksimalKb'))],
            'Jumlah' => ['required', 'string', 'regex:/^\d{1,16}(\.\d{1,2})?$/'],
            'TanggalTransfer' => ['required', 'date_format:Y-m-d'],
            'BankPengirim' => ['required', 'string', 'max:100'],
            'NamaPengirim' => ['required', 'string', 'max:150'],
            'KodeRekeningTujuan' => ['required', 'string', 'max:30'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'Bukti' => 'bukti transfer',
            'Jumlah' => 'jumlah transfer',
            'TanggalTransfer' => 'tanggal transfer',
            'BankPengirim' => 'bank pengirim',
            'NamaPengirim' => 'nama pemilik rekening pengirim',
            'KodeRekeningTujuan' => 'rekening tujuan',
        ];
    }

    public function AmbilData(): DataBuktiTransfer
    {
        $berkas = $this->file('Bukti');
        abort_unless($berkas instanceof UploadedFile, 422);

        return new DataBuktiTransfer(
            berkas: $berkas,
            jumlah: $this->string('Jumlah')->toString(),
            tanggalTransfer: CarbonImmutable::createFromFormat('!Y-m-d', $this->string('TanggalTransfer')->toString(), 'Asia/Jakarta') ?: CarbonImmutable::now('Asia/Jakarta'),
            bankPengirim: $this->string('BankPengirim')->trim()->toString(),
            namaPengirim: $this->string('NamaPengirim')->trim()->toString(),
            kodeRekeningTujuan: $this->string('KodeRekeningTujuan')->toString(),
        );
    }
}

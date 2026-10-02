<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Layanan;

use App\Domain\Bersama\Sinkron\Data\DataKonteksSinkron;
use App\Domain\Bersama\Sinkron\Enum\StatusItemSinkron;
use App\Domain\Bersama\Sinkron\Kontrak\PenanganItemSinkron;
use App\Domain\Kasir\Layanan\ValidasiItemSinkron;
use App\Domain\Penjualan\Aksi\TerimaKunjunganSalesPos;
use App\Domain\Penjualan\Enum\HasilKunjungan;
use Illuminate\Validation\Rule;

/**
 * Item outbox `Kunjungan.Catat` (Modul Salesman bagian 1, §9.7, SLS-11), dikirim sekali saat check-out. Bentuk `Data`:
 * `{UuidPelanggan, UuidPengguna, MasukPada, KeluarPada?, Latitude?, Longitude?, AkurasiMeter?, Hasil, Catatan?,
 * UuidPesananGrosir?}`. Uuid item = Uuid kunjungan. Koordinat **string desimal** (maks. 7 angka di belakang koma),
 * rentangnya diperiksa dengan pola teks — tidak pernah lewat float. Jenis perangkat tidak dibatasi (lihat
 * `PenanganSinkronBuatPesananGrosir`). Aturan bisnis di `TerimaKunjunganSalesPos`.
 */
final class PenanganSinkronCatatKunjungan implements PenanganItemSinkron
{
    public const JENIS = 'Kunjungan.Catat';

    /** -90 s.d. 90, maks. 7 desimal. */
    public const POLA_LATITUDE = '/^-?(90(\.0{1,7})?|[1-8]?\d(\.\d{1,7})?)$/';

    /** -180 s.d. 180, maks. 7 desimal. */
    public const POLA_LONGITUDE = '/^-?(180(\.0{1,7})?|(1[0-7]\d|[1-9]?\d)(\.\d{1,7})?)$/';

    public function __construct(private readonly TerimaKunjunganSalesPos $terima) {}

    public function AmbilJenis(): string
    {
        return self::JENIS;
    }

    public function Proses(string $uuid, array $data, DataKonteksSinkron $konteks): StatusItemSinkron
    {
        $valid = ValidasiItemSinkron::Validasi($data, [
            'UuidPelanggan' => ['required', 'string', 'ulid'],
            'UuidPengguna' => ['required', 'string', 'ulid'],
            'MasukPada' => ['required', 'string', 'regex:'.ValidasiItemSinkron::POLA_WAKTU, 'date'],
            'KeluarPada' => ['nullable', 'string', 'regex:'.ValidasiItemSinkron::POLA_WAKTU, 'date'],
            'Latitude' => ['nullable', 'string', 'required_with:Longitude', 'regex:'.self::POLA_LATITUDE],
            'Longitude' => ['nullable', 'string', 'required_with:Latitude', 'regex:'.self::POLA_LONGITUDE],
            'AkurasiMeter' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'Hasil' => ['required', 'string', Rule::enum(HasilKunjungan::class)],
            'Catatan' => ['nullable', 'string', 'max:255'],
            'UuidPesananGrosir' => ['nullable', 'string', 'ulid'],
        ]);

        $keluar = $valid['KeluarPada'] ?? null;
        $catatan = $valid['Catatan'] ?? null;

        return $this->terima->Jalankan(
            uuid: strtoupper($uuid),
            idPerangkat: $konteks->idPerangkat,
            idOutlet: $konteks->idOutlet,
            uuidPelanggan: strtoupper((string) $valid['UuidPelanggan']),
            uuidPengguna: strtoupper((string) $valid['UuidPengguna']),
            masukPada: ValidasiItemSinkron::AmbilWaktu((string) $valid['MasukPada']),
            keluarPada: is_string($keluar) ? ValidasiItemSinkron::AmbilWaktu($keluar) : null,
            latitude: is_string($valid['Latitude'] ?? null) ? $valid['Latitude'] : null,
            longitude: is_string($valid['Longitude'] ?? null) ? $valid['Longitude'] : null,
            akurasiMeter: isset($valid['AkurasiMeter']) ? (int) $valid['AkurasiMeter'] : null,
            hasil: HasilKunjungan::from((string) $valid['Hasil']),
            catatan: is_string($catatan) && trim($catatan) !== '' ? trim($catatan) : null,
            uuidPesananGrosir: is_string($valid['UuidPesananGrosir'] ?? null) ? strtoupper($valid['UuidPesananGrosir']) : null,
        );
    }
}

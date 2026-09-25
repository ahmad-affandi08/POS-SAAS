<?php

declare(strict_types=1);

namespace App\Domain\Kasir\Layanan;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Sinkron\Data\DataKonteksSinkron;
use App\Domain\Bersama\Sinkron\Enum\StatusItemSinkron;
use App\Domain\Bersama\Sinkron\Kontrak\PenanganItemSinkron;
use App\Domain\Kasir\Aksi\TutupShift;
use App\Domain\Kasir\Data\DataTutupShift;

/**
 * Item outbox `Shift.Tutup` (F-11): `{UuidShift, UuidPengguna, DitutupPada, KasAktual, PecahanKasAkhir|null,
 * NonTunai [{UuidMetodePembayaran, Jumlah}], Alasan|null, UuidPenyetuju|null, Ringkasan {KasSeharusnya, Selisih}}`.
 * `Uuid` item = ULID entri outbox (tidak disimpan); idempotensi per `UuidShift` (shift yang sudah ditutup dengan data
 * sama = `Duplikat`). `Ringkasan` boleh negatif.
 */
final class PenanganSinkronTutupShift implements PenanganItemSinkron
{
    /** Uang bertanda (ringkasan perangkat): boleh minus. */
    private const POLA_UANG_BERTANDA = '/^-?\d{1,16}(\.\d{1,2})?$/';

    public function __construct(private readonly TutupShift $tutup) {}

    public function AmbilJenis(): string
    {
        return 'Shift.Tutup';
    }

    public function Proses(string $uuid, array $data, DataKonteksSinkron $konteks): StatusItemSinkron
    {
        $valid = ValidasiItemSinkron::Validasi($data, [
            'UuidShift' => ['required', 'string', 'ulid'],
            'UuidPengguna' => ['required', 'string', 'ulid'],
            'DitutupPada' => ['required', 'string', 'regex:'.ValidasiItemSinkron::POLA_WAKTU, 'date'],
            'KasAktual' => ['required', 'string', 'regex:'.ValidasiItemSinkron::POLA_UANG],
            'PecahanKasAkhir' => ['nullable', 'array', 'max:30'],
            'PecahanKasAkhir.*.Nominal' => ['required', 'string', 'regex:'.ValidasiItemSinkron::POLA_UANG],
            'PecahanKasAkhir.*.Jumlah' => ['required', 'integer', 'min:0', 'max:100000'],
            'NonTunai' => ['present', 'array', 'max:50'],
            'NonTunai.*.UuidMetodePembayaran' => ['required', 'string', 'ulid'],
            'NonTunai.*.Jumlah' => ['required', 'string', 'regex:'.ValidasiItemSinkron::POLA_UANG],
            'Alasan' => ['nullable', 'string', 'max:255'],
            'UuidPenyetuju' => ['nullable', 'string', 'ulid'],
            'Ringkasan' => ['required', 'array'],
            'Ringkasan.KasSeharusnya' => ['required', 'string', 'regex:'.self::POLA_UANG_BERTANDA],
            'Ringkasan.Selisih' => ['required', 'string', 'regex:'.self::POLA_UANG_BERTANDA],
        ]);

        $pecahan = null;

        if (is_array($valid['PecahanKasAkhir'] ?? null)) {
            $pecahan = [];

            foreach ($valid['PecahanKasAkhir'] as $baris) {
                $pecahan[] = ['Nominal' => (string) $baris['Nominal'], 'Jumlah' => (int) $baris['Jumlah']];
            }
        }

        $nonTunai = [];

        foreach (is_array($valid['NonTunai'] ?? null) ? $valid['NonTunai'] : [] as $baris) {
            $nonTunai[] = ['UuidMetodePembayaran' => (string) $baris['UuidMetodePembayaran'], 'Jumlah' => Uang::Dari((string) $baris['Jumlah'])];
        }

        $alasan = isset($valid['Alasan']) ? trim((string) $valid['Alasan']) : '';

        return $this->tutup->Jalankan(new DataTutupShift(
            idPerangkat: $konteks->idPerangkat,
            uuidShift: (string) $valid['UuidShift'],
            uuidPenutup: (string) $valid['UuidPengguna'],
            ditutupPada: ValidasiItemSinkron::AmbilWaktu((string) $valid['DitutupPada']),
            kasAktual: Uang::Dari((string) $valid['KasAktual']),
            pecahan: $pecahan,
            nonTunai: $nonTunai,
            alasan: $alasan === '' ? null : $alasan,
            uuidPenyetuju: isset($valid['UuidPenyetuju']) ? (string) $valid['UuidPenyetuju'] : null,
            ringkasanKasSeharusnya: Uang::Dari((string) $valid['Ringkasan']['KasSeharusnya']),
            ringkasanSelisih: Uang::Dari((string) $valid['Ringkasan']['Selisih']),
        ));
    }
}

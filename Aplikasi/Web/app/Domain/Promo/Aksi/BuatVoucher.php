<?php

declare(strict_types=1);

namespace App\Domain\Promo\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Promo\Enum\StatusPromo;
use App\Domain\Promo\Enum\StatusVoucher;
use App\Domain\Promo\Model\Promo;
use App\Domain\Promo\Model\Voucher;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Tambah voucher untuk promo wajib voucher (F-16c bagian 2, CRM-06, izin `pelanggan.kelola`): satu kode pilihan
 * ([kode] diisi) atau kode massal ([jumlah] kode acak `{awalan}{8 karakter}` tanpa huruf/angka mirip seperti O/0, I/1).
 * Kode huruf besar, unik per tenant. [maksimalPakai] null = berulang tanpa batas; [kedaluwarsaPada] UTC eksklusif.
 * Audit `voucher.tambah` satu baris per permintaan. Hasil: kode yang dibuat.
 */
final class BuatVoucher
{
    public const JUMLAH_MAKSIMAL = 5000;

    public const PANJANG_ACAK = 8;

    private const HURUF = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(private readonly PencatatAudit $audit) {}

    /**
     * @return list<string>
     */
    public function Jalankan(Promo $promo, ?string $kode, int $jumlah, ?string $awalan, ?int $maksimalPakai, ?CarbonImmutable $kedaluwarsaPada, int $idPengguna): array
    {
        if (! $promo->CekWajibVoucher()) {
            throw new PelanggaranAturanBisnis('PromoTanpaVoucher', 'Aktifkan "Wajib kode voucher" di promo ini sebelum membuat voucher.', 'Kode');
        }

        if ($promo->Status !== StatusPromo::Aktif) {
            throw new PelanggaranAturanBisnis('PromoDiarsipkan', 'Promo sudah diarsipkan.', 'Kode');
        }

        if ($maksimalPakai !== null && ($maksimalPakai < 1 || $maksimalPakai > 1000000)) {
            throw new PelanggaranAturanBisnis('MaksimalPakaiTidakValid', 'Batas pakai 1–1.000.000 atau kosongkan untuk tanpa batas.', 'MaksimalPakai');
        }

        $daftarKode = $kode !== null ? [$this->PeriksaKode($kode)] : $this->BuatKodeAcak($jumlah, $awalan);

        return DB::transaction(function () use ($promo, $daftarKode, $maksimalPakai, $kedaluwarsaPada, $idPengguna, $kode, $awalan): array {
            $sudahAda = Voucher::query()->whereIn('Kode', $daftarKode)->pluck('Kode')->all();

            if ($sudahAda !== []) {
                if ($kode !== null) {
                    throw new PelanggaranAturanBisnis('KodeSudahDipakai', 'Kode voucher sudah dipakai.', 'Kode');
                }

                $daftarKode = array_values(array_diff($daftarKode, $sudahAda));
            }

            $sekarang = CarbonImmutable::now();

            foreach (array_chunk($daftarKode, 500) as $potongan) {
                Voucher::query()->insert(array_map(fn (string $k): array => [
                    'Uuid' => (string) Str::ulid(),
                    'IdTenant' => $promo->IdTenant,
                    'IdPromo' => $promo->Id,
                    'Kode' => $k,
                    'MaksimalPakai' => $maksimalPakai,
                    'JumlahDipakai' => 0,
                    'KedaluwarsaPada' => $kedaluwarsaPada,
                    'Status' => StatusVoucher::Aktif->value,
                    'DibuatPada' => $sekarang,
                    'DiubahPada' => $sekarang,
                ], $potongan));
            }

            $this->audit->Catat('voucher.tambah', $promo, null, [
                'Jumlah' => count($daftarKode),
                'Kode' => $kode !== null ? $daftarKode[0] : null,
                'Awalan' => $kode === null ? $awalan : null,
                'MaksimalPakai' => $maksimalPakai,
                'KedaluwarsaPada' => $kedaluwarsaPada?->toIso8601ZuluString(),
            ], idPengguna: $idPengguna);

            return $daftarKode;
        });
    }

    private function PeriksaKode(string $kode): string
    {
        $rapi = Voucher::RapikanKode($kode);

        if (preg_match('/^[A-Z0-9_-]{4,30}$/', $rapi) !== 1) {
            throw new PelanggaranAturanBisnis('KodeTidakValid', 'Kode voucher 4–30 huruf, angka, garis bawah, atau tanda hubung.', 'Kode');
        }

        return $rapi;
    }

    /**
     * @return list<string>
     */
    private function BuatKodeAcak(int $jumlah, ?string $awalan): array
    {
        if ($jumlah < 1 || $jumlah > self::JUMLAH_MAKSIMAL) {
            throw new PelanggaranAturanBisnis('JumlahTidakValid', 'Jumlah voucher 1–5.000 per pembuatan.', 'Jumlah');
        }

        $awalan = Voucher::RapikanKode($awalan ?? '');

        if (preg_match('/^[A-Z0-9_-]{0,12}$/', $awalan) !== 1) {
            throw new PelanggaranAturanBisnis('AwalanTidakValid', 'Awalan maksimal 12 huruf, angka, garis bawah, atau tanda hubung.', 'Awalan');
        }

        $hasil = [];

        while (count($hasil) < $jumlah) {
            $acak = '';

            for ($i = 0; $i < self::PANJANG_ACAK; $i++) {
                $acak .= self::HURUF[random_int(0, strlen(self::HURUF) - 1)];
            }

            $hasil[$awalan.$acak] = true;
        }

        return array_keys($hasil);
    }
}

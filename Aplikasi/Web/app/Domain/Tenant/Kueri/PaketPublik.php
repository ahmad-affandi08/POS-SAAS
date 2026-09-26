<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Kueri;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Tenant\Enum\StatusPaket;
use App\Domain\Tenant\Model\Fitur;
use App\Domain\Tenant\Model\Paket;
use Carbon\CarbonImmutable;

/**
 * Paket untuk halaman harga situs pemasaran (D-21): paket Aktif urut `Urutan`, harga terbit yang berlaku hari ini
 * (WIB) untuk pelanggan baru, batas paket, dan nama fitur. Paket tanpa harga berlaku dan bukan harga negosiasi tidak
 * ditampilkan. Perubahan paket/harga di konsol (P-04) langsung tampil di situs.
 */
final class PaketPublik
{
    /** Label batas paket untuk pembaca umum. */
    private const LABEL_BATAS = [
        'BatasOutlet' => ['outlet', 'Outlet tanpa batas'],
        'BatasPerangkatPerOutlet' => ['perangkat kasir per outlet', 'Perangkat tanpa batas'],
        'BatasPengguna' => ['pengguna', 'Pengguna tanpa batas'],
        'BatasSku' => ['produk (SKU)', 'Produk tanpa batas'],
        'KuotaPesanWaBulanan' => ['pesan WhatsApp/bulan', null],
    ];

    public function __construct(private readonly HargaPaketBerlaku $harga) {}

    /**
     * @return list<array{Kode: string, Nama: string, Keterangan: string|null, HargaNegosiasi: bool, MasaTrialHari: int, HargaBulanan: string|null, HargaTahunan: string|null, HematTahunan: string|null, Batas: list<string>, Fitur: list<string>}>
     */
    public function Ambil(): array
    {
        $hariIni = CarbonImmutable::now('Asia/Jakarta')->startOfDay();
        $namaFitur = Fitur::query()->pluck('Nama', 'Kunci')->all();
        $hasil = [];

        foreach (Paket::query()->where('Status', StatusPaket::Aktif->value)->with('Fitur')->orderBy('Urutan')->orderBy('Id')->get() as $paket) {
            $harga = $paket->HargaNegosiasi ? null : $this->harga->Cari($paket->Id, $hariIni, $hariIni);

            if (! $paket->HargaNegosiasi && $harga === null) {
                continue;
            }

            $bulanan = $harga === null ? null : Uang::Dari($harga->HargaBulanan);
            $tahunan = $harga === null ? null : Uang::Dari($harga->HargaTahunan);
            $hemat = $bulanan !== null && $tahunan !== null ? $bulanan->Kali(12)->Kurangi($tahunan) : null;

            $hasil[] = [
                'Kode' => $paket->Kode,
                'Nama' => $paket->Nama,
                'Keterangan' => $paket->Keterangan,
                'HargaNegosiasi' => $paket->HargaNegosiasi,
                'MasaTrialHari' => $paket->MasaTrialHari,
                'HargaBulanan' => $bulanan?->KeString(),
                'HargaTahunan' => $tahunan?->KeString(),
                'HematTahunan' => $hemat !== null && $hemat->Bandingkan(Uang::Nol()) > 0 ? $hemat->KeString() : null,
                'Batas' => self::SusunBatas($paket),
                'Fitur' => array_values(array_filter(array_map(
                    fn (string $kunci): ?string => $namaFitur[$kunci] ?? null,
                    $paket->AmbilKunciFitur(),
                ))),
            ];
        }

        return $hasil;
    }

    /** 12500 → "12.500" (tanpa number_format, D-05 arsitektur). */
    private static function FormatRibuan(int $nilai): string
    {
        return strrev(implode('.', str_split(strrev((string) $nilai), 3)));
    }

    /**
     * @return list<string>
     */
    private static function SusunBatas(Paket $paket): array
    {
        $baris = [];

        foreach (self::LABEL_BATAS as $kolom => [$label, $tanpaBatas]) {
            $nilai = $paket->AmbilBatas()[$kolom] ?? null;

            if ($nilai === null) {
                if ($tanpaBatas !== null) {
                    $baris[] = $tanpaBatas;
                }

                continue;
            }

            if ($nilai > 0) {
                $baris[] = self::FormatRibuan($nilai).' '.$label;
            }
        }

        return $baris;
    }
}

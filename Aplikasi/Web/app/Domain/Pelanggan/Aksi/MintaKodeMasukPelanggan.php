<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Integrasi\Whatsapp\PembuatPengirimWhatsapp;
use App\Domain\Integrasi\Whatsapp\PesanWhatsapp;
use App\Domain\Pelanggan\Layanan\NomorHp;
use App\Domain\Pelanggan\Layanan\SandiAkunOnline;
use App\Domain\Pelanggan\Model\KodeMasukPelanggan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * F-17 bagian 3: kirim kode masuk 6 digit ke WhatsApp pembeli toko online. Tidak memberi tahu apakah nomor itu sudah
 * terdaftar (jawaban selalu sama), supaya halaman ini tidak bisa dipakai menebak siapa pelanggan toko.
 *
 * Batas (dihitung dari baris `KodeMasukPelanggan`, termasuk yang gagal terkirim, jadi kegagalan tidak membuka celah):
 * satu kode per nomor per 60 detik, 5 kode per nomor per jam, 10 kode per IP per jam, dan 300 kode per toko per jam
 * (setiap pesan WhatsApp berbiaya). Kode lama untuk nomor yang sama langsung tidak berlaku begitu kode baru dibuat.
 */
final class MintaKodeMasukPelanggan
{
    public const MENIT_BERLAKU = 5;

    public const DETIK_JEDA = 60;

    public const BATAS_PER_NOMOR_PER_JAM = 5;

    public const BATAS_PER_IP_PER_JAM = 10;

    public const BATAS_PER_TOKO_PER_JAM = 300;

    public function __construct(private readonly PembuatPengirimWhatsapp $whatsapp) {}

    public function CekTersedia(): bool
    {
        return $this->whatsapp->AmbilAktif() !== null;
    }

    /**
     * @return array{KedaluwarsaPada: CarbonImmutable, KirimUlangPada: CarbonImmutable}
     */
    public function Jalankan(string $noHpMasukan, ?string $hashIp, string $namaToko): array
    {
        $noHp = NomorHp::Normalisasi($noHpMasukan)
            ?? throw new PelanggaranAturanBisnis('NoHpTidakValid', 'Nomor WhatsApp tidak valid. Contoh: 0812-3456-7890.', 'NoHp');
        $pengirim = $this->whatsapp->AmbilAktif()
            ?? throw new PelanggaranAturanBisnis('MasukBelumTersedia', 'Masuk dengan WhatsApp sedang tidak tersedia. Anda tetap bisa memesan tanpa masuk.', 'Umum', 409);
        $hashNoHp = SandiAkunOnline::BuatHash($noHp);
        $kode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $sekarang = CarbonImmutable::now();

        $baris = DB::transaction(function () use ($hashNoHp, $hashIp, $kode, $sekarang): KodeMasukPelanggan {
            $jamLalu = $sekarang->subHour();
            $terakhir = KodeMasukPelanggan::query()->where('HashNoHp', $hashNoHp)->orderByDesc('Id')->lockForUpdate()->first();

            if ($terakhir !== null && $terakhir->DibuatPada !== null && $terakhir->DibuatPada->greaterThan($sekarang->subSeconds(self::DETIK_JEDA))) {
                $tunggu = self::DETIK_JEDA - (int) $terakhir->DibuatPada->diffInSeconds($sekarang, true);

                throw new PelanggaranAturanBisnis('TungguSebentar', 'Kode baru saja dikirim. Coba kirim ulang dalam '.max(1, $tunggu).' detik.', 'NoHp', 429);
            }

            if (KodeMasukPelanggan::query()->where('HashNoHp', $hashNoHp)->where('DibuatPada', '>=', $jamLalu)->count() >= self::BATAS_PER_NOMOR_PER_JAM) {
                throw new PelanggaranAturanBisnis('TerlaluBanyakKode', 'Terlalu banyak permintaan kode untuk nomor ini. Coba lagi satu jam lagi.', 'NoHp', 429);
            }

            if ($hashIp !== null && KodeMasukPelanggan::query()->where('HashIp', $hashIp)->where('DibuatPada', '>=', $jamLalu)->count() >= self::BATAS_PER_IP_PER_JAM) {
                throw new PelanggaranAturanBisnis('TerlaluBanyakKode', 'Terlalu banyak permintaan kode dari jaringan ini. Coba lagi nanti.', 'Umum', 429);
            }

            if (KodeMasukPelanggan::query()->where('DibuatPada', '>=', $jamLalu)->count() >= self::BATAS_PER_TOKO_PER_JAM) {
                throw new PelanggaranAturanBisnis('TerlaluBanyakKode', 'Toko sedang menerima terlalu banyak permintaan masuk. Coba lagi beberapa menit lagi, atau pesan tanpa masuk.', 'Umum', 429);
            }

            // Kode lama yang belum dipakai tidak berlaku lagi: hanya kode terbaru di WhatsApp pembeli yang sah.
            KodeMasukPelanggan::query()->where('HashNoHp', $hashNoHp)->whereNull('DipakaiPada')->where('KedaluwarsaPada', '>', $sekarang)
                ->update(['KedaluwarsaPada' => $sekarang]);

            return KodeMasukPelanggan::query()->create([
                'HashNoHp' => $hashNoHp,
                'HashKode' => SandiAkunOnline::BuatHash($kode),
                'KedaluwarsaPada' => $sekarang->addMinutes(self::MENIT_BERLAKU),
                'HashIp' => $hashIp,
            ]);
        });

        $templat = $pengirim->CekResmi() ? $this->whatsapp->AmbilTemplatKodeMasuk() : null;
        $hasil = $pengirim->Kirim(new PesanWhatsapp(
            $noHp,
            "{$kode} adalah kode masuk Anda di {$namaToko}. Berlaku ".self::MENIT_BERLAKU." menit.\nJangan berikan kode ini kepada siapa pun, termasuk pihak toko.",
            $templat,
            $templat === null ? [] : [$kode],
            $templat === null ? null : $kode,
        ));

        if (! $hasil->berhasil) {
            // Baris tetap ada (tetap dihitung batas), hanya tidak bisa dipakai: kode yang tidak sampai tidak boleh sah.
            $baris->forceFill(['KedaluwarsaPada' => $sekarang])->save();
            Log::warning('Kode masuk pelanggan gagal dikirim.', ['IdTenant' => $baris->IdTenant, 'Penyedia' => $pengirim->AmbilKode()]);

            throw new PelanggaranAturanBisnis('KodeGagalTerkirim', 'Kode belum bisa dikirim ke WhatsApp. Coba lagi sebentar lagi, atau pesan tanpa masuk.', 'NoHp', 503);
        }

        return ['KedaluwarsaPada' => $sekarang->addMinutes(self::MENIT_BERLAKU), 'KirimUlangPada' => $sekarang->addSeconds(self::DETIK_JEDA)];
    }
}

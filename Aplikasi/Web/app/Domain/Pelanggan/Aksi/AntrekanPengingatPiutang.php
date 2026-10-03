<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Integrasi\Whatsapp\PembuatPengirimWhatsapp;
use App\Domain\Integrasi\Whatsapp\PesanWhatsapp;
use App\Domain\Pelanggan\Enum\JenisPengingatPiutang;
use App\Domain\Pelanggan\Enum\KanalPengingatPiutang;
use App\Domain\Pelanggan\Enum\StatusPengingatPiutang;
use App\Domain\Pelanggan\Enum\StatusPiutang;
use App\Domain\Pelanggan\Model\Pelanggan;
use App\Domain\Pelanggan\Model\PengingatPiutang;
use App\Domain\Pelanggan\Model\Piutang;
use App\Domain\Pelanggan\Tugas\KirimPengingatPiutangTugas;
use App\Domain\Tenant\Layanan\PemeriksaFiturTenant;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * D-23 D bagian 4b: antrekan satu pengingat piutang ke pelanggan lewat WhatsApp (nomor HP sah dan WhatsApp aktif untuk
 * usaha ini: integrasi P-05 + fitur `integrasi.whatsapp`). Sejak D-33 tidak ada lagi jalur email: notifikasi tenant
 * hanya lewat WhatsApp.
 * Piutang harus terbuka dan berpelanggan. Otomatis: sekali per (piutang, jenis) lewat `KunciOtomatis`; manual: paling
 * sering sekali per `JEDA_JAM` per piutang (tidak membanjiri pelanggan). Tugas antrean dijalankan setelah commit.
 */
final class AntrekanPengingatPiutang
{
    public const JEDA_JAM = 12;

    public const POLA_NOMOR = '/^628\d{7,12}$/';

    public function __construct(
        private readonly PembuatPengirimWhatsapp $whatsapp,
        private readonly PemeriksaFiturTenant $fitur,
    ) {}

    /** @return PengingatPiutang|null null bila pengingat otomatis jenis ini sudah pernah dibuat. */
    public function Jalankan(int $idTenant, Piutang $piutang, JenisPengingatPiutang $jenis, ?int $idPengguna = null): ?PengingatPiutang
    {
        if (! in_array($piutang->Status, [StatusPiutang::BelumLunas, StatusPiutang::DibayarSebagian], true)) {
            throw new PelanggaranAturanBisnis('PiutangTidakTerbuka', 'Piutang ini sudah lunas atau dibatalkan.', 'Umum', 409);
        }

        $pelanggan = $piutang->IdPelanggan === null ? null : Pelanggan::query()->find($piutang->IdPelanggan);

        if ($pelanggan === null) {
            throw new PelanggaranAturanBisnis('PelangganTidakAda', 'Piutang ini tanpa data pelanggan, jadi tidak bisa diingatkan.', 'Umum', 409);
        }

        [$kanal, $tujuan] = $this->PilihKanal($idTenant, $pelanggan)
            ?? throw new PelanggaranAturanBisnis('KontakTidakTersedia', 'Pelanggan belum punya nomor WhatsApp yang sah, atau pengiriman WhatsApp belum aktif untuk usaha ini.', 'Umum', 409);
        $kunci = $jenis === JenisPengingatPiutang::Manual ? null : "{$piutang->Id}:{$jenis->value}";

        if ($kunci !== null && PengingatPiutang::query()->where('KunciOtomatis', $kunci)->exists()) {
            return null;
        }

        try {
            return DB::transaction(function () use ($idTenant, $piutang, $jenis, $idPengguna, $kanal, $tujuan, $kunci): PengingatPiutang {
                Piutang::query()->whereKey($piutang->Id)->lockForUpdate()->first();

                if ($jenis === JenisPengingatPiutang::Manual && PengingatPiutang::query()
                    ->where('IdPiutang', $piutang->Id)
                    ->where('DibuatPada', '>', now()->subHours(self::JEDA_JAM))
                    ->whereIn('Status', [StatusPengingatPiutang::Diantrekan->value, StatusPengingatPiutang::Terkirim->value])
                    ->exists()) {
                    throw new PelanggaranAturanBisnis('PengingatBaruDikirim', 'Pelanggan ini baru saja diingatkan untuk nota yang sama. Coba lagi besok.', 'Umum', 429);
                }

                $pengingat = PengingatPiutang::query()->create([
                    'IdPiutang' => $piutang->Id,
                    'Jenis' => $jenis,
                    'Kanal' => $kanal,
                    'Tujuan' => $tujuan,
                    'Status' => StatusPengingatPiutang::Diantrekan,
                    'KunciOtomatis' => $kunci,
                    'DikirimOleh' => $idPengguna,
                ]);

                KirimPengingatPiutangTugas::dispatch($idTenant, $pengingat->Id)->afterCommit();

                return $pengingat;
            });
        } catch (UniqueConstraintViolationException) {
            // Dua putaran otomatis bersamaan: yang kalah tidak membuat pengingat kedua.
            return null;
        }
    }

    /** @return array{0: KanalPengingatPiutang, 1: string}|null */
    private function PilihKanal(int $idTenant, Pelanggan $pelanggan): ?array
    {
        $nomor = PesanWhatsapp::RapikanNomor($pelanggan->NoHp);

        if (preg_match(self::POLA_NOMOR, $nomor) === 1 && $this->whatsapp->AmbilAktif() !== null && $this->fitur->CekAktif($idTenant, PemeriksaFiturTenant::KUNCI_WHATSAPP)) {
            return [KanalPengingatPiutang::Whatsapp, $nomor];
        }

        return null;
    }
}

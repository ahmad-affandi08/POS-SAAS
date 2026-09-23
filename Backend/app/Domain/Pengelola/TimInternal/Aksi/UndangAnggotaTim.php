<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\TimInternal\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Pengelola\TimInternal\Model\PeranPengelola;
use App\Domain\Pengelola\TimInternal\Model\UndanganPengelola;
use App\Domain\Pengelola\TimInternal\Surel\UndanganTimInternal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * P-01 langkah 3: Super Admin mengundang anggota tim lewat email. Undangan berlaku 48 jam dan sekali pakai.
 * Undangan lama yang belum dipakai untuk email yang sama otomatis dibatalkan.
 */
final class UndangAnggotaTim
{
    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    /**
     * @param  list<string>  $kodePeran
     */
    public function Jalankan(PenggunaPengelola $pengundang, string $email, array $kodePeran): UndanganPengelola
    {
        $email = Str::lower(trim($email));
        $kodePeran = array_values(array_unique($kodePeran));
        $token = Str::random(48);

        $undangan = DB::transaction(function () use ($pengundang, $email, $kodePeran, $token): UndanganPengelola {
            if (PenggunaPengelola::query()->where('Email', $email)->exists()) {
                throw new PelanggaranAturanBisnis('EmailSudahTerdaftar', 'Email ini sudah terdaftar sebagai anggota tim internal.', 'Email');
            }

            if ($kodePeran === [] || PeranPengelola::query()->whereIn('Kode', $kodePeran)->count() !== count($kodePeran)) {
                throw new PelanggaranAturanBisnis('PeranTidakDikenal', 'Pilih minimal satu peran yang tersedia.', 'KodePeran');
            }

            UndanganPengelola::query()
                ->where('Email', $email)
                ->whereNull('DiterimaPada')
                ->whereNull('DibatalkanPada')
                ->update(['DibatalkanPada' => now()]);

            $undangan = UndanganPengelola::query()->create([
                'Email' => $email,
                'HashToken' => UndanganPengelola::HashDariToken($token),
                'KodePeran' => $kodePeran,
                'IdPenggunaPengelolaPengundang' => $pengundang->Id,
                'BerlakuSampai' => now()->addHours((int) config('pengelola.JamBerlakuUndangan')),
            ]);

            $this->audit->Catat(
                'tim.anggota.undang',
                $undangan,
                nilaiBaru: ['Email' => $email, 'KodePeran' => $kodePeran, 'BerlakuSampai' => $undangan->BerlakuSampai->toIso8601String()],
                idPelaku: $pengundang->Id,
            );

            return $undangan;
        });

        // Dikirim setelah commit dan tidak lewat queue, agar token asli tidak tersimpan di tabel jobs.
        Mail::to($email)->send(new UndanganTimInternal($undangan, $token, $pengundang->Nama));

        return $undangan;
    }
}

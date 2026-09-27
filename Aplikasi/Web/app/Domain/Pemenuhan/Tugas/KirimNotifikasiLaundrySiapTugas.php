<?php

declare(strict_types=1);

namespace App\Domain\Pemenuhan\Tugas;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Integrasi\Whatsapp\PembuatPengirimWhatsapp;
use App\Domain\Integrasi\Whatsapp\PesanWhatsapp;
use App\Domain\Pemenuhan\Enum\StatusLaundry;
use App\Domain\Pemenuhan\Model\TiketLaundry;
use App\Domain\Penjualan\Layanan\KodeStrukDigital;
use App\Domain\Tenant\Kueri\ProfilTenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Laundry (§9.9): WhatsApp "cucian siap diambil" dengan tautan lacak `/s/{kode}` (teks, atau templat resmi
 * `NamaTemplatLaundrySiap` bila diatur). Payload hanya `IdTenant` & `IdTiket`; nomor pelanggan tidak ikut antrean
 * maupun log. Dikirim sekali (`NotifikasiSiapPada`); tiket yang sudah diambil/dibatalkan dilewati.
 */
final class KirimNotifikasiLaundrySiapTugas implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public readonly int $idTenant,
        public readonly int $idTiket,
    ) {}

    public function handle(KonteksTenant $konteks, PembuatPengirimWhatsapp $whatsapp, ProfilTenant $profil): void
    {
        $sebelumnya = $konteks->Ambil();
        $konteks->Atur($this->idTenant);

        try {
            $t = TiketLaundry::query()->find($this->idTiket);
            $pengirim = $whatsapp->AmbilAktif();

            if ($t === null || $t->NoHp === null || $t->NotifikasiSiapPada !== null || $t->Status !== StatusLaundry::Siap || $pengirim === null) {
                return;
            }

            $toko = $profil->Ambil($this->idTenant)['Nama'];
            $tautan = url('/s/'.KodeStrukDigital::Buat($this->idTenant, $t->Uuid));
            $templat = $pengirim->CekResmi() ? $whatsapp->AmbilTemplatLaundrySiap() : null;
            $hasil = $pengirim->Kirim(new PesanWhatsapp(
                $t->NoHp,
                "Halo {$t->NamaPelanggan}, cucian {$t->Nomor} di {$toko} sudah siap diambil.\nLihat status: {$tautan}",
                $templat,
                $templat === null ? [] : [$toko, $t->Nomor, $tautan],
            ));

            if ($hasil->berhasil) {
                $t->NotifikasiSiapPada = now();
                $t->save();

                return;
            }

            Log::warning('Notifikasi laundry siap gagal dikirim.', ['IdTenant' => $this->idTenant, 'IdTiket' => $t->Id, 'Penyedia' => $pengirim->AmbilKode()]);
        } finally {
            $sebelumnya === null ? $konteks->Kosongkan() : $konteks->Atur($sebelumnya);
        }
    }
}

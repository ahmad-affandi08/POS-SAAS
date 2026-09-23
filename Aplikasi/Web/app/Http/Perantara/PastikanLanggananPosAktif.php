<?php

declare(strict_types=1);

namespace App\Http\Perantara;

use App\Domain\Tenant\Kueri\StatusLanggananTenant;
use App\Http\Respons\GalatApi;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * POS terkunci bila langganan Ditangguhkan/Berhenti (F-00 state machine; tenant yang turun ke paket Gratis boleh
 * berjualan lagi). Dipasang setelah `AutentikasiPerangkat` pada endpoint berjualan. `konfigurasi-aplikasi` tidak
 * memakainya agar aplikasi tetap bisa menampilkan status langganan.
 *
 * TODO F-07: `sinkron/kirim` tidak memakai perantara ini; transaksi offline yang sudah dibuat tetap diterima.
 */
final class PastikanLanggananPosAktif
{
    public function __construct(private readonly StatusLanggananTenant $statusLangganan) {}

    public function handle(Request $request, Closure $next): Response
    {
        $perangkat = AutentikasiPerangkat::AmbilPerangkat($request);
        $status = $this->statusLangganan->Ambil($perangkat->IdTenant);

        if (! $this->statusLangganan->CekBolehBertransaksiPos($status)) {
            return GalatApi::Buat('LanggananTidakAktif', 'Langganan usaha ini sedang ditangguhkan, jadi aplikasi kasir terkunci. Pemilik bisa membayar tagihan atau pindah ke paket Gratis di menu Langganan.', 403, [
                'StatusLangganan' => $status?->value,
            ]);
        }

        return $next($request);
    }
}

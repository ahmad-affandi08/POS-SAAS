<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\GerbangPembayaran\Adaptor;

use App\Domain\Integrasi\GerbangPembayaran\GalatGerbang;
use App\Domain\Integrasi\GerbangPembayaran\GerbangPembayaran;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Dasar adaptor gerbang: pengaturan & kredensial dari konfigurasi aktif P-05, mode Sandbox/Produksi, HTTP dengan
 * batas waktu, dan pesan galat yang disaring dari kredensial.
 */
abstract class AdaptorDasar implements GerbangPembayaran
{
    /**
     * @param  array<string, string|int>  $pengaturan
     * @param  array<string, string>  $kredensial
     */
    public function __construct(protected readonly array $pengaturan, protected readonly array $kredensial) {}

    protected function CekSandbox(): bool
    {
        return ($this->pengaturan['Mode'] ?? 'Sandbox') !== 'Produksi';
    }

    protected function Pengaturan(string $kunci): string
    {
        return trim((string) ($this->pengaturan[$kunci] ?? ''));
    }

    protected function Kredensial(string $kunci): string
    {
        return $this->kredensial[$kunci] ?? '';
    }

    protected function Http(): PendingRequest
    {
        return Http::timeout(15)->acceptJson();
    }

    /**
     * @param  callable(): Response  $kirim
     */
    protected function Kirim(callable $kirim): Response
    {
        try {
            return $kirim();
        } catch (ConnectionException) {
            throw new GalatGerbang('Gerbang pembayaran tidak bisa dihubungi. Coba lagi sebentar lagi.');
        }
    }

    protected function Saring(string $pesan): string
    {
        foreach ($this->kredensial as $rahasia) {
            if ($rahasia !== '') {
                $pesan = str_replace($rahasia, '••••', $pesan);
            }
        }

        return mb_substr($pesan, 0, 300);
    }

    protected function GagalRespons(Response $respons, string $pesanPenyedia): GalatGerbang
    {
        return new GalatGerbang($this->Saring("Gerbang menolak permintaan (HTTP {$respons->status()}): {$pesanPenyedia}"));
    }
}

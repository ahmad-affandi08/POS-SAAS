<?php

declare(strict_types=1);

namespace App\Domain\Bersama\Sinkron\Layanan;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Sinkron\Data\DataKonteksSinkron;
use App\Domain\Bersama\Sinkron\Data\HasilItemSinkron;
use App\Domain\Bersama\Sinkron\Enum\StatusItemSinkron;
use App\Domain\Bersama\Sinkron\Kontrak\PenanganItemSinkron;
use Illuminate\Contracts\Container\Container;

/**
 * Memproses batch outbox POS berurutan (PRD §18: FIFO per perangkat, shift sebelum transaksinya). Setiap item
 * diteruskan ke penangan sesuai `Jenis`; pelanggaran aturan bisnis menjadi `Ditolak` tanpa menghentikan item
 * berikutnya. Galat tak terduga dibiarkan naik (HTTP 500): perangkat mengirim ulang seluruh batch dan item yang sudah
 * diterima kembali sebagai `Duplikat`, sehingga aman.
 */
final class PemrosesSinkron
{
    /** @var array<string, PenanganItemSinkron>|null */
    private ?array $penangan = null;

    public function __construct(private readonly Container $container) {}

    /**
     * @param  list<array{Jenis: string, Uuid: string, Data: array<string, mixed>}>  $item
     * @return list<HasilItemSinkron>
     */
    public function Proses(array $item, DataKonteksSinkron $konteks): array
    {
        $hasil = [];

        foreach ($item as $satu) {
            $hasil[] = $this->ProsesSatu($satu, $konteks);
        }

        return $hasil;
    }

    /**
     * @param  array{Jenis: string, Uuid: string, Data: array<string, mixed>}  $item
     */
    private function ProsesSatu(array $item, DataKonteksSinkron $konteks): HasilItemSinkron
    {
        $penangan = $this->AmbilPenangan()[$item['Jenis']] ?? null;

        try {
            if ($penangan === null) {
                throw new PelanggaranAturanBisnis('JenisItemTidakDikenal', "Jenis data \"{$item['Jenis']}\" tidak dikenal server. Perbarui aplikasi kasir.", 'Jenis');
            }

            return new HasilItemSinkron($item['Uuid'], $item['Jenis'], $penangan->Proses($item['Uuid'], $item['Data'], $konteks));
        } catch (PelanggaranAturanBisnis $galat) {
            return new HasilItemSinkron($item['Uuid'], $item['Jenis'], StatusItemSinkron::Ditolak, [
                'Kode' => $galat->kode,
                'Pesan' => $galat->getMessage(),
                'Bidang' => $galat->bidang,
                'Detail' => $galat->detail,
            ]);
        }
    }

    /**
     * @return array<string, PenanganItemSinkron>
     */
    private function AmbilPenangan(): array
    {
        if ($this->penangan === null) {
            $this->penangan = [];

            foreach ($this->container->tagged(PenanganItemSinkron::TAG) as $penangan) {
                if ($penangan instanceof PenanganItemSinkron) {
                    $this->penangan[$penangan->AmbilJenis()] = $penangan;
                }
            }
        }

        return $this->penangan;
    }
}

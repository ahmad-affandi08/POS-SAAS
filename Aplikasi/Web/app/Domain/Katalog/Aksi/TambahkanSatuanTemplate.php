<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Katalog\Data\DataSatuanStandar;
use App\Domain\Katalog\Model\Satuan;
use Illuminate\Support\Facades\DB;

/**
 * F-01 (BR-01.1): satuan standar template sektor disalin ke `Satuan` tenant. Aditif & idempoten per `KodeStandar`;
 * satuan yang ada tidak diubah. Pemanggil sudah memegang kunci Tenant.
 */
final class TambahkanSatuanTemplate
{
    public function __construct(private readonly PencatatAudit $audit) {}

    /**
     * @param  list<DataSatuanStandar>  $satuan
     * @return list<string> kode standar yang ditambahkan
     */
    public function Jalankan(array $satuan): array
    {
        return DB::transaction(function () use ($satuan): array {
            $ada = array_map('strval', Satuan::query()->whereNotNull('KodeStandar')->pluck('KodeStandar')->all());
            $ditambahkan = [];

            foreach ($satuan as $data) {
                if (in_array($data->kode, $ada, true)) {
                    continue;
                }

                self::Buat($data);
                $ada[] = $data->kode;
                $ditambahkan[] = $data->kode;
            }

            if ($ditambahkan !== []) {
                $this->audit->Catat('satuan.tambah-template', nilaiBaru: ['KodeStandar' => $ditambahkan]);
            }

            return $ditambahkan;
        });
    }

    public static function Buat(DataSatuanStandar $data): Satuan
    {
        return Satuan::query()->create([
            'KodeStandar' => $data->kode,
            'Nama' => $data->nama,
            'Simbol' => $data->simbol,
            'BolehDesimal' => $data->bolehDesimal,
        ]);
    }
}

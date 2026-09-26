<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Penjualan\Enum\StatusPesananSendiri;
use Illuminate\Support\Carbon;

/**
 * Pesanan tamu dari QR meja (F-17 Self-Order, X12, SLS-04). `Uuid` dari peramban (idempoten, unik per tenant).
 * `Baris` = salinan baris berharga server (lihat migrasi). Tidak menyentuh stok/jurnal: setelah diterima, perangkat
 * POS membuat pesanan terbuka lewat outbox-nya (`UuidPesananTerbuka`).
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdOutlet
 * @property int $IdMeja
 * @property string $Nomor
 * @property string|null $NamaPemesan
 * @property string|null $Catatan
 * @property list<array{Uuid: string, UuidProduk: string, UuidProdukSatuan: string, NamaProduk: string, Jumlah: string, HargaSatuan: string, HargaPilihan: string, Pilihan: list<array{UuidPilihan: string, Nama: string, Harga: string}>, Catatan: string|null}> $Baris
 * @property string $Subtotal
 * @property StatusPesananSendiri $Status
 * @property string|null $UuidPesananTerbuka
 * @property int|null $IdPemroses
 * @property int|null $IdPerangkat
 * @property Carbon|null $DiprosesPada
 * @property string|null $AlasanTolak
 * @property string|null $HashIp
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 */
final class PesananSendiri extends ModelDasar
{
    use MilikTenant;

    protected $table = 'PesananSendiri';

    /** @var array<string, mixed> */
    protected $attributes = [
        'NamaPemesan' => null,
        'Catatan' => null,
        'UuidPesananTerbuka' => null,
        'IdPemroses' => null,
        'IdPerangkat' => null,
        'DiprosesPada' => null,
        'AlasanTolak' => null,
        'HashIp' => null,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Baris' => 'array',
            'Subtotal' => 'decimal:2',
            'Status' => StatusPesananSendiri::class,
            'DiprosesPada' => 'datetime',
        ];
    }
}

<?php

declare(strict_types=1);

use App\Domain\Pemenuhan\Enum\StatusPengirimanPesanan;
use App\Domain\Penjualan\Enum\StatusPesananOnline;

it('menjaga transisi status pesanan online', function (): void {
    expect(StatusPesananOnline::MenungguKonfirmasi->BisaBerubahKe(StatusPesananOnline::Dikonfirmasi))->toBeTrue()
        ->and(StatusPesananOnline::MenungguKonfirmasi->BisaBerubahKe(StatusPesananOnline::Selesai))->toBeFalse()
        ->and(StatusPesananOnline::Dikonfirmasi->BisaBerubahKe(StatusPesananOnline::Diproses))->toBeTrue()
        ->and(StatusPesananOnline::Diproses->BisaBerubahKe(StatusPesananOnline::Siap))->toBeTrue()
        ->and(StatusPesananOnline::Selesai->BisaBerubahKe(StatusPesananOnline::Dibatalkan))->toBeFalse();
});

it('menjaga urutan fulfillment kurir dan mengizinkan kirim ulang setelah gagal', function (): void {
    expect(StatusPengirimanPesanan::SiapKemas->BisaBerubahKe(StatusPengirimanPesanan::Dikemas))->toBeTrue()
        ->and(StatusPengirimanPesanan::SiapKemas->BisaBerubahKe(StatusPengirimanPesanan::Diterima))->toBeFalse()
        ->and(StatusPengirimanPesanan::Dikemas->BisaBerubahKe(StatusPengirimanPesanan::Dikirim))->toBeTrue()
        ->and(StatusPengirimanPesanan::Dikirim->BisaBerubahKe(StatusPengirimanPesanan::Gagal))->toBeTrue()
        ->and(StatusPengirimanPesanan::Gagal->BisaBerubahKe(StatusPengirimanPesanan::Dikirim))->toBeTrue()
        ->and(StatusPengirimanPesanan::Diterima->BisaBerubahKe(StatusPengirimanPesanan::Dikirim))->toBeFalse();
});

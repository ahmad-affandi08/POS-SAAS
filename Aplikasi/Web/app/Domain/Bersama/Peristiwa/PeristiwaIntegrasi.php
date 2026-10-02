<?php

declare(strict_types=1);

namespace App\Domain\Bersama\Peristiwa;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Str;

/**
 * Peristiwa bisnis untuk integrasi pihak ketiga (X7 §16.4: `produk.diubah`, `pelanggan.dibuat`, `shift.ditutup`,
 * `stok.disesuaikan`, `pesanan-pembelian.disetujui`, `penerimaan-barang.diposting`). Dikirim domain pemiliknya setelah
 * commit; `data` disusun domain itu sendiri dalam bentuk publik (Uuid saja, tanpa Id internal, uang/jumlah string
 * desimal) sehingga penerima (webhook keluar) tidak perlu membaca tabel domain lain. `kunci` unik per kejadian (ULID)
 * agar kejadian berulang pada dokumen yang sama (misal produk diubah dua kali) tetap jadi dua kiriman, tetapi penangan
 * yang diulang antrean tidak menggandakannya.
 */
final class PeristiwaIntegrasi implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public readonly string $kunci;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly int $idTenant,
        public readonly string $jenis,
        public readonly int $idDokumen,
        public readonly array $data,
        ?string $kunci = null,
    ) {
        $this->kunci = $kunci ?? (string) Str::ulid();
    }
}

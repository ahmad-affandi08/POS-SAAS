<?php

declare(strict_types=1);

namespace App\Http\Kontroler\ApiPublik\V1;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Katalog\Enum\PelacakanProduk;
use App\Domain\Katalog\Kueri\InfoProdukStok;
use App\Domain\Organisasi\Kueri\InfoGudang;
use App\Domain\Persediaan\Aksi\AjukanPenyesuaianStok;
use App\Domain\Persediaan\Aksi\SimpanPenyesuaianStok;
use App\Domain\Persediaan\Data\DataBarisDokumenStok;
use App\Domain\Persediaan\Data\DataPenyesuaianStok;
use App\Domain\Persediaan\Enum\AlasanPenyesuaian;
use App\Domain\Persediaan\Enum\StatusPenyesuaianStok;
use App\Domain\Persediaan\Kueri\PenyesuaianStokUntukApiPublik;
use App\Http\Perantara\AutentikasiTokenApi;
use App\Http\Permintaan\ApiPublik\BuatPenyesuaianStokApiPermintaan;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

/**
 * X7 bagian 4 — endpoint tulis Open API v1 (PRD §16.1 cakupan `stok:tulis`): sistem lain (WMS, marketplace, ERP)
 * membuat **penyesuaian stok** lewat aksi yang sama dengan back-office (`SimpanPenyesuaianStok` + `AjukanPenyesuaianStok`):
 * validasi baris, HPP masuk, batch, batas persetujuan nilai (di atas batas = `MenungguPersetujuan`, disetujui orang di
 * back-office), mutasi & jurnal di transaksi yang sama. Pelaku = Owner pembuat token; audit mencatat asalnya. Idempoten
 * per `Uuid` dokumen. Produk bernomor seri belum didukung lewat API (pilih unit di back-office).
 */
final class StokKontroler
{
    public function BuatPenyesuaian(
        BuatPenyesuaianStokApiPermintaan $permintaan,
        InfoGudang $gudang,
        InfoProdukStok $infoProduk,
        PenyesuaianStokUntukApiPublik $kueri,
        SimpanPenyesuaianStok $simpan,
        AjukanPenyesuaianStok $ajukan,
        PencatatAudit $audit,
    ): JsonResponse {
        $token = AutentikasiTokenApi::AmbilToken($permintaan);
        $audit->AturKonteks($token->DibuatOleh, $permintaan->ip(), mb_substr("Token API {$token->Prefiks} ({$token->Nama})", 0, 255));
        $uuid = strtoupper((string) $permintaan->validated('Uuid'));
        $ada = $kueri->Satu($uuid);

        if ($ada !== null && $ada['Status'] !== StatusPenyesuaianStok::Draf->value) {
            return response()->json(['Data' => $ada]);
        }

        $uuidGudang = strtoupper((string) $permintaan->validated('UuidGudang'));
        $lokasi = $gudang->AmbilDariUuid([$uuidGudang])[$uuidGudang] ?? throw new PelanggaranAturanBisnis('GudangTidakDikenal', 'Lokasi stok tidak ditemukan.', 'UuidGudang');
        /** @var list<array<string, mixed>> $barisMasuk */
        $barisMasuk = array_values((array) $permintaan->validated('Baris'));
        $produk = $infoProduk->AmbilDariUuid(array_values(array_map(fn (array $b): string => strtoupper((string) $b['UuidProduk']), $barisMasuk)));
        $baris = [];

        foreach ($barisMasuk as $i => $b) {
            $p = $produk[strtoupper((string) $b['UuidProduk'])] ?? throw new PelanggaranAturanBisnis('ProdukTidakDikenal', 'Produk baris ke-'.($i + 1).' tidak ditemukan.', "Baris.{$i}.UuidProduk");

            if ($p->pelacakan === PelacakanProduk::Seri) {
                throw new PelanggaranAturanBisnis('PelacakanBelumDidukung', "{$p->nama} bernomor seri; sesuaikan per unit dari back-office.", "Baris.{$i}.UuidProduk");
            }

            $jumlah = Kuantitas::Dari((string) $b['Jumlah']);
            $nomorBatch = isset($b['NomorBatch']) && is_string($b['NomorBatch']) && trim($b['NomorBatch']) !== '' ? trim($b['NomorBatch']) : null;
            $keluar = $jumlah->KeDesimal()->isNegative();
            $idBatch = null;

            if ($keluar && $nomorBatch !== null) {
                $idBatch = $kueri->CariIdBatch($p->id, $lokasi->id, $nomorBatch)
                    ?? throw new PelanggaranAturanBisnis('BatchTidakDikenal', "Batch {$nomorBatch} {$p->nama} tidak ada di lokasi ini.", "Baris.{$i}.NomorBatch");
            }

            $baris[] = new DataBarisDokumenStok(
                idProduk: $p->id,
                jumlah: $jumlah,
                hppSatuan: isset($b['HppSatuan']) && is_string($b['HppSatuan']) ? BigDecimal::of($b['HppSatuan']) : null,
                idBatchStok: $idBatch,
                nomorBatch: $keluar ? null : $nomorBatch,
                tanggalKedaluwarsa: ! $keluar && isset($b['TanggalKedaluwarsa']) && is_string($b['TanggalKedaluwarsa']) ? CarbonImmutable::parse($b['TanggalKedaluwarsa']) : null,
            );
        }

        $draf = $simpan->Jalankan(new DataPenyesuaianStok(
            uuid: $uuid,
            idGudang: $lokasi->id,
            tanggal: CarbonImmutable::parse((string) $permintaan->validated('Tanggal')),
            alasan: AlasanPenyesuaian::from((string) $permintaan->validated('Alasan')),
            keterangan: is_string($permintaan->validated('Keterangan')) ? $permintaan->validated('Keterangan') : null,
            baris: $baris,
        ), null);
        $ajukan->Jalankan($draf, $token->DibuatOleh);

        return response()->json(['Data' => $kueri->Satu($uuid)], $ada === null ? 201 : 200);
    }
}

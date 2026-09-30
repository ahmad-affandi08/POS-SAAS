<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Organisasi\Model\Outlet;
use App\Domain\Penjualan\Model\ZonaPengiriman;
use Illuminate\Support\Facades\DB;

final class SimpanZonaPengiriman
{
    public function __construct(private readonly PencatatAudit $audit) {}

    /** @param array<string, mixed> $data */
    public function Jalankan(Outlet $outlet, array $data, int $idPengguna, ?ZonaPengiriman $zona = null): ZonaPengiriman
    {
        $kodePos = array_values(array_unique(array_map('strval', (array) $data['KodePos'])));
        foreach ($kodePos as $kode) {
            if (preg_match('/^\d{5}$/', $kode) !== 1) {
                throw new PelanggaranAturanBisnis('KodePosTidakValid', 'Setiap kode pos harus tepat 5 digit.', 'KodePos');
            }
        }
        if ($kodePos === []) {
            throw new PelanggaranAturanBisnis('KodePosWajib', 'Isi minimal satu kode pos.', 'KodePos');
        }

        $idZona = $zona?->Id;
        $tumpangTindih = ZonaPengiriman::query()->where('IdOutlet', $outlet->Id)->where('Aktif', true)
            ->when($idZona !== null, fn ($q) => $q->whereKeyNot($idZona))->get()
            ->first(fn (ZonaPengiriman $z): bool => array_intersect($kodePos, $z->KodePos) !== []);
        if ($tumpangTindih instanceof ZonaPengiriman && (bool) $data['Aktif']) {
            throw new PelanggaranAturanBisnis('ZonaTumpangTindih', "Kode pos sudah dipakai zona {$tumpangTindih->Nama}.", 'KodePos');
        }

        return DB::transaction(function () use ($outlet, $data, $idPengguna, $zona, $kodePos): ZonaPengiriman {
            $baris = $zona ?? new ZonaPengiriman;
            $lama = $zona?->toArray();
            $baris->fill([...$data, 'IdOutlet' => $outlet->Id, 'KodePos' => $kodePos]);
            $baris->save();
            $this->audit->Catat($zona === null ? 'zona-pengiriman.buat' : 'zona-pengiriman.ubah', $baris, $lama, $baris->only(['Nama', 'KodePos', 'Ongkir', 'GratisMulai', 'EstimasiHariMin', 'EstimasiHariMaks', 'Aktif']), idPengguna: $idPengguna);

            return $baris;
        });
    }
}

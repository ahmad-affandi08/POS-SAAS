<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Impor\Kueri;

use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Bersama\Tabel\Layanan\PenerapKueriTabel;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Organisasi\Kueri\DaftarAnggota;
use App\Domain\Organisasi\Kueri\InfoGudang;
use App\Domain\Persediaan\Enum\StatusBarisImporStokAwal;
use App\Domain\Persediaan\Enum\StatusImporStokAwal;
use App\Domain\Persediaan\Impor\Aksi\LanjutkanImporStokAwal;
use App\Domain\Persediaan\Model\ImporStokAwal;
use App\Domain\Persediaan\Model\ImporStokAwalBaris;
use Illuminate\Support\Collection;

/**
 * Riwayat impor stok awal tenant (tipe FE `PropsDaftarImporStokAwal['Riwayat']`, DesainF05a E) untuk `TabelData`
 * (D-16), bawaan terbaru dulu. `hanyaPengguna` diisi untuk pelaku yang aksesnya dibatasi per outlet: ia hanya melihat impornya
 * sendiri (impor pengguna lain bisa memuat lokasi stok di luar aksesnya). Juga memetakan satu impor untuk halaman
 * detail & status JSON.
 */
final class DaftarImporStokAwal
{
    public const KOLOM_URUT = ['DibuatPada', 'NamaBerkas'];

    public const KOLOM_SARING = ['Status'];

    public const URUT_BAWAAN = '-DibuatPada';

    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly DaftarAnggota $anggota,
        private readonly InfoGudang $infoGudang,
    ) {}

    /**
     * Riwayat untuk `TabelData` (D-16): cari nama berkas, saring status.
     *
     * @return array{Data: list<array<string, mixed>>, Meta: array{Halaman: int, PerHalaman: int, Total: int, JumlahHalaman: int}}
     */
    public function AmbilTabel(DataPermintaanTabel $permintaan, ?int $hanyaPengguna = null): array
    {
        $status = $permintaan->AmbilDaftar('Status', array_map(fn (StatusImporStokAwal $s): string => $s->value, StatusImporStokAwal::cases()));
        $kueri = ImporStokAwal::query()
            ->when($hanyaPengguna !== null, fn ($kueri) => $kueri->where('IdPengguna', $hanyaPengguna))
            ->when($permintaan->cari !== '', fn ($k) => $k->where('NamaBerkas', 'like', PenerapKueriTabel::PolaCari($permintaan->cari)))
            ->when($status !== [], fn ($k) => $k->whereIn('Status', $status));

        return PenerapKueriTabel::Terapkan($kueri, $permintaan, ['DibuatPada' => 'DibuatPada', 'NamaBerkas' => 'NamaBerkas'], fn (Collection $impor): array => $this->Petakan(array_values($impor->all())));
    }

    /**
     * @return array<string, mixed>
     */
    public function AmbilRingkasan(ImporStokAwal $impor): array
    {
        return $this->Petakan([$impor])[0];
    }

    /**
     * @param  list<ImporStokAwal>  $impor
     * @return list<array<string, mixed>>
     */
    public function Petakan(array $impor): array
    {
        if ($impor === []) {
            return [];
        }

        $nama = $this->anggota->AmbilNamaPengguna($this->konteks->Wajib(), array_values(array_unique(array_map(fn (ImporStokAwal $i): int => $i->IdPengguna, $impor))));
        $gudang = $this->infoGudang->AmbilBanyak(array_values(array_unique(array_filter(array_map(fn (ImporStokAwal $i): ?int => $i->IdGudangBawaan, $impor)))));
        $pernah = $this->AmbilPernahDiimpor($impor);

        return array_map(fn (ImporStokAwal $i): array => [
            'Uuid' => $i->Uuid,
            'NamaBerkas' => $i->NamaBerkas,
            'Status' => $i->Status->value,
            'LabelStatus' => $i->Status->AmbilLabel(),
            'JumlahBaris' => $i->JumlahBaris,
            'JumlahValid' => $i->JumlahValid,
            'JumlahGalat' => $i->JumlahGalat,
            'JumlahDokumen' => $i->JumlahDokumen,
            'Progres' => self::HitungProgres($i),
            'PesanGalat' => $i->PesanGalat,
            'NamaGudangBawaan' => $i->IdGudangBawaan === null ? null : ($gudang[$i->IdGudangBawaan]->nama ?? null),
            'BerkasPernahDiimpor' => $pernah[$i->Id] ?? null,
            'BolehLanjutkan' => LanjutkanImporStokAwal::CekBolehLanjutkan($i),
            'DibuatPada' => $i->DibuatPada?->toIso8601String() ?? '',
            'SelesaiPada' => $i->SelesaiPada?->toIso8601String(),
            'NamaPengguna' => $nama[$i->IdPengguna] ?? null,
        ], $impor);
    }

    /** Persen 0–100 (bilangan bulat): validasi = baris terbaca, pembuatan draf = baris valid yang sudah masuk draf. */
    public static function HitungProgres(ImporStokAwal $impor): int
    {
        return match ($impor->Status) {
            StatusImporStokAwal::Memvalidasi => $impor->JumlahBaris === 0 ? 0
                : min(99, intdiv(ImporStokAwalBaris::query()->where('IdImporStokAwal', $impor->Id)->count() * 100, $impor->JumlahBaris)),
            StatusImporStokAwal::Menerapkan, StatusImporStokAwal::Gagal => $impor->JumlahValid === 0 ? 0
                : min($impor->Status === StatusImporStokAwal::Menerapkan ? 99 : 100, intdiv(
                    ImporStokAwalBaris::query()->where('IdImporStokAwal', $impor->Id)->where('Status', StatusBarisImporStokAwal::Diterapkan->value)->count() * 100,
                    $impor->JumlahValid,
                )),
            StatusImporStokAwal::Pratinjau, StatusImporStokAwal::Selesai => 100,
            default => 0,
        };
    }

    /**
     * Waktu terakhir berkas dengan hash sama pernah dibuatkan draf (≤ masa simpan), selain impor itu sendiri.
     *
     * @param  list<ImporStokAwal>  $impor
     * @return array<int, string>
     */
    private function AmbilPernahDiimpor(array $impor): array
    {
        $lain = ImporStokAwal::query()
            ->whereIn('HashBerkas', array_values(array_unique(array_map(fn (ImporStokAwal $i): string => $i->HashBerkas, $impor))))
            ->whereNotNull('DiterapkanPada')
            ->where('DibuatPada', '>=', now()->subDays((int) config('persediaan.Impor.HariSimpan', 30)))
            ->orderByDesc('DiterapkanPada')
            ->get(['Id', 'HashBerkas', 'DiterapkanPada']);
        $hasil = [];

        foreach ($impor as $satu) {
            $cocok = $lain->first(fn (ImporStokAwal $l): bool => $l->HashBerkas === $satu->HashBerkas && $l->Id !== $satu->Id && ($satu->DiterapkanPada === null || $l->Id < $satu->Id));

            if ($cocok !== null && $cocok->DiterapkanPada !== null) {
                $hasil[$satu->Id] = $cocok->DiterapkanPada->toIso8601String();
            }
        }

        return $hasil;
    }
}

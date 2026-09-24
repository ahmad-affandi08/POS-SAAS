<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Impor\Kueri;

use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Bersama\Tabel\Layanan\PenerapKueriTabel;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Impor\Aksi\LanjutkanImporProduk;
use App\Domain\Katalog\Impor\Enum\StatusImporProduk;
use App\Domain\Katalog\Impor\Layanan\PembacaPresetImpor;
use App\Domain\Katalog\Impor\Model\ImporProduk;
use App\Domain\Katalog\Impor\Model\ImporProdukBaris;
use App\Domain\Organisasi\Kueri\DaftarAnggota;
use Illuminate\Support\Collection;

/**
 * Riwayat impor produk tenant (F-03 E.10, tipe FE `RingkasanImpor`) untuk `TabelData` (D-16), bawaan terbaru dulu. Juga dipakai
 * halaman detail & status JSON untuk satu impor.
 */
final class DaftarImporProduk
{
    public const KOLOM_URUT = ['DibuatPada', 'NamaBerkas'];

    public const KOLOM_SARING = ['Status'];

    public const URUT_BAWAAN = '-DibuatPada';

    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PembacaPresetImpor $preset,
        private readonly DaftarAnggota $anggota,
    ) {}

    /**
     * Riwayat untuk `TabelData` (D-16): cari nama berkas, saring status.
     *
     * @return array{Data: list<array<string, mixed>>, Meta: array{Halaman: int, PerHalaman: int, Total: int, JumlahHalaman: int}}
     */
    public function AmbilTabel(DataPermintaanTabel $permintaan): array
    {
        $status = $permintaan->AmbilDaftar('Status', array_map(fn (StatusImporProduk $s): string => $s->value, StatusImporProduk::cases()));
        $kueri = ImporProduk::query()
            ->when($permintaan->cari !== '', fn ($k) => $k->where('NamaBerkas', 'like', PenerapKueriTabel::PolaCari($permintaan->cari)))
            ->when($status !== [], fn ($k) => $k->whereIn('Status', $status));

        return PenerapKueriTabel::Terapkan($kueri, $permintaan, ['DibuatPada' => 'DibuatPada', 'NamaBerkas' => 'NamaBerkas'], fn (Collection $impor): array => $this->Petakan(array_values($impor->all())));
    }

    /**
     * @param  list<ImporProduk>  $impor
     * @return list<array<string, mixed>>
     */
    public function Petakan(array $impor): array
    {
        $nama = $this->anggota->AmbilNamaPengguna($this->konteks->Wajib(), array_values(array_unique(array_map(fn (ImporProduk $i): int => $i->IdPengguna, $impor))));
        $pernah = $this->AmbilPernahDiimpor($impor);

        return array_map(fn (ImporProduk $i): array => $this->PetakanSatu($i, $nama[$i->IdPengguna] ?? null, $pernah[$i->Id] ?? null), $impor);
    }

    /**
     * @return array<string, mixed>
     */
    public function AmbilRingkasan(ImporProduk $impor): array
    {
        return $this->Petakan([$impor])[0];
    }

    /**
     * @return array<string, mixed>
     */
    private function PetakanSatu(ImporProduk $impor, ?string $namaPengguna, ?string $pernahDiimpor): array
    {
        return [
            'Uuid' => $impor->Uuid,
            'NamaBerkas' => $impor->NamaBerkas,
            'Sumber' => $impor->Sumber,
            'LabelSumber' => $this->preset->Cari($impor->Sumber)->nama ?? $impor->Sumber,
            'Status' => $impor->Status->value,
            'LabelStatus' => $impor->Status->AmbilLabel(),
            'JumlahBaris' => $impor->JumlahBaris,
            'JumlahValid' => $impor->JumlahValid,
            'JumlahGalat' => $impor->JumlahGalat,
            'JumlahDiterapkan' => $impor->JumlahDiterapkan,
            'JumlahDibuat' => $impor->JumlahDibuat,
            'JumlahDiperbarui' => $impor->JumlahDiperbarui,
            'JumlahDilewati' => $impor->JumlahDilewati,
            'JumlahGagal' => $impor->JumlahGagal,
            'Progres' => self::HitungProgres($impor),
            'PesanGalat' => $impor->PesanGalat,
            'BerkasPernahDiimpor' => $pernahDiimpor,
            'BolehLanjutkan' => LanjutkanImporProduk::CekBolehLanjutkan($impor),
            'DibuatPada' => $impor->DibuatPada?->toIso8601String() ?? '',
            'SelesaiPada' => $impor->SelesaiPada?->toIso8601String(),
            'NamaPengguna' => $namaPengguna,
        ];
    }

    /** Persen 0–100 (bilangan bulat): validasi = baris terbaca, penerapan = baris diproses dari baris valid. */
    public static function HitungProgres(ImporProduk $impor): int
    {
        return match ($impor->Status) {
            StatusImporProduk::Memvalidasi => $impor->JumlahBaris === 0 ? 0
                : min(99, intdiv(ImporProdukBaris::query()->where('IdImporProduk', $impor->Id)->count() * 100, $impor->JumlahBaris)),
            StatusImporProduk::Menerapkan, StatusImporProduk::Gagal => $impor->JumlahValid === 0 ? 0
                : min($impor->Status === StatusImporProduk::Menerapkan ? 99 : 100, intdiv(($impor->JumlahDiterapkan + $impor->JumlahGagal) * 100, $impor->JumlahValid)),
            StatusImporProduk::Pratinjau, StatusImporProduk::Selesai => 100,
            default => 0,
        };
    }

    /**
     * Tanggal terakhir berkas dengan hash sama pernah diterapkan (≤ masa simpan), selain impor itu sendiri.
     *
     * @param  list<ImporProduk>  $impor
     * @return array<int, string>
     */
    private function AmbilPernahDiimpor(array $impor): array
    {
        if ($impor === []) {
            return [];
        }

        $lain = ImporProduk::query()
            ->whereIn('HashBerkas', array_values(array_unique(array_map(fn (ImporProduk $i): string => $i->HashBerkas, $impor))))
            ->whereNotNull('DiterapkanMulaiPada')
            ->where('DibuatPada', '>=', now()->subDays((int) config('katalog.Impor.HariSimpan', 30)))
            ->orderByDesc('DiterapkanMulaiPada')
            ->get(['Id', 'HashBerkas', 'DiterapkanMulaiPada']);
        $hasil = [];

        foreach ($impor as $satu) {
            $cocok = $lain->first(fn (ImporProduk $l): bool => $l->HashBerkas === $satu->HashBerkas && $l->Id !== $satu->Id && ($satu->DiterapkanMulaiPada === null || $l->Id < $satu->Id));

            if ($cocok !== null && $cocok->DiterapkanMulaiPada !== null) {
                $hasil[$satu->Id] = $cocok->DiterapkanMulaiPada->toIso8601String();
            }
        }

        return $hasil;
    }
}

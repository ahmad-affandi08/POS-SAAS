<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Tagihan\Kueri;

use App\Domain\Bersama\Tenant\LingkupTenant;
use App\Domain\Tenant\Enum\StatusPembayaranLangganan;
use App\Domain\Tenant\Enum\StatusTagihanLangganan;
use App\Domain\Tenant\Kueri\TagihanLanggananTenant;
use App\Domain\Tenant\Model\PembayaranLangganan;
use App\Domain\Tenant\Model\TagihanLangganan;
use App\Domain\Tenant\Model\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tagihan & pembayaran langganan lintas tenant untuk Keuangan/Super Admin (P-08). Tagihan adalah data platform ke
 * tenant, bukan data operasional tenant; lingkup tenant dilepas hanya di sini (CLAUDE.md #11, Domain/Pengelola).
 * Data yang tampil terbatas pada nama usaha, angka tagihan, dan bukti transfer.
 */
final class DaftarTagihanPlatform
{
    private const PER_HALAMAN = 30;

    /**
     * @return Builder<TagihanLangganan>
     */
    public static function KueriTagihan(): Builder
    {
        return TagihanLangganan::query()->withoutGlobalScope(LingkupTenant::class);
    }

    /**
     * @return Builder<PembayaranLangganan>
     */
    public static function KueriPembayaran(): Builder
    {
        return PembayaranLangganan::query()->withoutGlobalScope(LingkupTenant::class);
    }

    /**
     * Antrean "Menunggu Verifikasi" (P-08 langkah 3), yang terlama di atas.
     *
     * @return list<array<string, mixed>>
     */
    public function AmbilAntrean(): array
    {
        $pembayaran = self::KueriPembayaran()
            ->where('Status', StatusPembayaranLangganan::Menunggu->value)
            ->orderBy('DibuatPada')
            ->orderBy('Id')
            ->limit(100)
            ->get();
        $tagihan = self::KueriTagihan()->with('Paket')->whereKey($pembayaran->pluck('IdTagihanLangganan')->all())->get()->keyBy('Id');
        $namaTenant = $this->AmbilNamaTenant($pembayaran->pluck('IdTenant')->all());

        return array_values($pembayaran->map(function (PembayaranLangganan $baris) use ($tagihan, $namaTenant): array {
            $induk = $tagihan->get($baris->IdTagihanLangganan);

            return [
                ...TagihanLanggananTenant::PetakanPembayaran($baris),
                'NamaTenant' => $namaTenant[$baris->IdTenant] ?? '—',
                'UuidTagihan' => $induk?->Uuid,
                'NomorTagihan' => $induk?->Nomor,
                'TotalTagihan' => $induk?->Total,
                'NamaPaket' => $induk?->Paket->Nama,
            ];
        })->all());
    }

    /**
     * @return LengthAwarePaginator<int, TagihanLangganan>
     */
    public function AmbilTagihan(?StatusTagihanLangganan $status, string $kata): LengthAwarePaginator
    {
        $kataAman = addcslashes($kata, '%_\\');

        return self::KueriTagihan()
            ->with('Paket')
            ->when($status !== null, fn ($kueri) => $kueri->where('Status', $status?->value))
            ->when($kata !== '', fn ($kueri) => $kueri->where(fn ($dalam) => $dalam
                ->where('Nomor', 'like', "%{$kataAman}%")
                ->orWhereIn('IdTenant', Tenant::query()->select('Id')->where('Nama', 'like', "%{$kataAman}%"))))
            ->orderByDesc('TerbitPada')
            ->orderByDesc('Id')
            ->paginate(self::PER_HALAMAN, ['*'], 'halaman')
            ->withQueryString();
    }

    public function CariTagihan(string $uuid): ?TagihanLangganan
    {
        return self::KueriTagihan()->with('Paket')->where('Uuid', $uuid)->first();
    }

    public function CariPembayaran(string $uuid): ?PembayaranLangganan
    {
        return self::KueriPembayaran()->where('Uuid', $uuid)->first();
    }

    /**
     * @return list<PembayaranLangganan>
     */
    public function AmbilPembayaranTagihan(TagihanLangganan $tagihan): array
    {
        return array_values(self::KueriPembayaran()
            ->where('IdTagihanLangganan', $tagihan->Id)
            ->orderByDesc('Id')
            ->get()
            ->all());
    }

    /**
     * @param  array<mixed>  $idTenant
     * @return array<int, string>
     */
    public function AmbilNamaTenant(array $idTenant): array
    {
        /** @var array<int, string> $nama */
        $nama = Tenant::query()->whereKey(array_values(array_unique($idTenant)))->pluck('Nama', 'Id')->all();

        return $nama;
    }

    /**
     * @return array<string, int>
     */
    public function HitungRingkasan(): array
    {
        return [
            'MenungguVerifikasi' => self::KueriPembayaran()->where('Status', StatusPembayaranLangganan::Menunggu->value)->count(),
            'BelumDibayar' => self::KueriTagihan()->whereIn('Status', StatusTagihanLangganan::NilaiTerbuka())->count(),
        ];
    }
}

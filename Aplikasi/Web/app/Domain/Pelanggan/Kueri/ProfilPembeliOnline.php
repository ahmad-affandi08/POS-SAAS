<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Kueri;

use App\Domain\Pelanggan\Layanan\BukuPoin;
use App\Domain\Pelanggan\Layanan\NomorHp;
use App\Domain\Pelanggan\Model\Pelanggan;
use App\Domain\Pelanggan\Model\TierPelanggan;

/**
 * F-17 bagian 3: profil pembeli toko online yang sudah masuk, untuk dirinya sendiri. Nomor HP tampil utuh karena ini
 * nomornya sendiri yang baru saja ia buktikan lewat kode WhatsApp. Poin hanya tampil bila program loyalti toko berlaku.
 */
final class ProfilPembeliOnline
{
    public function __construct(
        private readonly PengaturanLoyaltiTenant $loyalti,
        private readonly BukuPoin $poin,
    ) {}

    /**
     * @return array{Uuid: string, Nama: string, NoHp: string, Email: string|null, TanggalLahir: string|null, SetujuPemasaran: bool, Tier: string|null, Poin: int|null}
     */
    public function Ambil(Pelanggan $p): array
    {
        $tier = $p->IdTier === null ? null : TierPelanggan::query()->whereKey($p->IdTier)->value('Nama');

        return [
            'Uuid' => $p->Uuid,
            'Nama' => $p->Nama,
            'NoHp' => NomorHp::Format($p->NoHp),
            'Email' => $p->Email,
            'TanggalLahir' => $p->TanggalLahir?->toDateString(),
            'SetujuPemasaran' => $p->SetujuPemasaran,
            'Tier' => is_string($tier) ? $tier : null,
            'Poin' => $this->loyalti->Ambil()->CekBerlaku() ? $this->poin->AmbilSaldo($p->Id) : null,
        ];
    }
}

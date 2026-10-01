<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Kueri;

use App\Domain\Integrasi\Whatsapp\PesanWhatsapp;
use App\Domain\Pelanggan\Enum\KanalKampanye;
use App\Domain\Pelanggan\Enum\SegmenRfm;
use App\Domain\Pelanggan\Enum\StatusPelanggan;
use App\Domain\Pelanggan\Layanan\PenggolongRfm;
use App\Domain\Pelanggan\Model\Pelanggan;
use App\Domain\Pelanggan\Model\TierPelanggan;
use App\Domain\Penjualan\Kueri\BelanjaPelanggan;
use Carbon\CarbonImmutable;

/**
 * Memilih penerima kampanye CRM-07: hanya pelanggan **Aktif** yang **setuju menerima pemasaran** (UU PDP; pelanggan
 * tanpa persetujuan tidak pernah dihitung), lalu disaring segmen RFM, tier, tag (salah satu), dan ulang tahun bulan ini.
 * Kosong = tanpa saringan. Pelanggan tanpa nomor WhatsApp/email sah untuk kanal itu dihitung `TanpaKontak`.
 */
final class PemilihPenerimaKampanye
{
    public const POLA_NOMOR = '/^628\d{7,12}$/';

    public function __construct(private readonly BelanjaPelanggan $belanja) {}

    /**
     * @param  array{Rfm?: list<string>, UuidTier?: list<string>, Tag?: list<string>, UlangTahunBulanIni?: bool}  $segmen
     * @return array{Penerima: list<array{IdPelanggan: int, Tujuan: string}>, TanpaKontak: int, PerSegmen: array<string, int>}
     */
    public function Susun(array $segmen, KanalKampanye $kanal, CarbonImmutable $hariIni): array
    {
        $bahan = $this->belanja->AmbilBahanRfm($hariIni->subDays(PenggolongRfm::HARI_PERIODE - 1)->toDateString());
        $batasJuara = PenggolongRfm::HitungBatasJuara(array_values(array_map(fn (array $b): string => $b['TotalPeriode'], $bahan)));
        $rfm = array_values(array_filter(array_map(fn (string $s): ?SegmenRfm => SegmenRfm::tryFrom($s), $segmen['Rfm'] ?? [])));
        $tier = ($segmen['UuidTier'] ?? []) === [] ? null
            : TierPelanggan::query()->whereIn('Uuid', $segmen['UuidTier'] ?? [])->pluck('Id')->map(fn ($id): int => (int) $id)->all();
        $tag = array_map(fn (string $t): string => mb_strtolower($t), $segmen['Tag'] ?? []);
        $ulangTahun = ($segmen['UlangTahunBulanIni'] ?? false) === true;
        $perSegmen = array_fill_keys(array_map(fn (SegmenRfm $s): string => $s->value, SegmenRfm::cases()), 0);
        $penerima = [];
        $tanpaKontak = 0;

        Pelanggan::query()
            ->where('Status', StatusPelanggan::Aktif->value)
            ->where('SetujuPemasaran', true)
            ->select(['Id', 'NoHp', 'Email', 'IdTier', 'Tag', 'TanggalLahir'])
            ->chunkById(1000, function ($daftar) use ($bahan, $batasJuara, $hariIni, $rfm, $tier, $tag, $ulangTahun, $kanal, &$perSegmen, &$penerima, &$tanpaKontak): void {
                foreach ($daftar as $p) {
                    /** @var Pelanggan $p */
                    $golongan = PenggolongRfm::Golongkan($bahan[$p->Id] ?? null, $hariIni, $batasJuara);
                    $perSegmen[$golongan->value]++;

                    if (($rfm !== [] && ! in_array($golongan, $rfm, true))
                        || ($tier !== null && ! in_array($p->IdTier, $tier, true))
                        || ($tag !== [] && array_intersect($tag, array_map(fn (string $t): string => mb_strtolower($t), $p->Tag ?? [])) === [])
                        || ($ulangTahun && ($p->TanggalLahir === null || $p->TanggalLahir->month !== $hariIni->month))) {
                        continue;
                    }

                    $tujuan = self::AmbilTujuan($p, $kanal);

                    if ($tujuan === null) {
                        $tanpaKontak++;

                        continue;
                    }

                    $penerima[] = ['IdPelanggan' => $p->Id, 'Tujuan' => $tujuan];
                }
            }, 'Id');

        return ['Penerima' => $penerima, 'TanpaKontak' => $tanpaKontak, 'PerSegmen' => $perSegmen];
    }

    public static function AmbilTujuan(Pelanggan $pelanggan, KanalKampanye $kanal): ?string
    {
        if ($kanal === KanalKampanye::Whatsapp) {
            $nomor = PesanWhatsapp::RapikanNomor($pelanggan->NoHp);

            return preg_match(self::POLA_NOMOR, $nomor) === 1 ? $nomor : null;
        }

        $email = mb_strtolower(trim((string) $pelanggan->Email));

        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
    }
}

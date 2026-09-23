<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Layanan;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Tenant\Kueri\SumberFiturTenant;
use Closure;
use InvalidArgumentException;

/**
 * Penegakan batas paket di server (BR-P04.3, BR-02.1, §25 no. 14). Dipanggil Aksi di dalam transaksi DB sebelum
 * menambah sesuatu yang dibatasi paket. Baris langganan dikunci lebih dulu sehingga dua penambahan bersamaan dari
 * satu tenant tidak bisa sama-sama lolos. Aplikasi hanya memakai hasil `AmbilRingkasan` untuk UX.
 */
final class PastikanBatasPaket
{
    /** Nama objek per kolom batas untuk pesan ke pengguna. */
    private const NAMA_OBJEK = [
        'BatasOutlet' => 'outlet',
        'BatasPengguna' => 'pengguna',
        'BatasPerangkatPerOutlet' => 'perangkat per outlet',
        'BatasSku' => 'SKU produk',
    ];

    public function __construct(
        private readonly SumberFiturTenant $sumberFitur,
        private readonly EvaluatorFitur $evaluator,
    ) {}

    /**
     * @param  Closure(): int  $hitungPemakaian  dihitung setelah langganan terkunci
     *
     * @throws PelanggaranAturanBisnis bila penambahan satu lagi melewati batas efektif
     */
    public function Pastikan(int $idTenant, string $kolomBatas, Closure $hitungPemakaian): void
    {
        if (! array_key_exists($kolomBatas, self::NAMA_OBJEK)) {
            throw new InvalidArgumentException("Kolom batas {$kolomBatas} tidak dikenal.");
        }

        // Tanpa langganan, semua batas bernilai 0 sehingga penambahan ditolak.
        $sumber = $this->sumberFitur->Ambil($idTenant, kunci: true);
        $pemakaian = $hitungPemakaian();

        if ($this->evaluator->CekMasihDalamBatas($sumber, $kolomBatas, $pemakaian)) {
            return;
        }

        $batas = $this->evaluator->HitungBatasEfektif($sumber)[$kolomBatas];
        $objek = self::NAMA_OBJEK[$kolomBatas];
        $paket = $this->sumberFitur->AmbilNamaPaket($idTenant) ?? 'Anda';

        throw new PelanggaranAturanBisnis(
            'BR-02.1',
            "Paket {$paket} mencakup maksimal {$batas} {$objek} dan semuanya sudah terpakai ({$pemakaian}). "
            ."Tingkatkan paket atau tambah add-on di menu Langganan untuk menambah {$objek}.",
        );
    }

    /**
     * Batas efektif & pemakaian untuk ditampilkan di layar ("2 dari 3 outlet"). Null = tak terbatas.
     *
     * @return array{Batas: int|null, Terpakai: int}
     */
    public function AmbilRingkasan(int $idTenant, string $kolomBatas, int $pemakaian): array
    {
        $sumber = $this->sumberFitur->Ambil($idTenant);

        return [
            'Batas' => $this->evaluator->HitungBatasEfektif($sumber)[$kolomBatas] ?? null,
            'Terpakai' => $pemakaian,
        ];
    }
}

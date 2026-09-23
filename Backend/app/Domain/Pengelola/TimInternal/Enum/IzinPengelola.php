<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\TimInternal\Enum;

/**
 * Izin Platform Pengelola (PRD §19.3). Nilai memakai format permission D-06: huruf kecil, titik, kebab-case.
 * Izin flow berikutnya (P-02 dst.) ditambahkan bersama flow-nya.
 */
enum IzinPengelola: string
{
    case TimAnggotaLihat = 'tim.anggota.lihat';
    case TimAnggotaUndang = 'tim.anggota.undang';
    case TimAnggotaNonaktifkan = 'tim.anggota.nonaktifkan';
    case TimPeranTetapkan = 'tim.peran.tetapkan';
    case AuditLihat = 'audit.lihat';

    // P-02 Master regulasi & referensi.
    case ReferensiLihat = 'referensi.lihat';
    case ReferensiWilayahKelola = 'referensi.wilayah.kelola';
    case ReferensiBankKelola = 'referensi.bank.kelola';
    case ReferensiSatuanKelola = 'referensi.satuan.kelola';
    case ReferensiTarifPajakAjukan = 'referensi.tarif-pajak.ajukan';
    case ReferensiTarifPajakSetujui = 'referensi.tarif-pajak.setujui';
    case ReferensiHariLiburAjukan = 'referensi.hari-libur.ajukan';
    case ReferensiHariLiburSetujui = 'referensi.hari-libur.setujui';

    // P-04 Katalog paket & fitur.
    case KatalogLihat = 'katalog.lihat';
    case KatalogFiturKelola = 'katalog.fitur.kelola';
    case KatalogPaketAjukan = 'katalog.paket.ajukan';
    case KatalogPaketSetujui = 'katalog.paket.setujui';
    case KatalogAddonKelola = 'katalog.addon.kelola';
    case KatalogKuponKelola = 'katalog.kupon.kelola';

    // P-03 Template sektor.
    case TemplateLihat = 'template.lihat';
    case TemplateDrafKelola = 'template.draf.kelola';
    case TemplateIsiUbah = 'template.isi.ubah';
    case TemplateAkunUbah = 'template.akun.ubah';
    case TemplateTerbitkan = 'template.terbitkan';

    // P-05 Konfigurasi integrasi platform (§19.3: Teknis & Super Admin).
    case IntegrasiLihat = 'integrasi.lihat';
    case IntegrasiKelola = 'integrasi.kelola';

    // P-06 Dokumen legal.
    case LegalLihat = 'legal.lihat';
    case LegalKelola = 'legal.kelola';

    // P-08 Tagihan langganan & verifikasi pembayaran (§19.3: Keuangan & Super Admin).
    case TagihanLihat = 'tagihan.lihat';
    case TagihanVerifikasi = 'tagihan.verifikasi';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::TimAnggotaLihat => 'Melihat anggota tim internal',
            self::TimAnggotaUndang => 'Mengundang anggota tim internal',
            self::TimAnggotaNonaktifkan => 'Menonaktifkan anggota tim internal',
            self::TimPeranTetapkan => 'Menetapkan peran anggota tim internal',
            self::AuditLihat => 'Melihat log audit pengelola',
            self::ReferensiLihat => 'Melihat data referensi & regulasi',
            self::ReferensiWilayahKelola => 'Mengelola data wilayah',
            self::ReferensiBankKelola => 'Mengelola referensi pembayaran',
            self::ReferensiSatuanKelola => 'Mengelola satuan standar',
            self::ReferensiTarifPajakAjukan => 'Mengajukan tarif pajak',
            self::ReferensiTarifPajakSetujui => 'Menyetujui atau menolak tarif pajak',
            self::ReferensiHariLiburAjukan => 'Mengajukan hari libur',
            self::ReferensiHariLiburSetujui => 'Menyetujui atau menolak hari libur',
            self::KatalogLihat => 'Melihat katalog paket & fitur',
            self::KatalogFiturKelola => 'Mengelola katalog fitur',
            self::KatalogPaketAjukan => 'Menyusun paket & mengusulkan harga',
            self::KatalogPaketSetujui => 'Menyetujui harga, mengaktifkan/mengarsipkan paket, mengubah paket aktif',
            self::KatalogAddonKelola => 'Mengelola add-on',
            self::KatalogKuponKelola => 'Mengelola kupon langganan',
            self::TemplateLihat => 'Melihat template sektor',
            self::TemplateDrafKelola => 'Membuat draf versi baru, memvalidasi, dan menghapus draf template',
            self::TemplateIsiUbah => 'Membuat template & mengubah isi bisnis template',
            self::TemplateAkunUbah => 'Mengubah COA, pemetaan akun, dan kelompok pajak template',
            self::TemplateTerbitkan => 'Menerbitkan template sektor',
            self::IntegrasiLihat => 'Melihat status integrasi platform',
            self::IntegrasiKelola => 'Mengubah kredensial, menguji, dan mengaktifkan integrasi platform',
            self::LegalLihat => 'Melihat dokumen legal',
            self::LegalKelola => 'Menyusun dan menerbitkan dokumen legal',
            // P-08
            self::TagihanLihat => 'Melihat tagihan langganan & bukti transfer',
            self::TagihanVerifikasi => 'Menerima atau menolak pembayaran langganan',
        };
    }
}

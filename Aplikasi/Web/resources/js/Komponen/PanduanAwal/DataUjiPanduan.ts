/**
 * Data contoh untuk Vitest panduan awal (F-01). Hanya diimpor file *Tes.tsx; tidak ikut bundle halaman.
 */
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import type { LangkahPanduan, ProgresPanduan, RingkasanLangkah, StatusLangkahPanduan } from '@/Tipe/PanduanAwal';

const daftarLangkah: [LangkahPanduan, string, string][] = [
    ['ProfilUsaha', 'profil-usaha', 'Profil usaha'],
    ['Sektor', 'sektor', 'Jenis usaha & template'],
    ['Pajak', 'pajak', 'Pajak'],
    ['Produk', 'produk', 'Produk awal'],
    ['MetodePembayaran', 'metode-pembayaran', 'Metode pembayaran'],
    ['Perangkat', 'perangkat', 'Perangkat kasir'],
];

export function BuatLangkahContoh(
    status: Partial<Record<LangkahPanduan, StatusLangkahPanduan>> = {},
): RingkasanLangkah[] {
    return daftarLangkah.map(([kunci, slug, judul]) => ({
        Kunci: kunci,
        Slug: slug,
        Judul: judul,
        Status: status[kunci] ?? 'Belum',
        Tautan: `/kelola/panduan-awal/${slug}`,
    }));
}

export function BuatProgresContoh(status: Partial<Record<LangkahPanduan, StatusLangkahPanduan>> = {}): ProgresPanduan {
    return {
        Langkah: BuatLangkahContoh(status),
        SelesaiPada: null,
        Outlet: { Uuid: '01J8OUTLETUTAMA0000000000A', Kode: 'UTM', Nama: 'Outlet Utama' },
    };
}

export function BuatPropsBersamaContoh(): PropsBersamaAplikasi {
    return {
        NamaAplikasi: 'Kasir',
        Kilat: null,
        Pengguna: { Uuid: '01J', Nama: 'Rina Wulandari', Email: 'rina@kopinusantara.id', EmailTerverifikasi: true },
        TenantAktif: {
            Nama: 'Kopi Nusantara',
            StatusLangganan: 'Aktif',
            PeriodeSelesai: null,
            BatasTenggangPada: null,
        },
        PengumumanLegal: [],
        Akses: { Pemilik: true, Izin: [] },
        errors: {},
    };
}

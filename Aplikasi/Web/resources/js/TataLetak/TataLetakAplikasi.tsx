import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';

import Tombol from '@/Komponen/Formulir/Tombol';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { FormatTanggal, FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import type { PropsBersamaAplikasi, TenantAktif } from '@/Tipe/Aplikasi';
import { IzinTenant, PunyaIzinTenant, type KunciIzinTenant } from '@/Tipe/Organisasi';

type PropsTataLetak = { judul: string; children: ReactNode };

type ItemMenu = { label: string; href: string; izin: KunciIzinTenant | null };

// F-03: grup menu "Produk". Tampil sebagai sub-menu saat salah satu halamannya dibuka.
const menuProduk: ItemMenu[] = [
    { label: 'Produk', href: '/kelola/produk', izin: IzinTenant.ProdukLihat },
    { label: 'Kategori', href: '/kelola/kategori', izin: IzinTenant.ProdukLihat },
    { label: 'Satuan', href: '/kelola/satuan', izin: IzinTenant.ProdukLihat },
    { label: 'Daftar harga', href: '/kelola/daftar-harga', izin: IzinTenant.ProdukLihat },
    { label: 'Pilihan (modifier)', href: '/kelola/kelompok-pilihan', izin: IzinTenant.ProdukLihat },
    { label: 'Kelompok pajak', href: '/kelola/kelompok-pajak', izin: IzinTenant.ProdukLihat },
    { label: 'Impor produk', href: '/kelola/produk/impor', izin: IzinTenant.ProdukKelola },
];

/** Item sub-menu Produk yang aktif untuk URL ini: awalan terpanjang menang (/kelola/produk/impor vs /kelola/produk). */
export function CariMenuProdukAktif(url: string): string | null {
    const jalur = url.split('?')[0] ?? url;
    const cocok = menuProduk
        .filter((menu) => jalur === menu.href || jalur.startsWith(`${menu.href}/`))
        .sort((a, b) => b.href.length - a.href.length);

    return cocok[0]?.href ?? null;
}

// Menu back-office tenant berbasis izin (hanya UX; server tetap memeriksa izin lewat WajibIzinTenant).
const daftarMenu: ItemMenu[] = [
    { label: 'Beranda', href: '/kelola', izin: null },
    { label: 'Outlet', href: '/kelola/outlet', izin: IzinTenant.OutletLihat },
    { label: 'Produk', href: '/kelola/produk', izin: IzinTenant.ProdukLihat },
    // F-02b: perangkat POS.
    { label: 'Perangkat', href: '/kelola/perangkat', izin: IzinTenant.PerangkatLihat },
    { label: 'Pengguna & peran', href: '/kelola/pengguna', izin: IzinTenant.PenggunaLihat },
    { label: 'Log audit', href: '/kelola/log-audit', izin: IzinTenant.AuditLihat },
    { label: 'Langganan', href: '/kelola/langganan', izin: IzinTenant.LanggananKelola },
    { label: 'Bantuan', href: '/kelola/bantuan', izin: IzinTenant.BantuanTiketLihat },
];

/** F-00: banner selama langganan Tertunggak (masa tenggang) atau Ditangguhkan (hanya lihat, export, bayar). */
function BannerLangganan({ tenant, bolehBayar }: { tenant: TenantAktif; bolehBayar: boolean }) {
    const ajakan = bolehBayar ? (
        <Link href="/kelola/langganan" className="font-semibold text-brand underline">
            Bayar tagihan di menu Langganan
        </Link>
    ) : (
        <span>Hubungi pemilik usaha untuk membayar tagihan.</span>
    );

    if (tenant.StatusLangganan === 'Tertunggak') {
        return (
            <Pemberitahuan jenis="peringatan" judul="Tagihan langganan belum dibayar">
                <p>
                    Periode langganan berakhir {FormatTanggalWaktu(tenant.PeriodeSelesai)}. Semua fitur tetap berjalan
                    {tenant.BatasTenggangPada ? ` sampai ${FormatTanggalWaktu(tenant.BatasTenggangPada)}` : ''}; setelah
                    itu langganan ditangguhkan dan data tidak bisa diubah.
                </p>
                <p className="mt-1">{ajakan}</p>
            </Pemberitahuan>
        );
    }

    if (tenant.StatusLangganan === 'Ditangguhkan') {
        return (
            <Pemberitahuan jenis="bahaya" judul="Langganan ditangguhkan">
                <p>
                    Anda masih bisa masuk, melihat data dan laporan, serta mengekspor data. Menambah atau mengubah data
                    dan berjualan di POS terkunci sampai tagihan dibayar.
                </p>
                <p className="mt-1">{ajakan}</p>
            </Pemberitahuan>
        );
    }

    return null;
}

/** Tata letak back-office tenant (/kelola). Menu modul ditambahkan per flow (F-01 dst.). */
export default function TataLetakAplikasi({ judul, children }: PropsTataLetak) {
    const { props, url } = usePage<PropsBersamaAplikasi>();
    const tenantAktif = props.TenantAktif;
    const menuTerlihat = daftarMenu.filter((menu) => menu.izin === null || PunyaIzinTenant(props.Akses, menu.izin));
    const menuProdukAktif = CariMenuProdukAktif(url);
    const subMenuProduk = menuProduk.filter((menu) => menu.izin === null || PunyaIzinTenant(props.Akses, menu.izin));
    const [mengirim, AturMengirim] = useState(false);
    const KirimUlangVerifikasi = () =>
        router.post(
            '/verifikasi-email/kirim-ulang',
            {},
            { preserveScroll: true, onStart: () => AturMengirim(true), onFinish: () => AturMengirim(false) },
        );

    return (
        <>
            <Head title={judul} />
            <header className="border-b border-garis bg-permukaan">
                <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-3">
                    <p className="text-subjudul font-bold text-teks-utama">{tenantAktif?.Nama ?? props.NamaAplikasi}</p>
                    <div className="flex items-center gap-3">
                        <span className="text-label text-teks-sekunder">{props.Pengguna?.Nama}</span>
                        {/* Auth tenant: keamanan akun & 2FA (BR-00.8). */}
                        <Link
                            href="/kelola/keamanan"
                            className="text-label font-semibold text-brand underline outline-none focus-visible:ring-2 focus-visible:ring-brand"
                        >
                            Keamanan akun
                        </Link>
                        <Tombol varian="sekunder" onClick={() => router.post('/keluar')}>
                            Keluar
                        </Tombol>
                    </div>
                </div>
                {tenantAktif && props.Akses ? (
                    <nav aria-label="Menu utama" className="mx-auto flex max-w-6xl flex-wrap gap-1 px-4">
                        {menuTerlihat.map((menu) => {
                            const aktif =
                                menu.href === '/kelola'
                                    ? url === '/kelola'
                                    : menu.href === '/kelola/produk'
                                      ? menuProdukAktif !== null
                                      : url.startsWith(menu.href) ||
                                        (menu.href === '/kelola/pengguna' && url.startsWith('/kelola/peran'));

                            return (
                                <Link
                                    key={menu.href}
                                    href={menu.href}
                                    aria-current={aktif ? 'page' : undefined}
                                    className={`-mb-px border-b-2 px-3 py-2 text-label font-semibold outline-none focus-visible:ring-2 focus-visible:ring-brand ${
                                        aktif ? 'border-brand text-teks-utama' : 'border-transparent text-teks-sekunder'
                                    }`}
                                >
                                    {menu.label}
                                </Link>
                            );
                        })}
                    </nav>
                ) : null}
                {tenantAktif && props.Akses && menuProdukAktif !== null && subMenuProduk.length > 0 ? (
                    <nav
                        aria-label="Menu produk"
                        className="mx-auto flex max-w-6xl gap-1 overflow-x-auto border-t border-garis px-4"
                    >
                        {subMenuProduk.map((menu) => (
                            <Link
                                key={menu.href}
                                href={menu.href}
                                aria-current={menuProdukAktif === menu.href ? 'page' : undefined}
                                className={`shrink-0 px-3 py-2 text-label outline-none focus-visible:ring-2 focus-visible:ring-brand ${
                                    menuProdukAktif === menu.href
                                        ? 'font-semibold text-teks-utama underline underline-offset-4'
                                        : 'text-teks-sekunder'
                                }`}
                            >
                                {menu.label}
                            </Link>
                        ))}
                    </nav>
                ) : null}
            </header>
            <main className="mx-auto flex max-w-6xl flex-col gap-4 px-4 py-6">
                <h1 className="text-judul font-bold text-teks-utama">{judul}</h1>
                {props.Pengguna && !props.Pengguna.EmailTerverifikasi ? (
                    <Pemberitahuan jenis="peringatan" judul="Verifikasi email Anda">
                        <p>
                            Kami mengirim tautan verifikasi ke {props.Pengguna.Email}. Buka tautan itu untuk mengamankan
                            akun.
                        </p>
                        <div className="mt-2">
                            <Tombol varian="sekunder" memproses={mengirim} onClick={KirimUlangVerifikasi}>
                                Kirim ulang tautan
                            </Tombol>
                        </div>
                    </Pemberitahuan>
                ) : null}
                {/* BR-P06.5: pengumuman versi materiil dokumen legal selama masa pengumuman. */}
                {props.PengumumanLegal.length > 0 ? (
                    <Pemberitahuan jenis="info" judul="Perubahan dokumen legal">
                        <ul className="flex flex-col gap-1">
                            {props.PengumumanLegal.map((pengumuman) => (
                                <li key={`${pengumuman.Label}-${pengumuman.Versi}`}>
                                    {pengumuman.Label} versi {pengumuman.Versi} berlaku mulai{' '}
                                    {FormatTanggal(pengumuman.BerlakuMulai)}.{' '}
                                    <a href={pengumuman.Tautan} className="font-semibold text-brand underline">
                                        Baca perubahannya
                                    </a>
                                </li>
                            ))}
                        </ul>
                        <p className="mt-1">Anda akan diminta menyetujuinya setelah tanggal berlaku.</p>
                    </Pemberitahuan>
                ) : null}
                {tenantAktif ? (
                    <BannerLangganan
                        tenant={tenantAktif}
                        bolehBayar={PunyaIzinTenant(props.Akses, IzinTenant.LanggananKelola)}
                    />
                ) : null}
                {props.Kilat ? <Pemberitahuan jenis="sukses">{props.Kilat}</Pemberitahuan> : null}
                {props.errors.Umum ? <Pemberitahuan jenis="bahaya">{props.errors.Umum}</Pemberitahuan> : null}
                {children}
            </main>
        </>
    );
}

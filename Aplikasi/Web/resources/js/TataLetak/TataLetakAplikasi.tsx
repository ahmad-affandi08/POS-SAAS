import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    CreditCardIcon,
    HouseIcon,
    LifeBuoyIcon,
    MonitorSmartphoneIcon,
    PackageIcon,
    ScrollTextIcon,
    ShieldCheckIcon,
    StoreIcon,
    UsersIcon,
    type LucideIcon,
} from 'lucide-react';
import { useState, type ReactNode } from 'react';

import Tombol from '@/Komponen/Formulir/Tombol';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupContent,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    SidebarProvider,
    SidebarRail,
} from '@/Komponen/Ui/sidebar';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { FormatTanggal, FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import type { PropsBersamaAplikasi, TenantAktif } from '@/Tipe/Aplikasi';
import { IzinTenant, PunyaIzinTenant, type KunciIzinTenant } from '@/Tipe/Organisasi';

import { BacaSidebarTerbuka, KepalaTataLetak, MenuAkun, PemberitahuanMelayang } from './BagianTataLetak';

type PropsTataLetak = { judul: string; children: ReactNode };

type ItemMenu = { label: string; href: string; izin: KunciIzinTenant | null; ikon?: LucideIcon };

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
    { label: 'Beranda', href: '/kelola', izin: null, ikon: HouseIcon },
    { label: 'Outlet', href: '/kelola/outlet', izin: IzinTenant.OutletLihat, ikon: StoreIcon },
    { label: 'Produk', href: '/kelola/produk', izin: IzinTenant.ProdukLihat, ikon: PackageIcon },
    // F-02b: perangkat POS.
    { label: 'Perangkat', href: '/kelola/perangkat', izin: IzinTenant.PerangkatLihat, ikon: MonitorSmartphoneIcon },
    { label: 'Pengguna & peran', href: '/kelola/pengguna', izin: IzinTenant.PenggunaLihat, ikon: UsersIcon },
    { label: 'Log audit', href: '/kelola/log-audit', izin: IzinTenant.AuditLihat, ikon: ScrollTextIcon },
    { label: 'Langganan', href: '/kelola/langganan', izin: IzinTenant.LanggananKelola, ikon: CreditCardIcon },
    { label: 'Bantuan', href: '/kelola/bantuan', izin: IzinTenant.BantuanTiketLihat, ikon: LifeBuoyIcon },
];

/** Menu utama yang aktif untuk URL ini (Produk untuk seluruh grupnya; Pengguna & peran juga untuk /kelola/peran). */
export function CekMenuAktif(href: string, url: string): boolean {
    if (href === '/kelola') {
        return url === '/kelola';
    }

    if (href === '/kelola/produk') {
        return CariMenuProdukAktif(url) !== null;
    }

    return url.startsWith(href) || (href === '/kelola/pengguna' && url.startsWith('/kelola/peran'));
}

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

/**
 * Tata letak back-office tenant (/kelola): bilah menu samping shadcn/ui berbasis izin (bisa diciutkan menjadi ikon,
 * menjadi Sheet di layar sempit), bilah atas dengan remah roti & menu akun, lalu banner status dan isi halaman.
 * Menu modul ditambahkan per flow (F-01 dst.).
 */
export default function TataLetakAplikasi({ judul, children }: PropsTataLetak) {
    const { props, url } = usePage<PropsBersamaAplikasi>();
    const tenantAktif = props.TenantAktif;
    const menuTerlihat = daftarMenu.filter((menu) => menu.izin === null || PunyaIzinTenant(props.Akses, menu.izin));
    const menuProdukAktif = CariMenuProdukAktif(url);
    const subMenuProduk = menuProduk.filter((menu) => menu.izin === null || PunyaIzinTenant(props.Akses, menu.izin));
    const namaInduk = tenantAktif?.Nama ?? props.NamaAplikasi;
    const keamananAktif = url.startsWith('/kelola/keamanan');
    const [mengirim, AturMengirim] = useState(false);
    const KirimUlangVerifikasi = () =>
        router.post(
            '/verifikasi-email/kirim-ulang',
            {},
            { preserveScroll: true, onStart: () => AturMengirim(true), onFinish: () => AturMengirim(false) },
        );

    return (
        <SidebarProvider defaultOpen={BacaSidebarTerbuka()}>
            <Head title={judul} />
            <Sidebar collapsible="icon">
                <SidebarHeader className="border-b border-sidebar-border">
                    <p
                        className="truncate px-2 py-1.5 text-subjudul font-bold text-teks-utama group-data-[collapsible=icon]:sr-only"
                        title={namaInduk}
                    >
                        {namaInduk}
                    </p>
                </SidebarHeader>
                <SidebarContent>
                    {tenantAktif && props.Akses ? (
                        <nav aria-label="Menu utama">
                            <SidebarGroup>
                                <SidebarGroupContent>
                                    <SidebarMenu>
                                        {menuTerlihat.map((menu) => {
                                            const aktif = CekMenuAktif(menu.href, url);
                                            const Ikon = menu.ikon;

                                            return (
                                                <SidebarMenuItem key={menu.href}>
                                                    <SidebarMenuButton
                                                        asChild
                                                        isActive={aktif}
                                                        tooltip={menu.label}
                                                        className="text-label data-[active=true]:font-semibold"
                                                    >
                                                        <Link
                                                            href={menu.href}
                                                            aria-current={aktif ? 'page' : undefined}
                                                        >
                                                            {Ikon ? <Ikon aria-hidden="true" /> : null}
                                                            <span>{menu.label}</span>
                                                        </Link>
                                                    </SidebarMenuButton>
                                                    {menu.href === '/kelola/produk' &&
                                                    menuProdukAktif !== null &&
                                                    subMenuProduk.length > 0 ? (
                                                        <nav aria-label="Menu produk">
                                                            <SidebarMenuSub>
                                                                {subMenuProduk.map((sub) => (
                                                                    <SidebarMenuSubItem key={sub.href}>
                                                                        <SidebarMenuSubButton
                                                                            asChild
                                                                            isActive={menuProdukAktif === sub.href}
                                                                            className="text-label data-[active=true]:font-semibold"
                                                                        >
                                                                            <Link
                                                                                href={sub.href}
                                                                                aria-current={
                                                                                    menuProdukAktif === sub.href
                                                                                        ? 'page'
                                                                                        : undefined
                                                                                }
                                                                            >
                                                                                {sub.label}
                                                                            </Link>
                                                                        </SidebarMenuSubButton>
                                                                    </SidebarMenuSubItem>
                                                                ))}
                                                            </SidebarMenuSub>
                                                        </nav>
                                                    ) : null}
                                                </SidebarMenuItem>
                                            );
                                        })}
                                    </SidebarMenu>
                                </SidebarGroupContent>
                            </SidebarGroup>
                        </nav>
                    ) : null}
                </SidebarContent>
                <SidebarFooter className="border-t border-sidebar-border">
                    <SidebarMenu>
                        <SidebarMenuItem>
                            {/* Auth tenant: keamanan akun & 2FA (BR-00.8). */}
                            <SidebarMenuButton
                                asChild
                                isActive={keamananAktif}
                                tooltip="Keamanan akun"
                                className="text-label data-[active=true]:font-semibold"
                            >
                                <Link href="/kelola/keamanan" aria-current={keamananAktif ? 'page' : undefined}>
                                    <ShieldCheckIcon aria-hidden="true" />
                                    <span>Keamanan akun</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarFooter>
                <SidebarRail aria-label="Buka atau tutup menu samping" title="Buka atau tutup menu samping" />
            </Sidebar>
            <div data-slot="sidebar-inset" className="relative flex w-full min-w-0 flex-1 flex-col bg-latar">
                <KepalaTataLetak induk={namaInduk} judul={judul}>
                    <MenuAkun nama={props.Pengguna?.Nama} email={props.Pengguna?.Email} />
                </KepalaTataLetak>
                <main className="mx-auto flex w-full max-w-6xl flex-col gap-4 px-4 py-6">
                    <h1 className="text-judul font-bold text-teks-utama">{judul}</h1>
                    {props.Pengguna && !props.Pengguna.EmailTerverifikasi ? (
                        <Pemberitahuan jenis="peringatan" judul="Verifikasi email Anda">
                            <p>
                                Kami mengirim tautan verifikasi ke {props.Pengguna.Email}. Buka tautan itu untuk
                                mengamankan akun.
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
            </div>
            <PemberitahuanMelayang />
        </SidebarProvider>
    );
}

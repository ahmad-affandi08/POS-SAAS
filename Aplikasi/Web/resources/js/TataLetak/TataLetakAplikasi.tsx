import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    BanknoteIcon,
    BookOpenTextIcon,
    ChevronRightIcon,
    ChartColumnIcon,
    CreditCardIcon,
    HouseIcon,
    IdCardIcon,
    LifeBuoyIcon,
    MonitorSmartphoneIcon,
    PackageIcon,
    ReceiptTextIcon,
    ScrollTextIcon,
    ShieldCheckIcon,
    ShoppingCartIcon,
    StoreIcon,
    UsersIcon,
    UsersRoundIcon,
    WarehouseIcon,
    type LucideIcon,
} from 'lucide-react';
import { useState, type MouseEvent, type ReactNode } from 'react';

import Tombol from '@/Komponen/Formulir/Tombol';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/Komponen/Ui/collapsible';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupContent,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    SidebarProvider,
    SidebarRail,
    useSidebar,
} from '@/Komponen/Ui/sidebar';
import { cn } from '@/Komponen/Ui/utils';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { FormatTanggal, FormatTanggalWaktu } from '@/Pustaka/FormatWaktu';
import type { PropsBersamaAplikasi, TenantAktif } from '@/Tipe/Aplikasi';
import { IzinTenant, PunyaIzinTenant, type KunciIzinTenant } from '@/Tipe/Organisasi';

import {
    BacaSidebarTerbuka,
    kelasChevronGrupSidebar,
    kelasTombolGrupSidebar,
    kelasTombolMenuSidebar,
    kelasTombolSubMenuSidebar,
    KepalaTataLetak,
    MenuAkun,
    PemberitahuanMelayang,
} from './BagianTataLetak';
import KepalaSidebarMerek from './KepalaSidebarMerek';
import PencarianCepat, { type HalamanPencarian, type SumberPencarian } from './PencarianCepat';

type PropsTataLetak = { judul: string; children: ReactNode };

type ItemMenu = { label: string; href: string; izin: KunciIzinTenant | null; ikon?: LucideIcon };

/** Menu utama bersub-menu: tampil bila ada sub-menu yang boleh dibuka; tautannya = sub-menu pertama yang boleh. */
type GrupMenu = ItemMenu & { labelSub: string; sub: ItemMenu[] };

// F-03: grup menu "Produk". Tampil sebagai sub-menu saat salah satu halamannya dibuka.
const menuProduk: ItemMenu[] = [
    { label: 'Produk', href: '/kelola/produk', izin: IzinTenant.ProdukLihat },
    { label: 'Kategori', href: '/kelola/kategori', izin: IzinTenant.ProdukLihat },
    { label: 'Satuan', href: '/kelola/satuan', izin: IzinTenant.ProdukLihat },
    { label: 'Daftar harga', href: '/kelola/daftar-harga', izin: IzinTenant.ProdukLihat },
    { label: 'Pilihan (modifier)', href: '/kelola/kelompok-pilihan', izin: IzinTenant.ProdukLihat },
    { label: 'Kelompok pajak', href: '/kelola/kelompok-pajak', izin: IzinTenant.ProdukLihat },
    { label: 'Stasiun dapur', href: '/kelola/stasiun-dapur', izin: IzinTenant.ProdukLihat },
    { label: 'Impor produk', href: '/kelola/produk/impor', izin: IzinTenant.ProdukKelola },
];

// F-05a: grup menu "Persediaan" (DesainF05a E). Impor butuh persediaan.kelola, pengaturan butuh akuntansi.kelola.
const menuPersediaan: ItemMenu[] = [
    { label: 'Saldo stok', href: '/kelola/persediaan/saldo', izin: IzinTenant.PersediaanLihat },
    { label: 'Kartu stok', href: '/kelola/persediaan/kartu-stok', izin: IzinTenant.PersediaanLihat },
    { label: 'Stok awal', href: '/kelola/persediaan/stok-awal', izin: IzinTenant.PersediaanLihat },
    // F-05b: transfer, stok opname, penyesuaian (lihat: persediaan.lihat; tindakan dijaga di rute).
    { label: 'Transfer stok', href: '/kelola/persediaan/transfer', izin: IzinTenant.PersediaanLihat },
    { label: 'Stok opname', href: '/kelola/persediaan/opname', izin: IzinTenant.PersediaanLihat },
    { label: 'Penyesuaian stok', href: '/kelola/persediaan/penyesuaian', izin: IzinTenant.PersediaanLihat },
    { label: 'Impor stok awal', href: '/kelola/persediaan/stok-awal/impor', izin: IzinTenant.PersediaanKelola },
    { label: 'Pengaturan persediaan', href: '/kelola/persediaan/pengaturan', izin: IzinTenant.AkuntansiKelola },
];

// F-04 fase 1: grup menu "Pembelian" (pembelian.kelola); pengaturan pembelian butuh pembelian.po.setujui.
const menuPembelian: ItemMenu[] = [
    { label: 'Pesanan pembelian', href: '/kelola/pembelian/pesanan', izin: IzinTenant.PembelianKelola },
    { label: 'Penerimaan barang', href: '/kelola/pembelian/penerimaan', izin: IzinTenant.PembelianKelola },
    { label: 'Faktur pembelian', href: '/kelola/pembelian/faktur', izin: IzinTenant.PembelianKelola },
    { label: 'Hutang pemasok', href: '/kelola/pembelian/hutang', izin: IzinTenant.PembelianKelola },
    { label: 'Pembayaran hutang', href: '/kelola/pembelian/pembayaran', izin: IzinTenant.PembelianKelola },
    { label: 'Retur pembelian', href: '/kelola/pembelian/retur', izin: IzinTenant.PembelianKelola },
    { label: 'Pemasok', href: '/kelola/pembelian/pemasok', izin: IzinTenant.PembelianKelola },
    { label: 'Pengaturan pembelian', href: '/kelola/pembelian/pengaturan', izin: IzinTenant.PembelianPoSetujui },
];

// F-06: grup menu "Shift & kas" (pemantauan back-office; layar kasir ada di aplikasi Flutter): shift (laporan.penjualan.lihat), kategori kas (akuntansi.kelola), pengaturan (outlet.kelola).
// F-16a/F-16b: data pelanggan, tier, pengaturan loyalti.
const menuPelanggan: ItemMenu[] = [
    { label: 'Daftar pelanggan', href: '/kelola/pelanggan', izin: IzinTenant.PelangganLihat },
    { label: 'Tier pelanggan', href: '/kelola/pelanggan/tier', izin: IzinTenant.PelangganLihat },
    { label: 'Pengaturan loyalti', href: '/kelola/pelanggan/loyalti', izin: IzinTenant.PelangganLihat },
    { label: 'Promo', href: '/kelola/promo', izin: IzinTenant.PelangganLihat },
    // F-12: piutang pelanggan (penjualan tempo) & pelunasan.
    { label: 'Piutang pelanggan', href: '/kelola/piutang', izin: IzinTenant.PelangganLihat },
    { label: 'Pelunasan piutang', href: '/kelola/piutang/pelunasan', izin: IzinTenant.PelangganLihat },
];

// F-18: karyawan, jadwal kerja, rekap absensi (karyawan.lihat).
const menuKaryawan: ItemMenu[] = [
    { label: 'Daftar karyawan', href: '/kelola/karyawan', izin: IzinTenant.KaryawanLihat },
    { label: 'Jadwal kerja', href: '/kelola/karyawan/jadwal', izin: IzinTenant.KaryawanLihat },
    { label: 'Absensi', href: '/kelola/karyawan/absensi', izin: IzinTenant.KaryawanLihat },
    // F-18 bagian 2: komisi.
    { label: 'Aturan komisi', href: '/kelola/karyawan/komisi', izin: IzinTenant.KaryawanLihat },
    { label: 'Laporan komisi', href: '/kelola/karyawan/komisi/laporan', izin: IzinTenant.KaryawanLihat },
    // F-18 bagian 3: kasbon.
    { label: 'Kasbon', href: '/kelola/karyawan/kasbon', izin: IzinTenant.KaryawanLihat },
    // F-18 bagian 3: rekap gaji bulanan (memuat gaji).
    { label: 'Rekap gaji', href: '/kelola/karyawan/gaji', izin: IzinTenant.KaryawanKelola },
];

const menuKasir: ItemMenu[] = [
    { label: 'Shift kasir', href: '/kelola/kasir/shift', izin: IzinTenant.LaporanPenjualanLihat },
    // F-15: tutup harian (End of Day) per outlet.
    { label: 'Tutup harian', href: '/kelola/kasir/tutup-harian', izin: IzinTenant.LaporanPenjualanLihat },
    { label: 'Kategori kas', href: '/kelola/kasir/kategori-kas', izin: IzinTenant.AkuntansiKelola },
    { label: 'Pengaturan kasir', href: '/kelola/kasir/pengaturan', izin: IzinTenant.OutletKelola },
    { label: 'Pengaturan struk', href: '/kelola/kasir/struk', izin: IzinTenant.OutletKelola },
];

// F-07b/F-09: grup menu "Penjualan" (baca saja): daftar penjualan dan void & retur (anti-fraud BR-09.3).
const menuPenjualan: ItemMenu[] = [
    { label: 'Daftar penjualan', href: '/kelola/penjualan', izin: IzinTenant.LaporanPenjualanLihat },
    { label: 'Void & retur', href: '/kelola/penjualan/void-retur', izin: IzinTenant.LaporanPenjualanLihat },
    // F-12 bagian 2: pre-order & uang muka.
    { label: 'Pre-order', href: '/kelola/pre-order', izin: IzinTenant.LaporanPenjualanLihat },
];

// F-05a: grup menu "Akuntansi"; jurnal (baca saja) memakai laporan.keuangan.lihat (DesainF05a H-13).
// F-13a: bagan akun, pemetaan akun, kas & bank, dan laporan keuangan (lihat laporan.keuangan.lihat, ubah di halaman
// butuh akuntansi.kelola).
const menuAkuntansi: ItemMenu[] = [
    { label: 'Jurnal', href: '/kelola/akuntansi/jurnal', izin: IzinTenant.LaporanKeuanganLihat },
    { label: 'Kas & bank', href: '/kelola/akuntansi/kas-bank', izin: IzinTenant.LaporanKeuanganLihat },
    { label: 'Buku besar', href: '/kelola/akuntansi/laporan/buku-besar', izin: IzinTenant.LaporanKeuanganLihat },
    { label: 'Neraca saldo', href: '/kelola/akuntansi/laporan/neraca-saldo', izin: IzinTenant.LaporanKeuanganLihat },
    { label: 'Laba rugi', href: '/kelola/akuntansi/laporan/laba-rugi', izin: IzinTenant.LaporanKeuanganLihat },
    { label: 'Neraca', href: '/kelola/akuntansi/laporan/neraca', izin: IzinTenant.LaporanKeuanganLihat },
    { label: 'Arus kas', href: '/kelola/akuntansi/laporan/arus-kas', izin: IzinTenant.LaporanKeuanganLihat },
    { label: 'Tutup buku', href: '/kelola/akuntansi/tutup-buku', izin: IzinTenant.LaporanKeuanganLihat },
    { label: 'Bagan akun', href: '/kelola/akuntansi/akun', izin: IzinTenant.LaporanKeuanganLihat },
    { label: 'Pemetaan akun', href: '/kelola/akuntansi/pemetaan', izin: IzinTenant.LaporanKeuanganLihat },
];

// F-14a: grup menu "Laporan": penjualan (laporan.penjualan.lihat), pajak (laporan.keuangan.lihat), stok (persediaan.lihat).
const menuLaporan: ItemMenu[] = [
    { label: 'Laporan penjualan', href: '/kelola/laporan/penjualan', izin: IzinTenant.LaporanPenjualanLihat },
    { label: 'Laporan pajak', href: '/kelola/laporan/pajak', izin: IzinTenant.LaporanKeuanganLihat },
    { label: 'Laporan stok', href: '/kelola/laporan/stok', izin: IzinTenant.PersediaanLihat },
];

/** Item sub-menu yang aktif untuk URL ini: awalan terpanjang menang (/kelola/produk/impor vs /kelola/produk). */
export function CariSubMenuAktif(daftar: ItemMenu[], url: string): string | null {
    const jalur = url.split('?')[0] ?? url;
    const cocok = daftar
        .filter((menu) => jalur === menu.href || jalur.startsWith(`${menu.href}/`))
        .sort((a, b) => b.href.length - a.href.length);

    return cocok[0]?.href ?? null;
}

/** Item sub-menu Produk yang aktif untuk URL ini. */
export function CariMenuProdukAktif(url: string): string | null {
    return CariSubMenuAktif(menuProduk, url);
}

// Menu back-office tenant berbasis izin (hanya UX; server tetap memeriksa izin lewat WajibIzinTenant).
const daftarMenu: (ItemMenu | GrupMenu)[] = [
    { label: 'Beranda', href: '/kelola', izin: null, ikon: HouseIcon },
    { label: 'Outlet', href: '/kelola/outlet', izin: IzinTenant.OutletLihat, ikon: StoreIcon },
    {
        label: 'Produk',
        href: '/kelola/produk',
        izin: IzinTenant.ProdukLihat,
        ikon: PackageIcon,
        labelSub: 'Menu produk',
        sub: menuProduk,
    },
    {
        label: 'Persediaan',
        href: '/kelola/persediaan/saldo',
        izin: null,
        ikon: WarehouseIcon,
        labelSub: 'Menu persediaan',
        sub: menuPersediaan,
    },
    // F-04 fase 1: pembelian & hutang pemasok.
    {
        label: 'Pembelian',
        href: '/kelola/pembelian/pesanan',
        izin: null,
        ikon: ShoppingCartIcon,
        labelSub: 'Menu pembelian',
        sub: menuPembelian,
    },
    // F-07b: penjualan dari aplikasi POS (baca saja); F-09: void & retur.
    {
        label: 'Penjualan',
        href: '/kelola/penjualan',
        izin: null,
        ikon: ReceiptTextIcon,
        labelSub: 'Menu penjualan',
        sub: menuPenjualan,
    },
    {
        label: 'Pelanggan',
        href: '/kelola/pelanggan',
        izin: null,
        ikon: UsersRoundIcon,
        labelSub: 'Menu pelanggan',
        sub: menuPelanggan,
    },
    {
        label: 'Karyawan',
        href: '/kelola/karyawan',
        izin: null,
        ikon: IdCardIcon,
        labelSub: 'Menu karyawan',
        sub: menuKaryawan,
    },
    {
        label: 'Shift & kas',
        href: '/kelola/kasir/shift',
        izin: null,
        ikon: BanknoteIcon,
        labelSub: 'Menu shift & kas',
        sub: menuKasir,
    },
    {
        label: 'Akuntansi',
        href: '/kelola/akuntansi/jurnal',
        izin: null,
        ikon: BookOpenTextIcon,
        labelSub: 'Menu akuntansi',
        sub: menuAkuntansi,
    },
    // F-14a: laporan inti (penjualan, pajak, stok).
    {
        label: 'Laporan',
        href: '/kelola/laporan/penjualan',
        izin: null,
        ikon: ChartColumnIcon,
        labelSub: 'Menu laporan',
        sub: menuLaporan,
    },
    // F-02b: perangkat POS.
    { label: 'Perangkat', href: '/kelola/perangkat', izin: IzinTenant.PerangkatLihat, ikon: MonitorSmartphoneIcon },
    { label: 'Pengguna & peran', href: '/kelola/pengguna', izin: IzinTenant.PenggunaLihat, ikon: UsersIcon },
    { label: 'Log audit', href: '/kelola/log-audit', izin: IzinTenant.AuditLihat, ikon: ScrollTextIcon },
    { label: 'Langganan', href: '/kelola/langganan', izin: IzinTenant.LanggananKelola, ikon: CreditCardIcon },
    { label: 'Bantuan', href: '/kelola/bantuan', izin: IzinTenant.BantuanTiketLihat, ikon: LifeBuoyIcon },
];

/**
 * Sumber data pencarian cepat. Aktif hanya bila halaman daftarnya (`alamat`) ada di menu yang boleh dilihat, jadi
 * mengikuti izin yang sama dengan sidebar; server tetap memeriksa izin & tenant pada endpoint JSON TabelData.
 */
const sumberPencarian: SumberPencarian[] = [
    {
        id: 'produk',
        label: 'Produk',
        alamat: '/kelola/produk',
        ikon: PackageIcon,
        AmbilHasil: (b) => ({
            judul: String(b.Nama),
            keterangan: typeof b.Sku === 'string' ? b.Sku : null,
            href: `/kelola/produk/${String(b.Uuid)}`,
        }),
    },
    {
        id: 'pelanggan',
        label: 'Pelanggan',
        alamat: '/kelola/pelanggan',
        ikon: UsersRoundIcon,
        AmbilHasil: (b) => ({
            judul: String(b.Nama),
            keterangan: typeof b.NoHp === 'string' ? b.NoHp : null,
            href: `/kelola/pelanggan/${String(b.Uuid)}`,
        }),
    },
    {
        id: 'pemasok',
        label: 'Pemasok',
        alamat: '/kelola/pembelian/pemasok',
        ikon: ShoppingCartIcon,
        // Pemasok tidak punya halaman detail: buka daftarnya dengan pencarian nama ini.
        AmbilHasil: (b) => ({
            judul: String(b.Nama),
            keterangan: typeof b.Kode === 'string' ? b.Kode : null,
            href: `/kelola/pembelian/pemasok?${new URLSearchParams({ cari: String(b.Nama) }).toString()}`,
        }),
    },
    {
        id: 'penjualan',
        label: 'Penjualan',
        alamat: '/kelola/penjualan',
        ikon: ReceiptTextIcon,
        AmbilHasil: (b) => ({
            judul: String(b.Nomor),
            keterangan: typeof b.NamaOutlet === 'string' ? b.NamaOutlet : null,
            href: `/kelola/penjualan/${String(b.Uuid)}`,
        }),
    },
];

/** Halaman & sumber data pencarian cepat untuk menu yang boleh dilihat (grup menu jadi keterangan halaman). */
export function SusunPencarian(menuTerlihat: MenuTerlihat[]): {
    halaman: HalamanPencarian[];
    sumber: SumberPencarian[];
} {
    const halaman = menuTerlihat.flatMap(({ menu, sub }): HalamanPencarian[] =>
        sub.length === 0
            ? [{ label: menu.label, href: menu.href, grup: null, ikon: menu.ikon }]
            : sub.map((item) => ({ label: item.label, href: item.href, grup: menu.label, ikon: menu.ikon })),
    );
    const alamatTerlihat = new Set(halaman.map((h) => h.href));

    return { halaman, sumber: sumberPencarian.filter((s) => alamatTerlihat.has(s.alamat)) };
}

function CekGrupMenu(menu: ItemMenu | GrupMenu): menu is GrupMenu {
    return 'sub' in menu;
}

/** Menu utama yang aktif untuk URL ini (grup untuk seluruh sub-menunya; Pengguna & peran juga untuk /kelola/peran). */
export function CekMenuAktif(href: string, url: string): boolean {
    if (href === '/kelola') {
        return url === '/kelola';
    }

    const grup = daftarMenu.find((menu): menu is GrupMenu => CekGrupMenu(menu) && menu.href === href);

    if (grup) {
        return CariSubMenuAktif(grup.sub, url) !== null;
    }

    return url.startsWith(href) || (href === '/kelola/pengguna' && url.startsWith('/kelola/peran'));
}

type MenuTerlihat = { menu: ItemMenu; labelSub: string | null; sub: ItemMenu[] };

/** Menu utama yang boleh dilihat pemegang akses ini beserta sub-menunya; grup tanpa sub-menu boleh disembunyikan. */
export function SaringMenuTerlihat(akses: PropsBersamaAplikasi['Akses']): MenuTerlihat[] {
    const CekBoleh = (menu: ItemMenu) => menu.izin === null || PunyaIzinTenant(akses, menu.izin);

    return daftarMenu.flatMap((menu): MenuTerlihat[] => {
        if (!CekBoleh(menu)) {
            return [];
        }

        if (!CekGrupMenu(menu)) {
            return [{ menu, labelSub: null, sub: [] }];
        }

        const sub = menu.sub.filter(CekBoleh);
        const pertama = sub[0];

        return pertama === undefined ? [] : [{ menu: { ...menu, href: pertama.href }, labelSub: menu.labelSub, sub }];
    });
}

/**
 * Satu menu utama sidebar. Grup bersub-menu adalah tombol Collapsible: klik label membuka/menutup sub-menu dengan
 * animasi tinggi dan chevron memutar 90°. Saat sidebar diciutkan menjadi ikon (sub-menu tak terlihat), klik langsung
 * menuju sub-menu pertama. Grup halaman aktif terbuka sejak awal (tanpa animasi saat dimuat).
 */
function ItemMenuSidebar({ menu, labelSub, sub, url }: MenuTerlihat & { url: string }) {
    const { state, isMobile } = useSidebar();
    const subAktif = labelSub === null ? null : CariSubMenuAktif(sub, url);
    const aktif = labelSub === null ? CekMenuAktif(menu.href, url) : subAktif !== null;
    const [terbuka, AturTerbuka] = useState(subAktif !== null);
    const Ikon = menu.ikon;
    const ikon = Ikon ? <Ikon aria-hidden="true" /> : null;

    if (labelSub === null) {
        return (
            <SidebarMenuItem>
                <SidebarMenuButton asChild isActive={aktif} tooltip={menu.label} className={kelasTombolMenuSidebar}>
                    <Link href={menu.href} aria-current={aktif ? 'page' : undefined}>
                        {ikon}
                        <span>{menu.label}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        );
    }

    const TanganiKlikGrup = (peristiwa: MouseEvent<HTMLButtonElement>) => {
        if (state === 'collapsed' && !isMobile) {
            peristiwa.preventDefault();
            router.visit(menu.href);
        }
    };

    return (
        <Collapsible asChild open={terbuka} onOpenChange={AturTerbuka}>
            <SidebarMenuItem>
                <CollapsibleTrigger asChild onClick={TanganiKlikGrup}>
                    <SidebarMenuButton
                        isActive={aktif}
                        tooltip={menu.label}
                        className={cn(kelasTombolMenuSidebar, kelasTombolGrupSidebar)}
                    >
                        {ikon}
                        <span>{menu.label}</span>
                        <ChevronRightIcon aria-hidden="true" className={kelasChevronGrupSidebar} />
                    </SidebarMenuButton>
                </CollapsibleTrigger>
                <CollapsibleContent className="overflow-hidden data-[state=closed]:animate-collapsible-up data-[state=open]:animate-collapsible-down">
                    <nav aria-label={labelSub}>
                        <SidebarMenuSub className="mt-1 mb-1">
                            {sub.map((item) => (
                                <SidebarMenuSubItem key={item.href}>
                                    <SidebarMenuSubButton
                                        asChild
                                        isActive={subAktif === item.href}
                                        className={kelasTombolSubMenuSidebar}
                                    >
                                        <Link
                                            href={item.href}
                                            aria-current={subAktif === item.href ? 'page' : undefined}
                                        >
                                            {item.label}
                                        </Link>
                                    </SidebarMenuSubButton>
                                </SidebarMenuSubItem>
                            ))}
                        </SidebarMenuSub>
                    </nav>
                </CollapsibleContent>
            </SidebarMenuItem>
        </Collapsible>
    );
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
    const menuTerlihat = SaringMenuTerlihat(props.Akses);
    const pencarian = SusunPencarian(menuTerlihat);
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
            <Sidebar collapsible="icon" className="border-sidebar-border">
                <KepalaSidebarMerek nama={props.NamaAplikasi} />
                <SidebarContent>
                    {tenantAktif && props.Akses ? (
                        <nav aria-label="Menu utama">
                            <SidebarGroup className="px-3 py-3">
                                <SidebarGroupContent>
                                    <SidebarMenu>
                                        {menuTerlihat.map((terlihat) => (
                                            <ItemMenuSidebar key={terlihat.menu.label} {...terlihat} url={url} />
                                        ))}
                                    </SidebarMenu>
                                </SidebarGroupContent>
                            </SidebarGroup>
                        </nav>
                    ) : null}
                </SidebarContent>
                <SidebarFooter className="border-t border-sidebar-border px-3 py-3">
                    <SidebarMenu>
                        <SidebarMenuItem>
                            {/* Auth tenant: keamanan akun & 2FA (BR-00.8). */}
                            <SidebarMenuButton
                                asChild
                                isActive={keamananAktif}
                                tooltip="Keamanan akun"
                                className={kelasTombolMenuSidebar}
                            >
                                <Link href="/kelola/keamanan" aria-current={keamananAktif ? 'page' : undefined}>
                                    <ShieldCheckIcon aria-hidden="true" />
                                    <span>Keamanan akun</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarFooter>
                {/* Rel hanya pintasan tetikus (tabIndex -1); tombol di bilah atas adalah kontrol yang diumumkan. */}
                <SidebarRail aria-hidden="true" aria-label={undefined} title="Buka atau tutup menu samping" />
            </Sidebar>
            <div data-slot="sidebar-inset" className="relative flex w-full min-w-0 flex-1 flex-col bg-latar">
                <KepalaTataLetak induk={namaInduk} judul={judul}>
                    <PencarianCepat halaman={pencarian.halaman} sumber={pencarian.sumber} />
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

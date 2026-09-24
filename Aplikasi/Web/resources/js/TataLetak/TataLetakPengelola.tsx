import { Head, Link, usePage } from '@inertiajs/react';
import {
    ActivityIcon,
    BookOpenIcon,
    Building2Icon,
    HouseIcon,
    LayoutTemplateIcon,
    LibraryIcon,
    LifeBuoyIcon,
    PlugIcon,
    ReceiptIcon,
    ScaleIcon,
    ScrollTextIcon,
    UsersRoundIcon,
    type LucideIcon,
} from 'lucide-react';
import type { ReactNode } from 'react';

import {
    Sidebar,
    SidebarContent,
    SidebarGroup,
    SidebarGroupContent,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarProvider,
    SidebarRail,
} from '@/Komponen/Ui/sidebar';
import PenandaLingkungan from '@/Komponen/Umpan/PenandaLingkungan';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { IzinPengelola, PunyaIzin, type KunciIzinPengelola, type PropsBersamaPengelola } from '@/Tipe/Pengelola';

import { BacaSidebarTerbuka, KepalaTataLetak, MenuAkun, PemberitahuanMelayang } from './BagianTataLetak';

type PropsTataLetak = {
    judul: string;
    aksi?: ReactNode;
    children: ReactNode;
};

type ItemMenu = { label: string; href: string; izin: KunciIzinPengelola | null; ikon: LucideIcon };

const daftarMenu: ItemMenu[] = [
    { label: 'Beranda', href: '/', izin: null, ikon: HouseIcon },
    { label: 'Katalog', href: '/katalog/paket', izin: IzinPengelola.KatalogLihat, ikon: BookOpenIcon },
    // P-08 Tagihan langganan & verifikasi transfer.
    { label: 'Tagihan', href: '/tagihan', izin: IzinPengelola.TagihanLihat, ikon: ReceiptIcon },
    { label: 'Template sektor', href: '/template-sektor', izin: IzinPengelola.TemplateLihat, ikon: LayoutTemplateIcon },
    { label: 'Referensi', href: '/referensi/tarif-pajak', izin: IzinPengelola.ReferensiLihat, ikon: LibraryIcon },
    { label: 'Legal', href: '/legal', izin: IzinPengelola.LegalLihat, ikon: ScaleIcon },
    { label: 'Integrasi', href: '/integrasi', izin: IzinPengelola.IntegrasiLihat, ikon: PlugIcon },
    // P-09
    { label: 'Dukungan', href: '/dukungan/tiket', izin: IzinPengelola.DukunganTiketLihat, ikon: LifeBuoyIcon },
    // P-11
    { label: 'Operasional', href: '/operasional', izin: IzinPengelola.OperasionalLihat, ikon: ActivityIcon },
    { label: 'Tim internal', href: '/tim-internal', izin: IzinPengelola.TimAnggotaLihat, ikon: UsersRoundIcon },
    { label: 'Log audit', href: '/log-audit', izin: IzinPengelola.AuditLihat, ikon: ScrollTextIcon },
    // P-07 Siklus hidup tenant.
    { label: 'Tenant', href: '/tenant', izin: IzinPengelola.TenantLihat, ikon: Building2Icon },
];

/** Menu aktif: Beranda hanya untuk "/", lainnya menurut segmen pertama URL (/katalog/addon → Katalog). */
export function CekMenuPengelolaAktif(href: string, url: string): boolean {
    const awalan = href.split('/').slice(0, 2).join('/');

    return href === '/' ? url === '/' : url.startsWith(awalan);
}

/**
 * Tata letak Platform Pengelola (PRD §13.8): kepala gelap yang berbeda dari back-office tenant, penanda lingkungan
 * selalu terlihat, menu samping shadcn/ui sesuai izin.
 */
export default function TataLetakPengelola({ judul, aksi, children }: PropsTataLetak) {
    const { props, url } = usePage<PropsBersamaPengelola>();
    const pengguna = props.Pengguna;
    const menuTerlihat = daftarMenu.filter((menu) => menu.izin === null || PunyaIzin(pengguna, menu.izin));
    const namaPlatform = `${props.NamaAplikasi} · Pengelola`;

    return (
        <SidebarProvider defaultOpen={BacaSidebarTerbuka()}>
            <Head title={judul} />
            <Sidebar collapsible="icon">
                <SidebarHeader className="border-b border-sidebar-border">
                    <p
                        className="truncate px-2 py-1.5 text-subjudul font-bold text-teks-utama group-data-[collapsible=icon]:sr-only"
                        title={namaPlatform}
                    >
                        {namaPlatform}
                    </p>
                </SidebarHeader>
                <SidebarContent>
                    <nav aria-label="Menu utama">
                        <SidebarGroup>
                            <SidebarGroupContent>
                                <SidebarMenu>
                                    {menuTerlihat.map((menu) => {
                                        const aktif = CekMenuPengelolaAktif(menu.href, url);
                                        const Ikon = menu.ikon;

                                        return (
                                            <SidebarMenuItem key={menu.href}>
                                                <SidebarMenuButton
                                                    asChild
                                                    isActive={aktif}
                                                    tooltip={menu.label}
                                                    className="text-label data-[active=true]:font-semibold"
                                                >
                                                    <Link href={menu.href} aria-current={aktif ? 'page' : undefined}>
                                                        <Ikon aria-hidden="true" />
                                                        <span>{menu.label}</span>
                                                    </Link>
                                                </SidebarMenuButton>
                                            </SidebarMenuItem>
                                        );
                                    })}
                                </SidebarMenu>
                            </SidebarGroupContent>
                        </SidebarGroup>
                    </nav>
                </SidebarContent>
                <SidebarRail aria-label="Buka atau tutup menu samping" title="Buka atau tutup menu samping" />
            </Sidebar>
            <div data-slot="sidebar-inset" className="relative flex w-full min-w-0 flex-1 flex-col bg-latar">
                <div className="sticky top-0 z-20">
                    <PenandaLingkungan lingkungan={props.Lingkungan} />
                    <KepalaTataLetak induk={namaPlatform} judul={judul} gelap lengket={false}>
                        <MenuAkun nama={pengguna?.Nama} email={pengguna?.Email} gelap />
                    </KepalaTataLetak>
                </div>
                <main className="mx-auto flex w-full max-w-6xl flex-col gap-4 px-4 py-6">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <h1 className="text-judul font-bold text-teks-utama">{judul}</h1>
                        {aksi}
                    </div>
                    {props.PeringatanSuperAdmin ? (
                        <Pemberitahuan jenis="peringatan" judul="Super Admin aktif kurang dari 2">
                            Undang minimal satu Super Admin lagi agar Platform Pengelola tetap bisa dikelola bila satu
                            akun terkunci.
                        </Pemberitahuan>
                    ) : null}
                    {props.PeringatanIntegrasi.length > 0 ? (
                        <Pemberitahuan jenis="peringatan" judul="Status integrasi">
                            <ul className="list-disc pl-5">
                                {props.PeringatanIntegrasi.map((pesan) => (
                                    <li key={pesan}>{pesan}</li>
                                ))}
                            </ul>
                        </Pemberitahuan>
                    ) : null}
                    {/* P-11 BR-P11.1: banner kondisi operasional. */}
                    {props.PeringatanOperasional.length > 0 ? (
                        <Pemberitahuan jenis="bahaya" judul="Masalah operasional">
                            <ul className="list-disc pl-5">
                                {props.PeringatanOperasional.map((pesan) => (
                                    <li key={pesan}>{pesan}</li>
                                ))}
                            </ul>
                        </Pemberitahuan>
                    ) : null}
                    {props.Kilat ? <Pemberitahuan jenis="sukses">{props.Kilat}</Pemberitahuan> : null}
                    {children}
                </main>
            </div>
            <PemberitahuanMelayang />
        </SidebarProvider>
    );
}

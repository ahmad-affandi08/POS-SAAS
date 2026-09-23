import { Head, Link, router, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

import PenandaLingkungan from '@/Komponen/Umpan/PenandaLingkungan';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { IzinPengelola, PunyaIzin, type KunciIzinPengelola, type PropsBersamaPengelola } from '@/Tipe/Pengelola';

type PropsTataLetak = {
    judul: string;
    aksi?: ReactNode;
    children: ReactNode;
};

type ItemMenu = { label: string; href: string; izin: KunciIzinPengelola | null };

const daftarMenu: ItemMenu[] = [
    { label: 'Beranda', href: '/', izin: null },
    { label: 'Katalog', href: '/katalog/paket', izin: IzinPengelola.KatalogLihat },
    { label: 'Template sektor', href: '/template-sektor', izin: IzinPengelola.TemplateLihat },
    { label: 'Referensi', href: '/referensi/tarif-pajak', izin: IzinPengelola.ReferensiLihat },
    { label: 'Legal', href: '/legal', izin: IzinPengelola.LegalLihat },
    { label: 'Integrasi', href: '/integrasi', izin: IzinPengelola.IntegrasiLihat },
    { label: 'Tim internal', href: '/tim-internal', izin: IzinPengelola.TimAnggotaLihat },
    { label: 'Log audit', href: '/log-audit', izin: IzinPengelola.AuditLihat },
    // P-07 Siklus hidup tenant.
    { label: 'Tenant', href: '/tenant', izin: IzinPengelola.TenantLihat },
];

/**
 * Tata letak Platform Pengelola (PRD §13.8): kepala gelap yang berbeda dari back-office tenant,
 * penanda lingkungan selalu terlihat, menu sesuai izin.
 */
export default function TataLetakPengelola({ judul, aksi, children }: PropsTataLetak) {
    const { props, url } = usePage<PropsBersamaPengelola>();
    const pengguna = props.Pengguna;
    const menuTerlihat = daftarMenu.filter((menu) => menu.izin === null || PunyaIzin(pengguna, menu.izin));
    const Keluar = () => router.post('/keluar');

    return (
        <>
            <Head title={judul} />
            <PenandaLingkungan lingkungan={props.Lingkungan} />
            <header className="border-b border-garis bg-teks-utama text-permukaan">
                <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-3">
                    <p className="text-subjudul font-bold">{props.NamaAplikasi} · Pengelola</p>
                    <div className="flex items-center gap-3">
                        <span className="text-label">{pengguna?.Nama}</span>
                        <button
                            type="button"
                            onClick={Keluar}
                            className="rounded-kontrol border border-permukaan px-3 py-1 text-label font-semibold outline-none focus-visible:ring-2 focus-visible:ring-permukaan"
                        >
                            Keluar
                        </button>
                    </div>
                </div>
                <nav aria-label="Menu utama" className="mx-auto flex max-w-6xl gap-1 px-4">
                    {menuTerlihat.map((menu) => {
                        const awalan = menu.href.split('/').slice(0, 2).join('/');
                        const aktif = menu.href === '/' ? url === '/' : url.startsWith(awalan);

                        return (
                            <Link
                                key={menu.href}
                                href={menu.href}
                                aria-current={aktif ? 'page' : undefined}
                                className={`border-b-2 px-3 py-2 text-label font-semibold outline-none focus-visible:ring-2 focus-visible:ring-permukaan ${
                                    aktif ? 'border-permukaan' : 'border-transparent opacity-80'
                                }`}
                            >
                                {menu.label}
                            </Link>
                        );
                    })}
                </nav>
            </header>
            <main className="mx-auto flex max-w-6xl flex-col gap-4 px-4 py-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-judul font-bold text-teks-utama">{judul}</h1>
                    {aksi}
                </div>
                {props.PeringatanSuperAdmin ? (
                    <Pemberitahuan jenis="peringatan" judul="Super Admin aktif kurang dari 2">
                        Undang minimal satu Super Admin lagi agar Platform Pengelola tetap bisa dikelola bila satu akun
                        terkunci.
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
                {props.Kilat ? <Pemberitahuan jenis="sukses">{props.Kilat}</Pemberitahuan> : null}
                {children}
            </main>
        </>
    );
}

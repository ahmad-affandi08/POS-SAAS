import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';

import Tombol from '@/Komponen/Formulir/Tombol';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';
import { IzinTenant, PunyaIzinTenant, type KunciIzinTenant } from '@/Tipe/Organisasi';

type PropsTataLetak = { judul: string; children: ReactNode };

// F-02: menu back-office berbasis izin (hanya UX; server tetap memeriksa izin).
const daftarMenu: { label: string; href: string; izin: KunciIzinTenant | null }[] = [
    { label: 'Beranda', href: '/kelola', izin: null },
    { label: 'Outlet', href: '/kelola/outlet', izin: IzinTenant.OutletLihat },
    { label: 'Pengguna & peran', href: '/kelola/pengguna', izin: IzinTenant.PenggunaLihat },
    { label: 'Log audit', href: '/kelola/log-audit', izin: IzinTenant.AuditLihat },
];

/** Tata letak back-office tenant (/kelola). Menu modul ditambahkan per flow (F-01 dst.). */
export default function TataLetakAplikasi({ judul, children }: PropsTataLetak) {
    const { props, url } = usePage<PropsBersamaAplikasi>();
    const menuTerlihat = daftarMenu.filter((menu) => menu.izin === null || PunyaIzinTenant(props.Akses, menu.izin));
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
                    <p className="text-subjudul font-bold text-teks-utama">
                        {props.TenantAktif?.Nama ?? props.NamaAplikasi}
                    </p>
                    <div className="flex items-center gap-3">
                        <span className="text-label text-teks-sekunder">{props.Pengguna?.Nama}</span>
                        <Tombol varian="sekunder" onClick={() => router.post('/keluar')}>
                            Keluar
                        </Tombol>
                    </div>
                </div>
                {props.Akses ? (
                    <nav aria-label="Menu utama" className="mx-auto flex max-w-6xl flex-wrap gap-1 px-4">
                        {menuTerlihat.map((menu) => {
                            const aktif =
                                menu.href === '/kelola'
                                    ? url === '/kelola'
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
                {props.Kilat ? <Pemberitahuan jenis="sukses">{props.Kilat}</Pemberitahuan> : null}
                {props.errors.Umum ? <Pemberitahuan jenis="bahaya">{props.errors.Umum}</Pemberitahuan> : null}
                {children}
            </main>
        </>
    );
}

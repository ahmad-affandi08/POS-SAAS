import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';

import Tombol from '@/Komponen/Formulir/Tombol';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import { FormatTanggal } from '@/Pustaka/FormatWaktu';
import type { PropsBersamaAplikasi } from '@/Tipe/Aplikasi';

type PropsTataLetak = { judul: string; children: ReactNode };

/** Tata letak back-office tenant (/kelola). Menu modul ditambahkan per flow (F-01 dst.). */
export default function TataLetakAplikasi({ judul, children }: PropsTataLetak) {
    const { props } = usePage<PropsBersamaAplikasi>();
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
                {props.Kilat ? <Pemberitahuan jenis="sukses">{props.Kilat}</Pemberitahuan> : null}
                {props.errors.Umum ? <Pemberitahuan jenis="bahaya">{props.errors.Umum}</Pemberitahuan> : null}
                {children}
            </main>
        </>
    );
}

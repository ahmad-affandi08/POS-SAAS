import { Link, usePage } from '@inertiajs/react';

import TataLetakPengelola from '@/TataLetak/TataLetakPengelola';
import { IzinPengelola, PunyaIzin, type PropsBersamaPengelola } from '@/Tipe/Pengelola';

/** Beranda Platform Pengelola. Ringkasan per flow (tenant, tagihan, monitoring) ditambahkan di P-07 s.d. P-11. */
export default function Beranda() {
    const { props } = usePage<PropsBersamaPengelola>();
    const pengguna = props.Pengguna;

    return (
        <TataLetakPengelola judul={`Halo, ${pengguna?.Nama ?? ''}`}>
            <section className="rounded-panel border border-garis bg-permukaan p-6">
                <h2 className="text-subjudul font-semibold text-teks-utama">Yang bisa Anda lakukan</h2>
                <ul className="mt-2 flex list-disc flex-col gap-1 pl-5 text-isi text-teks-utama">
                    {PunyaIzin(pengguna, IzinPengelola.TimAnggotaLihat) ? (
                        <li>
                            <Link href="/tim-internal" className="font-semibold text-brand underline">
                                Kelola tim internal
                            </Link>
                            : undang anggota, tetapkan peran, nonaktifkan akun.
                        </li>
                    ) : null}
                    {PunyaIzin(pengguna, IzinPengelola.AuditLihat) ? (
                        <li>
                            <Link href="/log-audit" className="font-semibold text-brand underline">
                                Lihat log audit
                            </Link>
                            : semua aksi pengelola tercatat.
                        </li>
                    ) : null}
                    {pengguna?.Izin.length === 0 ? (
                        <li>
                            Menu untuk peran Anda tersedia bersama modul berikutnya. Hubungi Super Admin bila butuh
                            akses.
                        </li>
                    ) : null}
                </ul>
            </section>
        </TataLetakPengelola>
    );
}

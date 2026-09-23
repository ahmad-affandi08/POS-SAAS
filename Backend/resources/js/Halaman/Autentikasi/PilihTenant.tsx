import { router } from '@inertiajs/react';
import { useState } from 'react';

import Tombol from '@/Komponen/Formulir/Tombol';
import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import TataLetakAutentikasi from '@/TataLetak/TataLetakAutentikasi';

/** Pemilih tenant setelah masuk (BR-00.1). */
export default function HalamanPilihTenant({ Tenant }: { Tenant: { Uuid: string; Nama: string }[] }) {
    const [memproses, AturMemproses] = useState(false);

    return (
        <TataLetakAutentikasi judul="Pilih usaha">
            {Tenant.length === 0 ? (
                <div className="flex flex-col gap-4">
                    <Pemberitahuan jenis="info" judul="Belum ada usaha">
                        Akun Anda belum menjadi anggota usaha mana pun. Minta pemilik usaha mengundang Anda.
                    </Pemberitahuan>
                    <Tombol varian="sekunder" onClick={() => router.post('/keluar')}>
                        Keluar
                    </Tombol>
                </div>
            ) : (
                <ul className="flex flex-col gap-2">
                    {Tenant.map((tenant) => (
                        <li key={tenant.Uuid}>
                            <button
                                type="button"
                                disabled={memproses}
                                onClick={() =>
                                    router.post(
                                        '/pilih-tenant',
                                        { Tenant: tenant.Uuid },
                                        { onStart: () => AturMemproses(true), onFinish: () => AturMemproses(false) },
                                    )
                                }
                                className="w-full rounded-kontrol border border-garis-input px-4 py-3 text-left text-isi font-semibold text-teks-utama outline-none focus-visible:ring-2 focus-visible:ring-brand"
                            >
                                {tenant.Nama}
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </TataLetakAutentikasi>
    );
}

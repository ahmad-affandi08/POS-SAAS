import { Link } from '@inertiajs/react';

import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';
import TataLetakAutentikasiPengelola from '@/TataLetak/TataLetakAutentikasiPengelola';

/** Kode pemulihan 2FA, ditampilkan sekali saja setelah aktivasi. */
export default function KodePemulihan({ KodePemulihan }: { KodePemulihan: string[] }) {
    return (
        <TataLetakAutentikasiPengelola judul="Simpan kode pemulihan" keterangan="Verifikasi dua langkah sudah aktif.">
            <div className="flex flex-col gap-4">
                <Pemberitahuan jenis="peringatan" judul="Kode ini hanya ditampilkan sekali">
                    Simpan di pengelola kata sandi. Setiap kode hanya bisa dipakai sekali saat ponsel Anda tidak
                    tersedia.
                </Pemberitahuan>
                <ul className="grid grid-cols-2 gap-2 font-mono text-isi text-teks-utama">
                    {KodePemulihan.map((kode) => (
                        <li key={kode} className="rounded-kontrol border border-garis px-3 py-2 text-center">
                            {kode}
                        </li>
                    ))}
                </ul>
                <Link
                    href="/"
                    className="inline-flex h-10 items-center justify-center rounded-kontrol border border-brand bg-brand px-4 text-label font-semibold text-permukaan outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2"
                >
                    Saya sudah menyimpannya
                </Link>
            </div>
        </TataLetakAutentikasiPengelola>
    );
}

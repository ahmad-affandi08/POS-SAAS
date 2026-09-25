import { Link } from '@inertiajs/react';

import { Button } from '@/Komponen/Ui/button';
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
                        <li key={kode} className="rounded-kontrol border border-garis bg-latar px-3 py-2 text-center">
                            {kode}
                        </li>
                    ))}
                </ul>
                <Button
                    asChild
                    className="h-8 pointer-coarse:h-11 px-4 text-label font-semibold focus-visible:ring-offset-2"
                >
                    <Link href="/">Saya sudah menyimpannya</Link>
                </Button>
            </div>
        </TataLetakAutentikasiPengelola>
    );
}

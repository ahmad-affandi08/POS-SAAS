import { Link } from '@inertiajs/react';

import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';

/** Beranda back-office sementara; dasbor dibangun F-14. */
export default function HalamanBerandaKelola() {
    return (
        <TataLetakAplikasi judul="Beranda">
            <p className="text-isi text-teks-sekunder">
                Lanjutkan penyiapan usaha Anda di{' '}
                <Link href="/kelola/panduan-awal" className="font-semibold text-brand underline">
                    panduan awal
                </Link>
                .
            </p>
        </TataLetakAplikasi>
    );
}

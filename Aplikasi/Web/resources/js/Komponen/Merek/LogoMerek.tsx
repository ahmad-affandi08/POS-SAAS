import gambarIkon from '@/Aset/Merek/IkonMerek.png';
import gambarIkonPutih from '@/Aset/Merek/IkonMerekPutih.png';
import gambarLogo from '@/Aset/Merek/LogoHorizontal.png';
import gambarLogoPutih from '@/Aset/Merek/LogoHorizontalPutih.png';
import { cn } from '@/Komponen/Ui/utils';

type PropsLogo = { nama: string; className?: string; varian?: 'warna' | 'putih' };

/**
 * Logo horizontal PAYOU beserta tagline (sumber: `Spesifikasi/Merek`). Dipakai di layar masuk/daftar.
 * Teks alternatif = nama aplikasi, sehingga pembaca layar tetap membaca nama sistem.
 */
export function LogoMerek({ nama, className, varian = 'warna' }: PropsLogo) {
    return (
        <img
            src={varian === 'putih' ? gambarLogoPutih : gambarLogo}
            alt={nama}
            className={cn('h-14 w-auto', className)}
        />
    );
}

/** Tanda merek (huruf P) tanpa teks, untuk ruang sempit seperti kepala menu samping. */
export function IkonMerek({ nama, className, varian = 'warna' }: PropsLogo) {
    return (
        <img
            src={varian === 'putih' ? gambarIkonPutih : gambarIkon}
            alt={nama}
            className={cn('size-8 shrink-0 object-contain', className)}
        />
    );
}

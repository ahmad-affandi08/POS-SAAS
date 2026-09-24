import gambarIkon from '@/Aset/Merek/IkonMerek.png';
import gambarLogo from '@/Aset/Merek/LogoHorizontal.png';
import { cn } from '@/Komponen/Ui/utils';

type PropsLogo = { nama: string; className?: string };

/**
 * Logo horizontal PAYOU beserta slogan (sumber: `Spesifikasi/Merek`). Dipakai di layar masuk/daftar.
 * Teks alternatif = nama aplikasi, sehingga pembaca layar tetap membaca nama sistem.
 */
export function LogoMerek({ nama, className }: PropsLogo) {
    return <img src={gambarLogo} alt={nama} className={cn('h-14 w-auto', className)} />;
}

/** Tanda merek (huruf P) tanpa teks, untuk ruang sempit seperti kepala menu samping. */
export function IkonMerek({ nama, className }: PropsLogo) {
    return <img src={gambarIkon} alt={nama} className={cn('size-8 shrink-0 object-contain', className)} />;
}

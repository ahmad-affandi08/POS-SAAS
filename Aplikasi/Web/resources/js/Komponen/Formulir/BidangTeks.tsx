import { Eye, EyeOff } from 'lucide-react';
import { useId, useState, type InputHTMLAttributes } from 'react';

import { Input } from '@/Komponen/Ui/input';
import { cn } from '@/Komponen/Ui/utils';

import {
    BuatKelasKontrol,
    GabungDijelaskanOleh,
    GalatBidang,
    KerangkaBidang,
    KeteranganBidang,
    LabelBidang,
} from './BagianBidang';

type PropsBidangTeks = {
    label: string;
    nilai: string;
    saatBerubah: (nilai: string) => void;
    galat?: string | undefined;
    keterangan?: string;
    jenis?: 'text' | 'email' | 'password';
    kode?: boolean;
} & Pick<
    InputHTMLAttributes<HTMLInputElement>,
    'autoComplete' | 'autoFocus' | 'disabled' | 'inputMode' | 'maxLength' | 'required'
>;

/**
 * Input teks dengan label, keterangan, dan pesan galat yang terhubung ke aria (PRD §17.6).
 *
 * `jenis="password"` otomatis mendapat tombol mata untuk menampilkan/menyembunyikan isinya, jadi setiap
 * bidang kata sandi & PIN di seluruh aplikasi ikut tanpa perubahan di halamannya masing-masing.
 * Label tombol menyebut nama bidangnya ("Tampilkan Kata sandi baru"), karena satu formulir bisa memuat
 * tiga bidang kata sandi sekaligus dan tombol bernama sama akan ambigu bagi pembaca layar.
 */
export default function BidangTeks({
    label,
    nilai,
    saatBerubah,
    galat,
    keterangan,
    jenis = 'text',
    kode = false,
    ...atribut
}: PropsBidangTeks) {
    const id = useId();
    const idKeterangan = `${id}-keterangan`;
    const idGalat = `${id}-galat`;
    const [terlihat, AturTerlihat] = useState(false);
    const kataSandi = jenis === 'password';

    const bidang = (
        <Input
            id={id}
            // Hanya tampilan yang berubah; nilainya tetap dikirim sebagai kata sandi.
            type={kataSandi && terlihat ? 'text' : jenis}
            value={nilai}
            onChange={(peristiwa) => saatBerubah(peristiwa.target.value)}
            aria-invalid={galat ? true : undefined}
            aria-describedby={GabungDijelaskanOleh(keterangan && idKeterangan, galat && idGalat)}
            className={BuatKelasKontrol(
                galat,
                cn(kode && 'font-mono tracking-wide', kataSandi && 'pr-10 pointer-coarse:pr-12'),
            )}
            {...atribut}
        />
    );

    return (
        <KerangkaBidang galat={galat}>
            <LabelBidang htmlFor={id}>{label}</LabelBidang>
            {kataSandi ? (
                <div className="relative">
                    {bidang}
                    <button
                        type="button"
                        onClick={() => AturTerlihat((sebelumnya) => !sebelumnya)}
                        disabled={atribut.disabled}
                        // Keadaan dibacakan lewat label yang berganti, bukan aria-pressed, agar tidak diumumkan dua kali.
                        aria-label={`${terlihat ? 'Sembunyikan' : 'Tampilkan'} ${label}`}
                        className="absolute inset-y-0 right-0 inline-flex w-10 items-center justify-center rounded-r-md text-teks-sekunder hover:text-teks-utama focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 pointer-coarse:w-12"
                    >
                        {terlihat ? (
                            <EyeOff className="size-4 pointer-coarse:size-5" aria-hidden />
                        ) : (
                            <Eye className="size-4 pointer-coarse:size-5" aria-hidden />
                        )}
                    </button>
                </div>
            ) : (
                bidang
            )}
            {keterangan ? <KeteranganBidang id={idKeterangan}>{keterangan}</KeteranganBidang> : null}
            {galat ? <GalatBidang id={idGalat}>{galat}</GalatBidang> : null}
        </KerangkaBidang>
    );
}

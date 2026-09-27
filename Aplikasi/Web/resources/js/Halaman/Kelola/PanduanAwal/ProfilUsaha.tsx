import FormProfilUsaha from '@/Komponen/Kelola/FormProfilUsaha';
import TataLetakPanduan from '@/Komponen/PanduanAwal/TataLetakPanduan';
import { AlamatPanduan, type PropsProfilUsaha } from '@/Tipe/PanduanAwal';

/**
 * Langkah 1 F-01. Formulirnya dipakai bersama halaman Pengaturan › Profil usaha lewat `FormProfilUsaha`, supaya
 * profil usaha tetap bisa diubah setelah panduan awal selesai tanpa dua formulir yang bisa berbeda.
 */
export default function HalamanProfilUsaha({ Progres, Profil, Kota, BatasLogo }: PropsProfilUsaha) {
    return (
        <TataLetakPanduan progres={Progres} langkah="ProfilUsaha">
            <FormProfilUsaha alamat={AlamatPanduan.ProfilUsaha} profil={Profil} kota={Kota} batasLogo={BatasLogo} />
        </TataLetakPanduan>
    );
}

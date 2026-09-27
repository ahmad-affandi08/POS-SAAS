import FormProfilUsaha from '@/Komponen/Kelola/FormProfilUsaha';
import TataLetakAplikasi from '@/TataLetak/TataLetakAplikasi';
import type { Kota } from '@/Tipe/Organisasi';
import type { PropsProfilUsaha } from '@/Tipe/PanduanAwal';

type PropsPengaturanProfilUsaha = {
    Profil: PropsProfilUsaha['Profil'];
    Kota: Kota[];
    BatasLogo: PropsProfilUsaha['BatasLogo'];
};

/**
 * Pengaturan › Profil usaha. Formulirnya sama dengan langkah 1 panduan awal (F-01) lewat `FormProfilUsaha`;
 * bedanya hanya bingkai halaman dan tujuan kirimnya, sehingga profil tetap bisa diubah setelah panduan selesai.
 */
export default function HalamanPengaturanProfilUsaha({ Profil, Kota: kota, BatasLogo }: PropsPengaturanProfilUsaha) {
    return (
        <TataLetakAplikasi judul="Profil usaha">
            <p className="text-isi text-teks-sekunder">
                Nama, logo, dan alamat di sini dipakai pada struk, aplikasi kasir, dan dokumen usaha Anda.
            </p>
            <FormProfilUsaha
                alamat="/kelola/pengaturan/profil-usaha"
                profil={Profil}
                kota={kota}
                batasLogo={BatasLogo}
            />
        </TataLetakAplikasi>
    );
}

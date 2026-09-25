import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';

/** F-16b: paket tenant belum punya fitur `pelanggan.loyalti`. */
export default function PesanFiturLoyalti() {
    return (
        <Pemberitahuan jenis="info" judul="Loyalti tersedia di paket Pro ke atas">
            Tier dan pengaturan poin bisa disiapkan sekarang, tetapi poin baru diberikan dan tier baru dievaluasi
            setelah paket dinaikkan di menu Langganan.
        </Pemberitahuan>
    );
}

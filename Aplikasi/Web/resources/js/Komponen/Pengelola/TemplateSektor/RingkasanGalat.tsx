import { Alert, AlertDescription, AlertTitle } from '@/Komponen/Ui/alert';

/** Galat bentuk isian dari server (misal `Akun.3.Kode`) diringkas di atas formulir. */
export default function RingkasanGalat({ galat }: { galat: Record<string, string | undefined> }) {
    const daftar = Object.entries(galat).filter(([kunci, pesan]) => kunci !== 'Umum' && pesan);

    if (daftar.length === 0) {
        return null;
    }

    return (
        <Alert variant="destructive" className="border-bahaya">
            <AlertTitle className="text-label font-semibold text-teks-utama">Periksa kembali isian</AlertTitle>
            <AlertDescription className="text-isi text-teks-sekunder">
                <ul className="list-disc pl-5">
                    {daftar.map(([kunci, pesan]) => (
                        <li key={kunci}>{pesan}</li>
                    ))}
                </ul>
            </AlertDescription>
        </Alert>
    );
}

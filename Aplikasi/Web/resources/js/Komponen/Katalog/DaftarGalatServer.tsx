/**
 * Galat server yang tidak terikat ke isian yang sedang tampil (misal BR-02.1 saat memulihkan produk,
 * BR-03.2 saat menghapus). `Umum` sudah ditampilkan tata letak, jadi dilewati. Diumumkan lewat aria-live.
 */
export default function DaftarGalatServer({
    galat,
    kecuali = [],
}: {
    galat: Record<string, string | undefined>;
    /** Kunci yang sudah tampil di bawah isian formulir halaman ini. */
    kecuali?: string[];
}) {
    const pesan = Object.entries(galat)
        .filter(([kunci, isi]) => kunci !== 'Umum' && Boolean(isi) && !kecuali.includes(kunci))
        .map(([kunci, isi]) => ({ kunci, isi: isi ?? '' }));

    return (
        <div aria-live="polite" aria-atomic="true">
            {pesan.length > 0 ? (
                <div className="rounded-panel border border-l-4 border-bahaya bg-permukaan px-4 py-3" role="alert">
                    <p className="text-label font-semibold text-teks-utama">Perubahan tidak disimpan</p>
                    <ul className="list-disc pl-5 text-isi text-teks-sekunder">
                        {pesan.map((item) => (
                            <li key={item.kunci}>{item.isi}</li>
                        ))}
                    </ul>
                </div>
            ) : null}
        </div>
    );
}

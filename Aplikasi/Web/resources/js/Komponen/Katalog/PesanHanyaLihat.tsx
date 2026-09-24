import Pemberitahuan from '@/Komponen/Umpan/Pemberitahuan';

type PropsPesanHanyaLihat = { izin: string; objek: string };

/** Keadaan tanpa izin ubah (PRD §17.6.6): layar tetap bisa dibaca, tombol ubah disembunyikan, alasannya ditulis. */
export default function PesanHanyaLihat({ izin, objek }: PropsPesanHanyaLihat) {
    return (
        <Pemberitahuan jenis="info" judul="Hanya bisa melihat">
            Anda bisa melihat {objek}, tetapi tidak bisa mengubahnya. Minta Owner menambahkan izin{' '}
            <span className="font-mono">{izin}</span> ke peran Anda.
        </Pemberitahuan>
    );
}

import { Children, type ReactNode } from 'react';

type PropsAksiHalaman = {
    /** Keterangan singkat di kiri (opsional); aksi tetap rata kanan walau ini kosong. */
    keterangan?: ReactNode;
    children: ReactNode;
};

/**
 * Baris aksi halaman: keterangan di kiri, tombol aksi **selalu rata kanan** (D-27).
 *
 * Sebelum ini tiap halaman daftar merakit barisnya sendiri: sebagian memakai `justify-between` (tombol di kanan),
 * sebagian hanya `<div>` biasa (tombol di kiri). Hasilnya posisi tombol "Tambah" berpindah-pindah antar halaman
 * dan pengguna harus mencarinya tiap kali. Rata kanan dipakai lewat `ml-auto`, bukan `justify-between`, supaya
 * tetap kanan meski `keterangan` tidak diisi.
 *
 * Halaman yang tombolnya sudah berada di bilah alat `TabelData` (`aksiAlat`) tidak perlu komponen ini — di sana
 * tombolnya juga sudah rata kanan. Dijaga `AksiHalamanTes`.
 */
export default function AksiHalaman({ keterangan, children }: PropsAksiHalaman) {
    // Banyak halaman mengisi aksinya dengan nilai yang bisa `null` (tombol disembunyikan karena izin). Tanpa
    // penjagaan ini barisnya tetap terbentuk dan menyisakan celah kosong di atas tabel.
    if (Children.toArray(children).length === 0 && keterangan === undefined) {
        return null;
    }

    return (
        <div className="flex flex-wrap items-center gap-2">
            {keterangan}
            <div className="ml-auto flex flex-wrap items-center gap-2">{children}</div>
        </div>
    );
}

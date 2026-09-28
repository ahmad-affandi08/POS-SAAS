import type { ReactNode } from 'react';

/**
 * Bilah aksi formulir satu halaman (D-28, §17.4.4 & §17.4.11).
 *
 * Di bawah 640px bilah ini **menempel di tepi bawah**, jadi "Simpan" tetap terlihat tanpa menggulir sampai habis;
 * dari 640px ke atas ia kembali jadi baris biasa di akhir formulir. Ruang untuk indikator home iOS ikut lewat
 * `tepi-bawah-aman`.
 *
 * Masalah yang diperbaiki: pola ini dirakit sendiri di tiap halaman dengan **enam kombinasi kelas berbeda** — dua
 * halaman persediaan sudah menempel, tiga halaman memakai `justify-end` (padahal D-27 menetapkan tombol simpan di
 * dalam `<form>` tetap rata kiri), sisanya baris biasa. Jadi tindakan yang sama terasa berbeda tergantung modul:
 * di satu formulir Simpan selalu terlihat, di formulir lain pengguna harus menggulir 800 baris ke bawah.
 *
 * Urutan anak: aksi utama dulu, lalu "Batal" — sama seperti seluruh halaman lain.
 */
export default function BilahAksiForm({ children }: { children: ReactNode }) {
    return (
        <div className="sticky bottom-0 flex flex-wrap gap-2 bg-latar py-2 tepi-bawah-aman sm:static sm:bg-transparent sm:py-0">
            {children}
        </div>
    );
}

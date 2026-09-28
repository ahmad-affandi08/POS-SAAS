import { type ClassValue, clsx } from "clsx"
import { extendTailwindMerge } from "tailwind-merge"

/*
 * Helper `cn` shadcn/ui (alias `utils` di components.json). Registry shadcn terbaru mengimpor paket
 * npm `cn`; di proyek ini helper tetap lokal (clsx + tailwind-merge) agar tailwind-merge mengenal
 * token tipografi & radius dari Gaya/Aplikasi.css. Tanpa ini `cn("text-isi", "text-teks-utama")`
 * menganggap keduanya warna teks dan membuang salah satunya.
 *
 * Daftar `text` wajib memuat SELURUH token `--text-*` di Gaya/Aplikasi.css. Token skala situs
 * (sorotan, judul-bagian, pengantar) dulu tidak terdaftar, jadi tailwind-merge menganggapnya warna
 * dan membuang ukurannya: `cn("text-sorotan-besar-hp text-teks-utama sm:text-sorotan-besar")`
 * menghasilkan tanpa ukuran dasar sama sekali, sehingga judul hero di HP turun ke ukuran warisan
 * sementara ukuran `sm:` di layar lebar tetap berlaku. Dijaga Gaya/TokenGabungKelasTes.ts.
 */
const gabungKelasTailwind = extendTailwindMerge({
  extend: {
    theme: {
      text: [
        "sorotan-besar",
        "sorotan-besar-hp",
        "sorotan",
        "sorotan-hp",
        "judul-bagian",
        "judul-bagian-hp",
        "pengantar",
        "tampilan",
        "judul",
        "subjudul",
        "isi",
        "label",
        "keterangan",
      ],
      radius: ["kontrol", "panel"],
    },
  },
})

export function cn(...inputs: ClassValue[]) {
  return gabungKelasTailwind(clsx(inputs))
}

import { type ClassValue, clsx } from "clsx"
import { extendTailwindMerge } from "tailwind-merge"

/*
 * Helper `cn` shadcn/ui (alias `utils` di components.json). Registry shadcn terbaru mengimpor paket
 * npm `cn`; di proyek ini helper tetap lokal (clsx + tailwind-merge) agar tailwind-merge mengenal
 * token tipografi & radius dari Gaya/Aplikasi.css. Tanpa ini `cn("text-isi", "text-teks-utama")`
 * menganggap keduanya warna teks dan membuang salah satunya.
 */
const gabungKelasTailwind = extendTailwindMerge({
  extend: {
    theme: {
      text: ["tampilan", "judul", "subjudul", "isi", "label", "keterangan"],
      radius: ["kontrol", "panel"],
    },
  },
})

export function cn(...inputs: ClassValue[]) {
  return gabungKelasTailwind(clsx(inputs))
}

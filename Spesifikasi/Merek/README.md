# Merek PAYOU (D-15)

Tagline resmi: **Smart Choice Your Business Partner**.

## Sumber utama

| File | Isi |
|---|---|
| `Sumber/LembarMerek.png` | Lembar merek: logo utama, horizontal, ikon, monokrom, palet |
| `Sumber/LogoHorizontal.png` | Logo horizontal berwarna, latar transparan |
| `Sumber/IkonMerek.png` | Tanda huruf P + bintang, latar transparan |
| `Sumber/LogoMonokrom.png` | Logo satu warna (dokumen hitam-putih, struk, faks) |
| `Sumber/LogoHorizontalPutih.png` | Logo horizontal putih lengkap untuk permukaan gelap; dibuat deterministik oleh skrip turunan |
| `Sumber/IkonMerekPutih.png` | Tanda merek putih untuk sidebar yang diciutkan; dibuat deterministik oleh skrip turunan |

## Palet merek → token UI

| Merek | Hex | Token UI (§17.6.3) |
|---|---|---|
| Primary Indigo | `#6366F1` | `Brand` = `#5558E8` (digelapkan: teks putih di atas `#6366F1` hanya 4,47:1) |
| Indigo Gelap | `#1D29B8` | `BrandGelap` (latar sidebar/header merek; teks putih 10,2:1) |
| Navy | `#0F2747` | `TeksUtama` |
| Accent Yellow | `#FBBF24` | Tidak menjadi token UI; hanya di logo (agar tidak tertukar dengan `Peringatan`) |
| Warm Neutral | `#F9FAFB` | `Latar` |
| Cool Gray | `#E5E7EB` | `Garis` |

Nilai token hanya diubah di `Aplikasi/Web/resources/js/Gaya/Aplikasi.css` dan
`Paket/SistemDesain/lib/Token/TokenWarna.dart` (dijaga sama oleh `SumberWarna_test.dart`).

## Turunan

Jalankan setelah mengganti file di `Sumber/`:

```bash
pip install pillow
python3 Spesifikasi/Merek/BuatTurunanAset.py
```

Menghasilkan:

- **Web**: `Aplikasi/Web/public/favicon.ico` (16/32/48), `public/apple-touch-icon.png` (180),
  `resources/js/Aset/Merek/{LogoHorizontal,IkonMerek,LogoHorizontalPutih,IkonMerekPutih}.png`
  (dipakai `Komponen/Merek/LogoMerek.tsx`; pilih `varian="putih"` untuk permukaan gelap).
- **Android** (Kasir & Pemilik): `mipmap-*/ic_launcher.png` (lama) dan adaptive icon
  (`ic_launcher_foreground.png`, latar putih, mendukung ikon bertema Android 13).
- **iOS**: seluruh `AppIcon.appiconset` (tanpa alfa, sesuai syarat App Store).
- **Windows** (Kasir): `windows/runner/resources/app_icon.ico`.
- **Flutter dalam aplikasi**: `Paket/SistemDesain/assets/merek/` (dipakai widget `LogoMerek`;
  gunakan `LogoMerek.lengkapPutih()` atau `LogoMerek.ikonPutih()` untuk permukaan gelap).

Nama tampilan: **PAYOU POS** (Aplikasi POS) dan **PAYOU Owner** (Aplikasi Owner).

# AdaptorPerangkat

Abstraksi perangkat keras aplikasi kasir (PRD §17.2.5, §17.2.5a). Kode fitur tidak memanggil SDK/printer langsung.

- `Printer/DokumenStruk.dart`: isi struk yang netral terhadap printer (teks, dua kolom, garis, QR, gambar, laci).
- `Printer/TataLetakStruk.dart`: memecah dokumen menjadi baris selebar kertas (58 mm = 32 kolom, 80 mm = 48 kolom).
  Hasilnya dipakai pratinjau dan pengode, jadi yang tampil di layar sama dengan yang tercetak.
- `Printer/PengodeEscPos.dart`: dokumen → byte ESC/POS (teks ASCII, tebal/besar, QR `GS ( k`, gambar `GS v 0`,
  potong kertas, buka laci `ESC p`).
- `Printer/TransportPrinter.dart`: antarmuka pengiriman byte. `TransportJaringan` = printer LAN/Wi-Fi port 9100
  (Android, iOS, Windows). Bluetooth, USB, SDK vendor (Sunmi, iMin), dan printer sistem menyusul.
- `Printer/PrinterStruk.dart`: port printer (cetak, cetak uji, buka laci) di atas transport.

Dart murni: `dart test` dari folder ini.

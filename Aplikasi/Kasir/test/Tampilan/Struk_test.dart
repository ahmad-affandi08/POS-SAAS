import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:adaptor_perangkat/AdaptorPerangkat.dart';
import 'package:inti/Inti.dart';
import 'package:kasir/Domain/Struk/PemindaiPrinter.dart';
import 'package:kasir/Domain/Struk/ProfilPrinter.dart';
import 'package:kasir/Tampilan/RuangKerja/RuangKerja.dart';
import 'package:sistem_desain/SistemDesain.dart';

import '../Pendukung/KatalogUji.dart';
import '../Pendukung/LingkunganUji.dart';
import '../Pendukung/PasangAplikasi.dart';

/// Cetak struk di aplikasi (PRD v1.79): atur printer di Pengaturan (cetak uji sebelum simpan), cetak otomatis setelah
/// bayar tunai (plus buka laci), cetak ulang bertanda, galat printer tampil tanpa membatalkan transaksi, bilah status.
void main() {
  Future<LingkunganUji> Masuk(WidgetTester tester, Size ukuran, {ProfilPrinter? printer}) async {
    final u = LingkunganUji.Buat();
    await tester.runAsync(() async {
      await u.SiapkanAktif();
      await u.SiapkanKatalog();
      await printer?.Simpan(u.repositori);
      await u.shift.BukaShift(kasir: await u.Staf('Rina Wulandari'), kasAwal: Uang.DariBulat(500000));
    });
    u.server.penangan = (p) async {
      if (p.url.path.endsWith('/data-awal')) {
        return JsonUji(DataAwalUji());
      }
      if (p.url.path.endsWith('/katalog')) {
        return JsonUji(KatalogUji());
      }
      throw http.ClientException('offline');
    };
    await PasangAplikasi(tester, u, ukuran: ukuran);
    await Tunggu(tester, const Duration(milliseconds: 600));
    await tester.tap(find.text('Rina Wulandari'));
    await tester.pump();
    await KetikPin(tester, KasusPin(0)['Pin']! as String);
    await Tunggu(tester);
    expect(find.byType(RuangKerja), findsOneWidget);
    return u;
  }

  Future<void> Ketuk(WidgetTester tester, Finder finder) async {
    // Digulir ke tengah agar tidak tertutup bilah atas ruang kerja.
    await tester.runAsync(() => Scrollable.ensureVisible(tester.element(finder), alignment: 0.5));
    await tester.pump();
    await tester.tap(finder);
    await Tunggu(tester);
  }

  /// Gulir area kerja ke atas sampai [finder] terbangun (daftar Pengaturan dibangun lazy di layar sempit).
  Future<void> GulirKe(WidgetTester tester, Finder finder) async {
    await tester.scrollUntilVisible(finder, -200, scrollable: find.byType(Scrollable).first);
    await tester.pump();
  }

  Future<void> BayarUangPas(WidgetTester tester, Size ukuran) async {
    await Ketuk(tester, find.byWidgetPredicate((w) => w is UbinProduk && w.nama.startsWith('Americano')));
    if (ukuran.width < 600) {
      await Ketuk(tester, find.textContaining('Keranjang · '));
    }
    await Ketuk(tester, find.widgetWithText(FilledButton, 'Bayar').last);
    await Ketuk(tester, find.widgetWithText(ChoiceChip, 'Tunai'));
    await Ketuk(tester, find.widgetWithText(OutlinedButton, 'Uang pas'));
    await Ketuk(tester, find.widgetWithText(FilledButton, 'Selesaikan pembayaran'));
    expect(find.text('Pembayaran berhasil'), findsOneWidget);
  }

  for (final ukuran in const [Size(1280, 900), Size(800, 1000), Size(360, 740)]) {
    final dp = '${ukuran.width.toInt()} dp';

    testWidgets('atur printer di Pengaturan: validasi, cetak uji, simpan → bilah status "Printer siap" ($dp)', (
      tester,
    ) async {
      final u = await Masuk(tester, ukuran);
      await Ketuk(tester, find.text('Pengaturan').last);
      await Ketuk(tester, find.widgetWithText(FilledButton, 'Atur printer'));

      await Ketuk(tester, find.widgetWithText(FilledButton, 'Simpan printer'));
      expect(find.text('Isi alamat IP printer, misal 192.168.1.50.'), findsOneWidget);

      await tester.enterText(find.widgetWithText(TextField, 'Alamat IP printer'), '192.168.1.50');
      await Ketuk(tester, find.text('80 mm'));
      await Ketuk(tester, find.widgetWithText(OutlinedButton, 'Cetak uji'));
      expect(u.printer.AmbilTeks(), contains('CETAK UJI'));
      expect(u.printer.AmbilTeks(), contains('1234567890' * 4), reason: 'Penggaris 48 kolom kertas 80 mm.');
      expect(find.text('Printer belum diatur'), findsOneWidget, reason: 'Cetak uji belum menyimpan printer.');

      await Ketuk(tester, find.widgetWithText(FilledButton, 'Simpan printer'));
      await GulirKe(tester, find.text('Printer LAN/Wi-Fi 192.168.1.50:9100 · 80 mm'));
      expect(find.text('Printer LAN/Wi-Fi 192.168.1.50:9100 · 80 mm'), findsOneWidget);
      expect(find.text('Printer siap'), findsOneWidget);
      final profil = await tester.runAsync(() => ProfilPrinter.Muat(u.repositori));
      expect(profil?.alamat, '192.168.1.50');
      expect(tester.takeException(), isNull);
      await Lepas(tester, u);
    });

    testWidgets('bayar tunai → struk tercetak otomatis + laci terbuka; cetak lagi bertanda CETAK ULANG ($dp)', (
      tester,
    ) async {
      final u = await Masuk(tester, ukuran, printer: const ProfilPrinter(alamat: '192.168.1.50'));
      await BayarUangPas(tester, ukuran);
      await Tunggu(tester);
      expect(find.text('Struk sudah dicetak.'), findsOneWidget);
      expect(u.printer.kiriman, hasLength(1));
      expect(u.printer.AmbilTeks(), contains('INV/SLB/'));
      expect(u.printer.AmbilTeks(), contains('Americano'));
      expect(u.printer.CekBukaLaci(), isTrue);

      await Ketuk(tester, find.widgetWithText(OutlinedButton, 'Cetak ulang struk'));
      expect(u.printer.kiriman, hasLength(2));
      expect(u.printer.AmbilTeks(), contains('CETAK ULANG'));
      expect(u.printer.CekBukaLaci(), isFalse, reason: 'Laci hanya dibuka saat pembayaran, bukan saat cetak ulang.');
      expect(tester.takeException(), isNull);
      await Lepas(tester, u);
    });
  }

  testWidgets('printer gagal: pesan tampil, transaksi tetap tersimpan, bilah status "Printer bermasalah"', (
    tester,
  ) async {
    final u = await Masuk(tester, const Size(1280, 900), printer: const ProfilPrinter(alamat: '192.168.1.50'));
    u.printer.galat =
        'Printer di 192.168.1.50:9100 tidak tersambung. Pastikan printer menyala dan satu jaringan, '
        'lalu coba lagi.';
    await BayarUangPas(tester, const Size(1280, 900));
    await Tunggu(tester);
    expect(find.textContaining('tidak tersambung'), findsOneWidget);
    expect(find.text('Printer bermasalah'), findsOneWidget);
    expect(find.widgetWithText(OutlinedButton, 'Coba cetak lagi'), findsOneWidget);
    expect(await tester.runAsync(() => u.db.select(u.db.penjualan).get()), hasLength(1));

    u.printer.galat = null;
    await Ketuk(tester, find.widgetWithText(OutlinedButton, 'Coba cetak lagi'));
    expect(find.text('Struk sudah dicetak.'), findsOneWidget);
    expect(find.text('Printer siap'), findsOneWidget);

    // Riwayat: cetak ulang dari daftar transaksi hari ini.
    await Ketuk(tester, find.widgetWithText(FilledButton, 'Transaksi baru'));
    await Ketuk(tester, find.text('Riwayat').last);
    await Ketuk(tester, find.textContaining('INV/SLB/').first);
    await Ketuk(tester, find.widgetWithText(OutlinedButton, 'Cetak ulang struk'));
    expect(find.text('Struk dicetak ulang.'), findsOneWidget);
    expect(u.printer.AmbilTeks(), contains('CETAK ULANG'));
    await Lepas(tester, u);
  });

  testWidgets('printer belum diatur: layar selesai menjelaskan cara mengatur, tidak ada tombol cetak', (tester) async {
    final u = await Masuk(tester, const Size(1280, 900));
    await BayarUangPas(tester, const Size(1280, 900));
    expect(find.text('Printer struk belum diatur. Atur di menu Pengaturan agar struk tercetak.'), findsOneWidget);
    expect(find.widgetWithText(OutlinedButton, 'Cetak struk'), findsNothing);
    expect(u.printer.kiriman, isEmpty);
    await Lepas(tester, u);
  });

  for (final ukuran in const [Size(1280, 900), Size(360, 740)]) {
    testWidgets('printer Bluetooth: cari printer ter-pair, pilih, cetak uji, simpan ($ukuran)', (tester) async {
      final u = await Masuk(tester, ukuran);
      u.pemindai.hasil[JenisTransport.BluetoothKlasik] = const [
        PrinterDitemukan(jenis: JenisTransport.BluetoothKlasik, alamat: '66:22:11:AA:BB:CC', nama: 'RPP02N'),
        PrinterDitemukan(jenis: JenisTransport.BluetoothKlasik, alamat: '00:11:22:33:44:55', nama: 'MTP-II'),
      ];
      await Ketuk(tester, find.text('Pengaturan').last);
      await Ketuk(tester, find.widgetWithText(FilledButton, 'Atur printer'));
      await Ketuk(tester, find.text('Bluetooth'));
      expect(find.widgetWithText(TextField, 'Alamat IP printer'), findsNothing);

      await Ketuk(tester, find.widgetWithText(FilledButton, 'Simpan printer'));
      expect(find.text('Pilih printer dulu. Ketuk Cari printer untuk melihat daftarnya.'), findsOneWidget);

      await Ketuk(tester, find.widgetWithText(OutlinedButton, 'Cari printer'));
      expect(find.text('RPP02N'), findsOneWidget);
      await Ketuk(tester, find.text('MTP-II'));
      await Ketuk(tester, find.widgetWithText(OutlinedButton, 'Cetak uji'));
      expect(u.printer.AmbilTeks(), contains('CETAK UJI'));
      expect(u.pemindai.transportDibuat.last.alamat, '00:11:22:33:44:55');

      await Ketuk(tester, find.widgetWithText(FilledButton, 'Simpan printer'));
      await GulirKe(tester, find.text('Printer Bluetooth MTP-II · 58 mm'));
      expect(find.text('Printer Bluetooth MTP-II · 58 mm'), findsOneWidget);
      expect(find.text('Printer siap'), findsOneWidget);
      final profil = await tester.runAsync(() => ProfilPrinter.Muat(u.repositori));
      expect(
        (profil?.jenis, profil?.alamat, profil?.nama),
        (JenisTransport.BluetoothKlasik, '00:11:22:33:44:55', 'MTP-II'),
      );
      expect(tester.takeException(), isNull);
      await Lepas(tester, u);
    });
  }

  testWidgets('Bluetooth LE: izin ditolak → pesan; tidak ada printer → petunjuk; bayar mencetak lewat BLE', (
    tester,
  ) async {
    final u = await Masuk(tester, const Size(1280, 900));
    await Ketuk(tester, find.text('Pengaturan').last);
    await Ketuk(tester, find.widgetWithText(FilledButton, 'Atur printer'));
    await Ketuk(tester, find.text('Bluetooth LE'));

    u.pemindai.galatSiapkan = 'Bluetooth mati. Nyalakan Bluetooth, lalu coba lagi.';
    await Ketuk(tester, find.widgetWithText(OutlinedButton, 'Cari printer'));
    expect(find.text('Bluetooth mati. Nyalakan Bluetooth, lalu coba lagi.'), findsOneWidget);

    u.pemindai.galatSiapkan = null;
    await Ketuk(tester, find.widgetWithText(OutlinedButton, 'Cari printer'));
    expect(find.textContaining('Tidak ada printer Bluetooth LE di sekitar'), findsOneWidget);

    u.pemindai.hasil[JenisTransport.Ble] = const [
      PrinterDitemukan(jenis: JenisTransport.Ble, alamat: 'BLE-01', nama: 'Printer_5D2B'),
    ];
    await Ketuk(tester, find.widgetWithText(OutlinedButton, 'Cari printer'));
    // Satu printer ditemukan langsung terpilih.
    await Ketuk(tester, find.widgetWithText(FilledButton, 'Simpan printer'));
    await GulirKe(tester, find.text('Printer Bluetooth LE Printer_5D2B · 58 mm'));
    expect(find.text('Printer Bluetooth LE Printer_5D2B · 58 mm'), findsOneWidget);

    await Ketuk(tester, find.text('Jual').last);
    await BayarUangPas(tester, const Size(1280, 900));
    await Tunggu(tester);
    expect(find.text('Struk sudah dicetak.'), findsOneWidget);
    expect(u.pemindai.transportDibuat.last.jenis, JenisTransport.Ble);
    await Lepas(tester, u);
  });
}

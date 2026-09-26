import 'package:flutter_test/flutter_test.dart';
import 'package:kasir/Data/PesananMeja.dart';
import 'package:kasir/Domain/Dapur/LayananTiketDapur.dart';
import 'package:kasir/Domain/Katalog/KatalogLokal.dart';
import 'package:kasir/Domain/Penjualan/Keranjang.dart';
import 'package:kasir/Domain/Penjualan/KonteksPenjualan.dart';
import 'package:kasir/Domain/Sesi/StafLokal.dart';
import 'package:kasir/Domain/Struk/ProfilPrinter.dart';
import 'package:mesin_kasir/MesinKasir.dart';

import '../../Pendukung/KatalogUji.dart';
import '../../Pendukung/LingkunganUji.dart';

/// Cetak struk bagian 4c: tiket dapur per stasiun di printer saat pesanan meja dikirim ke dapur. Perutean sama dengan
/// server (kategori → stasiun, warisan induk, stasiun bawaan), hanya stasiun berprinter di perangkat ini, tanpa harga.
void main() {
  late LingkunganUji u;
  late KatalogLokal katalog;
  late KonteksPenjualan k;
  late StafLokal rina;
  late LayananTiketDapur layanan;

  const bar = '01K5STAS1VN000000000BAR001';
  const dapur = '01K5STAS1VN000000000DAPUR1';
  const mejaD01 = '01K5MEJA0000000000000D0101';

  setUp(() => u = LingkunganUji.Buat());
  tearDown(() => u.Tutup());

  Future<void> Siapkan() async {
    await u.SiapkanAktif();
    await u.SiapkanKatalog();
    await u.SiapkanMeja();
    katalog = await u.MuatKatalog();
    k = await u.MuatKonteks();
    rina = await u.Staf('Rina Wulandari');
    layanan = LayananTiketDapur(repositori: u.repositori, struk: u.struk);
    await const ProfilPrinter(alamat: '192.168.1.50').Simpan(u.repositori);
  }

  /// Pesanan meja D-01 dengan 2× Es Kopi Susu Aren (kurang manis, catatan) + 1× Croissant, dikirim ke dapur.
  Future<PesananMeja> KirimPesanan() async {
    final gula = [PilihanTerpilih(uuid: UuidUji.gulaKurang, nama: 'Kurang manis', harga: Uang.Nol())];
    final draf = [
      u.penjualan.BuatBaris(
        katalog,
        k,
        katalog.CariProduk(UuidUji.kopiSusu)!,
        pilihan: gula,
        jumlah: Kuantitas.DariBulat(2),
        catatan: 'Es dipisah',
      ),
      u.penjualan.BuatBaris(katalog, k, katalog.CariProduk(UuidUji.croissant)!),
    ];
    final pesanan = await u.pesananMeja.Buka(kasir: rina, k: k, meja: (await u.repositoriMeja.CariMeja(mejaD01))!);
    return u.pesananMeja.SimpanBaris(uuidPesanan: pesanan.uuid, draf: draf, kasir: rina, kirimDapur: true);
  }

  Iterable<String> Semua(PesananMeja p) => p.baris.map((b) => b.uuid);

  test('rute: kategori langsung, warisan induk, kategori tanpa stasiun → bawaan, stasiun tak dikenal → bawaan', () {
    const rute = RuteDapur(
      stasiun: [StasiunDapurLokal(bar, 'Bar'), StasiunDapurLokal(dapur, 'Dapur')],
      uuidBawaan: dapur,
      kategori: {'KOPI': bar, 'LAMA': '01K5STASIUNDIARSIPKAN00001'},
    );
    const induk = {'KOPI-SUSU': 'KOPI', 'KOPI': null, 'LAMA': null};
    expect(rute.AmbilStasiun('KOPI', induk)?.nama, 'Bar');
    expect(rute.AmbilStasiun('KOPI-SUSU', induk)?.nama, 'Bar');
    expect(rute.AmbilStasiun('MAKANAN', induk)?.nama, 'Dapur');
    expect(rute.AmbilStasiun(null, induk)?.nama, 'Dapur');
    expect(rute.AmbilStasiun('LAMA', induk)?.nama, 'Dapur');
    expect(const RuteDapur().AmbilStasiun('KOPI', induk), isNull);
  });

  test('tanpa printer dapur di perangkat ini: tidak ada tiket yang dicetak', () async {
    await Siapkan();
    final pesanan = await KirimPesanan();
    expect(await layanan.Cetak(pesanan: pesanan, uuidBaris: Semua(pesanan), katalog: katalog, waktu: u.jam), isEmpty);
    expect(u.printer.kiriman, isEmpty);
  });

  test('satu tiket per stasiun berprinter: isi tanpa harga, meja & jumlah, pilihan dan catatan', () async {
    await Siapkan();
    await PrinterDapur.SimpanSemua(u.repositori, {
      bar: const PrinterDapur.Struk(),
      dapur: const PrinterDapur.Sendiri(ProfilPrinter(alamat: '192.168.1.60')),
    });
    expect((await PrinterDapur.MuatSemua(u.repositori))[dapur]?.profil?.alamat, '192.168.1.60');
    final pesanan = await KirimPesanan();

    final hasil = await layanan.Cetak(
      pesanan: pesanan,
      uuidBaris: Semua(pesanan),
      katalog: katalog,
      waktu: u.jam,
      namaKasir: rina.nama,
    );

    expect(hasil.map((h) => (h.stasiun.nama, h.galat)), [('Bar', null), ('Dapur', null)]);
    expect(u.printer.kiriman, hasLength(2));
    final tiketBar = u.printer.AmbilTeks(0);
    expect(tiketBar, contains('TIKET BAR'));
    expect(tiketBar, contains('D-01'));
    expect(tiketBar, contains('Ronde 1'));
    // Item dicetak besar (setengah jumlah kolom) sehingga nama panjang terlipat.
    expect(tiketBar, contains('2 x Es Kopi Susu'));
    expect(tiketBar, contains('OB/SLB/260924/POS-001-0001'));
    expect(tiketBar, contains('+ Kurang manis'));
    expect(tiketBar, contains('Catatan: Es dipisah'));
    expect(tiketBar, contains('Kasir: Rina Wulandari'));
    expect(tiketBar, isNot(contains('Croissant')));
    expect(tiketBar, isNot(contains('18.000')));
    final tiketDapur = u.printer.AmbilTeks(1);
    expect(tiketDapur, contains('TIKET DAPUR'));
    expect(tiketDapur, contains('1 x Croissant'));
    expect(tiketDapur, contains('1 item'));
    expect(tiketDapur, isNot(contains('Kopi')));
  });

  test('hanya baris yang dikirim; stasiun tanpa printer dilewati; printer gagal dilaporkan per stasiun', () async {
    await Siapkan();
    await PrinterDapur.SimpanSemua(u.repositori, {bar: const PrinterDapur.Struk()});
    final pesanan = await KirimPesanan();
    final kopi = pesanan.baris.first.uuid;

    var hasil = await layanan.Cetak(
      pesanan: pesanan,
      uuidBaris: [pesanan.baris.last.uuid],
      katalog: katalog,
      waktu: u.jam,
    );
    expect(hasil, isEmpty);
    expect(u.printer.kiriman, isEmpty);

    u.printer.galat = 'Printer di 192.168.1.50:9100 tidak tersambung.';
    hasil = await layanan.Cetak(pesanan: pesanan, uuidBaris: [kopi], katalog: katalog, waktu: u.jam);
    expect(hasil.single.stasiun.nama, 'Bar');
    expect(hasil.single.galat, contains('tidak tersambung'));
  });
}

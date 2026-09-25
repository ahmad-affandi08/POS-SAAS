import 'dart:convert';
import 'dart:typed_data';

import 'package:adaptor_perangkat/AdaptorPerangkat.dart';
import 'package:drift/drift.dart' show Value;
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:kasir/Data/BasisData/BasisDataKasir.dart';
import 'package:kasir/Data/PenyimpanRahasia.dart';
import 'package:kasir/Data/RepositoriKasir.dart';
import 'package:kasir/Domain/Katalog/KatalogLokal.dart';
import 'package:kasir/Domain/Penjualan/Keranjang.dart';
import 'package:kasir/Domain/Penjualan/KonteksPenjualan.dart';
import 'package:kasir/Domain/Penjualan/LayananPenjualan.dart';
import 'package:kasir/Domain/Sesi/LayananPerangkat.dart';
import 'package:kasir/Domain/Sesi/StafLokal.dart';
import 'package:kasir/Domain/Struk/IdentitasStruk.dart';
import 'package:kasir/Domain/Struk/PenyusunStrukPenjualan.dart';
import 'package:kasir/Domain/Struk/ProfilPrinter.dart';
import 'package:klien_api/KlienApi.dart';
import 'package:mesin_kasir/MesinKasir.dart';

import '../../Pendukung/KatalogUji.dart';
import '../../Pendukung/LingkunganUji.dart';

/// Cetak struk (PRD v1.79): isi struk dari penjualan lokal sesuai pengaturan back-office, cetak otomatis/ulang, laci
/// hanya untuk tunai, galat printer tidak membatalkan penjualan, dan logo 1 bit dari data awal.
void main() {
  late LingkunganUji u;
  late KatalogLokal katalog;
  late KonteksPenjualan k;
  late StafLokal rina;

  const profil = ProfilPrinter(alamat: '192.168.1.50');

  Map<String, Object?> DataAwalStruk(Map<String, Object?> struk) => {
    ...DataAwalUji(),
    'Struk': {
      'NamaUsaha': 'Kopi Senja',
      'Npwp': '0123456789012345',
      'AdaLogo': false,
      'TandaAir': true,
      'TeksKepala': ['Buka 07.00-22.00'],
      'CatatanKaki': 'Barang yang sudah dibeli bisa ditukar dalam 7 hari.',
      ...struk,
    },
  };

  Future<void> Siapkan({Map<String, Object?> struk = const {}}) async {
    await u.SiapkanAktif(dataAwal: DataAwalStruk(struk));
    await u.SiapkanKatalog();
    katalog = await u.MuatKatalog();
    k = await u.MuatKonteks();
    rina = await u.Staf('Rina Wulandari');
    await u.shift.BukaShift(kasir: rina, kasAwal: Uang.DariBulat(500000));
  }

  /// 2× Es Kopi Susu Aren (kurang manis) + 1× Croissant = Rp 61.000 + PBJT 10% = Rp 67.100.
  Future<PenjualanTersimpan> Jual(String jenisMetode, {int jumlah = 100000}) {
    var keranjang = Keranjang.kosong;
    final gula = [PilihanTerpilih(uuid: UuidUji.gulaKurang, nama: 'Kurang manis', harga: Uang.Nol())];
    for (var i = 0; i < 2; i++) {
      keranjang = u.penjualan.TambahBaris(
        keranjang,
        u.penjualan.BuatBaris(katalog, k, katalog.CariProduk(UuidUji.kopiSusu)!, pilihan: gula),
        katalog,
        k,
      );
    }
    keranjang = u.penjualan.TambahBaris(
      keranjang,
      u.penjualan.BuatBaris(katalog, k, katalog.CariProduk(UuidUji.croissant)!),
      katalog,
      k,
    );
    final metode = k.metodePembayaran.firstWhere((m) => m.Jenis == jenisMetode);
    return u.penjualan.Bayar(
      keranjang: keranjang,
      pembayaran: [
        PembayaranMasukan(
          metode: metode,
          jumlah: jenisMetode == JenisMetodeBayar.tunai ? Uang.DariBulat(jumlah) : Uang.DariBulat(67100),
          referensi: jenisMetode == JenisMetodeBayar.edc ? 'BCA 123456' : null,
        ),
      ],
      kasir: rina,
      k: k,
    );
  }

  setUp(() => u = LingkunganUji.Buat());
  tearDown(() => u.Tutup());

  test('struk 58 mm: kepala dari pengaturan, rincian, total, kembalian, catatan kaki, tanda air', () async {
    await Siapkan();
    final hasil = await Jual(JenisMetodeBayar.tunai);
    final data = DataStrukPenjualan(
      penjualan: (await u.repositoriPenjualan.CariPenjualan(hasil.uuid))!,
      detail: await u.repositoriPenjualan.AmbilDetail(hasil.uuid),
      pembayaran: await u.repositoriPenjualan.AmbilPembayaran(hasil.uuid),
      namaPelanggan: 'Budi Santoso',
    );
    final baris = TataLetakStruk.KeTeks(
      PenyusunStrukPenjualan.Susun(await IdentitasStruk.Muat(u.repositori), data),
      LebarKertas.Mm58,
    ).map((b) => b.trim()).toList();

    expect(baris.take(3), ['Kopi Senja', 'Buka 07.00-22.00', 'Kopi Senja Solo Baru']);
    expect(baris, contains('NPWP 0123456789012345'));
    expect(baris, contains(hasil.nomor));
    expect(baris, contains('Kasir: Rina Wulandari'));
    expect(baris, contains('Pelanggan: Budi Santoso'));
    expect(baris, contains('+ Kurang manis'));
    expect(baris.any((b) => b.startsWith('2 x ') && b.endsWith('36.000')), isTrue, reason: baris.join('\n'));
    expect(baris.any((b) => b.startsWith('TOTAL') && b.endsWith('Rp 67.100')), isTrue);
    expect(baris.any((b) => b.startsWith('Pajak') && b.endsWith('6.100')), isTrue);
    expect(baris.any((b) => b.startsWith('Kembalian') && b.endsWith('32.900')), isTrue);
    expect(baris, contains('Barang yang sudah dibeli bisa'));
    expect(baris[baris.length - 2], 'Terima kasih atas kunjungan Anda');
    expect(baris.last, 'Dibuat dengan PAYOU');
    expect(baris, isNot(contains('CETAK ULANG')));
  });

  test('saklar back-office dipatuhi: tanpa kasir, pelanggan, NPWP; nama & penutup kustom; tanpa tanda air', () async {
    await Siapkan(
      struk: {
        'NamaDicetak': 'Senja Coffee',
        'TampilkanKasir': false,
        'TampilkanPelanggan': false,
        'TampilkanNpwp': false,
        'TeksPenutup': 'Sampai jumpa lagi',
        'TandaAir': false,
      },
    );
    final hasil = await Jual(JenisMetodeBayar.tunai);
    await profil.Simpan(u.repositori);
    await u.struk.CetakPenjualan(hasil.uuid, namaPelanggan: 'Budi Santoso');
    final teks = u.printer.AmbilTeks();
    expect(teks, contains('Senja Coffee'));
    expect(teks, isNot(contains('Kasir:')));
    expect(teks, isNot(contains('Budi Santoso')));
    expect(teks, isNot(contains('NPWP')));
    expect(teks, contains('Sampai jumpa lagi'));
    expect(teks, isNot(contains('PAYOU')));
  });

  test('cetak otomatis: tanpa printer / otomatis mati = tidak mencetak; tunai membuka laci, EDC tidak', () async {
    await Siapkan();
    final tunai = await Jual(JenisMetodeBayar.tunai);
    expect(await u.struk.CetakSetelahBayar(tunai.uuid), isFalse);
    expect(u.printer.kiriman, isEmpty);

    await profil.copyWith(cetakOtomatis: false).Simpan(u.repositori);
    expect(await u.struk.CetakSetelahBayar(tunai.uuid), isFalse);

    await profil.Simpan(u.repositori);
    expect(await u.struk.CetakSetelahBayar(tunai.uuid), isTrue);
    expect(u.printer.CekBukaLaci(), isTrue);

    final edc = await Jual(JenisMetodeBayar.edc);
    await u.struk.CetakSetelahBayar(edc.uuid);
    expect(u.printer.CekBukaLaci(), isFalse);

    await profil.copyWith(bukaLaciTunai: false).Simpan(u.repositori);
    await u.struk.CetakSetelahBayar(tunai.uuid);
    expect(u.printer.CekBukaLaci(), isFalse);
  });

  test('cetak ulang bertanda CETAK ULANG; penjualan void bertanda DIBATALKAN', () async {
    await Siapkan();
    final hasil = await Jual(JenisMetodeBayar.tunai);
    await profil.Simpan(u.repositori);
    await u.struk.CetakPenjualan(hasil.uuid, cetakUlang: true);
    expect(u.printer.AmbilTeks(), contains('CETAK ULANG'));

    await (u.db.update(
      u.db.penjualan,
    )..where((p) => p.Uuid.equals(hasil.uuid))).write(const PenjualanCompanion(Status: Value('Void')));
    await u.struk.CetakPenjualan(hasil.uuid, cetakUlang: true);
    expect(u.printer.AmbilTeks(), contains('DIBATALKAN'));
  });

  test(
    'printer gagal → GalatPrinter berpesan; penjualan tetap tersimpan; belum diatur → pesan cara mengatur',
    () async {
      await Siapkan();
      final hasil = await Jual(JenisMetodeBayar.tunai);
      await expectLater(
        u.struk.CetakPenjualan(hasil.uuid),
        throwsA(isA<GalatPrinter>().having((g) => g.pesan, 'pesan', contains('Printer belum diatur'))),
      );
      await profil.Simpan(u.repositori);
      u.printer.galat = 'Printer di 192.168.1.50:9100 tidak tersambung.';
      await expectLater(u.struk.CetakPenjualan(hasil.uuid), throwsA(isA<GalatPrinter>()));
      expect(await u.repositoriPenjualan.CariPenjualan(hasil.uuid), isNotNull);
    },
  );

  test('profil printer: simpan/muat/hapus, data rusak = belum diatur, validasi alamat & port', () async {
    await Siapkan();
    expect(await ProfilPrinter.Muat(u.repositori), isNull);
    await profil.copyWith(lebar: LebarKertas.Mm80, port: 9101).Simpan(u.repositori);
    final dimuat = (await ProfilPrinter.Muat(u.repositori))!;
    expect((dimuat.alamat, dimuat.port, dimuat.lebar), ('192.168.1.50', 9101, LebarKertas.Mm80));
    await u.repositori.SimpanPengaturan(KunciPengaturan.profilPrinter, '{rusak');
    expect(await ProfilPrinter.Muat(u.repositori), isNull);
    await ProfilPrinter.Hapus(u.repositori);
    expect(await ProfilPrinter.Muat(u.repositori), isNull);

    expect(ProfilPrinter.ValidasiAlamat(''), isNotNull);
    expect(ProfilPrinter.ValidasiAlamat('192.168.1.300'), isNotNull);
    expect(ProfilPrinter.ValidasiAlamat('192.168.1.50'), isNull);
    expect(ProfilPrinter.ValidasiAlamat('printer-kasir.local'), isNull);
    expect(ProfilPrinter.ValidasiPort('0'), isNotNull);
    expect(ProfilPrinter.ValidasiPort('9100'), isNull);
  });

  test(
    'logo struk: diunduh & disimpan 1 bit saat data awal; dimatikan → dihapus; gagal unduh → logo lama tetap',
    () async {
      final logo = GambarMonokrom(8, 1, Uint8List.fromList([1, 1, 1, 1, 0, 0, 0, 0]));
      var adaLogo = true;
      var logoTersedia = true;
      u.server.penangan = (p) async {
        if (p.url.path.endsWith('/data-awal')) {
          return JsonUji(DataAwalStruk({'AdaLogo': adaLogo}));
        }
        if (p.url.path.endsWith('/logo-struk')) {
          if (!logoTersedia) {
            throw http.ClientException('offline');
          }
          return BytesUji([137, 80, 78, 71]);
        }
        throw http.ClientException('offline');
      };
      final perangkat = LayananPerangkat(
        klien: u.klien,
        repositori: u.repositori,
        rahasia: u.rahasia,
        platform: 'Android',
        ubahLogo: (byte) async => logo,
      );
      u.rahasia.isi[PenyimpanRahasia.kunciToken] = '12|rahasia';

      await perangkat.SegarkanDataAwal();
      final identitas = await IdentitasStruk.Muat(u.repositori);
      expect(identitas.logo?.titik, logo.titik);
      expect(PenyusunStrukPenjualan.SusunKepala(identitas).first, isA<BarisGambar>());

      logoTersedia = false;
      await perangkat.SegarkanDataAwal();
      expect((await IdentitasStruk.Muat(u.repositori)).logo, isNotNull);

      adaLogo = false;
      await perangkat.SegarkanDataAwal();
      expect(await u.repositori.AmbilPengaturan(KunciPengaturan.logoStruk), '');
      expect((await IdentitasStruk.Muat(u.repositori)).logo, isNull);
      expect(jsonDecode((await u.repositori.AmbilPengaturan(KunciPengaturan.struk))!), isA<Map<String, Object?>>());
      expect(StrukPos.DariJson(null), isNull);
    },
  );
}

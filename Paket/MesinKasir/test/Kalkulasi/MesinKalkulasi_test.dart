import 'package:mesin_kasir/MesinKasir.dart';
import 'package:test/test.dart';

/// Perilaku `MesinKalkulasi` (F-07a) di luar test vector bersama: alokasi sisa terbesar dan validasi masukan.
void main() {
  const mesin = MesinKalkulasi();

  DataBarisKalkulasi Baris(String jumlah, String harga, {List<String>? kodePajak, List<DataPotongan>? potongan}) =>
      DataBarisKalkulasi(
        jumlah: Kuantitas.Dari(jumlah),
        hargaSatuan: Uang.Dari(harga),
        kodePajak: kodePajak,
        potongan: potongan ?? const [],
      );

  List<String> UbahKeTeks(List<Uang> daftar) => [for (final uang in daftar) uang.KeString()];

  group('alokasi metode sisa terbesar (Rincian F-07a)', () {
    test('sisa sen diberikan ke baris lebih awal saat pecahan seri', () {
      final hasil = MesinKalkulasi.AlokasikanSebanding(Uang.Dari('0.02'), [
        Uang.DariBulat(1000),
        Uang.DariBulat(1000),
        Uang.DariBulat(1000),
      ]);
      expect(UbahKeTeks(hasil), ['0.01', '0.01', '0.00']);
    });

    test('sisa sen diberikan ke pecahan terbesar, bukan ke baris pertama', () {
      // 100 × (1/6, 2/6, 3/6) = 16,666.. ; 33,333.. ; 50 → lantai 16,66 ; 33,33 ; 50,00, sisa 0,01 ke baris pertama
      // (pecahan 0,666..), bukan baris kedua (0,333..).
      final hasil = MesinKalkulasi.AlokasikanSebanding(Uang.Dari('100.00'), [
        Uang.DariBulat(1),
        Uang.DariBulat(2),
        Uang.DariBulat(3),
      ]);
      expect(UbahKeTeks(hasil), ['16.67', '33.33', '50.00']);
    });

    test('jumlah alokasi selalu sama persis dengan total dokumen', () {
      final bobot = [Uang.Dari('30107.45'), Uang.Dari('21500.00'), Uang.Dari('20000.00'), Uang.Dari('0.01')];
      final hasil = MesinKalkulasi.AlokasikanSebanding(Uang.Dari('2499.99'), bobot);
      expect(hasil.fold(Uang.Nol(), (a, b) => a.Tambah(b)), Uang.Dari('2499.99'));
    });

    test('bobot nol menghasilkan alokasi nol', () {
      final hasil = MesinKalkulasi.AlokasikanSebanding(Uang.DariBulat(100), [Uang.Nol(), Uang.Nol()]);
      expect(UbahKeTeks(hasil), ['0.00', '0.00']);
    });
  });

  group('invariant total', () {
    test('Σ TotalBaris = TotalAkhir − Pembulatan dan TotalDiskon = DiskonBaris + DiskonPesanan', () {
      final hasil = mesin.Hitung(
        DataKalkulasi(
          hargaTermasukPajak: false,
          persenBiayaLayanan: Decimal.parse('5'),
          pembulatanTunai: const DataPembulatanTunai(kelipatan: 100, arah: ArahPembulatan.Terdekat),
          pajak: [
            DataPajakKalkulasi(
              kode: 'PB1',
              tarif: Decimal.fromInt(10),
              dasarPengenaan: DasarPengenaanPajak.SubtotalPlusLayanan,
            ),
          ],
          baris: [
            Baris('3', '12345.67', potongan: [DataPotongan.DariPersen(Decimal.parse('12.5'))]),
            Baris('1.255', '23990'),
            Baris('1', '7777', kodePajak: const []),
          ],
          potonganPesanan: [DataPotongan.DariJumlah(Uang.Dari('1234.56'))],
          pembayaran: [DataPembayaranKalkulasi(metode: 'Tunai', jumlah: Uang.DariBulat(200000))],
        ),
      );
      final totalBaris = hasil.baris.fold(Uang.Nol(), (a, b) => a.Tambah(b.totalBaris));
      expect(totalBaris.Tambah(hasil.pembulatan), hasil.totalAkhir);
      expect(hasil.totalDiskon, hasil.diskonBaris.Tambah(hasil.diskonPesanan));
      expect(hasil.baris.last.pajak, Uang.Nol());
      expect(hasil.kembalian, Uang.DariBulat(200000).Kurangi(hasil.totalAkhir));
    });

    test('tanpa pembayaran tunai: tanpa pembulatan dan tanpa kembalian (BR-08.6)', () {
      final hasil = mesin.Hitung(
        DataKalkulasi(
          hargaTermasukPajak: false,
          pembulatanTunai: const DataPembulatanTunai(kelipatan: 100, arah: ArahPembulatan.Atas),
          baris: [Baris('1', '1234.56')],
          pembayaran: [DataPembayaranKalkulasi(metode: 'Qris', jumlah: Uang.Dari('1234.56'))],
        ),
      );
      expect(hasil.pembulatan, Uang.Nol());
      expect(hasil.totalAkhir, Uang.Dari('1234.56'));
      expect(hasil.kembalian, isNull);
    });
  });

  group('validasi masukan', () {
    DataKalkulasi Data({
      List<DataBarisKalkulasi>? baris,
      List<DataPotongan> potonganPesanan = const [],
      Decimal? persenBiayaLayanan,
      List<DataPembayaranKalkulasi> pembayaran = const [],
    }) => DataKalkulasi(
      hargaTermasukPajak: false,
      persenBiayaLayanan: persenBiayaLayanan,
      pajak: [DataPajakKalkulasi(kode: 'PPN', tarif: Decimal.fromInt(12))],
      baris: baris ?? [Baris('1', '10000')],
      potonganPesanan: potonganPesanan,
      pembayaran: pembayaran,
    );

    test('menolak jumlah baris nol atau negatif', () {
      expect(() => mesin.Hitung(Data(baris: [Baris('0', '10000')])), throwsArgumentError);
      expect(() => mesin.Hitung(Data(baris: [Baris('-1', '10000')])), throwsArgumentError);
    });

    test('menolak persen potongan di atas 100 atau negatif', () {
      final lebih = [DataPotongan.DariPersen(Decimal.parse('100.01'))];
      expect(() => mesin.Hitung(Data(baris: [Baris('1', '10000', potongan: lebih)])), throwsArgumentError);
      expect(() => mesin.Hitung(Data(potonganPesanan: lebih)), throwsArgumentError);
      expect(
        () => mesin.Hitung(Data(potonganPesanan: [DataPotongan.DariPersen(Decimal.parse('-1'))])),
        throwsArgumentError,
      );
      expect(() => mesin.Hitung(Data(persenBiayaLayanan: Decimal.fromInt(101))), throwsArgumentError);
    });

    test('menolak potongan nominal negatif', () {
      expect(
        () => mesin.Hitung(Data(potonganPesanan: [DataPotongan.DariJumlah(Uang.Dari('-1'))])),
        throwsArgumentError,
      );
    });

    test('menolak kode pajak baris yang tidak ada di daftar pajak dokumen', () {
      expect(
        () => mesin.Hitung(
          Data(
            baris: [
              Baris('1', '10000', kodePajak: const ['PB1']),
            ],
          ),
        ),
        throwsArgumentError,
      );
    });

    test('menolak pembayaran non-tunai tanpa jumlah', () {
      expect(
        () => mesin.Hitung(Data(pembayaran: const [DataPembayaranKalkulasi(metode: 'Qris')])),
        throwsArgumentError,
      );
    });

    test('persen tepat 100 diterima dan membuat baris gratis', () {
      final hasil = mesin.Hitung(
        Data(
          baris: [
            Baris('1', '10000', potongan: [DataPotongan.DariPersen(Decimal.fromInt(100))]),
          ],
        ),
      );
      expect(hasil.totalAkhir, Uang.Nol());
    });
  });
}

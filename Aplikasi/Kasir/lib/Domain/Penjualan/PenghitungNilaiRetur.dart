import 'package:inti/Inti.dart';
import 'package:klien_api/KlienApi.dart';

/// Nilai retur per baris (PRD "Rincian F-09 fase 1"), rumus sama persis dengan server
/// (`App\Domain\Penjualan\Layanan\PenghitungNilaiRetur`) agar `Ringkasan.TotalRefund` cocok (`HitunganTidakCocok`):
/// - retur yang menghabiskan sisa baris (jumlah = `JumlahBisaDiretur`) mengambil sisa nilai (`NilaiBisaDiretur`),
///   sehingga Σ retur sebuah baris = `TotalBaris`;
/// - selain itu `TotalBaris × jumlah ÷ jumlah jual`, dibulatkan ke sen HalfUp (setengah menjauhi nol).
abstract final class PenghitungNilaiRetur {
  static Uang Hitung(BarisPenjualanCariPos baris, Kuantitas jumlah) {
    if (jumlah.SamaDengan(Kuantitas.Dari(baris.jumlahBisaDiretur))) {
      return Uang.Dari(baris.nilaiBisaDiretur);
    }
    return HitungBagian(Uang.Dari(baris.totalBaris), jumlah, Kuantitas.Dari(baris.jumlah));
  }

  /// `nilai × jumlah ÷ jumlahJual`, dibulatkan ke sen HalfUp (padanan `dividedBy(..., 2, RoundingMode::HalfUp)`).
  static Uang HitungBagian(Uang nilai, Kuantitas jumlah, Kuantitas jumlahJual) {
    if (jumlahJual.Bandingkan(Kuantitas.Nol()) <= 0) {
      throw ArgumentError.value(jumlahJual.KeString(), 'jumlahJual', 'harus lebih dari 0');
    }
    final pecahan =
        (nilai.KeDesimal() * jumlah.KeDesimal()).shift(Uang.skala).toRational() / jumlahJual.KeDesimal().toRational();
    final sen = BagiBulat(pecahan.numerator, pecahan.denominator, ModePembulatan.SetengahMenjauhiNol);
    return Uang.DariDesimal(Decimal.fromBigInt(sen).shift(-Uang.skala));
  }

  /// Σ nilai retur semua baris terpilih (= `Ringkasan.TotalRefund`).
  static Uang HitungTotal(Iterable<({BarisPenjualanCariPos baris, Kuantitas jumlah})> pilihan) =>
      pilihan.fold(Uang.Nol(), (t, p) => t.Tambah(Hitung(p.baris, p.jumlah)));
}

import 'package:inti/Inti.dart';
import 'package:rational/rational.dart';

import 'DataKalkulasi.dart';
import 'HasilKalkulasi.dart';

/// Mesin kalkulasi penjualan F-07a (PRD §8 F-07, "Rincian F-07a").
///
/// Satu algoritma dengan `App\Domain\Penjualan\Kalkulasi` di Backend; keduanya wajib lolos test vector bersama
/// `Spesifikasi/VektorUjiKalkulasi/` sampai sen (CLAUDE.md #18). Semua hitungan memakai `Uang` dan pecahan eksak
/// (`Rational`); pembulatan uang setengah menjauhi nol ke sen.
///
/// 1. `Bruto` = bulat((HargaSatuan + HargaPilihan) × Jumlah).
/// 2. Diskon baris = Σ potongan (persen dari Bruto, dibulatkan), dibatasi Bruto; `Netto` = Bruto − Diskon.
/// 3. `Subtotal` = Σ Netto.
/// 4. Diskon pesanan = Σ potongan pesanan (persen dari Subtotal), dibatasi Subtotal, dialokasikan sebanding Netto.
/// 5. `BiayaLayanan` = bulat(persen × (Subtotal − DiskonPesanan)), dialokasikan sebanding Netto akhir.
/// 6. Pajak per baris dengan pecahan eksak. Eksklusif: DPP = (NettoAkhir + [biaya layanan baris bila
///    `SubtotalPlusLayanan`]) × p/q. Inklusif: Dasar = NettoAkhir ÷ (1 + Σ tarif × p/q), DPP = Dasar × p/q, dan
///    pajak atas biaya layanan selalu ditambahkan (biaya layanan tidak termasuk harga).
/// 7. Pembulatan per dokumen per jenis pajak, terpisah bagian eksklusif dan inklusif; baris menerima alokasi.
/// 8. `TotalAkhir` = Subtotal − DiskonPesanan + BiayaLayanan + pajak eksklusif + Pembulatan tunai.
///
/// Alokasi ke baris memakai metode sisa terbesar sehingga Σ baris selalu sama dengan angka dokumen.
final class MesinKalkulasi {
  const MesinKalkulasi();

  static final Rational _seratus = Rational.fromInt(100);

  HasilKalkulasi Hitung(DataKalkulasi data) {
    ValidasiMasukan(data);

    final daftarPajak = data.pajak;
    final jumlahBaris = data.baris.length;

    // Langkah 1–3: bruto, diskon baris, netto, subtotal.
    final bruto = <Uang>[];
    final diskon = <Uang>[];
    final netto = <Uang>[];
    for (final baris in data.baris) {
      final nilaiBruto = baris.hargaSatuan.Tambah(baris.hargaPilihan).Kali(baris.jumlah.KeDesimal());
      var nilaiDiskon = Uang.Nol();
      for (final potongan in baris.potongan) {
        nilaiDiskon = nilaiDiskon.Tambah(HitungPotongan(potongan, nilaiBruto));
      }
      nilaiDiskon = AmbilTerkecil(nilaiDiskon, nilaiBruto);
      bruto.add(nilaiBruto);
      diskon.add(nilaiDiskon);
      netto.add(nilaiBruto.Kurangi(nilaiDiskon));
    }
    final subtotal = JumlahkanUang(netto);
    final diskonBaris = JumlahkanUang(diskon);

    // Langkah 4: diskon pesanan, dialokasikan sebanding netto.
    var diskonPesanan = Uang.Nol();
    for (final potongan in data.potonganPesanan) {
      diskonPesanan = diskonPesanan.Tambah(HitungPotongan(potongan, subtotal));
    }
    diskonPesanan = AmbilTerkecil(diskonPesanan, subtotal);
    final diskonPesananBaris = AlokasikanSebanding(diskonPesanan, netto);
    final nettoAkhir = [for (var i = 0; i < jumlahBaris; i++) netto[i].Kurangi(diskonPesananBaris[i])];

    // Langkah 5: biaya layanan, dialokasikan sebanding netto akhir.
    final biayaLayanan = BulatkanKeSen(
      data.persenBiayaLayanan.toRational() / _seratus * subtotal.Kurangi(diskonPesanan).KeDesimal().toRational(),
    );
    final biayaLayananBaris = AlokasikanSebanding(biayaLayanan, nettoAkhir);

    // Langkah 6: pajak eksak per baris, dipisah bagian eksklusif dan inklusif per kode pajak.
    final tepatEksklusif = {for (final pajak in daftarPajak) pajak.kode: List.filled(jumlahBaris, Rational.zero)};
    final tepatInklusif = {for (final pajak in daftarPajak) pajak.kode: List.filled(jumlahBaris, Rational.zero)};
    final dppTepat = {for (final pajak in daftarPajak) pajak.kode: Rational.zero};
    for (var i = 0; i < jumlahBaris; i++) {
      final baris = data.baris[i];
      final kodeBerlaku = baris.kodePajak ?? [for (final pajak in daftarPajak) pajak.kode];
      final pajakBerlaku = daftarPajak.where((pajak) => kodeBerlaku.contains(pajak.kode)).toList();
      final inklusif = baris.hargaTermasukPajak ?? data.hargaTermasukPajak;
      final nilaiAkhir = nettoAkhir[i].KeDesimal().toRational();
      var faktor = Rational.zero;
      for (final pajak in pajakBerlaku) {
        faktor += HitungTarif(pajak) * pajak.pengaliDpp;
      }
      final dasar = inklusif ? nilaiAkhir / (Rational.one + faktor) : nilaiAkhir;
      final layanan = biayaLayananBaris[i].KeDesimal().toRational();
      for (final pajak in pajakBerlaku) {
        final tarif = HitungTarif(pajak);
        final dppBarang = dasar * pajak.pengaliDpp;
        final dppLayanan = pajak.dasarPengenaan == DasarPengenaanPajak.SubtotalPlusLayanan
            ? layanan * pajak.pengaliDpp
            : Rational.zero;
        dppTepat[pajak.kode] = dppTepat[pajak.kode]! + dppBarang + dppLayanan;
        if (inklusif) {
          tepatInklusif[pajak.kode]![i] += dppBarang * tarif;
          tepatEksklusif[pajak.kode]![i] += dppLayanan * tarif;
        } else {
          tepatEksklusif[pajak.kode]![i] += (dppBarang + dppLayanan) * tarif;
        }
      }
    }

    // Langkah 7: pembulatan per dokumen per jenis pajak, lalu alokasi ke baris.
    final pajakBaris = List.filled(jumlahBaris, Uang.Nol());
    final pajakEksklusifBaris = List.filled(jumlahBaris, Uang.Nol());
    final rincianPajak = <String, HasilPajakKalkulasi>{};
    var totalPajakEksklusif = Uang.Nol();
    var totalPajakInklusif = Uang.Nol();
    for (final pajak in daftarPajak) {
      final bagianEksklusif = tepatEksklusif[pajak.kode]!;
      final bagianInklusif = tepatInklusif[pajak.kode]!;
      final eksklusif = BulatkanKeSen(JumlahkanPecahan(bagianEksklusif));
      final inklusif = BulatkanKeSen(JumlahkanPecahan(bagianInklusif));
      final alokasiEksklusif = AlokasikanTepat(eksklusif, bagianEksklusif);
      final alokasiInklusif = AlokasikanTepat(inklusif, bagianInklusif);
      for (var i = 0; i < jumlahBaris; i++) {
        pajakEksklusifBaris[i] = pajakEksklusifBaris[i].Tambah(alokasiEksklusif[i]);
        pajakBaris[i] = pajakBaris[i].Tambah(alokasiEksklusif[i]).Tambah(alokasiInklusif[i]);
      }
      rincianPajak[pajak.kode] = HasilPajakKalkulasi(
        dpp: BulatkanKeSen(dppTepat[pajak.kode]!),
        jumlah: eksklusif.Tambah(inklusif),
      );
      totalPajakEksklusif = totalPajakEksklusif.Tambah(eksklusif);
      totalPajakInklusif = totalPajakInklusif.Tambah(inklusif);
    }

    // Langkah 8: total, pembulatan tunai (BR-08.6), kembalian.
    final totalSebelumPembulatan = subtotal.Kurangi(diskonPesanan).Tambah(biayaLayanan).Tambah(totalPajakEksklusif);
    var nonTunai = Uang.Nol();
    for (final bayar in data.pembayaran.where((bayar) => !bayar.CekTunai())) {
      nonTunai = nonTunai.Tambah(bayar.jumlah!);
    }
    final pembayaranTunai = data.pembayaran.where((bayar) => bayar.CekTunai()).toList();
    final pembulatan = HitungPembulatanTunai(
      data.pembulatanTunai,
      adaTunai: pembayaranTunai.isNotEmpty,
      sisaTunai: totalSebelumPembulatan.Kurangi(nonTunai),
    );
    final totalAkhir = totalSebelumPembulatan.Tambah(pembulatan);
    final kembalian = HitungKembalian(pembayaranTunai, totalAkhir.Kurangi(nonTunai));

    return HasilKalkulasi(
      subtotal: subtotal,
      diskonBaris: diskonBaris,
      diskonPesanan: diskonPesanan,
      totalDiskon: diskonBaris.Tambah(diskonPesanan),
      biayaLayanan: biayaLayanan,
      totalPajak: totalPajakEksklusif.Tambah(totalPajakInklusif),
      totalPajakEksklusif: totalPajakEksklusif,
      pembulatan: pembulatan,
      totalAkhir: totalAkhir,
      kembalian: kembalian,
      pajak: rincianPajak,
      baris: [
        for (var i = 0; i < jumlahBaris; i++)
          HasilBarisKalkulasi(
            bruto: bruto[i],
            diskon: diskon[i],
            diskonPesanan: diskonPesananBaris[i],
            biayaLayanan: biayaLayananBaris[i],
            pajak: pajakBaris[i],
            pajakEksklusif: pajakEksklusifBaris[i],
            totalBaris: nettoAkhir[i].Tambah(biayaLayananBaris[i]).Tambah(pajakEksklusifBaris[i]),
          ),
      ],
    );
  }

  /// Menolak masukan yang tidak sah dengan [ArgumentError] sebelum menghitung apa pun.
  static void ValidasiMasukan(DataKalkulasi data) {
    CekPersen(data.persenBiayaLayanan, 'persenBiayaLayanan');
    final kodeDokumen = <String>{};
    for (final pajak in data.pajak) {
      if (!kodeDokumen.add(pajak.kode)) {
        throw ArgumentError.value(pajak.kode, 'pajak', 'Kode pajak dokumen ganda');
      }
      if (pajak.tarif < Decimal.zero) {
        throw ArgumentError.value(pajak.tarif.toString(), 'tarif', 'Tarif pajak ${pajak.kode} tidak boleh negatif');
      }
      if (pajak.pengaliDpp <= Rational.zero) {
        throw ArgumentError.value(pajak.pengaliDpp.toString(), 'pengaliDpp', 'Pengali DPP harus lebih dari 0');
      }
    }
    for (final baris in data.baris) {
      if (baris.jumlah.Bandingkan(Kuantitas.Nol()) <= 0) {
        throw ArgumentError.value(baris.jumlah.KeString(), 'jumlah', 'Jumlah baris harus lebih dari 0');
      }
      if (baris.hargaSatuan.BernilaiNegatif() || baris.hargaPilihan.BernilaiNegatif()) {
        throw ArgumentError.value(baris.hargaSatuan.KeString(), 'hargaSatuan', 'Harga tidak boleh negatif');
      }
      for (final kode in baris.kodePajak ?? const <String>[]) {
        if (!kodeDokumen.contains(kode)) {
          throw ArgumentError.value(kode, 'kodePajak', 'Kode pajak baris tidak ada di daftar pajak dokumen');
        }
      }
      baris.potongan.forEach(CekPotongan);
    }
    data.potonganPesanan.forEach(CekPotongan);
    for (final bayar in data.pembayaran) {
      final jumlah = bayar.jumlah;
      if (jumlah == null && !bayar.CekTunai()) {
        throw ArgumentError.value(bayar.metode, 'pembayaran', 'Jumlah pembayaran non-tunai wajib diisi');
      }
      if (jumlah != null && jumlah.BernilaiNegatif()) {
        throw ArgumentError.value(jumlah.KeString(), 'pembayaran', 'Jumlah pembayaran tidak boleh negatif');
      }
    }
    final pembulatan = data.pembulatanTunai;
    if (pembulatan != null && pembulatan.kelipatan <= 0) {
      throw ArgumentError.value(pembulatan.kelipatan, 'kelipatan', 'Kelipatan pembulatan harus lebih dari 0');
    }
  }

  static void CekPotongan(DataPotongan potongan) {
    final persen = potongan.persen;
    final jumlah = potongan.jumlah;
    if ((persen == null) == (jumlah == null)) {
      throw ArgumentError('Potongan wajib berisi tepat satu dari persen atau jumlah');
    }
    if (persen != null) {
      CekPersen(persen, 'persen');
    }
    if (jumlah != null && jumlah.BernilaiNegatif()) {
      throw ArgumentError.value(jumlah.KeString(), 'jumlah', 'Potongan tidak boleh negatif');
    }
  }

  static void CekPersen(Decimal persen, String nama) {
    if (persen < Decimal.zero || persen > Decimal.fromInt(100)) {
      throw ArgumentError.value(persen.toString(), nama, 'Persen harus di antara 0 dan 100');
    }
  }

  /// Nilai potongan: nominal tetap, atau bulat([dasar] × persen / 100).
  static Uang HitungPotongan(DataPotongan potongan, Uang dasar) {
    final persen = potongan.persen;
    if (persen == null) {
      return potongan.jumlah!;
    }
    return BulatkanKeSen(dasar.KeDesimal().toRational() * persen.toRational() / _seratus);
  }

  static Rational HitungTarif(DataPajakKalkulasi pajak) => pajak.tarif.toRational() / _seratus;

  /// Selisih pembulatan tunai; nol bila tanpa pengaturan, tanpa pembayaran tunai, atau sisa tunai ≤ 0.
  static Uang HitungPembulatanTunai(
    DataPembulatanTunai? pengaturan, {
    required bool adaTunai,
    required Uang sisaTunai,
  }) {
    if (pengaturan == null || !adaTunai || sisaTunai.Bandingkan(Uang.Nol()) <= 0) {
      return Uang.Nol();
    }
    final mode = switch (pengaturan.arah) {
      ArahPembulatan.Bawah => ModePembulatan.KeBawah,
      ArahPembulatan.Atas => ModePembulatan.KeAtas,
      ArahPembulatan.Terdekat => ModePembulatan.SetengahMenjauhiNol,
    };
    return sisaTunai.BulatkanKeKelipatan(pengaturan.kelipatan, mode).Kurangi(sisaTunai);
  }

  /// Kembalian = uang tunai diterima − tagihan tunai. Null tanpa pembayaran tunai; tunai tanpa jumlah = uang pas.
  static Uang? HitungKembalian(List<DataPembayaranKalkulasi> pembayaranTunai, Uang tagihanTunai) {
    if (pembayaranTunai.isEmpty) {
      return null;
    }
    if (pembayaranTunai.any((bayar) => bayar.jumlah == null)) {
      return Uang.Nol();
    }
    var diterima = Uang.Nol();
    for (final bayar in pembayaranTunai) {
      diterima = diterima.Tambah(bayar.jumlah!);
    }
    return diterima.Kurangi(tagihanTunai);
  }

  /// Membagi [total] ke baris sebanding [bobot] dengan metode sisa terbesar. Σ hasil = [total].
  static List<Uang> AlokasikanSebanding(Uang total, List<Uang> bobot) {
    final jumlahBobot = JumlahkanUang(bobot);
    if (jumlahBobot.BernilaiNol() || total.BernilaiNol()) {
      return List.filled(bobot.length, Uang.Nol());
    }
    final nilaiTotal = total.KeDesimal().toRational();
    final penyebut = jumlahBobot.KeDesimal().toRational();
    return AlokasikanTepat(total, [for (final b in bobot) nilaiTotal * b.KeDesimal().toRational() / penyebut]);
  }

  /// Metode sisa terbesar: setiap [bagianTepat] dibulatkan ke bawah ke sen, sisa sen diberikan satu per satu ke
  /// bagian dengan pecahan terbesar (seri: indeks lebih awal). [total] wajib hasil pembulatan Σ [bagianTepat].
  static List<Uang> AlokasikanTepat(Uang total, List<Rational> bagianTepat) {
    final senLantai = [
      for (final bagian in bagianTepat)
        BagiBulat(bagian.numerator * BigInt.from(100), bagian.denominator, ModePembulatan.KeBawah),
    ];
    final pecahan = [for (var i = 0; i < bagianTepat.length; i++) bagianTepat[i] * _seratus - Rational(senLantai[i])];
    final totalSen = total.KeDesimal().shift(Uang.skala).toBigInt();
    var sisaSen = (totalSen - senLantai.fold(BigInt.zero, (a, b) => a + b)).toInt();
    if (sisaSen < 0 || sisaSen > bagianTepat.length) {
      throw StateError('Total alokasi $total tidak cocok dengan jumlah bagian');
    }
    final urutan = [for (var i = 0; i < bagianTepat.length; i++) i]
      ..sort((a, b) {
        final banding = pecahan[b].compareTo(pecahan[a]);
        return banding != 0 ? banding : a.compareTo(b);
      });
    final hasilSen = [...senLantai];
    for (final indeks in urutan) {
      if (sisaSen == 0) {
        break;
      }
      hasilSen[indeks] += BigInt.one;
      sisaSen--;
    }
    return [for (final sen in hasilSen) UbahSenKeUang(sen)];
  }

  /// Membulatkan pecahan eksak ke sen, setengah menjauhi nol.
  static Uang BulatkanKeSen(Rational nilai) => UbahSenKeUang(
    BagiBulat(nilai.numerator * BigInt.from(100), nilai.denominator, ModePembulatan.SetengahMenjauhiNol),
  );

  static Uang UbahSenKeUang(BigInt sen) => Uang.DariDesimal(Decimal.fromBigInt(sen).shift(-Uang.skala));

  static Uang JumlahkanUang(Iterable<Uang> daftar) => daftar.fold(Uang.Nol(), (a, b) => a.Tambah(b));

  static Rational JumlahkanPecahan(Iterable<Rational> daftar) => daftar.fold(Rational.zero, (a, b) => a + b);

  static Uang AmbilTerkecil(Uang a, Uang b) => a.Bandingkan(b) <= 0 ? a : b;
}

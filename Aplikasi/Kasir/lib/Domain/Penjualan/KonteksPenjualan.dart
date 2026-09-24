import 'dart:convert';

import 'package:klien_api/KlienApi.dart';
import 'package:mesin_kasir/MesinKasir.dart';

import '../../Data/BasisData/BasisDataKasir.dart';
import '../../Data/RepositoriKasir.dart';
import '../../Data/RepositoriKatalog.dart';

/// Jenis metode pembayaran fase 1 (sama dengan server, Rincian F-07b langkah 9).
abstract final class JenisMetodeBayar {
  static const String tunai = 'Tunai';
  static const String qrisStatis = 'QrisStatis';
  static const String edc = 'Edc';
  static const String transfer = 'Transfer';
  static const String ewallet = 'Ewallet';

  static const List<String> fase1 = [tunai, qrisStatis, edc, transfer, ewallet];
}

/// Tarif pajak terbit bertanggal berlaku (CLAUDE.md #12). Tanggal `YYYY-MM-DD`; `berlakuSampai` inklusif.
class TarifPajakLokal {
  const TarifPajakLokal({
    required this.kode,
    required this.tarif,
    required this.pembilang,
    required this.penyebut,
    required this.berlakuMulai,
    required this.berlakuSampai,
  });

  final String kode;

  /// Persen apa adanya dari server (misal `12.00`); dipakai juga di snapshot outbox.
  final String tarif;
  final int pembilang;
  final int penyebut;
  final String berlakuMulai;
  final String? berlakuSampai;

  bool CekBerlaku(String tanggal) =>
      berlakuMulai.compareTo(tanggal) <= 0 && (berlakuSampai == null || tanggal.compareTo(berlakuSampai!) <= 0);

  static TarifPajakLokal DariBaris(BarisTarifPajak b) => TarifPajakLokal(
    kode: b.KodeJenisPajak,
    tarif: b.Tarif,
    pembilang: b.PengaliDppPembilang,
    penyebut: b.PengaliDppPenyebut <= 0 ? 1 : b.PengaliDppPenyebut,
    berlakuMulai: b.BerlakuMulai,
    berlakuSampai: b.BerlakuSampai,
  );
}

/// Pengaturan yang dipakai saat menjual: identitas outlet & perangkat (nomor BR-07.1), profil pajak, tarif, batas
/// diskon (BR-07.3), pembulatan tunai (BR-08.6), dan metode pembayaran. Dimuat dari data awal tersimpan.
class KonteksPenjualan {
  const KonteksPenjualan({
    required this.uuidOutlet,
    required this.kodeOutlet,
    required this.kodePerangkat,
    required this.jamTutupBuku,
    required this.profilPajak,
    required this.tarif,
    required this.pembulatanTunai,
    required this.batasDiskonManual,
    required this.batasDiskonPenyetuju,
    required this.metodePembayaran,
  });

  final String? uuidOutlet;
  final String? kodeOutlet;
  final String? kodePerangkat;

  /// `HH:mm`; transaksi sebelum jam ini masuk tanggal bisnis hari sebelumnya.
  final String jamTutupBuku;
  final ProfilPajakPos profilPajak;
  final List<TarifPajakLokal> tarif;
  final DataPembulatanTunai? pembulatanTunai;
  final Decimal batasDiskonManual;
  final Decimal batasDiskonPenyetuju;

  /// Metode aktif berjenis fase 1, urut tampil.
  final List<BarisMetodePembayaran> metodePembayaran;

  Decimal AmbilPersenBiayaLayanan() =>
      profilPajak.biayaLayananAktif ? Decimal.tryParse(profilPajak.persenBiayaLayanan) ?? Decimal.zero : Decimal.zero;

  /// Tanggal bisnis `YYYY-MM-DD` dari waktu perangkat (zona waktu perangkat = zona outlet).
  String HitungTanggalBisnis(DateTime waktu) {
    var lokal = waktu.toLocal();
    final jam = '${lokal.hour.toString().padLeft(2, '0')}:${lokal.minute.toString().padLeft(2, '0')}';
    if (RegExp(r'^([01]\d|2[0-3]):[0-5]\d$').hasMatch(jamTutupBuku) && jam.compareTo(jamTutupBuku) < 0) {
      lokal = lokal.subtract(const Duration(days: 1));
    }
    return '${lokal.year.toString().padLeft(4, '0')}-${lokal.month.toString().padLeft(2, '0')}-'
        '${lokal.day.toString().padLeft(2, '0')}';
  }

  TarifPajakLokal? CariTarif(String kode, String tanggal) {
    for (final t in tarif) {
      if (t.kode == kode && t.CekBerlaku(tanggal)) {
        return t;
      }
    }
    return null;
  }

  static Future<KonteksPenjualan> Muat(RepositoriKasir repositori, RepositoriKatalog katalog) async {
    final profil = await repositori.AmbilPengaturan(KunciPengaturan.profilPajak);
    final pembulatan = await repositori.AmbilPengaturan(KunciPengaturan.pembulatanTunai);
    final petaPembulatan = pembulatan == null || pembulatan.isEmpty ? null : jsonDecode(pembulatan);
    final dataPembulatan = PembulatanTunaiPos.DariJson(petaPembulatan);
    return KonteksPenjualan(
      uuidOutlet: await repositori.AmbilPengaturan(KunciPengaturan.uuidOutlet),
      kodeOutlet: await repositori.AmbilPengaturan(KunciPengaturan.kodeOutlet),
      kodePerangkat: await repositori.AmbilPengaturan(KunciPengaturan.kodePerangkat),
      jamTutupBuku: await repositori.AmbilPengaturan(KunciPengaturan.jamTutupBuku) ?? '00:00',
      profilPajak: ProfilPajakPos.DariJson(profil == null ? null : jsonDecode(profil)),
      tarif: (await katalog.AmbilTarifPajak()).map(TarifPajakLokal.DariBaris).toList(),
      pembulatanTunai: dataPembulatan == null
          ? null
          : DataPembulatanTunai(
              kelipatan: dataPembulatan.kelipatan,
              arah:
                  ArahPembulatan.values.where((a) => a.name == dataPembulatan.arah).firstOrNull ??
                  ArahPembulatan.Terdekat,
            ),
      batasDiskonManual:
          Decimal.tryParse(await repositori.AmbilPengaturan(KunciPengaturan.batasDiskonManual) ?? '') ??
          Decimal.parse(DataAwal.batasDiskonManualBawaan),
      batasDiskonPenyetuju:
          Decimal.tryParse(await repositori.AmbilPengaturan(KunciPengaturan.batasDiskonPenyetuju) ?? '') ??
          Decimal.parse(DataAwal.batasDiskonPenyetujuBawaan),
      metodePembayaran: (await katalog.AmbilMetodePembayaran())
          .where((m) => JenisMetodeBayar.fase1.contains(m.Jenis))
          .toList(),
    );
  }
}

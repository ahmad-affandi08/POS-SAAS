import 'package:klien_api/KlienApi.dart';
import 'package:mesin_kasir/MesinKasir.dart';

import '../GalatKasir.dart';

/// Kanal struk digital (v2.05). Nilai sama dengan server (`KanalPesanKeluar`). Sejak D-33 (PRD v4.05) hanya WhatsApp:
/// notifikasi toko ke pelanggan tidak memakai email.
abstract final class KanalStruk {
  static const String whatsapp = 'Whatsapp';
}

/// Kirim struk digital (tautan `/s/{kodeStruk}`) ke pelanggan lewat WhatsApp (v2.05, D-33). Wajib online dan
/// penjualan sudah tersinkron; server mengantrekan pengiriman lewat penyedia yang aktif di konsol Platform Pengelola.
/// Nomor pelanggan hanya dikirim ke server, tidak disimpan di perangkat dan tidak dicatat di log.
class LayananKirimStruk {
  LayananKirimStruk({required this.klien, PembuatUlid? ulid}) : _ulid = ulid ?? PembuatUlid();

  final KlienPos klien;
  final PembuatUlid _ulid;

  /// Nomor WhatsApp Indonesia ke format 62xxxxxxxxxx (08…, +62…, 62…). Null = tidak sah.
  static String? RapikanNomor(String masukan) {
    var angka = masukan.replaceAll(RegExp(r'[\s\-().]'), '');
    if (angka.startsWith('+')) {
      angka = angka.substring(1);
    }
    if (!RegExp(r'^\d+$').hasMatch(angka)) {
      return null;
    }
    if (angka.startsWith('0')) {
      angka = '62${angka.substring(1)}';
    } else if (angka.startsWith('8')) {
      angka = '62$angka';
    }
    // 62 + 8xx…: nomor seluler Indonesia 10–13 digit setelah 62.
    if (!angka.startsWith('628') || angka.length < 11 || angka.length > 15) {
      return null;
    }
    return angka;
  }

  /// Nomor WhatsApp rapi, atau [GalatKasir] `TujuanTidakValid`.
  static String RapikanTujuan(String masukan) =>
      RapikanNomor(masukan.trim()) ??
      (throw const GalatKasir('TujuanTidakValid', 'Nomor WhatsApp tidak sah. Contoh: 0812 3456 7890.'));

  Future<PesanKeluarPos> Kirim({required String uuidPenjualan, required String tujuan}) async {
    final rapi = RapikanTujuan(tujuan);
    return _Jalankan(
      () =>
          klien.KirimStruk(uuidPenjualan: uuidPenjualan, uuid: _ulid.Buat(), kanal: KanalStruk.whatsapp, tujuan: rapi),
    );
  }

  Future<PesanKeluarPos> AmbilStatus(String uuid) => _Jalankan(() => klien.AmbilPesanKeluar(uuid));

  static Future<T> _Jalankan<T>(Future<T> Function() aksi) async {
    try {
      return await aksi();
    } on GalatJaringan {
      throw const GalatKasir('KirimStrukOffline', 'Kirim struk butuh internet. Coba lagi setelah perangkat online.');
    } on GalatApi catch (galat) {
      throw GalatKasir(galat.kode, switch (galat.kode) {
        'PenjualanBelumTersinkron' => 'Transaksi belum tersinkron ke server. Tunggu sebentar lalu coba lagi.',
        _ => galat.pesan,
      });
    }
  }
}

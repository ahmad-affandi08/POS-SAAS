import 'package:klien_api/KlienApi.dart';
import 'package:mesin_kasir/MesinKasir.dart';

import '../GalatKasir.dart';

/// Kanal struk digital (v2.05). Nilai sama dengan server (`KanalPesanKeluar`).
abstract final class KanalStruk {
  static const String whatsapp = 'Whatsapp';
  static const String email = 'Email';
}

/// Kirim struk digital (tautan `/s/{kodeStruk}`) ke pelanggan lewat WhatsApp atau email (v2.05). Wajib online dan
/// penjualan sudah tersinkron; server mengantrekan pengiriman lewat penyedia yang aktif di konsol Platform Pengelola.
/// Nomor/email pelanggan hanya dikirim ke server, tidak disimpan di perangkat dan tidak dicatat di log.
class LayananKirimStruk {
  LayananKirimStruk({required this.klien, PembuatUlid? ulid}) : _ulid = ulid ?? PembuatUlid();

  final KlienPos klien;
  final PembuatUlid _ulid;

  static final RegExp _polaEmail = RegExp(r'^[^\s@]+@[^\s@]+\.[^\s@]{2,}$');

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

  /// Tujuan rapi untuk [kanal], atau [GalatKasir] `TujuanTidakValid`.
  static String RapikanTujuan(String kanal, String masukan) {
    final teks = masukan.trim();
    if (kanal == KanalStruk.whatsapp) {
      return RapikanNomor(teks) ??
          (throw const GalatKasir('TujuanTidakValid', 'Nomor WhatsApp tidak sah. Contoh: 0812 3456 7890.'));
    }
    if (teks.length > 254 || !_polaEmail.hasMatch(teks)) {
      throw const GalatKasir('TujuanTidakValid', 'Alamat email tidak sah. Contoh: nama@contoh.co.id.');
    }
    return teks.toLowerCase();
  }

  Future<PesanKeluarPos> Kirim({required String uuidPenjualan, required String kanal, required String tujuan}) async {
    final rapi = RapikanTujuan(kanal, tujuan);
    return _Jalankan(
      () => klien.KirimStruk(uuidPenjualan: uuidPenjualan, uuid: _ulid.Buat(), kanal: kanal, tujuan: rapi),
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
